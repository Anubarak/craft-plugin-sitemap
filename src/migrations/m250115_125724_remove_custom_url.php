<?php

namespace anubarak\sitemap\migrations;

use Craft;
use craft\db\Migration;

/**
 * m250115_125724_remove_custom_url migration.
 */
class m250115_125724_remove_custom_url extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Place migration code here...
        $this->dropColumn('{{%dolphiq_sitemap_entries}}', 'useCustomUrl');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250115_125724_remove_custom_url cannot be reverted.\n";
        return false;
    }
}
