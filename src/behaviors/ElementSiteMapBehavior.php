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

namespace anubarak\sitemap\behaviors;

use yii\base\Behavior;

/**
 * Class ElementSiteMapBehavior
 * @package anubarak\sitemap\behaviors
 * @since   18.09.2019
 */
class ElementSiteMapBehavior extends Behavior
{
    public $priority;
    public $changefreq;
    /**
     * @var \craft\elements\Asset $siteMapAsset
     */
    public $siteMapAsset;
}