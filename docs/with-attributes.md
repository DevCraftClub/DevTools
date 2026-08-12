# With attributes and AbstractWith

Use `#[With]` and `#[WithItem]` to generate fluent setters for private (or protected) properties. Extend `AbstractWith` so `__call` routes those virtual methods through `WithHandler`.

## Quick start

```php
use Devcraft\Abstracts\AbstractWith;
use Devcraft\Attributes\With;
use Devcraft\Attributes\WithItem;

final class Query extends AbstractWith
{
    #[With]
    private ?int $page = null;

    #[With]
    private ?string $starting_after = null;

    #[With, WithItem('string')]
    private array $tags = [];

    #[WithItem(['int', 'string'], ['string', 'null'])]
    private array $labels = [];
}

$query = (new Query())
    ->withPage(2)
    ->withStartingAfter('cursor')
    ->withTags(['a'])
    ->withTagsItem('b')
    ->withLabelsItem('status', 'ready');
```

## AbstractWith

`Devcraft\Abstracts\AbstractWith` is the recommended base class:

```php
public function __call(string $methodName, array $arguments): mixed
{
    if (WithHandler::handles($this, $methodName)) {
        return WithHandler::call($this, $methodName, $arguments);
    }

    throw new BadMethodCallException(
        sprintf('Call to undefined method %s::%s()', $this::class, $methodName)
    );
}
```

You can call `WithHandler` yourself if you need a custom `__call`, but subclasses of `AbstractWith` get the routing for free.

## Property constraints

Attributes are only valid on properties that are:

- non-public (private or protected)
- non-static
- non-readonly

`#[WithItem]` additionally requires a non-nullable `array` property.

Invalid configurations throw `LogicException` when metadata is first built (on the first `handles()` / `call()` for that class).

## Method naming

Property names are converted to StudlyCase:

| Property | `#[With]` method | `#[WithItem]` method |
| --- | --- | --- |
| `$page` | `withPage($value)` | — |
| `$starting_after` | `withStartingAfter($value)` | — |
| `$tags` | `withTags($array)` | `withTagsItem($item)` |
| `$labels` | — | `withLabelsItem($key, $value)` |

Lookup is case-insensitive (`WITHPAGE` works). Method names must remain unique across the inheritance chain; collisions (including case-only collisions such as `$name` / `$NAME`) throw `LogicException`.

## `#[With]` — replace the whole value

```php
#[With]
private ?int $page = null;

$query->withPage(3);    // sets $page = 3
$query->withPage(null); // allowed when the property is nullable
```

- Expects exactly one argument.
- Uses PHP's normal property type check (no string→int coercion for typed properties).
- Returns `$this`.

## `#[WithItem]` — append or map

### Append (one type descriptor)

```php
#[WithItem('string')]
private array $tags = [];

$query->withTagsItem('proxy'); // $tags[] = 'proxy'
```

### Map (two type descriptors)

```php
#[WithItem('string', ['string', 'null'])]
private array $labels = [];

$query->withLabelsItem('status', 'ready');
$query->withLabelsItem('status', null); // replace
```

Map keys may only be typed as `int` and/or `string`.

### Combined with `#[With]`

```php
#[With, WithItem('string')]
private array $tags = [];

$query->withTags(['a', 'b']); // replace entire array
$query->withTagsItem('c');    // append
```

## Type descriptors

Descriptors are strings or lists of strings (unions):

```php
#[WithItem('string')]
#[WithItem(['int', 'string'])]
#[WithItem(ItemContract::class)]
#[WithItem(['int', 'string'], ['string', 'null'])]
```

Supported builtins: `string`, `int`, `float`, `bool`, `true`, `false`, `null`, `array`, `object`, `iterable`, `callable`, `mixed`.

Also supported: class, interface, and enum names that exist at runtime.

Notes:

- `mixed` cannot be combined with other types in the same union.
- `float` does not accept integers (no numeric coercion).
- `void` / `never` are rejected.
- Unknown class names throw `LogicException` at metadata build time.

## Runtime vs configuration errors

| Situation | Exception |
| --- | --- |
| Public / static / readonly property | `LogicException` |
| Nullable or non-array `WithItem` target | `LogicException` |
| Bad descriptor count / empty union / invalid map key types | `LogicException` |
| Repeated attribute / virtual method collision | `LogicException` |
| Uninitialized array on append/map | `LogicException` |
| Wrong argument count | `ArgumentCountError` |
| Value fails descriptor check | `TypeError` |
| Unknown virtual method via `WithHandler::call` | `BadMethodCallException` |

Invalid item/map arguments are checked before mutation, so failed calls do not partially update the array.

## Metadata caching

`WithHandler` builds and caches operation metadata per runtime class on first use. Subsequent calls reuse the same writers and property reflections. The cache stores closures bound to the declaring class, not to fixture instances.

## Inheritance

Attributes on private and protected properties declared in parent classes are discovered and work on child instances. Writers are bound to the declaring class so private parent properties remain writable through the virtual methods.
