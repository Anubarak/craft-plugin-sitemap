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

namespace Anubarak\Sitemap\Events;


use Anubarak\Sitemap\Models\SitemapEntry;
use CraftCms\Cms\Element\Queries\ElementQuery;
use CraftCms\Cms\Site\Data\Site;

/**
 * Class SearchElementsEvent
 * @since 17.09.2019
 */
class SearchElementsEvent
{
    public function __construct(
        /**
         * The Element Query
         *
         * @var ElementQuery $query
         */
        public ElementQuery      $query,
        /**
         * The SiteMap Entry
         *
         * @var \Anubarak\Sitemap\Models\SitemapEntry|null $siteMapEntry
         */
        public SitemapEntry|null $siteMapEntry = null,
        /**
         * @var Site $site
         */
        public Site              $site,
    ) {
    }
}