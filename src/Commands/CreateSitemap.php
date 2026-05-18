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

namespace Anubarak\Sitemap\Commands;

use Anubarak\Sitemap\Data\SitemapIndex;
use Anubarak\Sitemap\Sitemap;
use CraftCms\Cms\Site\Sites;
use Illuminate\Console\Command;
use function Laravel\Prompts\multiselect;

/**
 * Creates the sitemap.xml file
 *
 * Class DefaultController
 * @package Anubarak\Sitemap\console\controllers
 * @since   17.09.2019
 */
final class CreateSitemap extends Command
{
    protected     $signature        = 'sitemap:create
    {--all : Build sitemap for all sites}
    ';
    protected     $description      = 'Create a new sitemap for each site';
    private array $availableOptions = [
        'all' => 'All',
    ];

    public function __construct(
        private readonly Sites   $sites,
        private readonly Sitemap $sitemap,
    ) {

        foreach ($this->sites->getAllSites() as $site) {
            $this->availableOptions["site:{$site->handle}"] = $site->getName();
            $this->signature .= "{--site:{$site->handle} : Create sitemap for '{$site->getName()}' site}";
        }

        parent::__construct();
    }

    /**
     * Build the sitemap for all sites
     *
     * @return int
     * @throws \DOMException
     * @since  17.09.2019
     * @author Robin Schambach
     */
    public function handle(): int
    {

        $sites = [];
        if ($this->option('all')) {
            // Scenario A: User wants everything
            $sites = $this->sites->getAllSites()->all();
        } else {
            // Scenario B: Check if specific flags were passed --siteA --siteB
            foreach (array_keys($this->availableOptions) as $option) {
                $sites = [];
                if ($this->option($option)) {
                    $handle = str_replace('site:', '', $option);
                    $sites[] = $this->sites->getSiteByHandle($handle);
                }
            }
        }

        if (empty($sites)) {
            // Scenario C: No flags were passed, fall back to the interactive prompt
            $selectedOptions = multiselect(
                label: 'What Sites should be included?',
                options: $this->availableOptions
            );

            $selectedCollection = collect($selectedOptions);
            // Example of using the selected values:
            if ($selectedCollection->contains('all')) {
                $sites = $this->sites->getAllSites()->all();
            } else {
                $sites = $selectedCollection
                    ->map(fn($handle) => $this->sites->getSiteByHandle(str_replace('site:', '', $handle)))
                    ->all();
            }
        }

        if (empty($sites)) {
            $this->info('No sites selected for sitemap generation.');

            return self::SUCCESS;
        }

        $indexCb = function(SitemapIndex $index, int $current, int $max) {
            $this->newLine();
            $this->info("Generating sitemap {$index->getName()} {$current}/{$max}");
        };

        foreach ($sites as $site) {
            if ($site->hasUrls && $site->getBaseUrl()) {
                $this->newLine();
                $bar = $this->output->createProgressBar(0);

                $this->sitemap->buildIndexFile($site, $indexCb, function(int $current, int $max) use ($bar) {
                    $bar->setMaxSteps($max);
                    $bar->advance();
                });
                $bar->finish();
                $this->newLine();
            }
        }

        $this->info('All sitemaps generated');


        return self::SUCCESS;
    }
}