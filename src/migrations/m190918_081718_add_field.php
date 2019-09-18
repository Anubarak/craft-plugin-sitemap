<?php

namespace dolphiq\sitemap\migrations;

use Craft;
use craft\db\Migration;

/**
 * m190918_081718_add_field migration.
 */
class m190918_081718_add_field extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%dolphiq_sitemap_entries}}', 'fieldId', $this->integer());
        $this->addForeignKey(
            null,
            '{{%dolphiq_sitemap_entries}}',
            ['fieldId'],
            '{{%fields}}',
            ['id'],
            'CASCADE'
        );
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn('{{%dolphiq_sitemap_entries}}', 'fieldId');
        return true;
    }
}
