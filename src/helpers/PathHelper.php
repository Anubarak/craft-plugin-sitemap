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

namespace anubarak\sitemap\helpers;


use Craft;
use craft\helpers\FileHelper;

class PathHelper
{
    /**
     * Get the Path of all site-maps
     *
     * @param bool $createPath
     *
     * @return string
     *
     * @author Robin Schambach
     * @since  17.09.2019
     * @throws \yii\base\Exception
     */
    public static function getSiteMapPath(bool $createPath = true): string
    {
        $path = Craft::$app->getPath()->getStoragePath() . DIRECTORY_SEPARATOR . 'sitemaps' . DIRECTORY_SEPARATOR;
        FileHelper::createDirectory($path);

        return $path;
    }
}