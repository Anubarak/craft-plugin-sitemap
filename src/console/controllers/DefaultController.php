<?php
/**
 * Craft CMS Plugins for Craft CMS 3.x
 *
 * Created with PhpStorm.
 *
 * @link      https://github.com/Anubarak/
 * @email     anubarak1993@gmail.com
 * @copyright Copyright (c) 2019 Robin Schambach
 */

namespace anubarak\sitemap\console\controllers;

use Craft;
use craft\console\Controller;
use anubarak\sitemap\Sitemap;
use yii\console\ExitCode;

/**
 * Creates the sitemap.xml file
 *
 * Class DefaultController
 * @package anubarak\sitemap\console\controllers
 * @since   17.09.2019
 */
class DefaultController extends Controller
{
    /**
     * Default Action, creates the siteMap
     *
     * @return int
     *
     * @author Robin Schambach
     * @since  17.09.2019
     * @throws \yii\base\Exception
     */
    public function actionIndex(): int
    {
        $sites = Craft::$app->getSites()->getAllSites();
        foreach ($sites as $site){
            if($site->hasUrls && $site->getBaseUrl()){
                Sitemap::getInstance()->getSiteMap()->buildIndexFile($site);
            }
        }


        return ExitCode::OK;
    }
}