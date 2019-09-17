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

namespace dolphiq\sitemap\behaviors;

use yii\base\Behavior;

class ElementSiteMapBehavior extends Behavior
{
    public $priority;
    public $changefreq;
}