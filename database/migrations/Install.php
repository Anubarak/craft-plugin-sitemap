<?php
/**
 * sitemap plugin for Craft CMS 3.x
 *
 * Craft 3 plugin that provides an easy way to enable and manage a xml sitemap for search engines like Google
 *
 * @link      https://github.com/Dolphiq/craft3-plugin-sitemap
 * @copyright Copyright (c) 2017 Johan Zandstra
 */

namespace Anubarak\Sitemap\Migrations;


use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sitemap Install Migration
 *
 * If your plugin needs to create any custom database tables when it gets installed,
 * create a migrations/ folder within your plugin folder, and save an Install.php file
 * within it using the following template:
 *
 * If you need to perform any additional actions on install/uninstall, override the
 * safeUp() and safeDown() methods.
 *
 * @author    Johan Zandstra
 * @package   Sitemap
 * @since     1.0.0
 */
class Install extends \CraftCms\Cms\Database\Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dolphiq_sitemap_entries')) {
            Schema::create('dolphiq_sitemap_entries', function(Blueprint $table) {
                $table->id();
                $table->integer('linkId');
                $table->integer('fieldId')->nullable();
                $table->boolean('isNews')->default(false);
                $table->double('priority')->default(0.5);
                $table->string('changefreq', 30)->default('');
                $table->dateTime('dateCreated');
                $table->dateTime('dateUpdated');
                $table->uuid('uid');

                $table->foreign('fieldId')
                    ->references('id')
                    ->on('fields');


                $table->index(['type', 'linkId']);

                $table->foreign('linkSiteId')
                    ->references('id')
                    ->on('sites')
                    ->onDelete('SET NULL');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dolphiq_sitemap_entries');
    }
}

