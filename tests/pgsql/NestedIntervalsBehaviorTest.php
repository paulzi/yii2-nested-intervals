<?php
/**
 * @link https://github.com/paulzi/yii2-nested-intervals
 * @copyright Copyright (c) 2015 PaulZi <pavel.zimakoff@gmail.com>
 * @license MIT (https://github.com/paulzi/yii2-nested-intervals/blob/master/LICENSE)
 */

namespace paulzi\nestedintervals\tests\pgsql;

use paulzi\nestedintervals\tests\NestedIntervalsBehaviorTestCase;

/**
 * @group pgsql
 * @group 32bit
 *
 * @author PaulZi <pavel.zimakoff@gmail.com>
 */
class NestedIntervalsBehaviorTest extends NestedIntervalsBehaviorTestCase
{
    protected static $driverName = 'pgsql';
}