<?php
/**
 * sitemap plugin for Craft CMS 3.x
 *
 * Craft 3 plugin that provides an easy way to enable and manage a xml sitemap for search engines like Google
 *
 * @link      https://github.com/Dolphiq/craft3-plugin-sitemap
 * @copyright Copyright (c) 2017 Johan Zandstra
 */

namespace anubarak\sitemap\records;

use craft\db\ActiveRecord;
use craft\records\CategoryGroup;
use craft\records\Field;
use craft\records\Section;
use yii\db\ActiveQuery;

/**
 * SitemapRecord Record
 *
 * ActiveRecord is the base class for classes representing relational data in terms of objects.
 *
 * Active Record implements the [Active Record design pattern](http://en.wikipedia.org/wiki/Active_record).
 * The premise behind Active Record is that an individual [[ActiveRecord]] object is associated with a specific
 * row in a database table. The object's attributes are mapped to the columns of the corresponding table.
 * Referencing an Active Record attribute is equivalent to accessing the corresponding table column for that record.
 *
 * http://www.yiiframework.com/doc-2.0/guide-db-active-record.html
 *
 * @author    Johan Zandstra
 * @package   Sitemap
 * @since     1.0.0
 * @property int                                      $id
 * @property int                                      $linkId
 * @property string                                   $type
 * @property float                                    $priority
 * @property string                                   $changefreq
 * @property \yii\db\ActiveQuery                      $category
 * @property \yii\db\ActiveQuery|Section              $section
 * @property bool                                     $useCustomUrl
 * @property \yii\db\ActiveQuery|\craft\records\Field $field
 * @property int                                      $fieldId
 */
class SitemapEntry extends ActiveRecord
{
    // Properties
    // =========================================================================

    /**
     * @var int|null priority
     */
    //public $linkId;

    /**
     * @var string|null type
     */
    //  public $type;

    /**
     * @var string|null changefreq
     */
    // public $changefreq;

    /**
     * @var double|null priority
     */
    //  public $priority;

    // Public Static Methods
    // =========================================================================

    /**
     * Declares the name of the database table associated with this AR class.
     * By default this method returns the class name as the table name by calling [[Inflector::camel2id()]]
     * with prefix [[Connection::tablePrefix]]. For example if [[Connection::tablePrefix]] is `tbl_`,
     * `Customer` becomes `tbl_customer`, and `OrderItem` becomes `tbl_order_item`. You may override this method
     * if the table is not named after this convention.
     *
     * By convention, tables created by plugins should be prefixed with the plugin
     * name and an underscore.
     *
     * @return string the table name
     */
    public static function tableName()
    {
        return '{{%dolphiq_sitemap_entries}}';
    }

    /**
     * getSection
     *
     * @return \yii\db\ActiveQuery
     *
     * @author Robin Schambach
     * @since  17.09.2019
     */
    public function getSection(): ActiveQuery
    {
        return $this->hasOne(Section::class, ['id' => 'linkId']);
    }

    /**
     * getCategory
     *
     *
     * @return \yii\db\ActiveQuery
     *
     * @author Robin Schambach
     * @since  17.09.2019
     */
    public function getCategory(): ActiveQuery
    {
        return $this->hasOne(CategoryGroup::class, ['id' => 'linkId']);
    }

    /**
     * Get the field
     *
     *
     * @return \yii\db\ActiveQuery
     *
     * @author Robin Schambach
     * @since  18.09.2019
     */
    public function getField(): ActiveQuery
    {
        return $this->hasOne(Field::class, ['id' => 'fieldId']);
    }
}
