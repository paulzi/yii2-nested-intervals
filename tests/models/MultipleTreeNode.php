<?php
/**
 * @link https://github.com/paulzi/yii2-nested-intervals
 * @copyright Copyright (c) 2015 PaulZi <pavel.zimakoff@gmail.com>
 * @license MIT (https://github.com/paulzi/yii2-nested-intervals/blob/master/LICENSE)
 */

namespace paulzi\nestedintervals\tests\models;

use paulzi\nestedintervals\NestedIntervalsBehavior;

/**
 * @author PaulZi <pavel.zimakoff@gmail.com>
 *
 * @property integer $id
 * @property integer $tree
 * @property integer $lft
 * @property integer $rgt
 * @property integer $depth
 * @property string $slug
 *
 * @property Node[] $parents
 * @property Node $parent
 * @property Node $root
 * @property Node[] $descendants
 * @property Node[] $children
 * @property Node[] $leaves
 * @property Node $prev
 * @property Node $next
 *
 * @method static MultipleTreeNode|null findOne() findOne($condition)
 *
 * @mixin NestedIntervalsBehavior
 */
class MultipleTreeNode extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%multiple_tree}}';
    }
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'tree' => [
                'class' => NestedIntervalsBehavior::className(),
                'treeAttribute' => 'tree',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function transactions()
    {
        return [
            self::SCENARIO_DEFAULT => self::OP_ALL,
        ];
    }

    /**
     * @return NodeQuery
     */
    public static function find()
    {
        return new NodeQuery(get_called_class());
    }
}