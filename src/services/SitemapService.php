<?php /** @noinspection PhpComposerExtensionStubsInspection */

/**
 * sitemap plugin for Craft CMS 3.x
 *
 * Craft 3 plugin that provides an easy way to enable and manage a xml sitemap for search engines like Google
 *
 * @link      https://github.com/Dolphiq/craft3-plugin-sitemap
 * @copyright Copyright (c) 2017 Johan Zandstra
 */

namespace anubarak\sitemap\services;

use anubarak\sitemap\models\SitemapIndex;
use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\db\Table;
use craft\elements\Entry;
use craft\events\ConfigEvent;
use craft\events\RebuildConfigEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use craft\models\Site;
use DateTime;
use anubarak\sitemap\behaviors\ElementSiteMapBehavior;
use anubarak\sitemap\events\PopulateNewsEvent;
use anubarak\sitemap\events\SearchElementsEvent;
use anubarak\sitemap\records\SitemapEntry;
use anubarak\sitemap\Sitemap;
use DOMDocument;
use DOMElement;

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
class SitemapService extends Component
{
    /**
     * Key for the project config
     */
    public const PROJECT_CONFIG_KEY    = 'dolphiq_sitemap_entries';
    public const EVENT_SEARCH_ELEMENTS = 'searchElementsEvent';
    public const EVENT_POPULATE_NEWS   = 'populateNewsEvent';
    // Public Methods
    // =========================================================================

    /**
     * Save a new entry to the project config
     *
     * @param \anubarak\sitemap\records\SitemapEntry $record
     *
     * @return bool
     * @throws \yii\base\Exception
     * @throws \yii\base\NotSupportedException
     * @throws \yii\web\ServerErrorHttpException
     * @throws \yii\base\ErrorException
     */
    public function saveEntry(SitemapEntry $record): bool
    {
        // don't use the project-config and just save the record
        if (Sitemap::$plugin->getSettings()->useProjectConfig === false) {
            return $record->save();
        }

        // don't use the project-config

        // is it a new one?
        $isNew = empty($record->id);
        if ($isNew) {
            $record->uid = StringHelper::UUID();
        } else {
            if (!$record->uid) {
                $record->uid = Db::uidById(SitemapEntry::tableName(), $record->id);
            }
        }

        if (!$record->validate()) {
            return false;
        }
        $path = self::PROJECT_CONFIG_KEY . ".{$record->uid}";

        $uidById = $record->type === 'section' ? Db::uidById(Table::SECTIONS, $record->linkId) : Db::uidById(
            Table::CATEGORIES,
            $record->linkId
        );

        // set it in the config
        Craft::$app->getProjectConfig()->set($path, [
            'linkId'       => $uidById,
            'type'         => $record->type,
            'priority'     => $record->priority,
            'changefreq'   => $record->changefreq,
            'field'        => $record->fieldId !== null ? Db::uidById(
                Table::FIELDS,
                (int) $record->fieldId
            ) : null
        ]);

        if ($isNew) {
            $record->id = Db::idByUid(SitemapEntry::tableName(), $record->uid);
        }

        return true;
    }

    /**
     * Delete an entry from project config
     *
     * @param \anubarak\sitemap\records\SitemapEntry $record
     *
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function deleteEntry(SitemapEntry $record): void
    {
        if (Sitemap::$plugin->getSettings()->useProjectConfig === false) {
            // just delete the record
            $record->delete();

            return;
        }

        // use project-config
        $path = self::PROJECT_CONFIG_KEY . ".{$record->uid}";
        Craft::$app->projectConfig->remove($path);
    }

    /**
     * handleChangedSiteMapEntry
     *
     * @param \craft\events\ConfigEvent $event
     */
    public function handleChangedSiteMapEntry(ConfigEvent $event): void
    {
        if (Sitemap::$plugin->getSettings()->useProjectConfig === false) {
            return;
        }

        $uid = $event->tokenMatches[0];
        $id = Db::idByUid(SitemapEntry::tableName(), $uid);

        if ($id === null) {
            // new one
            $record = new SitemapEntry();
        } else {
            // update an existing one
            $record = SitemapEntry::findOne((int) $id);
        }

        $idByUid = $event->newValue['type'] === 'section' ? Db::idByUid(
            Table::SECTIONS,
            $event->newValue['linkId']
        ) : Db::idByUid(Table::CATEGORIES, $event->newValue['linkId']);

        $record->uid = $uid;
        $record->linkId = $idByUid;
        $record->type = $event->newValue['type'];
        $record->priority = $event->newValue['priority'];
        $record->changefreq = $event->newValue['changefreq'];
        $record->fieldId = $event->newValue['field'] !== null ? Db::idByUid(
            Table::FIELDS,
            $event->newValue['field']
        ) : null;
        $record->save();
    }

    /**
     * handleDeletedSiteMapEntry
     *
     * @param \craft\events\ConfigEvent $event
     *
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function handleDeletedSiteMapEntry(ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];
        // grab the record
        $record = SitemapEntry::find()->where(['uid' => $uid])->one();
        if ($record === null) {
            return;
        }

        // delete it
        $record->delete();
    }

    /**
     * rebuildProjectConfig
     *
     * @param \craft\events\RebuildConfigEvent $e
     */
    public function rebuildProjectConfig(RebuildConfigEvent $e): void
    {
        /** @var SitemapEntry[] $records */
        $records = SitemapEntry::find()->all();
        $e->config[self::PROJECT_CONFIG_KEY] = [];
        foreach ($records as $record) {
            $fieldRecord = $record->getField()->one();
            $e->config[self::PROJECT_CONFIG_KEY][$record->uid] = [
                'linkId'       => $this->getUidById($record),
                'type'         => $record->type,
                'priority'     => $record->priority,
                'changefreq'   => $record->changefreq,
                'field'        => $fieldRecord?->uid,
            ];
        }
    }

    public function getUidById(SitemapEntry $record)
    {
        $uid = $record->type === 'section' ? Db::uidById(
            Table::SECTIONS,
            $record->linkId
        ) : Db::uidById(Table::CATEGORIES, $record->linkId);

        return $uid;
    }

    /**
     * Build the index file and all sub-files for the siteMap
     *
     * @param \craft\models\Site|null $site
     *
     *
     * @return \DOMDocument         The created index file
     * @throws \yii\base\Exception
     * @since   17.09.2019
     * @author  Robin Schambach
     */
    public function buildIndexFile(Site $site): DOMDocument
    {
        // grab the different sections
        /** @var SitemapEntry[] $records */
        $records = SitemapEntry::find()->where(['type' => 'section'])->with(['section'])->all();
        $indexes = [];

        $max = Sitemap::$plugin->getSettings()->maxEntriesPerSitemap;
        foreach ($records as $record) {
            $count = $this->getEntryCount($record, $site);
            foreach ($this->processChunks($count, $max) as [$start, $end]) {
                $indexes[] = new SitemapIndex(
                    $record,
                    $record->section->handle,
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

        $path = Craft::$app->getPath()->getStoragePath() . DIRECTORY_SEPARATOR . 'sitemaps' . DIRECTORY_SEPARATOR;
        FileHelper::createDirectory($path);
        $baseUrl = $site->getBaseUrl() . 'sitemap_';
        foreach ($indexes as $index) {
            $subSiteMap = $this->buildSiteMap($index, $site);
            if ($subSiteMap) {
                $url = $dom->createElement('sitemap');
                $siteMapIndex->appendChild($url);
                $url->appendChild($dom->createElement('loc', $baseUrl . $index->getName() . '.xml'));

                $subSiteMap['siteMap']->save($path . 'sitemap_' . $site->id . '_' . $index->getName() . '.xml');
                $url->appendChild($dom->createElement('lastmod', $subSiteMap['lastEdited']));
            }
        }


        $dom->save($path . 'sitemap_' . $site->id . '.xml');

        return $dom;
    }

    /**
     * buildSiteMap
     *
     * @param \anubarak\sitemap\models\SitemapIndex $sitemapIndex
     * @param \craft\models\Site                    $site
     *
     * @return array
     *
     * @throws \DOMException
     * @throws \yii\base\InvalidConfigException
     * @since  17.09.2019
     * @author Robin Schambach
     */
    public function buildSiteMap(SitemapIndex $sitemapIndex, Site $site): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $news = Sitemap::$plugin->getSettings()->newsSections;
        $entriesBySite = $this->_getEntries($sitemapIndex, $site);
        if (empty($entriesBySite)) {
            return [];
        }

        $isNews = false;
        if (
            empty($entriesBySite) === false && isset($entriesBySite[$site->id]) &&
            empty($entriesBySite[$site->id]) === false
        ) {
            /** @var Entry $firstOne */
            $firstOne = ArrayHelper::firstValue($entriesBySite[$site->id]);
            if ($firstOne !== null && isset($news[$firstOne->getSection()->handle])) {
                $isNews = true;
            }
        }


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

        if (isset($entriesBySite[$site->id])) {

            foreach ($entriesBySite[$site->id] as $element) {
                $node = null;
                if ($isNews === false) {
                    $node = $this->createNode($element, $dom, $entriesBySite, $site);
                } else {
                    $node = $this->createNewsNode($element, $dom, $entriesBySite, $site);
                }

                if ($node !== null) {
                    $urlset->appendChild($node);
                }
                // add news information
            }
        } else {
            return [];
        }

        //@formatter:off
        $query = Entry::find()
            ->siteId('*')
            ->orderBy(['dateUpdated' => SORT_DESC])
            ->sectionId($sitemapIndex->getSectionIds());
        //@formatter:on

        $event = new SearchElementsEvent([
            'query' => $query,
            'site'  => $site
        ]);
        if ($this->hasEventHandlers(self::EVENT_SEARCH_ELEMENTS)) {
            $this->trigger(self::EVENT_SEARCH_ELEMENTS, $event);
        }
        /** @var \craft\base\Element $lastEditedEntry */
        $lastEditedEntry = $event->query->one();

        return [
            'siteMap'    => $dom,
            'lastEdited' => $lastEditedEntry !== null ? $lastEditedEntry->dateUpdated->format(DateTime::ATOM) : null
        ];
    }

    /**
     * createNode
     *
     * @param \craft\base\ElementInterface $element
     * @param DOMDocument                  $dom
     * @param array                        $entriesBySite
     * @param \craft\models\Site           $site
     *
     * @return DOMElement|null
     *
     * @throws \yii\base\InvalidConfigException
     * @since  18.09.2019
     * @author Robin Schambach
     */
    public function createNode(
        ElementInterface $element,
        DOMDocument      $dom,
        array            $entriesBySite,
        Site             $site
    ): ?DOMElement {
        /** @var \craft\base\Element $element */
        $loc = $element->getUrl();
        if ($loc === null) {
            return null;
        }

        $url = $dom->createElement('url');
        $url->appendChild($dom->createElement('loc', $loc));
        $url->appendChild($dom->createElement('priority', $element->priority));
        $url->appendChild($dom->createElement('changefreq', $element->changefreq));
        $dateUpdated = $element->dateUpdated->format(DATE_ATOM);
        $url->appendChild($dom->createElement('lastmod', $dateUpdated));

        $elementsForOtherSite = [];
        foreach ($entriesBySite as $key => $siteEntry) {
            if ((int) $key !== (int) $site->id && isset($entriesBySite[$key][$element->id])) {
                $elementsForOtherSite[] = $entriesBySite[$key][$element->id];
            }
        }

        if (empty($elementsForOtherSite) === false) {
            foreach ($elementsForOtherSite as $siteElement) {
                /** @var \craft\base\Element $siteElement */
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
        }

        // add images
        if ($element->siteMapAsset !== null) {
            /** @var \craft\elements\Asset $asset */
            $asset = $element->siteMapAsset;
            $image = $dom->createElement('image:image');
            $image->appendChild($dom->createElement('image:loc', $asset->getUrl()));
            $image->appendChild($dom->createElement('image:title', $asset->title));
            $url->appendChild($image);
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
            'author'   => $author !== null ? $author->getFullName() : '',
            'language' => $site->language,
            'postDate' => $element->postDate !== null ? $element->postDate->format(DateTime::ATOM) : null,
            'title'    => $element->title,
            'url'      => $element->getUrl()
        ];

        $event = new PopulateNewsEvent(['element' => $element, 'data' => $defaultData]);
        if ($this->hasEventHandlers(self::EVENT_POPULATE_NEWS)) {
            $this->trigger(self::EVENT_POPULATE_NEWS, $event);
        }

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
     * @param array $sectionIds
     * @param Site  $site
     *
     * @return array
     *
     * @author Robin Schambach
     * @since  17.09.2019
     */
    private function _getEntries(SitemapIndex $sitemapIndex, Site $site): array
    {
        $entries = [];
        $entryType = Craft::$app->getEntries()->getEntryTypeByHandle('link');

        if (empty($entryType) === false) {
            $entryTypes = [
                'not',
                $entryType->id
            ];
        } else {
            $entryTypes = null;
        }
        $newsSections = Sitemap::$plugin->getSettings()->newsSections;

        $section = null;
        $field = null;
        $record = $sitemapIndex->getSitemapEntry();
        if ($record) {
            $field = $record->field;
            $section = $record->section;
        }

        $query = Entry::find()
            ->siteId('*')
            ->typeId($entryTypes)
            ->sectionId($sitemapIndex->getSectionIds());
        // check for news...
        if ($section && isset($newsSections[$section->handle]) === true) {
            Craft::configure($query, $newsSections[$section->handle]);
        }

        $event = new SearchElementsEvent([
            'query'        => $query,
            'siteMapEntry' => $record,
            'site'         => $site
        ]);

        if ($this->hasEventHandlers(self::EVENT_SEARCH_ELEMENTS)) {
            $this->trigger(self::EVENT_SEARCH_ELEMENTS, $event);
        }


        if ($sitemapIndex->getLimit()) {
            $event->query->limit($sitemapIndex->getLimit());
        }
        if ($sitemapIndex->getOffset()) {
            $event->query->offset($sitemapIndex->getOffset());
        }

        if ($field !== null) {
            $event->query->andWith($field->handle);
        }

        /** @var \craft\base\Element[] $entriesForSection */
        $entriesForSection = $event->query->all();
        foreach ($entriesForSection as $element) {
            $asset = $field !== null ? $element->getFieldValue($field->handle)->one() : null;
            $element->attachBehavior('meta', [
                'class'        => ElementSiteMapBehavior::class,
                'priority'     => $record->priority,
                'changefreq'   => $record->changefreq,
                'siteMapAsset' => $asset,
            ]);
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
            $query->sectionId($sitemapEntry->section->id);
        }

        $event = new SearchElementsEvent([
            'query'        => $query,
            'siteMapEntry' => $sitemapEntry,
            'site'         => $site
        ]);
        if ($this->hasEventHandlers(self::EVENT_SEARCH_ELEMENTS)) {
            $this->trigger(self::EVENT_SEARCH_ELEMENTS, $event);
        }

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
