<?php /** @noinspection PhpComposerExtensionStubsInspection */

/**
 * sitemap plugin for Craft CMS 3.x
 *
 * Craft 3 plugin that provides an easy way to enable and manage a xml sitemap for search engines like Google
 *
 * @link      https://github.com/Dolphiq/craft3-plugin-sitemap
 * @copyright Copyright (c) 2017 Johan Zandstra
 */

namespace dolphiq\sitemap\services;

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
use dolphiq\sitemap\behaviors\ElementSiteMapBehavior;
use dolphiq\sitemap\events\PopulateNewsEvent;
use dolphiq\sitemap\events\SearchElementsEvent;
use dolphiq\sitemap\records\SitemapEntry;
use dolphiq\sitemap\Sitemap;
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
    public const PROJECT_CONFIG_KEY = 'dolphiq_sitemap_entries';
    public const EVENT_SEARCH_ELEMENTS = 'searchElementsEvent';
    public const EVENT_POPULATE_NEWS = 'populateNewsEvent';
    // Public Methods
    // =========================================================================

    /**
     * Save a new entry to the project config
     *
     * @param \dolphiq\sitemap\records\SitemapEntry $record
     *
     * @return bool
     * @throws \yii\base\Exception
     * @throws \yii\base\NotSupportedException
     * @throws \yii\web\ServerErrorHttpException
     * @throws \yii\base\ErrorException
     */
    public function saveEntry(SitemapEntry $record): bool
    {
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
        Craft::$app->getProjectConfig()->set(
            $path,
            [
                'linkId'       => $uidById,
                'type'         => $record->type,
                'priority'     => $record->priority,
                'changefreq'   => $record->changefreq,
                'useCustomUrl' => $record->useCustomUrl,
                'field'        => $record->fieldId !== null? Db::uidById(Table::FIELDS, (int)$record->fieldId) : null
            ]
        );

        if ($isNew) {
            $record->id = Db::idByUid(SitemapEntry::tableName(), $record->uid);
        }

        return true;
    }

    /**
     * Delete an entry from project config
     *
     * @param \dolphiq\sitemap\records\SitemapEntry $record
     */
    public function deleteEntry(SitemapEntry $record): void
    {
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
        $record->useCustomUrl = $event->newValue['useCustomUrl'];
        $record->fieldId =  $event->newValue['field'] !== null? Db::idByUid(Table::FIELDS, $event->newValue['field']) :  null;
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
            $e->config[self::PROJECT_CONFIG_KEY][$record->uid] = [
                'linkId'     => $this->getUidById($record),
                'type'       => $record->type,
                'priority'   => $record->priority,
                'changefreq' => $record->changefreq,
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
        $indexes = [
            ['name' => 'default', 'sectionIds' => []]
        ];
        foreach ($records as $record) {
            if ((bool) $record->useCustomUrl === true) {
                $indexes[] = [
                    'name'       => $record->section->handle,
                    'sectionIds' => [(int) $record->linkId]
                ];
            } else {
                $indexes[0]['sectionIds'][] = (int) $record->linkId;
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

            $url = $dom->createElement('sitemap');
            $siteMapIndex->appendChild($url);
            $url->appendChild($dom->createElement('loc', $baseUrl . $index['name'] . '.xml'));
            $subSiteMap = $this->buildSiteMap($index['sectionIds'], $site);

            $subSiteMap['siteMap']->save($path . 'sitemap_' . $site->id . '_' . $index['name'] . '.xml');
            $url->appendChild($dom->createElement('lastmod', $subSiteMap['lastEdited']));
        }


        $dom->save($path . 'sitemap_' . $site->id . '.xml');

        return $dom;
    }

    /**
     * buildSiteMap
     *
     * @param array              $sectionIds
     * @param \craft\models\Site $site
     *
     * @return array
     *
     * @throws \yii\base\InvalidConfigException
     * @since  17.09.2019
     * @author Robin Schambach
     */
    public function buildSiteMap(array $sectionIds, site $site): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $news = Sitemap::$plugin->getSettings()->newsSections;
        $entriesBySite = $this->_getEntries($sectionIds);
        $isNews = false;
        if (empty($entriesBySite) === false && isset($entriesBySite[$site->id]) &&
            empty($entriesBySite[$site->id]) === false) {
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
        }

        $query = Entry::find()->siteId('*')->orderBy(['dateUpdated' => SORT_DESC])->sectionId($sectionIds);

        $event = new SearchElementsEvent(['query' => $query]);
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
    public function createNode(ElementInterface $element, DOMDocument $dom, array $entriesBySite, Site $site): ?DOMElement
    {
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
        if($element->siteMapAsset !== null){
            /** @var \craft\elements\Asset $asset */
            $asset = $element->siteMapAsset;
            $image = $dom->createElement('image:image');
            $image->appendChild($dom->createElement('image:loc', $asset->getUrl()));
            $image->appendChild($dom->createElement('image:title', $asset->title));
            $url->appendChild($image);
        }

        return $url;
    }

    public function createNewsNode(ElementInterface $element, DOMDocument $dom, array $entriesBySite, Site $site): ?DOMElement
    {
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
     *
     * @return array
     *
     * @author Robin Schambach
     * @since  17.09.2019
     */
    private function _getEntries(array $sectionIds = []): array
    {
        /** @var SitemapEntry[] $records */
        $records = SitemapEntry::find()->where(['type' => 'section'])->andWhere(['IN', 'linkId', $sectionIds])->with(
            ['section', 'field']
        )->all();
        $entries = [];
        $entryTypes = ArrayHelper::getColumn(Craft::$app->getSections()->getEntryTypesByHandle('link'), 'id');
        if (empty($entryTypes) === false) {
            ArrayHelper::prependOrAppend($entryTypes, 'not', true);
        }
        $newsSections = Sitemap::$plugin->getSettings()->newsSections;
        foreach ($records as $record) {
            $field = $record->field;
            $query = Entry::find()->siteId('*')->typeId($entryTypes)->sectionId($record->section->id);
            if($field !== null){
                $query->with($field->handle);
            }

            // check for news...
            if (isset($newsSections[$record->section->handle]) === true) {
                Craft::configure($query, $newsSections[$record->section->handle]);
            }

            $event = new SearchElementsEvent(['query' => $query, 'siteMapEntry' => $record]);
            if ($this->hasEventHandlers(self::EVENT_SEARCH_ELEMENTS)) {
                $this->trigger(self::EVENT_SEARCH_ELEMENTS, $event);
            }
            /** @var \craft\base\Element[] $entriesForSection */
            $entriesForSection = $event->query->all();
            foreach ($entriesForSection as $element) {
                $assets = $field !== null? $element->{$field->handle} : null;
                $element->attachBehavior(
                    'meta',
                    [
                        'class'      => ElementSiteMapBehavior::class,
                        'priority'   => $record->priority,
                        'changefreq' => $record->changefreq,
                        'siteMapAsset' => $assets !== null && empty($assets) === false? $assets[0] : null,
                    ]
                );
                $entries[$element->siteId][$element->id] = $element;
            }
        }

        return $entries;
    }
}
