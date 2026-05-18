<?php
/**
 * sitemap plugin for Craft CMS 3.x
 *
 * Craft 3 plugin that provides an easy way to enable and manage a xml sitemap for search engines like Google
 *
 * @link      https://github.com/Dolphiq/craft3-plugin-sitemap
 * @copyright Copyright (c) 2017 Johan Zandstra
 */

namespace Anubarak\Sitemap\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Cms\Shared\Concerns\HasUid;

/**
 * SitemapRecord Record
 *
 * @author    Johan Zandstra
 * @package   Sitemap
 * @since     1.0.0
 * @property int     $id
 * @property int     $linkId
 * @property float   $priority
 * @property boolean $isNews
 * @property string  $changefreq
 * @property int     $fieldId
 */
class SitemapEntry extends BaseModel
{
    use HasUid;

    protected $table = 'dolphiq_sitemap_entries';
}
