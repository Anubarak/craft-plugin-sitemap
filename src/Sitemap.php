<?php
/**
 * sitemap plugin for Craft CMS 3.x
 *
 * Craft 3 plugin that provides an easy way to enable and manage a xml sitemap for search engines like Google
 *
 * @link      https://github.com/Dolphiq/craft3-plugin-sitemap
 * @copyright Copyright (c) 2017 Johan Zandstra
 */

namespace dolphiq\sitemap;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\services\ProjectConfig;
use craft\web\UrlManager;
use dolphiq\sitemap\models\Settings;
use dolphiq\sitemap\services\SitemapService;
use yii\base\Event;

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
 * @property  SitemapService                          $sitemapService
 * @property mixed                                    $settingsResponse
 * @property \dolphiq\sitemap\services\SitemapService $siteMap
 * @property  Settings                                $settings
 * @method    Settings getSettings()
 */
class Sitemap extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * Sitemap::$plugin
     *
     * @var Sitemap
     */
    public static $plugin;
    // Public Methods
    // =========================================================================

    public bool $hasCpSection = true;
    public bool $hasCpSettings = true;
    // table schema version
    public string $schemaVersion = '1.0.4';

    /**
     * Return the settings response (if some one clicks on the settings/plugin icon)
     *
     */

    public function getSettingsResponse(): mixed
    {
        $url = UrlHelper::cpUrl('settings/sitemap');

        return Craft::$app->controller->redirect($url);
    }

    /**
     * Register CP URL rules
     *
     * @param RegisterUrlRulesEvent $event
     */

    public function registerCpUrlRules(RegisterUrlRulesEvent $event): void
    {
        // only register CP URLs if the user is logged in
        if (!Craft::$app->getUser()->getIdentity()) {
            return;
        }

        $rules = [
            // register routes for the settings tab
            'settings/sitemap'              => [
                'route'  => 'sitemap/settings',
                'params' => ['source' => 'CpSettings']
            ],
            'sitemap'                       => [
                'route'  => 'sitemap/settings',
                'params' => ['source' => 'CpSettings']
            ],
            'settings/sitemap/save-sitemap' => [
                'route'  => 'sitemap/settings/save-sitemap',
                'params' => ['source' => 'CpSettings']
            ],
        ];
        $event->rules = array_merge($event->rules, $rules);
    }

    /**
     * Set our $plugin static property to this class so that it can be accessed via
     * Sitemap::$plugin
     *
     * Called after the plugin class is instantiated; do any one-time initialization
     * here such as hooks and events.
     *
     * If you have a '/vendor/autoload.php' file, it will be loaded for you automatically;
     * you do not need to load it in your init() method.
     *
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        $this->setComponents(
            [
                'sitemap' => SitemapService::class
            ]
        );

        $this->hasCpSection = $this->getSettings()->useProjectConfig === false;

        // Register our CP routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            [$this, 'registerCpUrlRules']
        );

        // Register our site routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            static function(RegisterUrlRulesEvent $event) {
                $event->rules[] = [
                    'pattern'  => 'sitemap<suffix:[a-zA-Z_-].*>.xml',
                    'route'    => 'sitemap/sitemap/index',
                    'defaults' => [
                        'suffix' => '',
                    ]
                ];
            }
        );

        $path = SitemapService::PROJECT_CONFIG_KEY . '.{uid}';
        Craft::$app->projectConfig->onAdd($path, [$this->getSiteMap(), 'handleChangedSiteMapEntry'])->onUpdate(
                $path,
                [
                    $this->getSiteMap(),
                    'handleChangedSiteMapEntry'
                ]
            )->onRemove($path, [$this->getSiteMap(), 'handleDeletedSiteMapEntry']);

        Event::on(
            ProjectConfig::class,
            ProjectConfig::EVENT_REBUILD,
            [$this->getSiteMap(), 'rebuildProjectConfig']
        );

        Craft::info(
            Craft::t(
                'sitemap',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );

    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates and returns the model used to store the plugin’s settings.
     *
     * @return Settings
     */
    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    /**
     * Returns the rendered settings HTML, which will be inserted into the content
     * block on the settings page.
     *
     * @return string The rendered settings HTML
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Twig\Error\LoaderError
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate(
            'sitemap/settings',
            [
                'settings' => $this->getSettings()
            ]
        );
    }

    /**
     * Returns the Service to handle project config
     *
     * @return \dolphiq\sitemap\services\SitemapService
     */
    public function getSiteMap(): SitemapService
    {
        return $this->sitemapService;
    }
}
