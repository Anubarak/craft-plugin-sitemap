<?php
/**
 * sitemap plugin for Craft CMS 3.x
 *
 * Craft 3 plugin that provides an easy way to enable and manage a xml sitemap for search engines like Google
 *
 * @link      https://github.com/Dolphiq/craft3-plugin-sitemap
 * @copyright Copyright (c) 2017 Johan Zandstra
 */

namespace Anubarak\Sitemap\Http\Controllers;

use Craft;
use craft\helpers\DateTimeHelper;
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use Anubarak\Sitemap\helpers\PathHelper;
use Anubarak\Sitemap\models\SitemapEntryModel;
use Anubarak\Sitemap\records\SitemapCrawlerVisit;
use Anubarak\Sitemap\Plugin;
use DOMDocument;
use Exception;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use secondred\formbuilder\elements\db\EntryQuery;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Default Controller
 * @author    Johan Zandstra
 * @package   Sitemap
 * @since     1.0.0
 */
class SitemapController
{

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
    public function index(string $suffix = '')
    {
        // grab the site-map
        $site = Craft::$app->getSites()->getCurrentSite();
        $path = PathHelper::getSiteMapPath();
        // only default route
        $name = 'sitemap_' . $site->id  . $suffix . '.xml';
        if(file_exists($path . $name)){
            $date = FileHelper::lastModifiedTime($path . $name);
            $date = DateTimeHelper::toDateTime($date);
            // older than a week? regenerate by force
            $now = new \DateTime();
            if($now->modify('-1 week') > $date){
                // rebuild
                Plugin::getInstance()->getSiteMap()->buildIndexFile($site);
            }

            $xmlString = file_get_contents($path . $name);
        }else{
            if($suffix){
                return $this->redirect('sitemap.xml');
            }else{
                throw new HttpException(400, 'No sitemap found -> you need to generate it first via console command „php craft secondred-sitemap“');
            }
        }

        $this->response->format = Response::FORMAT_RAW;
        $this->response->getHeaders()->add('Content-Type', 'application/xml');

        return $xmlString;
    }
}