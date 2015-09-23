<?php
/**
 * @link https://github.com/paulzi/yii2-nested-intervals
 * @copyright Copyright (c) 2015 PaulZi <pavel.zimakoff@gmail.com>
 * @license MIT (https://github.com/paulzi/yii2-nested-intervals/blob/master/LICENSE)
 */

namespace paulzi\nestedintervals\tests\pgsql;

use paulzi\nestedintervals\tests\NestedIntervalsBehavior64TestCase;

/**
 * @group pgsql
 * @group 64bit
 *
 * @author PaulZi <pavel.zimakoff@gmail.com>
 */
class NestedIntervalsBehavior64Test extends NestedIntervalsBehavior64TestCase
{
    protected static $driverName = 'pgsql';
}