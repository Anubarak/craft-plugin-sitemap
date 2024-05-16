<?php
/**
 * sitemap plugin for Craft CMS 3.x
 *
 * Craft 3 plugin that provides an easy way to enable and manage a xml sitemap for search engines like Google
 *
 * @link      https://github.com/Dolphiq/craft3-plugin-sitemap
 * @copyright Copyright (c) 2017 Johan Zandstra
 */

namespace anubarak\sitemap\models;

use anubarak\sitemap\Sitemap;

use Craft;
use craft\base\Model;

/**
 * Sitemap Settings Model
 *
 * This is a model used to define the plugin's settings.
 *
 * Models are containers for data. Just about every time information is passed
 * between services, controllers, and templates in Craft, it’s passed via a model.
 *
 * https://craftcms.com/docs/plugins/models
 *
 * @author    Johan Zandstra
 * @package   Sitemap
 * @since     1.0.0
 */
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * Special Criteria for news section
     *
     * @var array $newsSections
     */
    public array $newsSections = [];
    /**
     * Use the project-config or not
     *
     * @var bool $useProjectConfig
     */
    public bool $useProjectConfig = false;
}
