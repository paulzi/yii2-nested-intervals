<?php
/**
 * @link https://github.com/paulzi/yii2-nested-intervals
 * @copyright Copyright (c) 2015 PaulZi <pavel.zimakoff@gmail.com>
 * @license MIT (https://github.com/paulzi/yii2-nested-intervals/blob/master/LICENSE)
 */

namespace paulzi\nestedintervals\tests;

use paulzi\nestedintervals\tests\models\Node;
use paulzi\nestedintervals\tests\models\MultipleTreeNode;
use Yii;

/**
 * @author PaulZi <pavel.zimakoff@gmail.com>
 */
class NestedIntervalsQueryTraitTestCase extends BaseTestCase
{
    public function testRoots()
    {
        $this->assertEquals([1], array_map(function ($value) { return $value->id; }, Node::find()->roots()->all()));
        $this->assertEquals([1, 26], array_map(function ($value) { return $value->id; }, MultipleTreeNode::find()->roots()->all()));
    }
}