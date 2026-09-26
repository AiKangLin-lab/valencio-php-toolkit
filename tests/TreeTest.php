<?php

declare(strict_types=1);

namespace Valencio\PhpToolkit\Tests;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Valencio\PhpToolkit\Tree;

/**
 * Tree 单元测试
 *
 * 正式迁移自 Tree 开发过程中的各轮验收测试，
 * 覆盖全部已确认行为与边界。
 */
final class TreeTest extends TestCase
{
    /**
     * 常规树 fixture：
     * 1 -> {2, 3}, 2 -> {4, 5}, 4 -> 6
     */
    private function makeTree (): Tree
    {
        return (new Tree())->init([
            ['id' => 1, 'parent_id' => null, 'name' => '系统'],
            ['id' => 2, 'parent_id' => 1, 'name' => '用户'],
            ['id' => 3, 'parent_id' => 1, 'name' => '角色'],
            ['id' => 4, 'parent_id' => 2, 'name' => '管理员'],
            ['id' => 5, 'parent_id' => 2, 'name' => '访客'],
            ['id' => 6, 'parent_id' => 4, 'name' => '超管'],
        ]);
    }

    // ========== init ==========

    public function testInitReturnsSelfForChaining (): void
    {
        $tree = new Tree();

        $this->assertSame($tree, $tree->init([]));
    }

    public function testInitReInitializesAndClearsOldIndexes (): void
    {
        $tree = $this->makeTree();
        $tree->init([['id' => 100, 'parent_id' => null]]);

        $this->assertSame(['id' => 100, 'parent_id' => null], $tree->getNodeById(100));
        $this->assertNull($tree->getNodeById(1));
        $this->assertSame([], $tree->getChildrenByParentId(1));
    }

    public function testInitWithMissingPrimaryKeyThrows (): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Tree())->init([['parent_id' => null]]);
    }

    public function testInitWithMissingParentKeyThrows (): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Tree())->init([['id' => 1]]);
    }

    public function testInitWithDuplicateIdThrows (): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Tree())->init([
            ['id' => 1, 'parent_id' => null],
            ['id' => 1, 'parent_id' => null],
        ]);
    }

    public function testInitWithInvalidIdTypeThrows (): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Tree())->init([['id' => 1.5, 'parent_id' => null]]);
    }

    public function testInitWithInvalidParentIdTypeThrows (): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Tree())->init([['id' => 1, 'parent_id' => []]]);
    }

    public function testInitWithBoolIdThrows (): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Tree())->init([['id' => true, 'parent_id' => null]]);
    }

    public function testInitWithNullParentIdIsAccepted (): void
    {
        $tree = (new Tree())->init([['id' => 1, 'parent_id' => null]]);

        $this->assertNotNull($tree->getNodeById(1));
    }

    public function testInitWithCustomKeys (): void
    {
        $tree = (new Tree())->init(
            [
                ['node_id' => 'a', 'pid' => null, 'name' => 'root'],
                ['node_id' => 'b', 'pid' => 'a'],
            ],
            parentKey: 'pid',
            primaryKey: 'node_id',
            childrenKey: 'sub'
        );

        $this->assertNotNull($tree->getNodeById('a'));
        $this->assertSame([$tree->getNodeById('b')], $tree->getChildrenByParentId('a'));
    }

    // ========== getNodeById ==========

    public function testGetNodeByIdReturnsNodeData (): void
    {
        $tree = $this->makeTree();

        $this->assertSame(
            ['id' => 2, 'parent_id' => 1, 'name' => '用户'],
            $tree->getNodeById(2)
        );
    }

    public function testGetNodeByIdReturnsNullWhenMissing (): void
    {
        $this->assertNull($this->makeTree()->getNodeById(999));
    }

    // ========== getChildrenByParentId / getChildrenIdsByParentId ==========

    public function testGetChildrenByParentIdKeepsOriginalOrder (): void
    {
        $children = $this->makeTree()->getChildrenByParentId(1);

        $this->assertSame([2, 3], array_column($children, 'id'));
    }

    public function testGetChildrenByParentIdWithNullReturnsRoots (): void
    {
        $children = $this->makeTree()->getChildrenByParentId(null);

        $this->assertSame([1], array_column($children, 'id'));
    }

    public function testGetChildrenByParentIdReturnsEmptyArrayForLeaf (): void
    {
        $this->assertSame([], $this->makeTree()->getChildrenByParentId(6));
    }

    public function testGetChildrenByParentIdAllowsDanglingParentLookup (): void
    {
        $tree = (new Tree())->init([
            ['id' => 10, 'parent_id' => 999],
        ]);

        $this->assertSame([10], array_column($tree->getChildrenByParentId(999), 'id'));
    }

    public function testGetChildrenIdsByParentIdKeepsOriginalOrder (): void
    {
        $this->assertSame([2, 3], $this->makeTree()->getChildrenIdsByParentId(1));
    }

    public function testGetChildrenIdsByParentIdReturnsEmptyArrayWhenMissing (): void
    {
        $this->assertSame([], $this->makeTree()->getChildrenIdsByParentId(999));
    }

    public function testGetChildrenIdsByParentIdWithNullReturnsRootIds (): void
    {
        $this->assertSame([1], $this->makeTree()->getChildrenIdsByParentId(null));
    }

    public function testGetChildrenIdsByParentIdResultIsSafeFromExternalMutation (): void
    {
        $tree = $this->makeTree();

        $ids = $tree->getChildrenIdsByParentId(1);
        $ids[] = 999;

        $this->assertSame([2, 3], $tree->getChildrenIdsByParentId(1));
    }

    // ========== getDescendantsById / getDescendantIdsById ==========

    public function testGetDescendantIdsUsesDfsPreorder (): void
    {
        $this->assertSame([2, 4, 6, 5, 3], $this->makeTree()->getDescendantIdsById(1));
    }

    public function testGetDescendantIdsForMidLevelNode (): void
    {
        $this->assertSame([4, 6, 5], $this->makeTree()->getDescendantIdsById(2));
    }

    public function testGetDescendantIdsWithIncludeSelf (): void
    {
        $this->assertSame([1, 2, 4, 6, 5, 3], $this->makeTree()->getDescendantIdsById(1, true));
    }

    public function testGetDescendantIdsReturnsEmptyArrayWhenMissing (): void
    {
        $this->assertSame([], $this->makeTree()->getDescendantIdsById(999));
    }

    public function testGetDescendantIdsWithIncludeSelfStillEmptyWhenMissing (): void
    {
        $this->assertSame([], $this->makeTree()->getDescendantIdsById(999, true));
    }

    public function testGetDescendantsReturnsNodeDataInSameOrder (): void
    {
        $tree = $this->makeTree();

        $this->assertSame(
            $tree->getDescendantIdsById(1),
            array_column($tree->getDescendantsById(1), 'id')
        );
    }

    public function testGetDescendantsWithIncludeSelfStartsWithSelf (): void
    {
        $descendants = $this->makeTree()->getDescendantsById(1, true);

        $this->assertSame(1, $descendants[0]['id']);
    }

    public function testGetDescendantsReturnsEmptyArrayWhenMissing (): void
    {
        $this->assertSame([], $this->makeTree()->getDescendantsById(999));
    }

    public function testDescendantTraversalThrowsOnCycle (): void
    {
        $tree = (new Tree())->init([
            ['id' => 1, 'parent_id' => 2],
            ['id' => 2, 'parent_id' => 1],
        ]);

        $this->expectException(LogicException::class);

        $tree->getDescendantIdsById(1);
    }

    public function testDescendantTraversalThrowsOnSelfReference (): void
    {
        $tree = (new Tree())->init([
            ['id' => 5, 'parent_id' => 5],
        ]);

        $this->expectException(LogicException::class);

        $tree->getDescendantIdsById(5);
    }

    public function testDescendantTraversalIgnoresUnreachableCycle (): void
    {
        $tree = (new Tree())->init([
            ['id' => 5, 'parent_id' => null],
            ['id' => 1, 'parent_id' => 2],
            ['id' => 2, 'parent_id' => 1],
        ]);

        $this->assertSame([], $tree->getDescendantIdsById(5));
    }

    public function testDescendantOfDanglingParentReturnsEmptyArray (): void
    {
        $tree = (new Tree())->init([
            ['id' => 2, 'parent_id' => 99],
        ]);

        $this->assertSame([], $tree->getDescendantIdsById(99));
    }

    // ========== getParentIdById / getParentById ==========

    public function testGetParentIdByIdReturnsDeclaredValue (): void
    {
        $this->assertSame(2, $this->makeTree()->getParentIdById(4));
    }

    public function testGetParentIdByIdReturnsNullForRoot (): void
    {
        $this->assertNull($this->makeTree()->getParentIdById(1));
    }

    public function testGetParentIdByIdReturnsNullWhenMissing (): void
    {
        $this->assertNull($this->makeTree()->getParentIdById(999));
    }

    public function testGetParentIdByIdReturnsDanglingParentAsDeclared (): void
    {
        $tree = (new Tree())->init([['id' => 10, 'parent_id' => 999]]);

        $this->assertSame(999, $tree->getParentIdById(10));
    }

    public function testGetParentByIdReturnsParentNodeData (): void
    {
        $tree = $this->makeTree();

        $this->assertSame(
            ['id' => 2, 'parent_id' => 1, 'name' => '用户'],
            $tree->getParentById(4)
        );
    }

    public function testGetParentByIdReturnsNullForRoot (): void
    {
        $this->assertNull($this->makeTree()->getParentById(1));
    }

    public function testGetParentByIdReturnsNullWhenMissing (): void
    {
        $this->assertNull($this->makeTree()->getParentById(999));
    }

    public function testGetParentByIdReturnsNullForDanglingParent (): void
    {
        $tree = (new Tree())->init([['id' => 10, 'parent_id' => 999]]);

        $this->assertNull($tree->getParentById(10));
    }

    // ========== getParentsById / getParentIdsById ==========

    public function testGetParentIdsOrderIsTopmostToNearest (): void
    {
        // 6 <- 4 <- 2 <- 1，祖先顺序应为 [1, 2, 4]。
        $this->assertSame([1, 2, 4], $this->makeTree()->getParentIdsById(6));
    }

    public function testGetParentIdsForDirectChildOfRoot (): void
    {
        $this->assertSame([1], $this->makeTree()->getParentIdsById(2));
    }

    public function testGetParentIdsReturnsEmptyArrayForRoot (): void
    {
        $this->assertSame([], $this->makeTree()->getParentIdsById(1));
    }

    public function testGetParentIdsReturnsEmptyArrayWhenMissing (): void
    {
        $this->assertSame([], $this->makeTree()->getParentIdsById(999));
    }

    public function testGetParentIdsStopsAtDanglingParentWithoutIncludingIt (): void
    {
        $tree = (new Tree())->init([
            ['id' => 10, 'parent_id' => 999],
            ['id' => 11, 'parent_id' => 10],
        ]);

        $this->assertSame([10], $tree->getParentIdsById(11));
        $this->assertSame([], $tree->getParentIdsById(10));
    }

    public function testGetParentsReturnsNodeDataInSameOrder (): void
    {
        $tree = $this->makeTree();

        $this->assertSame(
            $tree->getParentIdsById(6),
            array_column($tree->getParentsById(6), 'id')
        );
    }

    public function testGetParentsDoesNotIncludeSelf (): void
    {
        $this->assertNotContains(6, $this->makeTree()->getParentIdsById(6));
    }

    public function testAncestorTraversalThrowsOnCycle (): void
    {
        $tree = (new Tree())->init([
            ['id' => 1, 'parent_id' => 2],
            ['id' => 2, 'parent_id' => 1],
        ]);

        $this->expectException(LogicException::class);

        $tree->getParentIdsById(1);
    }

    public function testAncestorTraversalThrowsOnSelfReference (): void
    {
        $tree = (new Tree())->init([
            ['id' => 5, 'parent_id' => 5],
        ]);

        $this->expectException(LogicException::class);

        $tree->getParentIdsById(5);
    }

    // ========== hasChildren ==========

    public function testHasChildrenReturnsTrueForInternalNode (): void
    {
        $this->assertTrue($this->makeTree()->hasChildren(2));
    }

    public function testHasChildrenReturnsFalseForLeaf (): void
    {
        $this->assertFalse($this->makeTree()->hasChildren(6));
    }

    public function testHasChildrenReturnsFalseWhenMissing (): void
    {
        $this->assertFalse($this->makeTree()->hasChildren(999));
    }

    public function testHasChildrenReturnsFalseForDanglingParentEvenWithOrphans (): void
    {
        $tree = (new Tree())->init([
            ['id' => 10, 'parent_id' => 888],
            ['id' => 11, 'parent_id' => 10],
        ]);

        $this->assertFalse($tree->hasChildren(888));
        $this->assertTrue($tree->hasChildren(10));
    }

    // ========== buildTree ==========

    public function testBuildTreeDefaultStartsAtZero (): void
    {
        $tree = (new Tree())->init([
            ['id' => 1, 'parent_id' => 0, 'name' => 'A'],
            ['id' => 2, 'parent_id' => 1, 'name' => 'B'],
            ['id' => 3, 'parent_id' => 1, 'name' => 'C'],
            ['id' => 4, 'parent_id' => 2, 'name' => 'D'],
        ]);

        $expected = [
            [
                'id' => 1,
                'parent_id' => 0,
                'name' => 'A',
                'children' => [
                    [
                        'id' => 2,
                        'parent_id' => 1,
                        'name' => 'B',
                        'children' => [
                            ['id' => 4, 'parent_id' => 2, 'name' => 'D'],
                        ],
                    ],
                    [
                        'id' => 3,
                        'parent_id' => 1,
                        'name' => 'C',
                    ],
                ],
            ],
        ];

        $this->assertSame($expected, $tree->buildTree());
    }

    public function testBuildTreeFromGivenParentExcludesSelf (): void
    {
        $result = $this->makeTree()->buildTree(1);

        $this->assertSame([2, 3], array_column($result, 'id'));
    }

    public function testBuildTreeWithNullParentReturnsRoots (): void
    {
        $tree = (new Tree())->init([
            ['id' => 1, 'parent_id' => null, 'name' => '系统'],
            ['id' => 2, 'parent_id' => 1, 'name' => '用户'],
            ['id' => 3, 'parent_id' => null, 'name' => '配置'],
        ]);

        $result = $tree->buildTree(null);

        $this->assertSame([1, 3], array_column($result, 'id'));
        $this->assertSame([2], array_column($result[0]['children'], 'id'));
    }

    public function testBuildTreeKeepsSiblingOrder (): void
    {
        $result = $this->makeTree()->buildTree(1);

        $this->assertSame([2, 3], array_column($result, 'id'));
        $this->assertSame([4, 5], array_column($result[0]['children'], 'id'));
    }

    public function testBuildTreeLeafNodeHasNoChildrenKey (): void
    {
        // buildTree(2) 返回 4（有子节点）和 5（叶子）。
        $result = $this->makeTree()->buildTree(2);

        $this->assertArrayHasKey('children', $result[0]);
        $this->assertArrayNotHasKey('children', $result[1]);
    }

    public function testBuildTreeUsesConfiguredChildrenKey (): void
    {
        $tree = (new Tree())->init(
            [
                ['id' => 1, 'parent_id' => 0],
                ['id' => 2, 'parent_id' => 1],
            ],
            childrenKey: 'sub'
        );

        $result = $tree->buildTree();

        $this->assertArrayHasKey('sub', $result[0]);
        $this->assertSame(2, $result[0]['sub'][0]['id']);
    }

    public function testBuildTreeBuildsOrphanBranchForDanglingParent (): void
    {
        $tree = (new Tree())->init([
            ['id' => 10, 'parent_id' => 888],
            ['id' => 11, 'parent_id' => 10],
        ]);

        $result = $tree->buildTree(888);

        $this->assertSame([10], array_column($result, 'id'));
        $this->assertSame([11], array_column($result[0]['children'], 'id'));
    }

    public function testBuildTreeReturnsEmptyArrayWhenNothingMatches (): void
    {
        $this->assertSame([], $this->makeTree()->buildTree(999));
    }

    public function testBuildTreeRegeneratesChildrenFieldAndIgnoresOriginal (): void
    {
        $tree = (new Tree())->init([
            ['id' => 1, 'parent_id' => 0, 'children' => ['旧垃圾数据']],
            ['id' => 2, 'parent_id' => 1],
            ['id' => 3, 'parent_id' => 1, 'children' => '字符串也不行'],
        ]);

        $result = $tree->buildTree();

        $this->assertSame([2, 3], array_column($result[0]['children'], 'id'));
        $this->assertArrayNotHasKey('children', $result[0]['children'][1]);
    }

    public function testBuildTreeDoesNotModifyOriginalNodeData (): void
    {
        $tree = (new Tree())->init([
            ['id' => 1, 'parent_id' => 0, 'name' => 'A'],
            ['id' => 2, 'parent_id' => 1, 'name' => 'B'],
            ['id' => 3, 'parent_id' => 1, 'children' => '旧值'],
        ]);

        $before = [
            $tree->getNodeById(1),
            $tree->getNodeById(2),
            $tree->getNodeById(3),
        ];

        $tree->buildTree();

        $this->assertSame($before, [
            $tree->getNodeById(1),
            $tree->getNodeById(2),
            $tree->getNodeById(3),
        ]);
        // nodeMap 中的原始数据保留原有 children 字段不被破坏。
        $this->assertSame('旧值', $tree->getNodeById(3)['children']);
    }

    public function testBuildTreeIsStableAcrossRepeatedCalls (): void
    {
        $tree = $this->makeTree();

        $first = $tree->buildTree(1);
        $second = $tree->buildTree(1);
        $fromRoot = $tree->buildTree(null);

        $this->assertSame($first, $second);
        $this->assertSame($first, $fromRoot[0]['children']);
    }

    public function testBuildTreeThrowsWhenCyclePassesThroughStart (): void
    {
        $tree = (new Tree())->init([
            ['id' => 1, 'parent_id' => 2],
            ['id' => 2, 'parent_id' => 1],
        ]);

        $this->expectException(LogicException::class);

        $tree->buildTree(1);
    }

    public function testBuildTreeThrowsOnSelfReference (): void
    {
        $tree = (new Tree())->init([
            ['id' => 7, 'parent_id' => 7],
        ]);

        $this->expectException(LogicException::class);

        $tree->buildTree(7);
    }

    public function testBuildTreeIgnoresUnreachableCycle (): void
    {
        $tree = (new Tree())->init([
            ['id' => 5, 'parent_id' => 0],
            ['id' => 1, 'parent_id' => 2],
            ['id' => 2, 'parent_id' => 1],
        ]);

        $this->assertSame([5], array_column($tree->buildTree(), 'id'));
    }

    // ========== int / string ID 隔离 ==========

    public function testIntAndStringIdsAreDistinctNodes (): void
    {
        $tree = (new Tree())->init([
            ['id' => 1, 'parent_id' => null, 'name' => 'int 1'],
            ['id' => '1', 'parent_id' => null, 'name' => 'string 1'],
        ]);

        $this->assertSame('int 1', $tree->getNodeById(1)['name']);
        $this->assertSame('string 1', $tree->getNodeById('1')['name']);
    }

    public function testIntAndStringDuplicateIdsAreNotAllowedAsDistinctNodes (): void
    {
        // int 1 与 string '1' 因类型隔离是两个不同节点，不视为重复 ID。
        $tree = (new Tree())->init([
            ['id' => 1, 'parent_id' => null],
            ['id' => '1', 'parent_id' => null],
        ]);

        $this->assertNotNull($tree->getNodeById(1));
        $this->assertNotNull($tree->getNodeById('1'));
    }

    public function testChildrenLookupRespectsIdTypeIsolation (): void
    {
        $tree = (new Tree())->init([
            ['id' => 1, 'parent_id' => null],
            ['id' => '1', 'parent_id' => 1],
            ['id' => 2, 'parent_id' => '1'],
        ]);

        $this->assertSame(['1'], $tree->getChildrenIdsByParentId(1));
        $this->assertSame([2], $tree->getChildrenIdsByParentId('1'));
    }

    public function testDescendantLookupRespectsIdTypeIsolation (): void
    {
        $tree = (new Tree())->init([
            ['id' => 1, 'parent_id' => null],
            ['id' => '1', 'parent_id' => 1],
            ['id' => 'a', 'parent_id' => '1'],
        ]);

        // int 1 的后代：直接子节点 string '1'，以及 '1' 的子节点 'a'。
        $this->assertSame(['1', 'a'], $tree->getDescendantIdsById(1));
        // string '1' 的后代：仅 'a'。
        $this->assertSame(['a'], $tree->getDescendantIdsById('1'));
    }

    public function testAncestorChainMayContainMixedIdTypes (): void
    {
        $tree = (new Tree())->init([
            ['id' => 1, 'parent_id' => null],
            ['id' => '1', 'parent_id' => 1],
            ['id' => 'x', 'parent_id' => '1'],
        ]);

        $this->assertSame([1, '1'], $tree->getParentIdsById('x'));
    }

    public function testBuildTreeRespectsIdTypeIsolation (): void
    {
        $tree = (new Tree())->init([
            ['id' => 1, 'parent_id' => null],
            ['id' => '1', 'parent_id' => 1],
            ['id' => 'a', 'parent_id' => '1'],
            ['id' => 2, 'parent_id' => '1'],
        ]);

        $this->assertSame(['1'], array_column($tree->buildTree(1), 'id'));
        $this->assertSame(['a', 2], array_column($tree->buildTree('1'), 'id'));
    }

    public function testMissingNodeLookupRespectsIdTypeIsolation (): void
    {
        $tree = (new Tree())->init([
            ['id' => '2', 'parent_id' => null],
        ]);

        $this->assertNull($tree->getNodeById(2));
        $this->assertNotNull($tree->getNodeById('2'));
    }
}
