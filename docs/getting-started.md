# Getting started

This guide walks through installing Devcraft Dev Tools, choosing the right base class, and building a first fluent builder and a first response DTO.

## Requirements

- PHP 8.3 or newer
- [Composer](https://getcomposer.org/)
- A consuming project that can declare a Composer path repository (or a published package version later)

## Installation

Path repositories must be configured by the consuming root project. Composer does not inherit them transitively.

In the consumer's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../DevTool",
            "options": {"symlink": true}
        }
    ],
    "require": {
        "devcraft/dev-tools": "@dev"
    }
}
```

Adjust `url` to the relative or absolute path of this package on disk. Then install:

```bash
composer update devcraft/dev-tools
```

Publish this package later, or replace the path repository with a normal VCS/Packagist repository and a version constraint.

## Choosing a base class

| Need | Extend | Property style |
| --- | --- | --- |
| Fluent builders / query objects | `AbstractWith` | Private (or protected), non-static, non-readonly |
| API response / request DTOs | `AbstractReflection` | Public, typed, hydratable |

Do **not** combine them through inheritance. Private fluent properties and public mapped properties solve different problems. If a type needs both shapes, prefer composition or two separate classes.

A real-world fluent example is a WebShare-style query builder: chain `withPage()`, `withPageSize()`, and filter helpers, then render a query string. That pattern maps cleanly onto `AbstractWith` plus `#[With]` / `#[WithItem]`.

## Use case A — Fluent builder

Extend `AbstractWith` and annotate private properties:

```php
use Devcraft\Abstracts\AbstractWith;
use Devcraft\Attributes\With;
use Devcraft\Attributes\WithItem;

final class Query extends AbstractWith
{
    #[With]
    private ?int $page = null;

    #[With, WithItem('string')]
    private array $tags = [];

    #[WithItem('string', ['string', 'null'])]
    private array $labels = [];

    public function page(): ?int
    {
        return $this->page;
    }

    public function tags(): array
    {
        return $this->tags;
    }

    public function labels(): array
    {
        return $this->labels;
    }
}
```

Use the generated methods:

```php
$query = (new Query())
    ->withPage(1)
    ->withTagsItem('proxy')
    ->withLabelsItem('status', 'ready');

$query->page();   // 1
$query->tags();   // ['proxy']
$query->labels(); // ['status' => 'ready']
```

How it works:

- `AbstractWith::__call()` forwards unknown methods to `WithHandler`.
- `#[With]` creates `withPropertyName($value)` and replaces the whole property.
- `#[WithItem]` creates `withPropertyNameItem(...)`. One type descriptor means append; two descriptors mean map set.
- Methods return `$this`, so chaining is the normal style.

See [With attributes](with-attributes.md) for property constraints, naming rules, unions, and error types.

## Use case B — API response DTO

Extend `AbstractReflection` and declare public typed properties, optionally with validation attributes:

```php
use Devcraft\Abstracts\AbstractReflection;
use Devcraft\Attributes\ArrayOf;
use Devcraft\Attributes\Range;
use Devcraft\Attributes\Regex;

final class Address extends AbstractReflection
{
    public string $city;
}

final class Proxy extends AbstractReflection
{
    #[Regex('/^[0-9a-f-]{36}$/i')]
    public string $id;

    #[Range(min: 1, max: 65535)]
    public int $port;

    public Address $address;

    #[ArrayOf(Address::class)]
    public array $locations = [];
}
```

Hydrate from decoded JSON (or any associative array):

```php
$proxy = Proxy::fromArray([
    'id' => '550e8400-e29b-41d4-a716-446655440000',
    'port' => '8080',
    'address' => ['city' => 'Berlin'],
    'locations' => [
        ['city' => 'Berlin'],
        ['city' => 'Paris'],
    ],
]);

$proxy->port;              // int 8080 (string coerced)
$proxy->address->city;     // 'Berlin'
$proxy->toArray();         // nested arrays
echo $proxy->toJson();     // pretty-printed JSON
```

How it works:

- `fromArray()` creates an instance and hydrates public properties through `ReflectionMapper`.
- Scalars may be coerced from strings where safe (`"8080"` → `8080`, `"false"` → `false`).
- Nested classes that extend `AbstractReflection` are hydrated recursively.
- `#[ArrayOf]` maps list elements to a declared type.
- Validation attributes run after conversion; failures raise `ValidationException`.

See [Reflection mapper](reflection-mapper.md) and [Validation](validation.md) for details.

## Running tests in this package

From the DevTool repository root:

```bash
composer install
composer test
```

`composer test` runs PHPUnit via `phpunit.xml.dist`, which bootstraps `vendor/autoload.php` and discovers everything under `tests/`.

## Next steps

1. Read [With attributes](with-attributes.md) if you are building fluent APIs.
2. Read [Reflection mapper](reflection-mapper.md) if you are mapping API payloads.
3. Read [Validation](validation.md) to attach `Filter`, `Range`, `Regex`, or `ArrayOf` rules.
