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

namespace anubarak\sitemap\events;

use craft\elements\db\ElementQuery;
use craft\models\Site;
use anubarak\sitemap\records\SitemapEntry;
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
    public ElementQuery $query;
    /**
     * The SiteMap Entry
     *
     * @var \anubarak\sitemap\records\SitemapEntry|null $siteMapEntry
     */
    public SitemapEntry|null $siteMapEntry = null;
    /**
     * @var \craft\models\Site $site
     */
    public Site $site;
}