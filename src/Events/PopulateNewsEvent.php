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


use CraftCms\Cms\Entry\Elements\Entry;

/**
 * Class PopulateNewsEvent
 * @package Anubarak\Sitemap\events
 * @since   18.09.2019
 */
class PopulateNewsEvent
{
    public function __construct(
        /**
         * @var Entry $element
         */
        public Entry $element,
        /**
         * An array with Keys
         * author
         * language
         * postDate
         * title
         *
         * @var array $data
         */
        public array $data = []
    ) {
    }
}