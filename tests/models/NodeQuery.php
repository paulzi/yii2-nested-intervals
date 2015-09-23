<?php
/**
 * @link https://github.com/paulzi/yii2-nested-intervals
 * @copyright Copyright (c) 2015 PaulZi <pavel.zimakoff@gmail.com>
 * @license MIT (https://github.com/paulzi/yii2-nested-intervals/blob/master/LICENSE)
 */

namespace paulzi\nestedintervals\tests\models;

use paulzi\nestedintervals\NestedIntervalsQueryTrait;

/**
 * @author PaulZi <pavel.zimakoff@gmail.com>
 */
class NodeQuery extends \yii\db\ActiveQuery
{
    use NestedIntervalsQueryTrait;
}