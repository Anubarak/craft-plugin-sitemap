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

use Anubarak\Sitemap\Sitemap;
use Anubarak\Sitemap\Support\PathHelper;
use CraftCms\Cms\Site\Sites;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Cms\Support\File;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Default Controller
 * @author    Johan Zandstra
 * @package   Sitemap
 * @since     1.0.0
 */
class SitemapController
{

    public function __construct(
        private readonly Sites $sites,
        private readonly Sitemap $sitemap,
    )
    {
    }

    /**
     * Handle a request going to our plugin's index action URL,
     * e.g.: actions/sitemap/default
     *
     * @param string $suffix
     *
     * @return mixed
     */
    public function index(string $suffix = '')
    {
        // grab the site-map
        $site = $this->sites->getCurrentSite();
        $path = PathHelper::getSiteMapPath();

        // only default route
        $name = 'sitemap_' . $site->id  . $suffix . '.xml';
        if(file_exists($path . $name)){
            $date = File::lastModified($path . $name);
            $date = DateTimeHelper::toDateTime($date);
            // older than a week? regenerate by force
            $now = new \DateTime();
            if($now->modify('-1 week') > $date){
                // rebuild
                $this->sitemap->buildIndexFile($site);
            }

            $xmlString = file_get_contents($path . $name);
        }else{
            if($suffix){
                return redirect()->route('sitemap-index');
            }else{
                throw new HttpException(400, 'No sitemap found -> you need to generate it first via console command „php craft secondred-sitemap“');
            }
        }

        return response($xmlString, 200, [
            'Content-Type' => 'application/xml'
        ]);
    }
}