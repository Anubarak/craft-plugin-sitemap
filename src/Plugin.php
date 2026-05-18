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


use Anubarak\Sitemap\Commands\CreateSitemap;

/**
 * Craft plugins are very much like little applications in and of themselves. We’ve made
 * it as simple as we can, but the training wheels are off. A little prior knowledge is
 * going to be required to write a plugin.
 *
 * For the purposes of the plugin docs, we’re going to assume that you know PHP and SQL,
 * as well as some semi-advanced concepts like object-oriented programming and PHP namespaces.
 *
 * https://craftcms.com/docs/plugins/introduction
 *
 * @author    Johan Zandstra
 * @package   Sitemap
 * @since     1.0.0
 *
 * @method    Settings getSettings()
 */
class Plugin extends \CraftCms\Cms\Plugin\Plugin
{
    /**
     * @inheritdoc
     */
    protected array $commands = [
        CreateSitemap::class,
    ];
    /** @inheritdoc */
    public bool $hasCpSettings = false;
    /** @inheritdoc */
    public bool $hasCpSection = true;
    /** @inheritdoc */
    public string $schemaVersion = '1.0.5';

    /**
     * Creates and returns the model used to store the plugin’s settings.
     *
     * @return Settings
     */
    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }
}
