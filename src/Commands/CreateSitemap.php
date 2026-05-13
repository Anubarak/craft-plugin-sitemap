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

namespace Anubarak\Sitemap\Commands;

use Craft;
use craft\console\Controller;
use Anubarak\Sitemap\Plugin;
use Illuminate\Console\Command;
use yii\console\ExitCode;

/**
 * Creates the sitemap.xml file
 *
 * Class DefaultController
 * @package Anubarak\Sitemap\console\controllers
 * @since   17.09.2019
 */
class CreateSitemap extends Command
{
    protected $signature   = 'sitemap:create';
    protected $description = 'Create a new sitemap for each site';

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
                Plugin::getInstance()->getSiteMap()->buildIndexFile($site);
            }
        }


        return ExitCode::OK;
    }
}