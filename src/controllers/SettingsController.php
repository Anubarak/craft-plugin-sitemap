<?php
/**
 * sitemap plugin for Craft CMS 3.x
 *
 * Craft 3 plugin that provides an easy way to enable and manage a xml sitemap for search engines like Google
 *
 * @link      https://github.com/Dolphiq/craft3-plugin-sitemap
 * @copyright Copyright (c) 2017 Johan Zandstra
 */

namespace dolphiq\sitemap\controllers;

use Craft;
use craft\db\Query;
use craft\elements\Entry;
use craft\fields\Assets;
use craft\web\Controller;
use dolphiq\sitemap\records\SitemapEntry;
use dolphiq\sitemap\Sitemap;
use yii\helpers\ArrayHelper;

/**
 * Default Controller
 *
 * Generally speaking, controllers are the middlemen between the front end of
 * the CP/website and your plugin’s services. They contain action methods which
 * handle individual tasks.
 *
 * A common pattern used throughout Craft involves a controller action gathering
 * post data, saving it on a model, passing the model off to a service, and then
 * responding to the request appropriately depending on the service method’s response.
 *
 * Action methods begin with the prefix “action”, followed by a description of what
 * the method does (for example, actionSaveIngredient()).
 *
 * https://craftcms.com/docs/plugins/controllers
 *
 * @author    Johan Zandstra
 * @package   Sitemap
 * @since     1.0.0
 */
class SettingsController extends Controller
{
    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = false;

    /**
     * Get Sections
     *
     * @return array
     *
     * @author Robin Schambach
     * @since  17.09.2019
     */
    private function _getSections(): array
    {
        $sections = Craft::$app->getSections()->getAllSections();
        $response = [];

        $allSectionIds = Craft::$app->getSections()->getAllSectionIds();
        $siteMapRecords = ArrayHelper::index(
            SitemapEntry::find()->where(
                [
                    'and',
                    ['IN', 'linkId', $allSectionIds],
                    ['=', 'type', 'section']
                ]
            )->asArray()->all(),
            'linkId'
        );

        foreach ($sections as $section) {
            $siteSettings = $section->getSiteSettings();
            $hasUrls = false;
            foreach ($siteSettings as $siteSetting) {
                if ((bool) $siteSetting->hasUrls === true) {
                    $hasUrls = true;
                }
            }

            if ($hasUrls === false) {
                continue;
            }

            $siteMapRecord = $siteMapRecords[$section->id] ?? [
                    'changefreq' => 'weekly',
                    'priority'   => '0.5',
                    'id'         => null,
                    'useCustomUrl'  => false
                ];
            $response[] = [
                'id'             => (int) $section->id,
                'structureId'    => (int) $section->structureId,
                'name'           => $section->name,
                'fieldId'           => $siteMapRecord['fieldId']?? null,
                'handle'         => $section->handle,
                'type'           => $section->type,
                'elementCount'   => Entry::find()->sectionId($section->id)->count(),
                'sitemapEntryId' => $siteMapRecord['id'],
                'changefreq'     => $siteMapRecord['changefreq'],
                'priority'       => $siteMapRecord['priority'],
                'useCustomUrl'      => $siteMapRecord['useCustomUrl']
            ];
        }

        return $response;
    }

    private function _createCategoryQuery(): Query
    {
        return (new Query())->select(
            [
                'categorygroups.id',
                'categorygroups.name',
                'count(DISTINCT entries.id) entryCount',
                'count(DISTINCT elements.id) elementCount',
                'sitemap_entries.id sitemapEntryId',
                'sitemap_entries.changefreq changefreq',
                'sitemap_entries.priority priority',
            ]
        )->from(['{{%categories}} categories'])->innerJoin(
            '{{%categorygroups}} categorygroups',
            '[[categories.groupId]] = [[categorygroups.id]]'
        )->innerJoin(
            '{{%categorygroups_sites}} categorygroups_sites',
            '[[categorygroups_sites.groupId]] = [[categorygroups.id]] AND [[categorygroups_sites.hasUrls]] = 1'
        )->leftJoin('{{%entries}} entries', '[[categories.id]] = [[entries.sectionId]]')->leftJoin(
            '{{%elements}} elements',
            '[[entries.id]] = [[elements.id]] AND [[elements.enabled]] = 1'
        )->leftJoin(
            '{{%dolphiq_sitemap_entries}} sitemap_entries',
            '[[categorygroups.id]] = [[sitemap_entries.linkId]] AND [[sitemap_entries.type]] = "category"'
        )->groupBy(['categorygroups.id'])->orderBy(['name' => SORT_ASC]);
    }

    // Public Methods
    // =========================================================================

    /**
     * Handle a request going to our plugin's index action URL,
     * e.g.: actions/sitemap/default
     *
     * @return mixed
     */
    public function actionIndex(): craft\web\Response
    {
        $this->requireLogin();

        $routeParameters = Craft::$app->getUrlManager()->getRouteParams();

        $source = $routeParameters['source'] ?? 'CpSection';


        // $allSections = Craft::$app->getSections()->getAllSections();
        $allSections = $this->_getSections();
        $allStructures = [];

        if (is_array($allSections)) {
            foreach ($allSections as $section) {
                $allStructures[] = [
                    'id'           => $section['id'],
                    'type'         => $section['type'],
                    'heading'      => $section['name'],
                    'enabled'      => $section['sitemapEntryId'] > 0,
                    'elementCount' => $section['elementCount'],
                    'changefreq'   => $section['changefreq'],
                    'priority'     => $section['priority'],
                    'useCustomUrl' => (bool)$section['useCustomUrl'],
                    'fieldId'      => $section['fieldId']
                ];
            }
        }

        /*
        $allCategories = $this->_createCategoryQuery()->all();
        $allCategoryStructures = [];
        if (is_array($allCategories)) {
            // print_r($allSections);
            foreach ($allCategories as $category) {
                $allCategoryStructures[] = [
                    'id'           => $category['id'],
                    'type'         => 'category',
                    'heading'      => $category['name'],
                    'enabled'      => ($category['sitemapEntryId'] > 0 ? true : false),
                    'elementCount' => $category['elementCount'],
                    'changefreq'   => ($category['sitemapEntryId'] > 0 ? $category['changefreq'] : 'weekly'),
                    'priority'     => ($category['sitemapEntryId'] > 0 ? $category['priority'] : 0.5),
                ];
            }
        }
        */

        $fields = Craft::$app->getFields()->getAllFields();
        $fieldData = [['value' => null, 'label' => '']];
        foreach ($fields as $field){
            if($field instanceof Assets){
                $fieldData[] = [
                    'label' => $field->name,
                    'value' => $field->id
                ];
            }
        }

        $variables = [
            'settings'      => Sitemap::$plugin->getSettings(),
            'source'        => $source,
            'pathPrefix'    => $source === 'CpSettings' ? 'settings/' : '',
            'allStructures' => $allStructures,
            'fields'        => $fieldData
            //'allCategories' => $allCategoryStructures
            // 'allRedirects' => $allRedirects
        ];

        return $this->renderTemplate('sitemap/settings', $variables);
    }

    /**
     * Called when saving the settings.
     *
     * @return \Craft\web\Response
     * @throws \yii\base\Exception
     * @throws \yii\base\NotSupportedException
     * @throws \yii\web\BadRequestHttpException
     * @throws \yii\web\ForbiddenHttpException
     * @throws \yii\web\ServerErrorHttpException
     * @throws \yii\base\ErrorException
     */
    public function actionSaveSitemap(): craft\web\Response
    {
        $this->requirePostRequest();
        $this->requireAdmin();
        $request = Craft::$app->getRequest();
        // @TODO: check the input and save the sections
        $sitemapSections = $request->getBodyParam('sitemapSections');
        // filter the enabled sections
        $allSectionIds = [];

        $siteMapService = Sitemap::getInstance()->getSiteMap();

        if (is_array($sitemapSections)) {
            foreach ($sitemapSections as $key => $entry) {
                if ($entry['enabled']) {
                    // filter section id from key

                    $id = (int) str_replace('id:', '', $key);
                    if ($id > 0) {
                        // find the entry, else add one
                        $sitemapEntry = SitemapEntry::find()->where(['linkId' => $id, 'type' => 'section'])->one();
                        if (!$sitemapEntry) {
                            // insert / update this section
                            $sitemapEntry = new SitemapEntry();
                        }
                        $sitemapEntry->linkId = $id;
                        $sitemapEntry->type = 'section';
                        $sitemapEntry->priority = $entry['priority'];
                        $sitemapEntry->changefreq = $entry['changefreq'];
                        $sitemapEntry->useCustomUrl = $entry['useCustomUrl'] ?? false;
                        $sitemapEntry->fieldId = $entry['fieldId'] ?? null;
                        $siteMapService->saveEntry($sitemapEntry);
                        $allSectionIds[] = $id;
                    }
                }
            }
        }

        // remove all sitemaps not in the id list
        if (empty($allSectionIds)) {
            $records = SitemapEntry::findAll(['type' => 'section']);
            foreach ($records as $record) {
                $siteMapService->deleteEntry($record);
            }
        } else {
            foreach (SitemapEntry::find()
                         ->where(['type' => 'section'])
                         ->andWhere(['NOT IN', 'linkId', $allSectionIds])
                         ->all() as $entry) {
                $siteMapService->deleteEntry($entry);
            }
        }

        // now save the sitemapCategories
        $sitemapCategories = $request->getBodyParam('sitemapCategories');
        // filter the enabled sections
        $allCategoryIds = [];
        if (is_array($sitemapCategories)) {
            foreach ($sitemapCategories as $key => $entry) {
                if ($entry['enabled']) {
                    // filter section id from key

                    $id = (int) str_replace('id:', '', $key);
                    if ($id > 0) {
                        // find the entry, else add one
                        $sitemapEntry = SitemapEntry::find()->where(['linkId' => $id, 'type' => 'category'])->one();
                        if (!$sitemapEntry) {
                            // insert / update this section
                            $sitemapEntry = new SitemapEntry();
                        }
                        $sitemapEntry->linkId = $id;
                        $sitemapEntry->type = 'category';
                        $sitemapEntry->priority = $entry['priority'];
                        $sitemapEntry->changefreq = $entry['changefreq'];
                        $siteMapService->saveEntry($sitemapEntry);
                        $allCategoryIds[] = $id;
                    }
                }
            }
        }
        // remove all sitemaps not in the id list
        if (empty($allCategoryIds)) {
            $entries = SitemapEntry::findAll(['type' => 'category']);
            foreach ($entries as $entry) {
                $siteMapService->deleteEntry($entry);
            }
        } else {
            foreach (SitemapEntry::find()->where(['type' => 'category'])->andWhere(
                ['NOT IN', 'linkId', $allCategoryIds]
            )->all() as $entry) {
                $siteMapService->deleteEntry($entry);
            }
        }

        return $this->actionIndex();
    }
}
