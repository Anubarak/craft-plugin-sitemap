<?php
/**
 * sitemap plugin for Craft CMS 3.x
 *
 * Craft 3 plugin that provides an easy way to enable and manage a xml sitemap for search engines like Google
 *
 * @link      https://github.com/Dolphiq/craft3-plugin-sitemap
 * @copyright Copyright (c) 2017 Johan Zandstra
 */

namespace Anubarak\Sitemap;

use CraftCms\Cms\Plugin\PluginSettings;

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
class Settings extends PluginSettings
{
    public int $maxEntriesPerSitemap = 1000;
}
