<?php
/**
 * sitemap plugin for Craft CMS 3.x
 *
 * Craft 3 plugin that provides an easy way to enable and manage a xml sitemap for search engines like Google
 *
 * @link      https://github.com/Dolphiq/craft3-plugin-sitemap
 * @copyright Copyright (c) 2017 Johan Zandstra
 */

namespace Anubarak\Sitemap\Http\Controllers;


use Anubarak\Sitemap\Models\SitemapEntry;
use Anubarak\Sitemap\Plugin;
use Anubarak\Sitemap\Sitemap;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Field\Assets;
use CraftCms\Cms\Field\Fields;
use CraftCms\Cms\Section\Sections;
use CraftCms\Cms\Twig\TemplateRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Default Controller
 *
 * Generally speaking, controllers are the middlemen between the front end of
 * the CP/website and your plugin’s services. They contain action methods which
 * handle individual tasks.
 *
 * A common pattern used throughout Craft involves a controller action gathering
 * post data, saving it on a model, passing the model off to a service, and then
 * responding to the request appropriately depending on the service method’s response.
 *
 * Action methods begin with the prefix “action”, followed by a description of what
 * the method does (for example, actionSaveIngredient()).
 *
 * https://craftcms.com/docs/plugins/controllers
 *
 * @author    Johan Zandstra
 * @package   Sitemap
 * @since     1.0.0
 */
class SettingsController
{
    public function __construct(
        private readonly Plugin           $plugin,
        private readonly TemplateRenderer $renderer,
        private readonly Fields           $fields,
        private readonly Sections         $sections,
        private readonly Request          $request,
    ) {
    }

    /**
     * Get Sections
     *
     * @return array
     *
     * @author Robin Schambach
     * @since  17.09.2019
     */
    private function getSections(): array
    {
        $sections = $this->sections->getAllSections();
        $response = [];

        $allSectionIds = $this->sections->getAllSectionIds();
        $siteMapRecords = SitemapEntry::query()
            ->where('linkId', 'IN', $allSectionIds)
            ->get();

        foreach ($sections->all() as $section) {
            $siteSettings = $section->getSiteSettings();
            $hasUrls = false;
            foreach ($siteSettings as $siteSetting) {
                if ((bool) $siteSetting->hasUrls === true) {
                    $hasUrls = true;
                }
            }

            if ($hasUrls === false) {
                continue;
            }

            $model = $siteMapRecords->first(fn(SitemapEntry $m) => $m->linkId === $section->id);

            $response[] = [
                'id'             => (int) $section->id,
                'structureId'    => (int) $section->structureId,
                'name'           => $section->name,
                'heading'        => $section->name,
                'fieldId'        => $model?->fieldId ?? null,
                'handle'         => $section->handle,
                'type'           => $section->type,
                'elementCount'   => Entry::find()->sectionId($section->id)->count(),
                'sitemapEntryId' => $model?->id,
                'changefreq'     => $model?->changefreq ?? 'weekly',
                'priority'       => $model?->priority ?? '0.5',
                'isNews'         => $model?->isNews ?? false,
                'enabled'        => $model !== null,
            ];
        }

        return $response;
    }

    // Public Methods
    // =========================================================================

    /**
     * Handle a request going to our plugin's index action URL,
     * e.g.: actions/sitemap/default
     *
     * @return mixed
     */
    public function index()
    {
        Gate::authorize('accessPlugin-' . $this->plugin->handle);
        $allStructures = $this->getSections();

        $fields = $this->fields->getAllFields();
        $fieldData = [['value' => null, 'label' => '']];
        foreach ($fields->all() as $field) {
            if ($field instanceof Assets) {
                $fieldData[] = [
                    'label' => $field->name,
                    'value' => $field->id
                ];
            }
        }

        return $this->renderer->renderTemplate('secondred-sitemap/settings', [
            'settings'      => $this->plugin->getSettings(),
            'allStructures' => $allStructures,
            'fields'        => $fieldData
        ]);
    }

    /**
     * Called when saving the settings.
     *
     * @return \Craft\web\Response
     * @throws \yii\base\ErrorException
     * @throws \yii\base\Exception
     * @throws \yii\base\NotSupportedException
     * @throws \yii\db\StaleObjectException
     * @throws \yii\web\BadRequestHttpException
     * @throws \yii\web\ForbiddenHttpException
     * @throws \yii\web\ServerErrorHttpException
     * @throws \Throwable
     */
    public function save()
    {
        Gate::authorize('accessPlugin-' . $this->plugin->handle);

        $settings = $this->plugin->getSettings();

        $sitemapSections = $this->request->post('sitemapSections');
        // filter the enabled sections
        $allSectionIds = [];


        if (is_array($sitemapSections)) {
            foreach ($sitemapSections as $key => $entry) {
                if (!$entry['enabled']) {
                    continue;
                }

                // filter section id from key

                $id = (int) str_replace('id:', '', $key);
                if ($id > 0) {
                    // find the entry, else add one
                    $sitemapEntry = SitemapEntry::query()
                        ->where('linkId', '=', $id)
                        ->first();
                    if (!$sitemapEntry) {
                        // insert / update this section
                        $sitemapEntry = new SitemapEntry();
                    }
                    $sitemapEntry->linkId = $id;
                    $sitemapEntry->type = 'section';
                    $sitemapEntry->priority = $entry['priority'];
                    $sitemapEntry->changefreq = $entry['changefreq'];
                    $sitemapEntry->fieldId = $entry['fieldId'] ?? null;

                    $sitemapEntry->save();

                    //                        $this->sitemap->saveEntry($sitemapEntry);
                    $allSectionIds[] = $id;
                }
            }
        }

        // remove all sitemaps not in the id list
        if (empty($allSectionIds)) {
            SitemapEntry::query()
                ->delete();
        } else {
            SitemapEntry::query()
                ->where('linkId', 'NOT IN', $allSectionIds)
                ->delete();
        }


        return $this->index();
    }
}
