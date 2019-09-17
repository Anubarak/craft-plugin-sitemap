<?php

namespace dolphiq\sitemap\migrations;

use Craft;
use craft\db\Migration;

/**
 * m190917_111542_add_custom_url migration.
 */
class m190917_111542_add_custom_url extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%dolphiq_sitemap_entries}}', 'useCustomUrl', $this->boolean());
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn('{{%dolphiq_sitemap_entries}}', 'useCustomUrl');
        return true;
    }
}
