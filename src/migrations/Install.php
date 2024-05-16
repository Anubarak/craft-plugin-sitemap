<?php
/**
 * sitemap plugin for Craft CMS 3.x
 *
 * Craft 3 plugin that provides an easy way to enable and manage a xml sitemap for search engines like Google
 *
 * @link      https://github.com/Dolphiq/craft3-plugin-sitemap
 * @copyright Copyright (c) 2017 Johan Zandstra
 */

namespace anubarak\sitemap\migrations;

use anubarak\sitemap\Sitemap;

use Craft;
use craft\config\DbConfig;
use craft\db\Migration;

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
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->db->tableExists('{{%dolphiq_sitemap_entries}}')) {
            $this->createTable(
                '{{%dolphiq_sitemap_entries}}',
                [
                    'id'           => $this->primaryKey(),
                    'linkId'       => $this->integer()->notNull(),
                    'useCustomUrl' => $this->boolean(),
                    'fieldId'      => $this->integer(),
                    'type'         => $this->string(30)->notNull()->defaultValue(''),
                    'priority'     => $this->double(2)->notNull()->defaultValue(0.5),
                    'changefreq'   => $this->string(30)->notNull()->defaultValue(''),
                    'dateCreated'  => $this->dateTime()->notNull(),
                    'dateUpdated'  => $this->dateTime()->notNull(),
                    'uid'          => $this->uid(),
                ]
            );

            $this->addForeignKey(
                null,
                '{{%dolphiq_sitemap_entries}}',
                ['fieldId'],
                '{{%fields}}',
                ['id'],
                'CASCADE'
            );

            $this->createIndex(
                'dolphiq_sitemap_entries_idx',
                '{{%dolphiq_sitemap_entries}}',
                ['type', 'linkId'],
                true
            );
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropTableIfExists('{{%dolphiq_sitemap_entries}}');

        return true;
    }
}

