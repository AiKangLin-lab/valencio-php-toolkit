<?php
// +----------------------------------------------------------------------
// | Success, real success,
// | is being willing to do the things that other people are not.
// +----------------------------------------------------------------------
// | Author:    Valencio Kang <ailin1219@foxmail.com>
// +----------------------------------------------------------------------
// | FileName:  Tree.php
// +----------------------------------------------------------------------
// | Year:      2026
// +----------------------------------------------------------------------
declare(strict_types=1);

namespace Valencio\PhpToolkit;

use InvalidArgumentException;
use LogicException;

/**
 * 树形数据处理类
 *
 * 用于处理基于主键和父级主键关系组成的扁平层级数据。
 */
class Tree
{
    /**
     * 原始数据。
     *
     * @var array<int, array<string, mixed>>
     */
    private array $data = [];

    /**
     * 父节点字段名。
     */
    private string $parentKey = 'parent_id';

    /**
     * 节点主键字段名。
     */
    private string $primaryKey = 'id';

    /**
     * 构建树时使用的子节点字段名。
     */
    private string $childrenKey = 'children';

    /**
     * 节点索引。
     *
     * key 为内部索引键（见 indexKey()），value 为完整节点数据。
     *
     * @var array<string, array<string, mixed>>
     */
    private array $nodeMap = [];

    /**
     * 子节点索引。
     *
     * key 为父节点内部索引键（见 indexKey()），value 为其直接子节点 ID 列表。
     *
     * @var array<string, array<int, int|string>>
     */
    private array $childrenMap = [];

    /**
     * 父节点索引。
     *
     * key 为节点内部索引键（见 indexKey()），value 为其父节点 ID。
     *
     * @var array<string, int|string|null>
     */
    private array $parentMap = [];

    /**
     * 初始化树数据。
     *
     * @param array<int, array<string, mixed>> $data
     * @param string $parentKey 父节点字段名
     * @param string $primaryKey 主键字段名
     * @param string $childrenKey 子节点字段名
     */
    public function init (
        array  $data = [],
        string $parentKey = 'parent_id',
        string $primaryKey = 'id',
        string $childrenKey = 'children'
    ): self {
        $this->data = $data;
        $this->parentKey = $parentKey;
        $this->primaryKey = $primaryKey;
        $this->childrenKey = $childrenKey;

        $this->buildIndexes();

        return $this;
    }

    /**
     * 根据当前数据建立内部索引。
     */
    private function buildIndexes (): void
    {
        // 每次重新初始化时必须清空旧索引。
        $this->nodeMap = [];
        $this->childrenMap = [];
        $this->parentMap = [];

        foreach ($this->data as $index => $item) {
            // Tree 的基本数据必须包含主键和父级字段。
            if (!array_key_exists($this->primaryKey, $item)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Tree node at index %d is missing primary key "%s".',
                        $index,
                        $this->primaryKey
                    )
                );
            }

            if (!array_key_exists($this->parentKey, $item)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Tree node at index %d is missing parent key "%s".',
                        $index,
                        $this->parentKey
                    )
                );
            }

            $id = $item[$this->primaryKey];
            $parentId = $item[$this->parentKey];

            // 当前版本只允许整数或字符串作为节点 ID。
            if (!is_int($id) && !is_string($id)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Tree node primary key "%s" must be an integer or string.',
                        $this->primaryKey
                    )
                );
            }

            if ($parentId !== null && !is_int($parentId) && !is_string($parentId)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Tree node parent key "%s" must be an integer, string or null.',
                        $this->parentKey
                    )
                );
            }

            $idKey = $this->indexKey($id);
            $parentIdKey = $this->indexKey($parentId);

            // ID 是树结构的唯一标识，不允许重复。
            if (array_key_exists($idKey, $this->nodeMap)) {
                throw new InvalidArgumentException(
                    sprintf('Duplicate tree node ID "%s".', $id)
                );
            }

            // 节点索引：ID -> 节点。
            $this->nodeMap[$idKey] = $item;

            // 父节点索引：ID -> parent ID。
            $this->parentMap[$idKey] = $parentId;

            // 子节点索引：parent ID -> children IDs。
            $this->childrenMap[$parentIdKey][] = $id;
        }
    }

    /**
     * 生成内部索引键。
     *
     * 避免 null、整数和字符串在数组键中产生歧义
     * （例如 PHP 会把纯数字字符串键隐式转换为整数键）。
     */
    private function indexKey (int|string|null $value): string
    {
        return match (true) {
            $value === null => 'null:',
            is_int($value) => 'int:' . $value,
            default => 'string:' . $value,
        };
    }

    /**
     * 根据节点 ID 获取节点数据。
     *
     * @param int|string $id 节点 ID
     * @return array<string, mixed>|null 节点不存在时返回 null
     */
    public function getNodeById (int|string $id): ?array
    {
        return $this->nodeMap[$this->indexKey($id)] ?? null;
    }

    /**
     * 根据父节点 ID 获取直接子节点。
     *
     * 仅返回当前父节点的一级子节点，不递归获取后代节点。
     *
     * @param int|string|null $parentId 父节点 ID
     * @return array<int, array<string, mixed>>
     */
    public function getChildrenByParentId (int|string|null $parentId): array
    {
        $childrenIds = $this->childrenMap[$this->indexKey($parentId)] ?? [];

        $children = [];

        foreach ($childrenIds as $id) {
            $node = $this->getNodeById($id);

            if ($node !== null) {
                $children[] = $node;
            }
        }

        return $children;
    }

    /**
     * 根据父节点 ID 获取直接子节点 ID。
     *
     * 仅返回当前父节点的一级子节点 ID，不递归获取后代节点，
     * 并保持原始数据顺序。
     *
     * @param int|string|null $parentId 父节点 ID
     * @return array<int, int|string> 无子节点时返回空数组
     */
    public function getChildrenIdsByParentId (int|string|null $parentId): array
    {
        return $this->childrenMap[$this->indexKey($parentId)] ?? [];
    }

    /**
     * 根据节点 ID 获取所有后代节点。
     *
     * 深度优先遍历，同层级保持原始数据顺序。
     * 节点不存在时始终返回空数组（无论 includeSelf 取值）。
     *
     * @param int|string $id 节点 ID
     * @param bool $includeSelf 结果第一项是否包含自身节点
     * @return array<int, array<string, mixed>>
     */
    public function getDescendantsById (int|string $id, bool $includeSelf = false): array
    {
        $nodes = [];

        foreach ($this->traverseDescendantIds($id, $includeSelf) as $nodeId) {
            $nodes[] = $this->nodeMap[$this->indexKey($nodeId)];
        }

        return $nodes;
    }

    /**
     * 根据节点 ID 获取所有后代节点 ID。
     *
     * 遍历顺序与 getDescendantsById() 完全一致，
     * 二者共享同一条内部遍历逻辑。
     *
     * @param int|string $id 节点 ID
     * @param bool $includeSelf 结果第一项是否包含自身 ID
     * @return array<int, int|string>
     */
    public function getDescendantIdsById (int|string $id, bool $includeSelf = false): array
    {
        return $this->traverseDescendantIds($id, $includeSelf);
    }

    /**
     * 统一的后代遍历逻辑。
     *
     * 深度优先遍历指定节点的所有后代，同层级保持原始数据顺序。
     * 节点不存在时返回空数组。
     *
     * @param int|string $id 节点 ID
     * @param bool $includeSelf 结果第一项是否包含自身 ID
     * @return array<int, int|string>
     */
    private function traverseDescendantIds (int|string $id, bool $includeSelf): array
    {
        $idKey = $this->indexKey($id);

        if (!array_key_exists($idKey, $this->nodeMap)) {
            return [];
        }

        $result = [];

        if ($includeSelf) {
            $result[] = $id;
        }

        $visited = [$idKey => true];

        $this->collectDescendantIds($id, $visited, $result);

        return $result;
    }

    /**
     * 递归收集后代节点 ID。
     *
     * @param int|string $parentId 当前父节点 ID
     * @param array<string, true> $visited 已访问的节点内部索引键
     * @param array<int, int|string> $result 收集结果（按引用传递）
     */
    private function collectDescendantIds (int|string $parentId, array &$visited, array &$result): void
    {
        foreach ($this->childrenMap[$this->indexKey($parentId)] ?? [] as $childId) {
            $childKey = $this->indexKey($childId);

            // 同一条遍历路径上再次遇到已访问节点，说明数据存在循环引用。
            if (isset($visited[$childKey])) {
                throw new LogicException(
                    sprintf('Circular reference detected at tree node ID "%s".', $childId)
                );
            }

            $visited[$childKey] = true;
            $result[] = $childId;

            $this->collectDescendantIds($childId, $visited, $result);
        }
    }

    /**
     * 根据节点 ID 获取其原始声明的父节点 ID。
     *
     * 返回的是节点数据中 parent 字段的原始值，即使该父节点
     * 不在当前数据集中也照常返回（区别于 getParentById()）。
     *
     * @param int|string $id 节点 ID
     * @return int|string|null 节点不存在或 parent 为 null 时返回 null
     */
    public function getParentIdById (int|string $id): int|string|null
    {
        return $this->parentMap[$this->indexKey($id)] ?? null;
    }

    /**
     * 根据节点 ID 获取其直接父节点数据。
     *
     * 返回的是实际存在于当前数据集中的父节点，
     * 悬空 parent ID（父节点不在数据集中）时返回 null。
     *
     * @param int|string $id 节点 ID
     * @return array<string, mixed>|null
     */
    public function getParentById (int|string $id): ?array
    {
        $parentId = $this->getParentIdById($id);

        if ($parentId === null) {
            return null;
        }

        return $this->getNodeById($parentId);
    }

    /**
     * 根据节点 ID 获取所有实际存在的祖先节点。
     *
     * 顺序为：最顶层祖先 -> 最近父节点。
     * 悬空 parent ID（父节点不在当前数据集中）时停止向上追溯。
     *
     * @param int|string $id 节点 ID
     * @return array<int, array<string, mixed>>
     */
    public function getParentsById (int|string $id): array
    {
        $parents = [];

        foreach ($this->traverseAncestorIds($id) as $ancestorId) {
            $parents[] = $this->nodeMap[$this->indexKey($ancestorId)];
        }

        return $parents;
    }

    /**
     * 根据节点 ID 获取所有实际存在的祖先节点 ID。
     *
     * 遍历规则与 getParentsById() 完全一致，
     * 二者共享同一条内部遍历逻辑。
     *
     * @param int|string $id 节点 ID
     * @return array<int, int|string>
     */
    public function getParentIdsById (int|string $id): array
    {
        return $this->traverseAncestorIds($id);
    }

    /**
     * 统一的祖先遍历逻辑。
     *
     * 沿 parent 链向上收集所有实际存在的祖先 ID，
     * 顺序为：最顶层祖先 -> 最近父节点。
     * 节点不存在、parent 为 null 或悬空 parent ID 时停止。
     *
     * @param int|string $id 节点 ID
     * @return array<int, int|string>
     */
    private function traverseAncestorIds (int|string $id): array
    {
        $visited = [];

        $currentId = $id;
        $ancestors = [];

        while (true) {
            $parentId = $this->parentMap[$this->indexKey($currentId)] ?? null;

            // 当前节点不存在、parent 为 null 或悬空 parent ID 时停止。
            if ($parentId === null || !array_key_exists($this->indexKey($parentId), $this->nodeMap)) {
                break;
            }

            $parentKey = $this->indexKey($parentId);

            // 父级链上再次遇到已访问节点，说明数据存在循环父级关系。
            if (isset($visited[$parentKey])) {
                throw new LogicException(
                    sprintf('Circular parent reference detected at tree node ID "%s".', $parentId)
                );
            }

            $visited[$parentKey] = true;

            // 头插，使最终顺序为：最顶层祖先 -> 最近父节点。
            array_unshift($ancestors, $parentId);

            $currentId = $parentId;
        }

        return $ancestors;
    }

    /**
     * 判断指定节点是否拥有直接子节点。
     *
     * 仅针对实际存在于当前数据集中的节点判断，
     * 悬空 parent ID（如 999 本身不是节点）始终返回 false，
     * 与 getChildrenByParentId() 的孤儿节点语义不同。
     *
     * @param int|string $id 节点 ID
     */
    public function hasChildren (int|string $id): bool
    {
        $idKey = $this->indexKey($id);

        if (!array_key_exists($idKey, $this->nodeMap)) {
            return false;
        }

        return ($this->childrenMap[$idKey] ?? []) !== [];
    }

    /**
     * 从指定 parent ID 开始构建树形结构。
     *
     * 返回该 parent ID 下所有直接子节点及其完整后代，
     * 不包含 parent ID 对应节点自身。
     * 即使该 parent ID 不是实际节点，只要存在节点
     * 声明归属于它，仍可正常构建（孤儿分支语义）。
     *
     * 原始节点数据中已存在的 children 字段会被忽略，
     * 始终根据当前索引重新生成。
     *
     * @param int|string|null $parentId 起始父节点 ID，默认 0
     * @return array<int, array<string, mixed>>
     */
    public function buildTree (int|string|null $parentId = 0): array
    {
        // 起始 parent ID 本身也加入递归路径，
        // 以检测经过起始节点的循环引用（如 1 -> 2 -> 1）。
        $path = [$this->indexKey($parentId) => true];

        return $this->buildBranch($parentId, $path);
    }

    /**
     * 递归构建指定 parent ID 下的树分支。
     *
     * @param int|string|null $parentId 当前父节点 ID
     * @param array<string, true> $path 当前递归路径上的节点内部索引键
     * @return array<int, array<string, mixed>>
     */
    private function buildBranch (int|string|null $parentId, array $path): array
    {
        $branch = [];

        foreach ($this->childrenMap[$this->indexKey($parentId)] ?? [] as $childId) {
            $childKey = $this->indexKey($childId);

            // 当前递归路径上再次遇到该节点，说明数据存在循环引用。
            if (isset($path[$childKey])) {
                throw new LogicException(
                    sprintf('Circular reference detected at tree node ID "%s".', $childId)
                );
            }

            // 必须复制后再组装，避免污染 nodeMap 中的原始节点。
            $node = $this->nodeMap[$childKey];

            // 无条件移除原始数据中的 children 字段，禁止信任旧内容。
            unset($node[$this->childrenKey]);

            $childPath = $path;
            $childPath[$childKey] = true;

            $grandChildren = $this->buildBranch($childId, $childPath);

            if ($grandChildren !== []) {
                $node[$this->childrenKey] = $grandChildren;
            }

            $branch[] = $node;
        }

        return $branch;
    }
}
