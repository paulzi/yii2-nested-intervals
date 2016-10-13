<?php
/**
 * @link https://github.com/paulzi/yii2-nested-intervals
 * @copyright Copyright (c) 2015 PaulZi <pavel.zimakoff@gmail.com>
 * @license MIT (https://github.com/paulzi/yii2-nested-intervals/blob/master/LICENSE)
 */

namespace paulzi\nestedintervals\tests;

use paulzi\nestedintervals\tests\migrations\TestMigration;
use paulzi\nestedintervals\tests\models\MultipleTreeNode64;
use Yii;

/**
 * @author PaulZi <pavel.zimakoff@gmail.com>
 */
class NestedIntervalsBehavior64TestCase extends BaseTestCase
{
    /**
     * @inheritdoc
     */
    public function getDataSet()
    {
        return new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(require(__DIR__ . '/data/data-64.php'));
    }

    public function testGetParents()
    {
        $data = [1, 4, 9];
        $this->assertEquals($data, array_map(function ($value) { return $value->id; }, MultipleTreeNode64::findOne(21)->parents));

        $data = [];
        $this->assertEquals($data, array_map(function ($value) { return $value->id; }, MultipleTreeNode64::findOne(1)->parents));

        $data = [2, 7];
        $this->assertEquals($data, array_map(function ($value) { return $value->id; }, MultipleTreeNode64::findOne(17)->getParents(2)->all()));

        $data = [26, 30];
        $this->assertEquals($data, array_map(function ($value) { return $value->id; }, MultipleTreeNode64::findOne(38)->parents));
    }

    public function testGetParent()
    {
        $this->assertEquals(5, MultipleTreeNode64::findOne(12)->parent->id);

        $this->assertEquals(26, MultipleTreeNode64::findOne(29)->getParent()->one()->getAttribute('id'));

        $this->assertEquals(null, MultipleTreeNode64::findOne(1)->parent);
    }

    public function testGetRoot()
    {
        $this->assertEquals(26, MultipleTreeNode64::findOne(28)->root->id);

        $this->assertEquals(26, MultipleTreeNode64::findOne(26)->getRoot()->one()->getAttribute('id'));
    }

    public function testGetDescendants()
    {
        $data = [8, 9, 20, 21, 22, 10, 23, 24, 25];
        $this->assertEquals($data, array_map(function ($value) { return $value->id; }, MultipleTreeNode64::findOne(4)->descendants));

        $data = [2, 5, 6, 7];
        $this->assertEquals($data, array_map(function ($value) { return $value->id; }, MultipleTreeNode64::findOne(2)->getDescendants(1, true)->all()));

        $data = [10, 25, 24, 23, 9, 22, 21, 20, 8];
        $this->assertEquals($data, array_map(function ($value) { return $value->id; }, MultipleTreeNode64::findOne(4)->getDescendants(3, false, true)->all()));

        $data = [];
        $this->assertEquals($data, array_map(function ($value) { return $value->id; }, MultipleTreeNode64::findOne(8)->descendants));
    }

    public function testGetChildren()
    {
        $data = [8, 9, 10];
        $this->assertEquals($data, array_map(function ($value) { return $value->id; }, MultipleTreeNode64::findOne(4)->children));

        $data = [];
        $this->assertEquals($data, array_map(function ($value) { return $value->id; }, MultipleTreeNode64::findOne(28)->getChildren()->all()));
    }

    public function testGetLeaves()
    {
        $data = [8, 20, 21, 22, 23, 24, 25];
        $this->assertEquals($data, array_map(function ($value) { return $value->id; }, MultipleTreeNode64::findOne(4)->leaves));

        $data = [3, 8];
        $this->assertEquals($data, array_map(function ($value) { return $value->id; }, MultipleTreeNode64::findOne(1)->getLeaves(2)->all()));
    }

    public function testGetPrev()
    {
        $this->assertEquals(11, MultipleTreeNode64::findOne(12)->prev->id);

        $this->assertEquals(null, MultipleTreeNode64::findOne(20)->getPrev()->one());
    }

    public function testGetNext()
    {
        $this->assertEquals(13, MultipleTreeNode64::findOne(12)->next->id);

        $this->assertEquals(null, MultipleTreeNode64::findOne(19)->getNext()->one());
    }

    public function testPopulateTree()
    {
        $node = MultipleTreeNode64::findOne(2);
        $node->populateTree();
        $this->assertEquals(true, $node->isRelationPopulated('children'));
        $this->assertEquals(true, $node->children[0]->isRelationPopulated('children'));
        $this->assertEquals(11, $node->children[0]->children[0]->id);

        $node = MultipleTreeNode64::findOne(2);
        $node->populateTree(1);
        $this->assertEquals(true, $node->isRelationPopulated('children'));
        $this->assertEquals(false, $node->children[0]->isRelationPopulated('children'));
        $this->assertEquals(5, $node->children[0]->id);

        $node = MultipleTreeNode64::findOne(19);
        $node->populateTree();
        $this->assertEquals(true, $node->isRelationPopulated('children'));

        $node = MultipleTreeNode64::findOne(19);
        $node->populateTree(1);
        $this->assertEquals(true, $node->isRelationPopulated('children'));
    }

    public function testIsRoot()
    {
        $this->assertTrue(MultipleTreeNode64::findOne(1)->isRoot());
        $this->assertTrue(MultipleTreeNode64::findOne(26)->isRoot());

        $this->assertFalse(MultipleTreeNode64::findOne(3)->isRoot());
        $this->assertFalse(MultipleTreeNode64::findOne(37)->isRoot());
    }

    public function testIsChildOf()
    {
        $this->assertTrue(MultipleTreeNode64::findOne(10)->isChildOf(MultipleTreeNode64::findOne(1)));

        $this->assertTrue(MultipleTreeNode64::findOne(9)->isChildOf(MultipleTreeNode64::findOne(4)));

        $this->assertFalse(MultipleTreeNode64::findOne(12)->isChildOf(MultipleTreeNode64::findOne(15)));

        $this->assertFalse(MultipleTreeNode64::findOne(21)->isChildOf(MultipleTreeNode64::findOne(22)));

        $this->assertFalse(MultipleTreeNode64::findOne(8)->isChildOf(MultipleTreeNode64::findOne(8)));

        $this->assertFalse(MultipleTreeNode64::findOne(6)->isChildOf(MultipleTreeNode64::findOne(27)));
    }

    public function testIsLeaf()
    {
        $this->assertTrue(MultipleTreeNode64::findOne(3)->isLeaf());

        $this->assertFalse(MultipleTreeNode64::findOne(4)->isLeaf());
    }
    
    public function testMakeRootInsert()
    {
        (new TestMigration())->up();
        $dataSet = new ArrayDataSet(require(__DIR__ . '/data/empty.php'));
        $this->getDatabaseTester()->setDataSet($dataSet);
        $this->getDatabaseTester()->onSetUp();

        $node = new MultipleTreeNode64(['slug' => 'r1']);
        $this->assertTrue($node->makeRoot()->save());

        $node = new MultipleTreeNode64([
            'slug' => 'r2',
            'tree' => 223372036854775807,
        ]);
        $this->assertTrue($node->makeRoot()->save());

        $dataSet = $this->getConnection()->createDataSet(['multiple_tree_64']);
        $expectedDataSet = new ArrayDataSet(require(__DIR__ . '/data/test-make-root-insert-64.php'));
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testMakeRootUpdate()
    {
        $node = MultipleTreeNode64::findOne(9);
        $this->assertTrue($node->makeRoot()->save());

        $node = MultipleTreeNode64::findOne(27);
        $node->setAttribute('tree', 223372036854775807);
        $this->assertTrue($node->makeRoot()->save());

        $dataSet = $this->getConnection()->createDataSet(['multiple_tree_64']);
        $expectedDataSet = new ArrayDataSet(require(__DIR__ . '/data/test-make-root-update-64.php'));
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testPrependToInsertInNoEmpty()
    {
        $node = new MultipleTreeNode64(['slug' => 'new1']);
        $this->assertTrue($node->prependTo(MultipleTreeNode64::findOne(1))->save());

        $node = new MultipleTreeNode64(['slug' => 'new2']);
        $this->assertTrue($node->prependTo(MultipleTreeNode64::findOne(6))->save());

        $dataSet = $this->getConnection()->createDataSet(['multiple_tree_64']);
        $expectedDataSet = new ArrayDataSet(require(__DIR__ . '/data/test-prepend-to-insert-in-no-empty-64.php'));
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testPrependToInsertInEmpty()
    {
        $node = new MultipleTreeNode64(['slug' => 'new1']);
        $this->assertTrue($node->prependTo(MultipleTreeNode64::findOne(16))->save());

        $node = new MultipleTreeNode64(['slug' => 'new2']);
        $this->assertTrue($node->prependTo(MultipleTreeNode64::findOne(18))->save());

        $dataSet = $this->getConnection()->createDataSet(['multiple_tree_64']);
        $expectedDataSet = new ArrayDataSet(require(__DIR__ . '/data/test-prepend-to-insert-in-empty-64.php'));
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testPrependToInsertInEmptyAmount77NoPrepend()
    {
        $config = [
            'amountOptimize' => 77,
            'noPrepend'      => true,
        ];

        $node = new MultipleTreeNode64(['slug' => 'new1']);
        Yii::configure($node->getBehavior('tree'), $config);
        $this->assertTrue($node->prependTo(MultipleTreeNode64::findOne(16))->save());

        $node = new MultipleTreeNode64(['slug' => 'new2']);
        Yii::configure($node->getBehavior('tree'), $config);
        $this->assertTrue($node->prependTo(MultipleTreeNode64::findOne(18))->save());

        $dataSet = $this->getConnection()->createDataSet(['multiple_tree_64']);
        $expectedDataSet = new ArrayDataSet(require(__DIR__ . '/data/test-prepend-to-insert-in-empty-amount-77-no-prepend-64.php'));
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testPrependToInsertInEmptyAmount4NoAppend()
    {
        $config = [
            'amountOptimize' => 4,
            'noAppend'       => true,
        ];

        $node = new MultipleTreeNode64(['slug' => 'new1']);
        Yii::configure($node->getBehavior('tree'), $config);
        $this->assertTrue($node->prependTo(MultipleTreeNode64::findOne(16))->save());

        $node = new MultipleTreeNode64(['slug' => 'new2']);
        Yii::configure($node->getBehavior('tree'), $config);
        $this->assertTrue($node->prependTo(MultipleTreeNode64::findOne(18))->save());

        $dataSet = $this->getConnection()->createDataSet(['multiple_tree_64']);
        $expectedDataSet = new ArrayDataSet(require(__DIR__ . '/data/test-prepend-to-insert-in-empty-amount-4-no-append-64.php'));
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testPrependToInsertInEmptyAmount7NoInsert()
    {
        $config = [
            'amountOptimize' => 7,
            'noInsert'       => true,
        ];

        $node = new MultipleTreeNode64(['slug' => 'new1']);
        Yii::configure($node->getBehavior('tree'), $config);
        $this->assertTrue($node->prependTo(MultipleTreeNode64::findOne(16))->save());

        $node = new MultipleTreeNode64(['slug' => 'new2']);
        Yii::configure($node->getBehavior('tree'), $config);
        $this->assertTrue($node->prependTo(MultipleTreeNode64::findOne(18))->save());

        $dataSet = $this->getConnection()->createDataSet(['multiple_tree_64']);
        $expectedDataSet = new ArrayDataSet(require(__DIR__ . '/data/test-prepend-to-insert-in-empty-amount-7-no-insert-64.php'));
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testPrependToInsertInEmptyAmount13Reserve3()
    {
        $config = [
            'amountOptimize' => 13,
            'reserveFactor'  => 3,
        ];

        $node = new MultipleTreeNode64(['slug' => 'new']);
        Yii::configure($node->getBehavior('tree'), $config);
        $this->assertTrue($node->prependTo(MultipleTreeNode64::findOne(6))->save());

        $dataSet = $this->getConnection()->createDataSet(['multiple_tree_64']);
        $expectedDataSet = new ArrayDataSet(require(__DIR__ . '/data/test-prepend-to-insert-in-empty-amount-13-reserve-3-64.php'));
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testPrependToUpdate()
    {
        $node = MultipleTreeNode64::findOne(4);
        $this->assertTrue($node->prependTo(MultipleTreeNode64::findOne(1))->save());

        $dataSet = $this->getConnection()->createDataSet(['multiple_tree_64']);
        $expectedDataSet = new ArrayDataSet(require(__DIR__ . '/data/test-prepend-to-update-64.php'));
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testPrependToUpdateAnotherTree()
    {
        $node = MultipleTreeNode64::findOne(30);
        $this->assertTrue($node->prependTo(MultipleTreeNode64::findOne(4))->save());

        $dataSet = $this->getConnection()->createDataSet(['multiple_tree_64']);
        $expectedDataSet = new ArrayDataSet(require(__DIR__ . '/data/test-prepend-to-update-another-tree-64.php'));
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testAppendToInsertInNoEmpty()
    {
        $node = new MultipleTreeNode64(['slug' => 'new1']);
        $this->assertTrue($node->appendTo(MultipleTreeNode64::findOne(2))->save());

        $node = new MultipleTreeNode64(['slug' => 'new2']);
        $this->assertTrue($node->appendTo(MultipleTreeNode64::findOne(6))->save());

        $dataSet = $this->getConnection()->createDataSet(['multiple_tree_64']);
        $expectedDataSet = new ArrayDataSet(require(__DIR__ . '/data/test-append-to-insert-in-no-empty-64.php'));
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testAppendToInsertInEmpty()
    {
        $node = new MultipleTreeNode64(['slug' => 'new1']);
        $this->assertTrue($node->appendTo(MultipleTreeNode64::findOne(16))->save());

        $node = new MultipleTreeNode64(['slug' => 'new2']);
        $this->assertTrue($node->appendTo(MultipleTreeNode64::findOne(18))->save());

        $dataSet = $this->getConnection()->createDataSet(['multiple_tree_64']);
        $expectedDataSet = new ArrayDataSet(require(__DIR__ . '/data/test-append-to-insert-in-empty-64.php'));
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testAppendToInsertInEmptyAmount77NoPrepend()
    {
        $config = [
            'amountOptimize' => 77,
            'noPrepend'      => true,
        ];

        $node = new MultipleTreeNode64(['slug' => 'new1']);
        Yii::configure($node->getBehavior('tree'), $config);
        $this->assertTrue($node->appendTo(MultipleTreeNode64::findOne(16))->save());

        $node = new MultipleTreeNode64(['slug' => 'new2']);
        Yii::configure($node->getBehavior('tree'), $config);
        $this->assertTrue($node->appendTo(MultipleTreeNode64::findOne(18))->save());

        $dataSet = $this->getConnection()->createDataSet(['multiple_tree_64']);
        $expectedDataSet = new ArrayDataSet(require(__DIR__ . '/data/test-append-to-insert-in-empty-amount-77-no-prepend-64.php'));
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testAppendToInsertInEmptyAmount4NoAppend()
    {
        $config = [
            'amountOptimize' => 4,
            'noAppend'       => true,
        ];

        $node = new MultipleTreeNode64(['slug' => 'new1']);
        Yii::configure($node->getBehavior('tree'), $config);
        $this->assertTrue($node->appendTo(MultipleTreeNode64::findOne(16))->save());

        $node = new MultipleTreeNode64(['slug' => 'new2']);
        Yii::configure($node->getBehavior('tree'), $config);
        $this->assertTrue($node->appendTo(MultipleTreeNode64::findOne(18))->save());

        $dataSet = $this->getConnection()->createDataSet(['multiple_tree_64']);
        $expectedDataSet = new ArrayDataSet(require(__DIR__ . '/data/test-append-to-insert-in-empty-amount-4-no-append-64.php'));
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testAppendToInsertInEmptyAmount7NoInsert()
    {
        $config = [
            'amountOptimize' => 7,
            'noInsert'       => true,
        ];

        $node = new MultipleTreeNode64(['slug' => 'new1']);
        Yii::configure($node->getBehavior('tree'), $config);
        $this->assertTrue($node->appendTo(MultipleTreeNode64::findOne(16))->save());

        $node = new MultipleTreeNode64(['slug' => 'new2']);
        Yii::configure($node->getBehavior('tree'), $config);
        $this->assertTrue($node->appendTo(MultipleTreeNode64::findOne(18))->save());

        $dataSet = $this->getConnection()->createDataSet(['multiple_tree_64']);
        $expectedDataSet = new ArrayDataSet(require(__DIR__ . '/data/test-append-to-insert-in-empty-amount-7-no-insert-64.php'));
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testAppendToInsertInEmptyAmount13Reserve3()
    {
        $config = [
            'amountOptimize' => 13,
            'reserveFactor'  => 3,
        ];

        $node = new MultipleTreeNode64(['slug' => 'new']);
        Yii::configure($node->getBehavior('tree'), $config);
        $this->assertTrue($node->appendTo(MultipleTreeNode64::findOne(6))->save());

        $dataSet = $this->getConnection()->createDataSet(['multiple_tree_64']);
        $expectedDataSet = new ArrayDataSet(require(__DIR__ . '/data/test-append-to-insert-in-empty-amount-13-reserve-3-64.php'));
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testAppendToUpdate()
    {
        $node = MultipleTreeNode64::findOne(2);
        $this->assertTrue($node->appendTo(MultipleTreeNode64::findOne(1))->save());

        $dataSet = $this->getConnection()->createDataSet(['multiple_tree_64']);
        $expectedDataSet = new ArrayDataSet(require(__DIR__ . '/data/test-append-to-update-64.php'));
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testAppendToUpdateAnotherTree()
    {
        $node = MultipleTreeNode64::findOne(30);
        $this->assertTrue($node->appendTo(MultipleTreeNode64::findOne(4))->save());

        $dataSet = $this->getConnection()->createDataSet(['multiple_tree_64']);
        $expectedDataSet = new ArrayDataSet(require(__DIR__ . '/data/test-append-to-update-another-tree-64.php'));
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testInsertBefore()
    {
        $node = new MultipleTreeNode64(['slug' => 'new1']);
        $this->assertTrue($node->insertBefore(MultipleTreeNode64::findOne(16))->save());

        $node = new MultipleTreeNode64(['slug' => 'new2']);
        $this->assertTrue($node->insertBefore(MultipleTreeNode64::findOne(33))->save());

        $dataSet = $this->getConnection()->createDataSet(['multiple_tree_64']);
        $expectedDataSet = new ArrayDataSet(require(__DIR__ . '/data/test-insert-before-insert-64.php'));
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testInsertBeforeUpdate()
    {
        $node = MultipleTreeNode64::findOne(38);
        $this->assertTrue($node->insertBefore(MultipleTreeNode64::findOne(37))->save());

        $dataSet = $this->getConnection()->createDataSet(['multiple_tree_64']);
        $expectedDataSet = new ArrayDataSet(require(__DIR__ . '/data/test-insert-before-update-64.php'));
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testInsertAfterInsert()
    {
        $node = new MultipleTreeNode64(['slug' => 'new1']);
        $this->assertTrue($node->insertAfter(MultipleTreeNode64::findOne(14))->save());

        $node = new MultipleTreeNode64(['slug' => 'new2']);
        $this->assertTrue($node->insertAfter(MultipleTreeNode64::findOne(37))->save());

        $dataSet = $this->getConnection()->createDataSet(['multiple_tree_64']);
        $expectedDataSet = new ArrayDataSet(require(__DIR__ . '/data/test-insert-after-insert-64.php'));
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testInsertAfterUpdate()
    {
        $node = MultipleTreeNode64::findOne(36);
        $this->assertTrue($node->insertAfter(MultipleTreeNode64::findOne(37))->save());

        $dataSet = $this->getConnection()->createDataSet(['multiple_tree_64']);
        $expectedDataSet = new ArrayDataSet(require(__DIR__ . '/data/test-insert-after-update-64.php'));
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testDelete()
    {
        $this->assertEquals(1, MultipleTreeNode64::findOne(30)->delete());

        $dataSet = $this->getConnection()->createDataSet(['multiple_tree_64']);
        $expectedDataSet = new ArrayDataSet(require(__DIR__ . '/data/test-delete-64.php'));
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testDeleteWithChildren()
    {
        $this->assertEquals(1, MultipleTreeNode64::findOne(28)->deleteWithChildren());
        $this->assertEquals(25, MultipleTreeNode64::findOne(1)->deleteWithChildren());

        $dataSet = $this->getConnection()->createDataSet(['multiple_tree_64']);
        $expectedDataSet = new ArrayDataSet(require(__DIR__ . '/data/test-delete-with-children-64.php'));
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }

    public function testOptimize()
    {
        MultipleTreeNode64::findOne(6)->optimize();

        $dataSet = $this->getConnection()->createDataSet(['multiple_tree_64']);
        $expectedDataSet = new ArrayDataSet(require(__DIR__ . '/data/test-optimize-64.php'));
        $this->assertDataSetsEqual($expectedDataSet, $dataSet);
    }
}