<?php
/**
 * @link https://github.com/paulzi/yii2-nested-intervals
 * @copyright Copyright (c) 2015 PaulZi <pavel.zimakoff@gmail.com>
 * @license MIT (https://github.com/paulzi/yii2-nested-intervals/blob/master/LICENSE)
 */

namespace paulzi\nestedintervals\tests\mysql;

use paulzi\nestedintervals\tests\NestedIntervalsQueryTraitTestCase;

/**
 * @group mysql
 * @group 32bit
 *
 * @author PaulZi <pavel.zimakoff@gmail.com>
 */
class NestedIntervalsQueryTraitTest extends NestedIntervalsQueryTraitTestCase
{
    protected static $driverName = 'mysql';
}