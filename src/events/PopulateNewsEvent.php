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

use craft\elements\Entry;
use yii\base\Event;

/**
 * Class PopulateNewsEvent
 * @package dolphiq\sitemap\events
 * @since   18.09.2019
 */
class PopulateNewsEvent extends Event
{
    /**
     * @var \craft\elements\Entry $element
     */
    public Entry $element;
    /**
     * An array with Keys
     * author
     * language
     * postDate
     * title
     *
     * @var array $data
     */
    public array $data;
}