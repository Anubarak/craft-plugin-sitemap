<?php
/**
 * sitemap plugin for Craft CMS 3.x
 *
 * Craft 3 plugin that provides an easy way to enable and manage a xml sitemap for search engines like Google
 *
 * @link      https://github.com/Dolphiq/craft3-plugin-sitemap
 * @copyright Copyright (c) 2017 Johan Zandstra
 */

namespace anubarak\sitemap\controllers;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Entry;
use craft\events\CancelableEvent;
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use anubarak\sitemap\helpers\PathHelper;
use anubarak\sitemap\models\SitemapEntryModel;
use anubarak\sitemap\records\SitemapCrawlerVisit;
use anubarak\sitemap\Sitemap;
use DOMDocument;
use Exception;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use secondred\formbuilder\elements\db\EntryQuery;
use yii\web\HttpException;
use yii\web\Response;

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
class SitemapController extends Controller
{
    private                  $_sourceRouteParams = [];
    protected int|bool|array $allowAnonymous     = ['index'];
    // Public Methods
    // =========================================================================


    /**
     * @inheritdoc
     */
    private function getUrl($uri, $siteId)
    {
        if ($uri !== null) {
            $path = ($uri === '__home__') ? '' : $uri;

            return UrlHelper::siteUrl($path, null, null, $siteId);
        }

        return null;
    }

    /**
     * Handle a request going to our plugin's index action URL,
     * e.g.: actions/sitemap/default
     *
     * @param string $suffix
     *
     * @return mixed
     * @throws \yii\base\Exception
     * @throws \craft\errors\SiteNotFoundException
     */
    public function actionIndex(string $suffix = '')
    {
        try {
            // try to register the searchengine visit
            $CrawlerDetect = new CrawlerDetect;

            // Check the user agent of the current 'visitor'
            if ($CrawlerDetect->isCrawler()) {
                // insert into table!
                $visit = new SitemapCrawlerVisit();
                $visit->name = $CrawlerDetect->getMatches();
                $visit->save();
            }
        } catch (Exception $err) {
        }

        // grab the site-map
        $site = Craft::$app->getSites()->getCurrentSite();
        $path = PathHelper::getSiteMapPath();
        // only default route
        $name = 'sitemap_' . $site->id  . $suffix . '.xml';
        if(file_exists($path . $name)){
            $simpleXml = simplexml_load_string(file_get_contents($path . $name));
        }else{
            if($suffix){
                return $this->redirect('sitemap.xml');
            }else{
                throw new HttpException(400, 'No sitemap found -> you need to generate it first via console command „php craft secondred-sitemap“');
            }
        }

        $response = Craft::$app->response;
        $response->format = Response::FORMAT_RAW;
        $headers = $response->getHeaders();
        $headers->add('Content-Type', 'application/xml');

        return $simpleXml->saveXML();
    }
}