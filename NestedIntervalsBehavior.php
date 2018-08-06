<?php
/**
 * @link https://github.com/paulzi/yii2-nested-intervals
 * @copyright Copyright (c) 2015 PaulZi <pavel.zimakoff@gmail.com>
 * @license MIT (https://github.com/paulzi/yii2-nested-intervals/blob/master/LICENSE)
 */

namespace paulzi\nestedintervals;

use Yii;
use yii\base\Behavior;
use yii\base\Exception;
use yii\base\NotSupportedException;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\db\Query;

/**
 * Nested Intervals Behavior for Yii2
 * @author PaulZi <pavel.zimakoff@gmail.com>
 *
 * @property ActiveRecord $owner
 */
class NestedIntervalsBehavior extends Behavior
{

    const OPERATION_MAKE_ROOT       = 1;
    const OPERATION_PREPEND_TO      = 2;
    const OPERATION_APPEND_TO       = 3;
    const OPERATION_INSERT_BEFORE   = 4;
    const OPERATION_INSERT_AFTER    = 5;
    const OPERATION_DELETE_ALL      = 6;

    /**
     * @var string|null
     */
    public $treeAttribute;

    /**
     * @var string
     */
    public $leftAttribute = 'lft';

    /**
     * @var string
     */
    public $rightAttribute = 'rgt';

    /**
     * @var string
     */
    public $depthAttribute = 'depth';

    /**
     * @var int[]
     */
    public $range = [0, 2147483647];

    /**
     * @var int|int[] Average amount of children in each level
     */
    public $amountOptimize = 10;

    /**
     * @var float
     */
    public $reserveFactor = 1;

    /**
     * @var bool
     */
    public $noPrepend = false;

    /**
     * @var bool
     */
    public $noAppend = false;

    /**
     * @var bool
     */
    public $noInsert = false;

    /**
     * @var string|null
     */
    protected $operation;

    /**
     * @var ActiveRecord|self|null
     */
    protected $node;

    /**
     * @var string
     */
    protected $treeChange;


    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_INSERT   => 'beforeInsert',
            ActiveRecord::EVENT_AFTER_INSERT    => 'afterInsert',
            ActiveRecord::EVENT_BEFORE_UPDATE   => 'beforeUpdate',
            ActiveRecord::EVENT_AFTER_UPDATE    => 'afterUpdate',
            ActiveRecord::EVENT_BEFORE_DELETE   => 'beforeDelete',
            ActiveRecord::EVENT_AFTER_DELETE    => 'afterDelete',
        ];
    }

    /**
     * @param int|null $depth
     * @return \yii\db\ActiveQuery
     */
    public function getParents($depth = null)
    {
        $tableName = $this->owner->tableName();
        $condition = [
            'and',
            ['<', "{$tableName}.[[{$this->leftAttribute}]]",  $this->owner->getAttribute($this->leftAttribute)],
            ['>', "{$tableName}.[[{$this->rightAttribute}]]", $this->owner->getAttribute($this->rightAttribute)],
        ];
        if ($depth !== null) {
            $condition[] = ['>=', "{$tableName}.[[{$this->depthAttribute}]]", $this->owner->getAttribute($this->depthAttribute) - $depth];
        }

        $query = $this->owner->find()
            ->andWhere($condition)
            ->andWhere($this->treeCondition())
            ->addOrderBy(["{$tableName}.[[{$this->leftAttribute}]]" => SORT_ASC]);
        $query->multiple = true;

        return $query;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getParent()
    {
        $tableName = $this->owner->tableName();
        $query = $this->getParents(1)
            ->orderBy(["{$tableName}.[[{$this->leftAttribute}]]" => SORT_DESC])
            ->limit(1);
        $query->multiple = false;
        return $query;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRoot()
    {
        $tableName = $this->owner->tableName();
        $query = $this->owner->find()
            ->andWhere(["{$tableName}.[[{$this->leftAttribute}]]" => $this->range[0]])
            ->andWhere($this->treeCondition())
            ->limit(1);
        $query->multiple = false;
        return $query;
    }

    /**
     * @param int|null $depth
     * @param bool $andSelf
     * @param bool $backOrder
     * @return \yii\db\ActiveQuery
     */
    public function getDescendants($depth = null, $andSelf = false, $backOrder = false)
    {
        $tableName = $this->owner->tableName();
        $attribute = $backOrder ? $this->rightAttribute : $this->leftAttribute;
        $condition = [
            'and',
            [$andSelf ? '>=' : '>', "{$tableName}.[[{$attribute}]]",  $this->owner->getAttribute($this->leftAttribute)],
            [$andSelf ? '<=' : '<', "{$tableName}.[[{$attribute}]]",  $this->owner->getAttribute($this->rightAttribute)],
        ];

        if ($depth !== null) {
            $condition[] = ['<=', "{$tableName}.[[{$this->depthAttribute}]]", $this->owner->getAttribute($this->depthAttribute) + $depth];
        }

        $query = $this->owner->find()
            ->andWhere($condition)
            ->andWhere($this->treeCondition())
            ->addOrderBy(["{$tableName}.[[{$attribute}]]" => $backOrder ? SORT_DESC : SORT_ASC]);
        $query->multiple = true;

        return $query;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getChildren()
    {
        return $this->getDescendants(1);
    }

    /**
     * @param int|null $depth
     * @return \yii\db\ActiveQuery
     */
    public function getLeaves($depth = null)
    {
        $tableName = $this->owner->tableName();
        $condition = [
            'and',
            ['>', "leaves.[[{$this->leftAttribute}]]",  new Expression("{$tableName}.[[{$this->leftAttribute}]]")],
            ['<', "leaves.[[{$this->leftAttribute}]]",  new Expression("{$tableName}.[[{$this->rightAttribute}]]")],
        ];

        if ($this->treeAttribute !== null) {
            $condition[] = ["leaves.[[{$this->treeAttribute}]]" => new Expression("{$tableName}.[[{$this->treeAttribute}]]")];
        }

        $query = $this->getDescendants($depth)
            ->leftJoin("{$tableName} leaves", $condition)
            ->andWhere(["leaves.[[{$this->leftAttribute}]]" => null]);
        $query->multiple = true;
        return $query;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPrev()
    {
        $tableName = $this->owner->tableName();
        $query = $this->owner->find()
            ->andWhere([
                'and',
                ['<', "{$tableName}.[[{$this->rightAttribute}]]", $this->owner->getAttribute($this->leftAttribute)],
                ['>', "{$tableName}.[[{$this->rightAttribute}]]", $this->getParent()->select([$this->leftAttribute])],
            ])
            ->andWhere($this->treeCondition())
            ->orderBy(["{$tableName}.[[{$this->rightAttribute}]]" => SORT_DESC])
            ->limit(1);
        $query->multiple = false;
        return $query;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getNext()
    {
        $tableName = $this->owner->tableName();
        $query = $this->owner->find()
            ->andWhere([
                'and',
                ['>', "{$tableName}.[[{$this->leftAttribute}]]", $this->owner->getAttribute($this->rightAttribute)],
                ['<', "{$tableName}.[[{$this->leftAttribute}]]", $this->getParent()->select([$this->rightAttribute])],
            ])
            ->andWhere($this->treeCondition())
            ->orderBy(["{$tableName}.[[{$this->leftAttribute}]]" => SORT_ASC])
            ->limit(1);
        $query->multiple = false;
        return $query;
    }

    /**
     * Populate children relations for self and all descendants
     * @param int $depth = null
     * @param string|array $with = null
     * @return static
     */
    public function populateTree($depth = null, $with = null)
    {
        /** @var ActiveRecord[]|static[] $nodes */
        $query = $this->getDescendants($depth);
        if ($with) {
            $query->with($with);
        }
        $nodes = $query->all();

        $key = $this->owner->getAttribute($this->leftAttribute);
        $relates = [];
        $parents = [$key];
        $prev = $this->owner->getAttribute($this->depthAttribute);
        foreach($nodes as $node)
        {
            $level = $node->getAttribute($this->depthAttribute);
            if ($level <= $prev) {
                $parents = array_slice($parents, 0, $level - $prev - 1);
            }

            $key = end($parents);
            if (!isset($relates[$key])) {
                $relates[$key] = [];
            }
            $relates[$key][] = $node;

            $parents[] = $node->getAttribute($this->leftAttribute);
            $prev = $level;
        }

        $ownerDepth = $this->owner->getAttribute($this->depthAttribute);
        $nodes[] = $this->owner;
        foreach ($nodes as $node) {
            $key = $node->getAttribute($this->leftAttribute);
            if (isset($relates[$key])) {
                $node->populateRelation('children', $relates[$key]);
            } elseif ($depth === null || $ownerDepth + $depth > $node->getAttribute($this->depthAttribute)) {
                $node->populateRelation('children', []);
            }
        }

        return $this->owner;
    }

    /**
     * @return bool
     */
    public function isRoot()
    {
        return $this->owner->getAttribute($this->leftAttribute) == $this->range[0];
    }

    /**
     * @param ActiveRecord $node
     * @return bool
     */
    public function isChildOf($node)
    {
        $result = $this->owner->getAttribute($this->leftAttribute) > $node->getAttribute($this->leftAttribute)
            && $this->owner->getAttribute($this->rightAttribute) < $node->getAttribute($this->rightAttribute);

        if ($result && $this->treeAttribute !== null) {
            $result = $this->owner->getAttribute($this->treeAttribute) === $node->getAttribute($this->treeAttribute);
        }

        return $result;
    }

    /**
     * @return bool
     */
    public function isLeaf()
    {
        return count($this->owner->children) === 0;
    }

    /**
     * @return ActiveRecord
     */
    public function makeRoot()
    {
        $this->operation = self::OPERATION_MAKE_ROOT;
        return $this->owner;
    }

    /**
     * @param ActiveRecord $node
     * @return ActiveRecord
     */
    public function prependTo($node)
    {
        $this->operation = self::OPERATION_PREPEND_TO;
        $this->node = $node;
        return $this->owner;
    }

    /**
     * @param ActiveRecord $node
     * @return ActiveRecord
     */
    public function appendTo($node)
    {
        $this->operation = self::OPERATION_APPEND_TO;
        $this->node = $node;
        return $this->owner;
    }

    /**
     * @param ActiveRecord $node
     * @return ActiveRecord
     */
    public function insertBefore($node)
    {
        $this->operation = self::OPERATION_INSERT_BEFORE;
        $this->node = $node;
        return $this->owner;
    }

    /**
     * @param ActiveRecord $node
     * @return ActiveRecord
     */
    public function insertAfter($node)
    {
        $this->operation = self::OPERATION_INSERT_AFTER;
        $this->node = $node;
        return $this->owner;
    }

    /**
     * Need for paulzi/auto-tree
     */
    public function preDeleteWithChildren()
    {
        $this->operation = self::OPERATION_DELETE_ALL;
    }

    /**
     * @return bool|int
     * @throws \Exception
     * @throws \yii\db\Exception
     */
    public function deleteWithChildren()
    {
        $this->operation = self::OPERATION_DELETE_ALL;
        if (!$this->owner->isTransactional(ActiveRecord::OP_DELETE)) {
            $transaction = $this->owner->getDb()->beginTransaction();
            try {
                $result = $this->deleteWithChildrenInternal();
                if ($result === false) {
                    $transaction->rollBack();
                } else {
                    $transaction->commit();
                }
                return $result;
            } catch (\Exception $e) {
                $transaction->rollBack();
                throw $e;
            }
        } else {
            $result = $this->deleteWithChildrenInternal();
        }
        return $result;
    }

    /**
     * @return ActiveRecord
     * @throws \Exception
     * @throws \yii\db\Exception
     */
    public function optimize()
    {
        if (!$this->owner->isTransactional(ActiveRecord::OP_UPDATE)) {
            $transaction = $this->owner->getDb()->beginTransaction();
            try {
                $this->optimizeInternal();
                $transaction->commit();
            } catch (\Exception $e) {
                $transaction->rollBack();
                throw $e;
            }
        } else {
            $this->optimizeInternal();
        }
        return $this->owner;
    }

    /**
     * @throws Exception
     * @throws NotSupportedException
     */
    public function beforeInsert()
    {
        if ($this->node !== null && !$this->node->getIsNewRecord()) {
            $this->node->refresh();
        }
        switch ($this->operation) {
            case self::OPERATION_MAKE_ROOT:
                $condition = array_merge([$this->leftAttribute => $this->range[0]], $this->treeCondition());
                if ($this->owner->find()->andWhere($condition)->one() !== null) {
                    throw new Exception('Can not create more than one root.');
                }
                $this->owner->setAttribute($this->leftAttribute,  $this->range[0]);
                $this->owner->setAttribute($this->rightAttribute, $this->range[1]);
                $this->owner->setAttribute($this->depthAttribute, 0);
                break;

            case self::OPERATION_PREPEND_TO:
                $this->findPrependRange($left, $right);
                $this->insertNode($left, $right, 1, false);
                break;

            case self::OPERATION_APPEND_TO:
                $this->findAppendRange($left, $right);
                $this->insertNode($left, $right, 1, true);
                break;

            case self::OPERATION_INSERT_BEFORE:
                $parent = $this->findInsertBeforeRange($left, $right);
                $this->insertNode($left, $right, 0, false, $parent);
                break;

            case self::OPERATION_INSERT_AFTER:
                $parent = $this->findInsertAfterRange($left, $right);
                $this->insertNode($left, $right, 0, true, $parent);
                break;

            default:
                throw new NotSupportedException('Method "'. $this->owner->className() . '::insert" is not supported for inserting new nodes.');
        }
    }

    /**
     * @throws Exception
     */
    public function afterInsert()
    {
        if ($this->operation === self::OPERATION_MAKE_ROOT && $this->treeAttribute !== null && $this->owner->getAttribute($this->treeAttribute) === null) {
            $id = $this->owner->getPrimaryKey();
            $this->owner->setAttribute($this->treeAttribute, $id);
            $this->owner->updateAll([$this->treeAttribute => $id], [$this->getPrimaryKey() => $id]);
        }
        $this->operation = null;
        $this->node      = null;
    }

    /**
     * @throws Exception
     */
    public function beforeUpdate()
    {
        if ($this->node !== null && !$this->node->getIsNewRecord()) {
            $this->node->refresh();
        }

        switch ($this->operation) {
            case self::OPERATION_MAKE_ROOT:
                if ($this->treeAttribute === null) {
                    throw new Exception('Can not move a node as the root when "treeAttribute" is not set.');
                }
                if ($this->owner->getOldAttribute($this->treeAttribute) !== $this->owner->getAttribute($this->treeAttribute)) {
                    $this->treeChange = $this->owner->getAttribute($this->treeAttribute);
                    $this->owner->setAttribute($this->treeAttribute, $this->owner->getOldAttribute($this->treeAttribute));
                }
                break;

            case self::OPERATION_INSERT_BEFORE:
            case self::OPERATION_INSERT_AFTER:
                if ($this->node->isRoot()) {
                    throw new Exception('Can not move a node before/after root.');
                }

            case self::OPERATION_PREPEND_TO:
            case self::OPERATION_APPEND_TO:
                if ($this->node->getIsNewRecord()) {
                    throw new Exception('Can not move a node when the target node is new record.');
                }

                if ($this->owner->equals($this->node)) {
                    throw new Exception('Can not move a node when the target node is same.');
                }

                if ($this->node->isChildOf($this->owner)) {
                    throw new Exception('Can not move a node when the target node is child.');
                }
        }
    }

    /**
     *
     */
    public function afterUpdate()
    {
        switch ($this->operation) {
            case self::OPERATION_MAKE_ROOT:
                $this->moveNodeAsRoot();
                break;

            case self::OPERATION_PREPEND_TO:
                $this->findPrependRange($left, $right);
                if ($right !== $this->owner->getAttribute($this->leftAttribute)) {
                    $this->moveNode($left, $right, 1);
                }
                break;

            case self::OPERATION_APPEND_TO:
                $this->findAppendRange($left, $right);
                if ($left !== $this->owner->getAttribute($this->rightAttribute)) {
                    $this->moveNode($left, $right, 1);
                }
                break;

            case self::OPERATION_INSERT_BEFORE:
                $this->findInsertBeforeRange($left, $right);
                if ($left !== $this->owner->getAttribute($this->rightAttribute)) {
                    $this->moveNode($left, $right);
                }
                break;

            case self::OPERATION_INSERT_AFTER:
                $this->findInsertAfterRange($left, $right);
                if ($right !== $this->owner->getAttribute($this->leftAttribute)) {
                    $this->moveNode($left, $right);
                }
                break;
        }
        $this->operation = null;
        $this->node      = null;
    }

    /**
     * @throws Exception
     */
    public function beforeDelete()
    {
        if ($this->owner->getIsNewRecord()) {
            throw new Exception('Can not delete a node when it is new record.');
        }
        if ($this->isRoot() && $this->operation !== self::OPERATION_DELETE_ALL) {
            throw new Exception('Method "'. $this->owner->className() . '::delete" is not supported for deleting root nodes.');
        }
        $this->owner->refresh();
    }

    /**
     *
     */
    public function afterDelete()
    {
        if ($this->operation !== self::OPERATION_DELETE_ALL) {
            $this->owner->updateAll([$this->depthAttribute => new Expression("[[{$this->depthAttribute}]] - 1")], $this->getDescendants()->where);
        }
        $this->operation = null;
        $this->node      = null;
    }

    /**
     * @return mixed
     * @throws Exception
     */
    protected function getPrimaryKey()
    {
        $primaryKey = $this->owner->primaryKey();
        if (!isset($primaryKey[0])) {
            throw new Exception('"' . $this->owner->className() . '" must have a primary key.');
        }
        return $primaryKey[0];
    }

    /**
     * @return self
     */
    public function getNodeBehavior()
    {
        foreach ($this->node->behaviors as $behavior) {
            if ($behavior instanceof NestedIntervalsBehavior) {
                return $behavior;
            }
        }
        return null;
    }

    /**
     * @return int
     */
    protected function deleteWithChildrenInternal()
    {
        if (!$this->owner->beforeDelete()) {
            return false;
        }
        $result = $this->owner->deleteAll($this->getDescendants(null, true)->where);
        $this->owner->setOldAttributes(null);
        $this->owner->afterDelete();
        return $result;
    }

    /**
     * @param int $left
     * @param int $right
     * @param int $depth
     * @param bool $forward
     * @param array|null $parent
     * @throws Exception
     */
    protected function insertNode($left, $right, $depth = 0, $forward = true, $parent = null)
    {
        if ($this->node->getIsNewRecord()) {
            throw new Exception('Can not create a node when the target node is new record.');
        }

        if ($depth === 0 && $this->getNodeBehavior()->isRoot()) {
            throw new Exception('Can not insert a node before/after root.');
        }
        $this->owner->setAttribute($this->depthAttribute, $this->node->getAttribute($this->depthAttribute) + $depth);
        if ($this->treeAttribute !== null) {
            $this->owner->setAttribute($this->treeAttribute, $this->node->getAttribute($this->treeAttribute));
        }
        if ($right - $left < 3) {
            for ($i = $right - $left; $i < 3; $i++) {
                $unallocated = $this->findUnallocatedAll($left, $right);
                if ($unallocated < $left) {
                    $this->shift($unallocated, $left, -1);
                    $left--;
                } else {
                    $this->shift($right, $unallocated, 1);
                    $right++;
                }
            }
            $this->owner->setAttribute($this->leftAttribute,  $left  + 1);
            $this->owner->setAttribute($this->rightAttribute, $right - 1);
        } else {
            $left++;
            $right--;

            $isPadding = false;
            if ($depth === 1 || $parent !== null) {
                // prepending/appending
                if (is_array($this->amountOptimize)) {
                    if (isset($this->amountOptimize[$depth - 1])) {
                        $amountOptimize = $this->amountOptimize[$depth - 1];
                    } else {
                        $amountOptimize = $this->amountOptimize[count($this->amountOptimize) - 1];
                    }
                } else {
                    $amountOptimize = $this->amountOptimize;
                }
                $pLeft     = $parent !== null ? (int)$parent[$this->leftAttribute]  : $this->node->getAttribute($this->leftAttribute);
                $pRight    = $parent !== null ? (int)$parent[$this->rightAttribute] : $this->node->getAttribute($this->rightAttribute);
                $isCenter  = !$this->noAppend && !$this->noPrepend;
                $isFirst   = $left === $pLeft + 1 && $right === $pRight - 1;
                $isPadding = !$this->noInsert || ($isFirst && ($forward ? !$this->noPrepend : !$this->noAppend));
                $step      = $amountOptimize + $this->reserveFactor * ($this->noInsert ? ($isCenter ? 2 : 1) : $amountOptimize + 1);
                $step      = ($pRight - $pLeft - 1) / $step;
                $stepGap   = $step * $this->reserveFactor;
                $padding   = $isPadding ? $stepGap : 0;

                if ($forward) {
                    $pLeft  = $left + (int)floor($padding);
                    $pRight = $left + (int)floor($padding + $step) - 1;
                } else {
                    $pLeft  = $right - (int)floor($padding + $step) + 1;
                    $pRight = $right - (int)floor($padding);
                }
                if ($isFirst && $isCenter) {
                    $initPosition = (int)floor(($amountOptimize - 1) / 2) * (int)floor($step + ($this->noInsert ? 0 : $stepGap)) + ($this->noInsert ? 1 : 0);
                    $pLeft  += $forward ? $initPosition : -$initPosition;
                    $pRight += $forward ? $initPosition : -$initPosition;
                }
                if ($pLeft < $pRight && $pRight <= $right && $left <= $pLeft && ($forward || $left < $pLeft) && (!$forward || $pRight < $right)) {
                    $this->owner->setAttribute($this->leftAttribute,  $pLeft);
                    $this->owner->setAttribute($this->rightAttribute, $pRight);
                    return;
                }
            }

            $isPadding = $isPadding || !$this->noInsert;
            $step = (int)floor(($right - $left) / ($isPadding ? 3 : 2));
            $this->owner->setAttribute($this->leftAttribute,  $left  + ($forward  && !$isPadding ? 0 : $step));
            $this->owner->setAttribute($this->rightAttribute, $right - (!$forward && !$isPadding ? 0 : $step));
        }
    }

    /**
     * @param int $left
     * @param int $right
     * @param int $depth
     * @throws Exception
     */
    protected function moveNode($left, $right, $depth = 0)
    {
        $targetDepth = $this->node->getAttribute($this->depthAttribute) + $depth;
        $depthDelta = $this->owner->getAttribute($this->depthAttribute) - $targetDepth;
        if ($this->treeAttribute === null || $this->owner->getAttribute($this->treeAttribute) === $this->node->getAttribute($this->treeAttribute)) {
            // same root
            $sLeft = $this->owner->getAttribute($this->leftAttribute);
            $sRight = $this->owner->getAttribute($this->rightAttribute);
            $this->owner->updateAll(
                [$this->depthAttribute => new Expression("-[[{$this->depthAttribute}]]" . sprintf('%+d', $depthDelta))],
                $this->getDescendants(null, true)->where
            );
            $sDelta = $sRight - $sLeft + 1;
            if ($sLeft >= $right) {
                $this->shift($right, $sLeft - 1, $sDelta);
                $delta = $right - $sLeft;
            } else {
                $this->shift($sRight + 1, $left, -$sDelta);
                $delta = $left - $sRight;
            }
            $this->owner->updateAll(
                [
                    $this->leftAttribute  => new Expression("[[{$this->leftAttribute}]]"  . sprintf('%+d', $delta)),
                    $this->rightAttribute => new Expression("[[{$this->rightAttribute}]]" . sprintf('%+d', $delta)),
                    $this->depthAttribute => new Expression("-[[{$this->depthAttribute}]]"),
                ],
                [
                    'and',
                    $this->getDescendants(null, true)->where,
                    ['<', $this->depthAttribute, 0],
                ]
            );
        } else {
            // move from other root (slow!)
            /** @var ActiveRecord|self $root */
            $root      = $this->getNodeBehavior()->getRoot()->one();
            $countTo   = (int)$root->getDescendants()->orderBy(null)->count();
            $countFrom = (int)$this->getDescendants(null, true)->orderBy(null)->count();
            $size  = (int)floor(($this->range[1] - $this->range[0]) / (($countFrom + $countTo) * 2 + 1));

            $leftIdx  = $this->optimizeAttribute($root->getDescendants(null, false, false), $this->leftAttribute,  $this->range[0], $size,  0, $left,  $countFrom, $targetDepth);
            $rightIdx = $this->optimizeAttribute($root->getDescendants(null, false, true),  $this->rightAttribute, $this->range[1], $size, 0,  $right, $countFrom, $targetDepth);
            if ($leftIdx !== null && $rightIdx !== null) {
                $this->optimizeAttribute($this->getDescendants(null, true, false), $this->leftAttribute,  $this->range[0], $size, $leftIdx);
                $this->optimizeAttribute($this->getDescendants(null, true, true), $this->rightAttribute, $this->range[1],  $size, $rightIdx, null, 0,  null, [
                    $this->treeAttribute  => $this->node->getAttribute($this->treeAttribute),
                    $this->depthAttribute => new Expression("[[{$this->depthAttribute}]]" . sprintf('%+d', -$depthDelta)),
                ]);
            } else {
                throw new Exception('Error move a node from other tree');
            }
        }
    }

    /**
     *
     */
    protected function moveNodeAsRoot()
    {
        $left   = $this->owner->getAttribute($this->leftAttribute);
        $right  = $this->owner->getAttribute($this->rightAttribute);
        $depth  = $this->owner->getAttribute($this->depthAttribute);
        $tree   = $this->treeChange ? $this->treeChange : $this->owner->getPrimaryKey();

        $factor = ($this->range[1] - $this->range[0]) / ($right - $left);
        $formula = sprintf('ROUND(([[%%s]] * 1.0 %+d) * %d %+d)', -$left, (int)$factor, $this->range[0]);
        $this->owner->updateAll(
            [
                $this->leftAttribute  => new Expression(sprintf($formula, $this->leftAttribute)),
                $this->rightAttribute => new Expression(sprintf($formula, $this->rightAttribute)),
                $this->depthAttribute => new Expression("[[{$this->depthAttribute}]] - {$depth}"),
                $this->treeAttribute  => $tree,
            ],
            $this->getDescendants()->where
        );
        $this->owner->updateAll(
            [
                $this->leftAttribute  => $this->range[0],
                $this->rightAttribute => $this->range[1],
                $this->depthAttribute => 0,
                $this->treeAttribute  => $tree,
            ],
            [$this->getPrimaryKey() => $this->owner->getPrimaryKey()]
        );
        $this->owner->refresh();
    }

    /**
     * @throws Exception
     */
    protected function optimizeInternal()
    {
        $left  = $this->owner->getAttribute($this->leftAttribute);
        $right = $this->owner->getAttribute($this->rightAttribute);

        $count = $this->getDescendants()->orderBy(null)->count() * 2 + 1;
        $size  = (int)floor(($right - $left) / $count);

        $this->optimizeAttribute($this->getDescendants(null, false, false), $this->leftAttribute,  $left,  $size);
        $this->optimizeAttribute($this->getDescendants(null, false, true),  $this->rightAttribute, $right, $size);
    }

    /**
     * @param \yii\db\ActiveQuery $query
     * @param string $attribute
     * @param int $from
     * @param int $size
     * @param int $offset
     * @param int|null $freeFrom
     * @param int $freeSize
     * @param int|null $targetDepth
     * @param array $additional
     * @return int|null
     * @throws Exception
     * @throws \yii\db\Exception
     */
    protected function optimizeAttribute($query, $attribute, $from, $size, $offset = 0, $freeFrom = null, $freeSize = 0, $targetDepth = null, $additional = [])
    {
        $primaryKey = $this->getPrimaryKey();
        $result     = null;
        $isForward  = $attribute === $this->leftAttribute;

        // @todo: pgsql and mssql optimization
        if (in_array($this->owner->getDb()->driverName, ['mysql', 'mysqli'])) {
            // mysql optimization
            $tableName = $this->owner->tableName();
            $additionalString = null;
            $additionalParams = [];
            foreach ($additional as $name => $value) {
                $additionalString .= ", [[{$name}]] = ";
                if ($value instanceof Expression) {
                    $additionalString .= $value->expression;
                    foreach ($value->params as $n => $v) {
                        $additionalParams[$n] = $v;
                    }
                } else {
                    $paramName = ':nestedIntervals' . count($additionalParams);
                    $additionalString .= $paramName;
                    $additionalParams[$paramName] = $value;
                }
            }

            $command = $query
                ->select([$primaryKey, $attribute, $this->depthAttribute])
                ->orderBy([$attribute => $isForward ? SORT_ASC : SORT_DESC])
                ->createCommand();
            $this->owner->getDb()->createCommand("
                UPDATE
                    {$tableName} u,
                    (SELECT
                        [[{$primaryKey}]],
                        IF (@i := @i + 1, 0, 0)
                        + IF ([[{$attribute}]] " . ($isForward ? '>' : '<') . " @freeFrom,
                            IF (
                                (@result := @i)
                                + IF (@depth - :targetDepth > 0, @result := @result + @depth - :targetDepth, 0)
                                + (@i := @i + :freeSize * 2)
                                + (@freeFrom := NULL), 0, 0),
                            0)
                        + IF (@depth - [[{$this->depthAttribute}]] >= 0,
                            IF (@i := @i + @depth - [[{$this->depthAttribute}]] + 1, 0, 0),
                            0)
                        + (:from " . ($isForward ? '+' : '-') . " (CAST(@i AS UNSIGNED INTEGER) + :offset) * :size)
                        + IF ([[{$attribute}]] = @freeFrom,
                            IF ((@result := @i) + (@i := @i + :freeSize * 2) + (@freeFrom := NULL), 0, 0),
                            0)
                        + IF (@depth := [[{$this->depthAttribute}]], 0, 0)
                        as 'new'
                    FROM
                        (SELECT @i := 0, @depth := -1, @freeFrom := :freeFrom, @result := NULL) v,
                        (" . $command->sql . ") t
                    ) tmp
                SET u.[[{$attribute}]]=tmp.[[new]] {$additionalString}
                WHERE tmp.[[{$primaryKey}]]=u.[[{$primaryKey}]]")
                ->bindValues($additionalParams)
                ->bindValues($command->params)
                ->bindValues([
                    ':from'        => $from,
                    ':size'        => $size,
                    ':offset'      => $offset,
                    ':freeFrom'    => $freeFrom,
                    ':freeSize'    => $freeSize,
                    ':targetDepth' => $targetDepth,
                ])
                ->execute();
            if ($freeFrom !== null) {
                $result = $this->owner->getDb()->createCommand("SELECT IFNULL(@result, @i + 1 + IF (@depth - :targetDepth > 0, @depth - :targetDepth, 0))")
                    ->bindValue(':targetDepth', $targetDepth)
                    ->queryScalar();
                $result = $result === null ? null : (int)$result;
            }
            return $result;
        } else {
            // generic algorithm (very slow!)
            $query
                ->select([$primaryKey, $attribute, $this->depthAttribute])
                ->asArray()
                ->orderBy([$attribute => $isForward ? SORT_ASC : SORT_DESC]);

            $prevDepth = -1;
            $i = 0;
            foreach ($query->each() as $data) {
                $i++;
                if ($freeFrom !== null && $freeFrom !== (int)$data[$attribute] && ($freeFrom > (int)$data[$attribute] xor $isForward)) {
                    $result = $i;
                    $depthDiff = $prevDepth - $targetDepth;
                    if ($depthDiff > 0) {
                        $result += $depthDiff;
                    }
                    $i += $freeSize * 2;
                    $freeFrom = null;
                }
                $depthDiff = $prevDepth - $data[$this->depthAttribute];
                if ($depthDiff >= 0) {
                    $i += $depthDiff + 1;
                }
                $this->owner->updateAll(
                    array_merge($additional, [$attribute  => $isForward ? $from + ($i + $offset) * $size : $from - ($i + $offset) * $size]),
                    [$primaryKey => $data[$primaryKey]]
                );
                if ($freeFrom !== null && $freeFrom === (int)$data[$attribute]) {
                    $result = $i;
                    $i += $freeSize * 2;
                    $freeFrom = null;
                }
                $prevDepth = $data[$this->depthAttribute];
            }
            if ($freeFrom !== null) {
                $result = $i + 1;
                $depthDiff = $prevDepth - $targetDepth;
                if ($depthDiff > 0) {
                    $result += $depthDiff;
                }
            }
            return $result;
        }
    }

    /**
     * @param int $left
     * @param int $right
     */
    protected function findPrependRange(&$left, &$right)
    {
        $left  = $this->node->getAttribute($this->leftAttribute);
        $right = $this->node->getAttribute($this->rightAttribute);
        $child = $this->getNodeBehavior()->getChildren()
            ->select($this->leftAttribute)
            ->orderBy([$this->leftAttribute => SORT_ASC])
            ->limit(1)
            ->scalar();
        if ($child !== false) {
            $right = (int)$child;
        }
    }

    /**
     * @param int $left
     * @param int $right
     */
    protected function findAppendRange(&$left, &$right)
    {
        $left  = $this->node->getAttribute($this->leftAttribute);
        $right = $this->node->getAttribute($this->rightAttribute);
        $child = $this->getNodeBehavior()->getChildren()
            ->select($this->rightAttribute)
            ->orderBy([$this->rightAttribute => SORT_DESC])
            ->limit(1)
            ->scalar();
        if ($child !== false) {
            $left = (int)$child;
        }
    }

    /**
     * @param int $left
     * @param int $right
     * @return array|null
     */
    protected function findInsertBeforeRange(&$left, &$right)
    {
        $result = null;
        $right = $this->node->getAttribute($this->leftAttribute);
        $left  = $this->getNodeBehavior()->getPrev()
            ->select($this->rightAttribute)
            ->scalar();
        if ($left === false) {
            $result = $this->getNodeBehavior()->getParent()
                ->select([$this->leftAttribute, $this->rightAttribute])
                ->createCommand()
                ->queryOne();
            $left = $result[$this->leftAttribute];
        }
        $left = (int)$left;
        return $result;
    }

    /**
     * @param int $left
     * @param int $right
     * @return array|null
     */
    protected function findInsertAfterRange(&$left, &$right)
    {
        $result = null;
        $left  = $this->node->getAttribute($this->rightAttribute);
        $right = $this->getNodeBehavior()->getNext()
            ->select($this->leftAttribute)
            ->scalar();
        if ($right === false) {
            $result = $this->getNodeBehavior()->getParent()
                ->select([$this->leftAttribute, $this->rightAttribute])
                ->createCommand()
                ->queryOne();
            $right = $result[$this->rightAttribute];
        }
        $right = (int)$right;
        return $result;
    }

    /**
     * @param int $value
     * @param string $attribute
     * @param bool $forward
     * @return bool|int
     */
    protected function findUnallocated($value, $attribute, $forward = true)
    {
        $tableName = $this->owner->tableName();
        $leftCondition  = "l.[[{$this->leftAttribute}]]  = {$tableName}.[[{$attribute}]] " . ($forward ? '+' : '-') . " 1";
        $rightCondition = "r.[[{$this->rightAttribute}]] = {$tableName}.[[{$attribute}]] " . ($forward ? '+' : '-') . " 1";
        if ($this->treeAttribute !== null) {
            $leftCondition  = ['and', $leftCondition,  "l.[[{$this->treeAttribute}]] = {$tableName}.[[{$this->treeAttribute}]]"];
            $rightCondition = ['and', $rightCondition, "r.[[{$this->treeAttribute}]] = {$tableName}.[[{$this->treeAttribute}]]"];
        }
        $result = (new Query())
            ->select("{$tableName}.[[{$attribute}]]")
            ->from("{$tableName}")
            ->leftJoin("{$tableName} l", $leftCondition)
            ->leftJoin("{$tableName} r", $rightCondition)
            ->where([
                'and',
                [$forward ? '>=' : '<=', "{$tableName}.[[{$attribute}]]", $value],
                [$forward ? '<'  : '>',  "{$tableName}.[[{$attribute}]]", $this->range[$forward ? 1 : 0]],
                [
                    "l.[[{$this->leftAttribute}]]"  => null,
                    "r.[[{$this->rightAttribute}]]" => null,
                ],
            ])
            ->andWhere($this->treeCondition())
            ->orderBy(["{$tableName}.[[{$attribute}]]" => $forward ? SORT_ASC : SORT_DESC])
            ->limit(1)
            ->scalar($this->owner->getDb());
        if ($result !== false) {
            $result += $forward ? 1 : -1;
        }
        return $result;
    }

    /**
     * @param int $left
     * @param int $right
     * @return bool|int
     */
    protected function findUnallocatedAll($left, $right)
    {
        $unallocated = false;
        if ($right < (($this->range[1] - $this->range[0])>>1)) {
            $unallocated = $unallocated ?: $this->findUnallocated($right, $this->rightAttribute, true);
            $unallocated = $unallocated ?: $this->findUnallocated($right, $this->leftAttribute,  true);
            $unallocated = $unallocated ?: $this->findUnallocated($left,  $this->leftAttribute,  false);
            $unallocated = $unallocated ?: $this->findUnallocated($left,  $this->rightAttribute, false);
        } else {
            $unallocated = $unallocated ?: $this->findUnallocated($left,  $this->leftAttribute,  false);
            $unallocated = $unallocated ?: $this->findUnallocated($left,  $this->rightAttribute, false);
            $unallocated = $unallocated ?: $this->findUnallocated($right, $this->rightAttribute, true);
            $unallocated = $unallocated ?: $this->findUnallocated($right, $this->leftAttribute,  true);
        }
        return $unallocated;
    }

    /**
     * @param int $from
     * @param int $to
     * @param int $delta
     */
    protected function shift($from, $to, $delta)
    {
        if ($to >= $from && $delta !== 0) {
            foreach ([$this->leftAttribute, $this->rightAttribute] as $i => $attribute) {
                $this->owner->updateAll(
                    [$attribute => new Expression("[[{$attribute}]]" . sprintf('%+d', $delta))],
                    [
                        'and',
                        ['between', $attribute, $from, $to],
                        $this->treeCondition()
                    ]
                );
            }
        }
    }

    /**
     * @return array
     */
    protected function treeCondition()
    {
        $tableName = $this->owner->tableName();
        if ($this->treeAttribute === null) {
            return [];
        } else {
            return ["{$tableName}.[[{$this->treeAttribute}]]" => $this->owner->getAttribute($this->treeAttribute)];
        }
    }
}
