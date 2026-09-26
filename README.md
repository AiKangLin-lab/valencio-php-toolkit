# PHP Toolkit

[![PHP Version](https://img.shields.io/badge/PHP-%5E8.3-8892BF.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

A modern PHP toolkit providing reusable, dependency-free utilities for data processing — built for PHP 8.3+ with strict types throughout.

## Requirements

- PHP 8.3 or newer
- Composer 2.x
- No runtime dependencies (no Laravel, no mbstring, no third-party packages)

## Installation

```bash
composer require valencio/php-toolkit
```

## Components

### Tree

A tree processor for flat hierarchical data linked by a primary key and a parent key. It builds internal indexes once on `init()` and answers all relationship queries — children, descendants, parents, ancestors — without ever re-scanning the source array.

```php
use Valencio\PhpToolkit\Tree;

$tree = new Tree();
$tree->init([
    ['id' => 1, 'parent_id' => null, 'name' => 'System'],
    ['id' => 2, 'parent_id' => 1,    'name' => 'Users'],
    ['id' => 3, 'parent_id' => 1,    'name' => 'Roles'],
    ['id' => 4, 'parent_id' => 2,    'name' => 'Admins'],
    ['id' => 5, 'parent_id' => 2,    'name' => 'Guests'],
    ['id' => 6, 'parent_id' => 4,    'name' => 'Super Admin'],
]);
```

#### Node lookup

```php
$tree->getNodeById(4);
// ['id' => 4, 'parent_id' => 2, 'name' => 'Admins']

$tree->getNodeById(999);   // null — missing nodes return null, not []
```

#### Downward queries

```php
// Direct children (data / IDs)
$tree->getChildrenByParentId(1);
$tree->getChildrenIdsByParentId(1);   // [2, 3]

$tree->getChildrenByParentId(null);   // root nodes with parent_id = null

$tree->hasChildren(2);                // true

// All descendants (DFS preorder, sibling order preserved)
$tree->getDescendantIdsById(1);       // [2, 4, 6, 5, 3]
$tree->getDescendantsById(1);         // node data, same order
$tree->getDescendantIdsById(1, includeSelf: true);
// [1, 2, 4, 6, 5, 3]
```

#### Upward queries

```php
// Declared parent ID — returns the raw field value,
// even if that parent does not exist in the dataset
$tree->getParentIdById(6);            // 4

// Actual parent node — null if the parent is missing from the dataset
$tree->getParentById(6);

// Ancestors, ordered topmost -> nearest
$tree->getParentIdsById(6);           // [1, 2, 4]
$tree->getParentsById(6);             // node data, same order
```

#### Building a nested tree

```php
$tree->buildTree(1);
```

Returns the subtree under node `1` (the node itself excluded), using the configured children key:

```php
[
    [
        'id' => 2, 'parent_id' => 1, 'name' => 'Users',
        'children' => [
            [
                'id' => 4, 'parent_id' => 2, 'name' => 'Admins',
                'children' => [
                    ['id' => 6, 'parent_id' => 4, 'name' => 'Super Admin'],
                ],
            ],
            ['id' => 5, 'parent_id' => 2, 'name' => 'Guests'],
        ],
    ],
    ['id' => 3, 'parent_id' => 1, 'name' => 'Roles'],
]
```

Custom keys are supported:

```php
$tree->init($data, parentKey: 'pid', primaryKey: 'node_id', childrenKey: 'items');
```

#### Semantics & guarantees

- **Typed IDs.** Integer and string IDs are strictly isolated — `1` and `'1'` are two distinct nodes. Internal index keys never collide through PHP's implicit array key casting.
- **Nullable parents.** `parent_id` accepts `int|string|null`; `null` denotes a root.
- **Dangling references.** A node whose parent is missing from the dataset is treated as the root of its own branch: it is reachable via `getChildrenByParentId($missingId)` and `buildTree($missingId)`, but `getParentById()` returns `null` and ancestor traversal stops there. Partial trees are valid input — a parent that simply was not loaded is not an error.
- **Immutability.** Node data is never modified. `buildTree()` copies each node before assembling `children`, and any pre-existing `children` field in the source data is discarded and regenerated.
- **Cycle safety.** Circular references (including self-reference and cycles passing through the traversal start) throw `LogicException`. Cycles unreachable from the queried node are ignored.
- **Deterministic order.** Siblings keep their original order; descendants follow DFS preorder; ancestors run topmost to nearest.

### Generate

A stateless, final utility class for random values, identifiers, and business serial numbers. All randomness comes from `random_bytes()` / `random_int()` — never `rand()`, `mt_rand()`, or `uniqid()`.

```php
use Valencio\PhpToolkit\Generate;
```

#### Identifiers

```php
Generate::uuidV4();
// RFC 4122 v4, always lowercase: "9a7f6c2e-1b3d-4e5f-8a9b-0c1d2e3f4a5b"
```

#### Random strings & numbers

```php
Generate::randomString(32);               // [A-Za-z0-9], exact length
Generate::randomString(8, 'ABC123');      // custom alphabet (deduplicated, printable ASCII only)
Generate::randomNumber(6);                // digits only, leading zeros kept: "003821"
Generate::verificationCode();             // semantic alias for a 6-digit code
```

#### Tokens & keys

```php
Generate::secureToken();                    // 32 random bytes, URL-safe Base64 (43 chars)
Generate::secureToken(prefix: 'tk_');       // "tk_..." — no separator added
Generate::secureKey();                      // 32 bytes as lowercase hex (64 chars)
Generate::secureKey(16, prefix: 'sk_');     // "sk_..." + 32 hex chars
```

`secureToken()` is for URL contexts (reset links, invitation tokens); `secureKey()` is for secrets (API keys, signing keys). The `bytes` parameter is raw random entropy — not the output length.

#### Business serial numbers

```php
Generate::orderNumber();                  // "20260926183045128472" (YmdHis + 6 digits)
Generate::orderNumber('ORD');             // "ORD20260926183045128472"
Generate::serialNumber('PAY');            // "PAY2026092618304512345678" (8 random digits)
Generate::serialNumber('REFUND', 4);      // custom random length
```

> Time-based serial numbers provide high collision resistance but **are not guaranteed unique** across processes or machines. Enforce strict uniqueness with your database, Redis, or a Snowflake service.

## Testing

```bash
composer test
```

Runs the full PHPUnit suite (PHPUnit ^12.5 as a dev dependency only):

```text
OK (117 tests, 742 assertions)
```

## License

The MIT License. See [LICENSE](LICENSE).
