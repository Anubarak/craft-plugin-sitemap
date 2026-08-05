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
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Field\Assets;
use CraftCms\Cms\Field\Fields;
use CraftCms\Cms\Section\Sections;
use CraftCms\Cms\View\TemplateManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Settings Controller, render and store sitemap settings
 *
 * @author    Johan Zandstra
 * @package   Sitemap
 * @since     1.0.0
 */
class SettingsController
{
    public function __construct(
        private readonly Plugin           $plugin,
        private readonly TemplateManager $renderer,
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
            ->whereIn('linkId', $allSectionIds)
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

    /**
     * Display the sitemap
     *
     * @return string
     * @author Robin Schambach
     * @since  18.05.26
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

        return $this->renderer->renderPageTemplate('secondred-sitemap/settings', [
            'settings'      => $this->plugin->getSettings(),
            'allStructures' => $allStructures,
            'fields'        => $fieldData
        ]);
    }

    /**
     * Called when saving the settings.
     *
     * @throws \Throwable
     */
    public function save(): string
    {
        Gate::authorize('accessPlugin-' . $this->plugin->handle);

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
                    $sitemapEntry->isNews = $entry['isNews'] ?? false;
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
                ->whereNotIn('linkId', $allSectionIds)
                ->delete();
        }


        return $this->index();
    }
}
