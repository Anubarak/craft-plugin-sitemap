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

namespace Anubarak\Sitemap\Support;


use CraftCms\Cms\Support\File;
use CraftCms\Cms\Support\Path;

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
     */
    public static function getSiteMapPath(bool $createPath = true): string
    {
        $path = app(Path::class)->storage() . DIRECTORY_SEPARATOR . 'sitemaps' . DIRECTORY_SEPARATOR;
        File::makeDirectory($path);

        return $path;
    }
}