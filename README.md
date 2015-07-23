# Yii2 Nested Intervals Behavior

Implementation of nested intervals algorithm for storing the trees in DB tables.  

## Install

Install via Composer:

```bash
composer require paulzi/yii2-nested-intervals
```

or add

```bash
"paulzi/yii2-nested-intervals" : "^1.0"
```

to the `require` section of your `composer.json` file.

## Migrations

Sample migrations are in the folder `sample-migrations`:

- `m150722_150000_single_tree.php` - for single tree tables;
- `m150722_150100_multiple_tree.php` - for multiple tree tables.

## Configuring

```php
use paulzi\nestedintervals\NestedIntervalsBehavior;

class Sample extends \yii\db\ActiveRecord
{
    public function behaviors() {
        return [
            [
                'class' => NestedIntervalsBehavior::className(),
                // 'treeAttribute' => 'tree',
            ],
        ];
    }

    public function transactions()
    {
        return [
            self::SCENARIO_DEFAULT => self::OP_ALL,
        ];
    }
}
```

Optional you can setup Query for finding roots:

```php
class Sample extends \yii\db\ActiveRecord
{
    public static function find()
    {
        return new SampleQuery(get_called_class());
    }
}
```

Query class:

```php
use paulzi\nestedintervals\NestedIntervalsQueryTrait;

class SampleQuery extends \yii\db\ActiveQuery
{
    use NestedIntervalsQueryTrait;
}
```

## Options

- `$treeAttribute = null` - setup tree attribute for multiple tree in table schema.
- `$leftAttribute = 'lft'` - left attribute in table schema.
- `$rightAttribute = 'rgt'` - right attribute in table schema.
- `$depthAttribute = 'depth'` - depth attribute in table schema (note: it must be signed int).
- `$range = [0, 2147483647]` - interval size. Default values is max value for work in 32 bit php and standard signed int columns. If you have BIGINT left and right columns and 64 bit version of php, you can use `[0, 9223372036854775807]`.
- `$amountOptimize = 10` - optimization of the insert - the average number of children per level. The value can be an integer or an array indicating the value for each level. If the level is deeper than specified in the property, the value is taken from the last level in the array.
- `$reserveFactor = 1` - factor determining the size of the gaps between the nodes. Default is 1, which corresponds to the fact that the intervals are equal to the size of the elements themselves. If you have many use of  `insertBefore()` and `insertAfter()` methods, you can try to increase this factor for better efficiency. 
- `$noPrepend = false` - if true, then when you insert into an empty node will use the initial position of the gap.
- `$noAppend = false` - if true, then when you insert into an empty node will be used by the final position of the gap.
- `$noInsert = false` - if true, then between neighboring nodes will not be gaps.

## Usage

### Selection

**Getting the root nodes**

If you connect `NestedIntervalsQueryTrait`, you can get all the root nodes:

```php
$roots = Sample::find()->roots()->all();
```

**Getting ancestors of a node**

To get ancestors of a node:

```php
$node11 = Sample::findOne(['name' => 'node 1.1']);
$parents = $node11->parents; // via relation
$parents = $node11->getParents()->all(); // via query
$parents = $node11->getParents(2)->all(); // get 2 levels of ancestors
```

To get parent of a node:

```php
$node11 = Sample::findOne(['name' => 'node 1.1']);
$parent = $node11->parent; // via relation
$parent = $node11->getParent()->one(); // via query
```

To get root of a node:

```php
$node11 = Sample::findOne(['name' => 'node 1.1']);
$root = $node11->root; // via relation
$root = $node11->getRoot()->one(); // via query
```

**Getting descendants of a node**

To get all the descendants of a node:

```php
$node11 = Sample::findOne(['name' => 'node 1.1']);
$descendants = $node11->descendants; // via relation
$descendants = $node11->getDescendants()->all(); // via query
$descendants = $node11->getDescendants(2, true)->all(); // get 2 levels of descendants and self node
$descendants = $node11->getDescendants(3, false, true)->all(); // get 3 levels of descendants in back order
```

To get the children of a node:

```php
$node11 = Sample::findOne(['name' => 'node 1.1']);
$children = $node11->children; // via relation
$children = $node11->getChildren()->all(); // via query
```

**Getting the leaves nodes**

To get all the leaves of a node:

```php
$node11 = Sample::findOne(['name' => 'node 1.1']);
$leaves = $node11->leaves; // via relation
$leaves = $node11->getLeaves(2)->all(); // get 2 levels of leaves via query
```

**Getting the neighbors nodes**

To get the next node:

```php
$node11 = Sample::findOne(['name' => 'node 1.1']);
$next = $node11->next; // via relation
$next = $node11->getNext()->one(); // via query
```

To get the previous node:

```php
$node11 = Sample::findOne(['name' => 'node 1.1']);
$prev = $node11->prev; // via relation
$prev = $node11->getPrev()->one(); // via query
```

### Some checks

```php
$node1 = Sample::findOne(['name' => 'node 1']);
$node11 = Sample::findOne(['name' => 'node 1.1']);
$node11->isRoot() - return true, if node is root
$node11->isLeaf() - return true, if node is leaf
$node11->isChildOf($node1) - return true, if node11 is child of $node1
```


### Modifications

To make a root node:

```php
$node11 = new Sample();
$node11->name = 'node 1.1';
$node11->makeRoot()->save();
```

*Note: if you allow multiple trees and attribute `tree` is not set, it automatically takes the primary key value.*

To prepend a node as the first child of another node:

```php
$node1 = Sample::findOne(['name' => 'node 1']);
$node11 = new Sample();
$node11->name = 'node 1.1';
$node11->prependTo($node1)->save(); // inserting new node
```

To append a node as the last child of another node:

```php
$node11 = Sample::findOne(['name' => 'node 1.1']);
$node12 = Sample::findOne(['name' => 'node 1.2']);
$node12->appendTo($node11)->save(); // move existing node
```

To insert a node before another node:

```php
$node13 = Sample::findOne(['name' => 'node 1.3']);
$node12 = new Sample();
$node12->name = 'node 1.2';
$node12->insertBefore($node13)->save(); // inserting new node
```

To insert a node after another node:

```php
$node13 = Sample::findOne(['name' => 'node 1.3']);
$node14 = Sample::findOne(['name' => 'node 1.4']);
$node14->insertAfter($node13)->save(); // move existing node
```

To delete a node with descendants:

```php
$node11 = Sample::findOne(['name' => 'node 1.1']);
$node11->delete(); // delete node, children come up to the parent
$node11->deleteWithChildren(); // delete node and all descendants 
```

### Optimisation

For uniform distribution of nodes over the interval (slow!):
```php
$node11 = Sample::findOne(['name' => 'node 1.1']);
$node11->optimize();
```