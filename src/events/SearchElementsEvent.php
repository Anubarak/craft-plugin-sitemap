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

namespace dolphiq\sitemap\events;

use yii\base\Event;

/**
 * Class SearchElementsEvent
 * @since 17.09.2019
 */
class SearchElementsEvent extends Event
{
    /**
     * The Element Query
     *
     * @var \craft\elements\db\ElementQuery $query
     */
    public $query;
    /**
     * The SiteMap Entry
     *
     * @var \dolphiq\sitemap\records\SitemapEntry $siteMapEntry
     */
    public $siteMapEntry;
}