<?php /** @noinspection PhpComposerExtensionStubsInspection */

/**
 * sitemap plugin for Craft CMS 3.x
 *
 * Craft 3 plugin that provides an easy way to enable and manage a xml sitemap for search engines like Google
 *
 * @link      https://github.com/Dolphiq/craft3-plugin-sitemap
 * @copyright Copyright (c) 2017 Johan Zandstra
 */

namespace Anubarak\Sitemap;


use Anubarak\Sitemap\Data\SitemapIndex;
use Anubarak\Sitemap\Support\PathHelper;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Entry\EntryTypes;
use CraftCms\Cms\Field\Fields;
use CraftCms\Cms\Section\Data\Section;
use CraftCms\Cms\Section\Sections;
use CraftCms\Cms\Site\Data\Site;
use DateTime;
use Anubarak\Sitemap\Events\PopulateNewsEvent;
use Anubarak\Sitemap\Events\SearchElementsEvent;
use Anubarak\Sitemap\Models\SitemapEntry;
use DOMDocument;
use DOMElement;
use Illuminate\Container\Attributes\Singleton;

/**
 * SitemapService Service
 *
 * All of your plugin’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    Johan Zandstra
 * @package   Sitemap
 * @since     1.0.0
 */
#[Singleton]
class Sitemap
{
    public function __construct(
        private readonly Sections   $sections,
        private readonly EntryTypes $entryTypes,
        private readonly Fields     $fields,
    ) {
    }

    /**
     * Build the index file and all sub-files for the siteMap
     *
     * @param Site $site
     *
     *
     * @return \DOMDocument         The created index file
     * @throws \DOMException
     * @since   17.09.2019
     * @author  Robin Schambach
     */
    public function buildIndexFile(
        Site      $site,
        ?callable $indexCallback = null,
        ?callable $entryCb = null
    ): DOMDocument {
        $records = SitemapEntry::query()
            ->get();
        $indexes = [];

        $max = app(Plugin::class)->getSettings()->maxEntriesPerSitemap;
        foreach ($records as $record) {
            $count = $this->getEntryCount($record, $site);
            foreach ($this->processChunks($count, $max) as [$start, $end]) {

                /** @var Section|null $section */
                $section = $this->sections->getAllSections()
                    ->first(fn(Section $section) => $section->id === $record->linkId);
                if (!$section) {
                    continue;
                }

                $indexes[] = new SitemapIndex(
                    $record,
                    $section->handle,
                    [(int) $record->linkId],
                    $start,
                    $max,
                );
            }
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $siteMapIndex = $dom->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9', 'sitemapindex');
        $siteMapIndex->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:xhtml',
            'http://www.w3.org/1999/xhtml'
        );
        $dom->appendChild($siteMapIndex);

        $path = PathHelper::getSiteMapPath();

        $baseUrl = $site->getBaseUrl() . 'sitemap_';

        $current = 1;
        $max = count($indexes);

        foreach ($indexes as $index) {

            if ($indexCallback) {
                $indexCallback($index, $current, $max);
            }

            $subSiteMap = $this->buildSiteMap($index, $site, function(int $current, int $max) use ($entryCb, $index) {
                if ($entryCb) {
                    $entryCb($current, $max);
                }
            });
            if ($subSiteMap) {
                $url = $dom->createElement('sitemap');
                $siteMapIndex->appendChild($url);
                $url->appendChild($dom->createElement('loc', $baseUrl . $index->getName() . '.xml'));

                $subSiteMap['siteMap']->save($path . 'sitemap_' . $site->id . '_' . $index->getName() . '.xml');
                $url->appendChild($dom->createElement('lastmod', $subSiteMap['lastEdited']));
            }
            $current++;
        }


        $dom->save($path . 'sitemap_' . $site->id . '.xml');

        return $dom;
    }

    /**
     * buildSiteMap
     *
     * @param \Anubarak\Sitemap\Data\SitemapIndex $sitemapIndex
     * @param Site                                $site
     *
     * @return array
     *
     * @throws \DOMException
     * @since  17.09.2019
     * @author Robin Schambach
     */
    public function buildSiteMap(SitemapIndex $sitemapIndex, Site $site, ?callable $cb = null): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $entriesBySite = $this->getEntries($sitemapIndex, $site);
        if (empty($entriesBySite)) {
            return [];
        }

        $isNews = $sitemapIndex->getSitemapEntry()->isNews;
        $urlset = $dom->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9', 'urlset');
        if ($isNews === false) {
            $urlset->setAttributeNS(
                'http://www.w3.org/2000/xmlns/',
                'xmlns:image',
                'http://www.google.com/schemas/sitemap-image/1.1'
            );
        } else {
            $urlset->setAttributeNS(
                'http://www.w3.org/2000/xmlns/',
                'xmlns:news',
                'http://www.google.com/schemas/sitemap-news/0.9'
            );
        }


        $dom->appendChild($urlset);

        if (!isset($entriesBySite[$site->id])) {
            return [];
        }
        $current = 1;
        $max = count($entriesBySite[$site->id] ?? []);
        foreach ($entriesBySite[$site->id] as $element) {
            $node = null;
            if ($isNews === false) {
                $node = $this->createNode(
                    $sitemapIndex,
                    $element,
                    $dom,
                    $entriesBySite,
                    $site
                );
            } else {
                $node = $this->createNewsNode($element, $dom, $entriesBySite, $site);
            }

            if ($node !== null) {
                $urlset->appendChild($node);
            }
            // add news information

            if ($cb) {
                $cb($current, $max);
            }
        }

        $query = Entry::find()
            ->siteId('*')
            ->orderBy('dateUpdated', 'desc')
            ->sectionId($sitemapIndex->getSectionIds());


        $event = new SearchElementsEvent(
            $query,
            $sitemapIndex->getSitemapEntry(),
            $site
        );
        event($event);
        $lastEditedEntry = $event->query->one();

        return [
            'siteMap'    => $dom,
            'lastEdited' => $lastEditedEntry !== null ? $lastEditedEntry->dateUpdated->format(DateTime::ATOM) : null
        ];
    }

    /**
     * createNode
     *
     * @param \Anubarak\Sitemap\Data\SitemapIndex $sitemapIndex
     * @param ElementInterface                    $element
     * @param DOMDocument                         $dom
     * @param array<int, array<int, Entry>>       $entriesBySite
     * @param Site                                $site
     *
     * @return DOMElement|null
     *
     * @throws \DOMException
     * @since  18.09.2019
     * @author Robin Schambach
     */
    public function createNode(
        SitemapIndex     $sitemapIndex,
        ElementInterface $element,
        DOMDocument      $dom,
        array            $entriesBySite,
        Site             $site
    ): ?DOMElement {
        $loc = $element->getUrl();
        if ($loc === null) {
            return null;
        }

        $url = $dom->createElement('url');
        $url->appendChild($dom->createElement('loc', $loc));
        $url->appendChild($dom->createElement('priority', $sitemapIndex->getSitemapEntry()->priority));
        $url->appendChild($dom->createElement('changefreq', $sitemapIndex->getSitemapEntry()->changefreq));
        $dateUpdated = $element->dateUpdated->format(DATE_ATOM);
        $url->appendChild($dom->createElement('lastmod', $dateUpdated));

        $elementsForOtherSite = [];
        foreach ($entriesBySite as $key => $siteEntries) {
            if ($key !== $site->id && isset($siteEntries[$element->id])) {
                $elementsForOtherSite[] = $siteEntries[$element->id];
            }
        }

        if (empty($elementsForOtherSite) === false) {
            foreach ($elementsForOtherSite as $siteElement) {
                $alternateLoc = $siteElement->getUrl();
                if ($alternateLoc === null) {
                    continue;
                }

                $alternateLink = $dom->createElementNS('http://www.w3.org/1999/xhtml', 'xhtml:link');
                $alternateLink->setAttribute('rel', 'alternate');
                $alternateLink->setAttribute('hreflang', $siteElement->getSite()->language);
                $alternateLink->setAttribute('href', $alternateLoc);
                $url->appendChild($alternateLink);
            }

            // add self
            $alternateLink = $dom->createElementNS('http://www.w3.org/1999/xhtml', 'xhtml:link');
            $alternateLink->setAttribute('rel', 'alternate');
            $alternateLink->setAttribute('hreflang', $element->getSite()->getLanguage());
            $alternateLink->setAttribute('href', $element->getUrl());
            $url->appendChild($alternateLink);
        }

        $field = null;
        if ($sitemapIndex->getSitemapEntry()->fieldId) {
            $field = $this->fields->getFieldById($sitemapIndex->getSitemapEntry()->fieldId);
        }

        if ($field) {
            $asset = $element->getFieldValue($field->handle)->one();
            if ($asset) {
                $image = $dom->createElement('image:image');
                $image->appendChild($dom->createElement('image:loc', $asset->getUrl()));
                $image->appendChild($dom->createElement('image:title', $asset->title));
                $url->appendChild($image);
            }
        }

        return $url;
    }

    public function createNewsNode(
        ElementInterface $element,
        DOMDocument      $dom,
        array            $entriesBySite,
        Site             $site
    ): ?DOMElement {
        /** @var Entry $element */
        $author = $element->getAuthor();
        $defaultData = [
            'author'   => $author !== null ? ($author->fullName ?? $author->friendlyName) : '',
            'language' => $site->language,
            'postDate' => $element->postDate !== null ? $element->postDate->format(DateTime::ATOM) : null,
            'title'    => $element->title,
            'url'      => $element->getUrl()
        ];

        event($event = new PopulateNewsEvent($element, $defaultData));

        $data = $event->data;
        if ($data['url'] === null || $data['author'] === null || $data['language'] === null) {
            return null;
        }

        $url = $dom->createElement('url');
        $url->appendChild($dom->createElement('loc', $data['url']));
        $news = $dom->createElement('news:news');
        $url->appendChild($news);

        // publication
        $publication = $dom->createElement('news:publication');
        $publication->appendChild($dom->createElement('news:name', $data['author']));
        $publication->appendChild($dom->createElement('news:language', $data['language']));
        $news->appendChild($publication);

        // release date
        $news->appendChild($dom->createElement('news:publication_date', $data['postDate']));

        // title
        $news->appendChild($dom->createElement('news:title', $data['title']));

        $url->appendChild($news);

        return $url;
    }

    /**
     * Get all Entries in those sections
     *
     * @param \Anubarak\Sitemap\Data\SitemapIndex $sitemapIndex
     * @param Site                                $site
     *
     * @return array
     *
     * @author Robin Schambach
     * @since  17.09.2019
     */
    private function getEntries(SitemapIndex $sitemapIndex, Site $site): array
    {
        $entries = [];
        $entryType = $this->entryTypes->getEntryTypeByHandle('link');

        if (empty($entryType) === false) {
            $entryTypes = [
                'not',
                $entryType->id
            ];
        } else {
            $entryTypes = null;
        }

        $field = null;
        $record = $sitemapIndex->getSitemapEntry();
        if ($record && $record->fieldId) {
            $field = $this->fields->getFieldById($record->fieldId);
        }


        $query = Entry::find()
            ->siteId('*')
            ->typeId($entryTypes)
            ->sectionId($sitemapIndex->getSectionIds());


        $event = new SearchElementsEvent(
            $query,
            $record,
            $site
        );
        event($event);

        if ($sitemapIndex->getLimit()) {
            $event->query->limit($sitemapIndex->getLimit());
        }
        if ($sitemapIndex->getOffset()) {
            $event->query->offset($sitemapIndex->getOffset());
        }

        if ($field !== null) {
            $event->query->andWith($field->handle);
        }

        /** @var Entry[] $entriesForSection */
        $entriesForSection = $event->query->all();

        foreach ($entriesForSection as $element) {
            //            $asset = $field !== null ? $element->getFieldValue($field->handle)->one() : null;
            //            $element->attachBehavior('meta', [
            //                'class'        => ElementSiteMapBehavior::class,
            //                'priority'     => $record->priority,
            //                'changefreq'   => $record->changefreq,
            //                'siteMapAsset' => $asset,
            //            ]);
            $entries[$element->siteId][$element->id] = $element;
        }

        return $entries;
    }

    protected function getEntryCount(SitemapEntry|null $sitemapEntry, Site $site, array $sectionIds = []): int
    {
        $query = Entry::find()
            ->siteId($site->id);
        if ($sectionIds) {
            $query->sectionId($sectionIds);
        }

        if ($sitemapEntry) {
            $query->sectionId($sitemapEntry->linkId);
        }

        $event = new SearchElementsEvent(
            $query,
            $sitemapEntry,
            $site
        );
        event($event);

        return $event->query->count();
    }

    /**
     * @param int $totalElements
     * @param int $maxChunkSize
     *
     * @return array
     */
    protected function processChunks(int $totalElements, int $maxChunkSize): array
    {
        // Calculate the total number of chunks
        $totalChunks = ceil($totalElements / $maxChunkSize);

        $junks = [];

        for ($chunkIndex = 0; $chunkIndex < $totalChunks; $chunkIndex++) {
            // Calculate the start index for the current chunk
            $start = $chunkIndex * $maxChunkSize;

            // Calculate the end index for the current chunk
            $end = min($start + $maxChunkSize, $totalElements);

            $junks[] = [$start, $end];
        }

        return $junks;
    }
}
