# Reflection mapper and AbstractReflection

`AbstractReflection` is the base class for DTOs with public typed properties. It exposes `fromArray()`, `toArray()`, and `toJson()`. Hydration and serialization are implemented by `ReflectionMapper`.

## Quick start

```php
use Devcraft\Abstracts\AbstractReflection;
use Devcraft\Attributes\ArrayOf;
use Devcraft\Attributes\Range;

final class Address extends AbstractReflection
{
    public string $city;
}

final class Profile extends AbstractReflection
{
    public string $name;

    public Address $address;

    #[ArrayOf(Address::class)]
    public array $locations = [];

    #[Range(min: 0)]
    public int $score = 0;
}

$profile = Profile::fromArray([
    'name' => 'Ada',
    'address' => ['city' => 'Berlin'],
    'locations' => [
        ['city' => 'Berlin'],
        ['city' => 'Paris'],
    ],
    'score' => '10',
]);

$profile->toArray();
$profile->toJson();
```

## AbstractReflection API

| Method | Description |
| --- | --- |
| `static fromArray(array $data): static` | Create an instance and hydrate public properties |
| `toArray(): array` | Export initialized public properties (nested DTOs recursively) |
| `toJson(): string` | Pretty-printed JSON with Unicode and slashes unescaped; throws `JsonException` on failure |
| `static setLogger(LoggerInterface $logger): void` | Override the logger used for validation failures |
| `static resetLogger(): void` | Clear the custom logger (tests / teardown) |

Only **public, non-static** properties participate in mapping. Private/protected fields are ignored by the mapper.

## Hydration rules

`ReflectionMapper::hydrate($target, $data)` walks public properties:

1. Missing keys or explicit `null` trigger the null fallback.
2. Values are converted according to the property type.
3. `#[ArrayOf]` post-processes list properties.
4. Validation attributes run on the converted value.
5. Failures are logged and collected; after the pass, a non-empty error map raises `ValidationException`.

### Scalar conversion

| Declared type | Accepted input |
| --- | --- |
| `string` | string only |
| `int` | int, or numeric string via `FILTER_VALIDATE_INT` |
| `float` | float, int, or numeric string via `FILTER_VALIDATE_FLOAT` |
| `bool` | bool, `0`/`1`, or boolean strings via `FILTER_VALIDATE_BOOLEAN` |
| `array` | array |
| `object` | object |
| `mixed` / untyped | anything |

Union and intersection property types are not supported and fail conversion.

### Nested DTOs

If the property type is a subclass of `AbstractReflection` and the input is an array, the mapper instantiates the nested class and hydrates it with a dotted path prefix (`address.city`, `locations.0.city`).

If the input is already an instance of the declared type (or interface), it is accepted as-is.

### Null fallback

When a key is missing or the value is `null`:

- properties with a default value keep their default
- nullable properties without a usable value become `null`
- required non-nullable properties without a default record `is required`

When conversion or validation fails for a **nullable** property, the mapper sets the property to `null` instead of adding that path to the thrown error map (the failure is still logged).

## `#[ArrayOf]`

`ArrayOf` is both a conversion hint for the mapper and a `ValidationRule`.

During conversion, the property value must be a **list** (`array_is_list`). Each element is converted to the declared type (builtin or nested `AbstractReflection`).

```php
#[ArrayOf('int')]
public array $ids = [];

#[ArrayOf(Address::class)]
public array $locations = [];
```

Associative arrays fail with `must be a list`. Element failures appear as `ids.1`, `locations.0.city`, and so on.

## Serialization

`toArray()` includes only initialized public properties. Nested `AbstractReflection` instances are expanded recursively. Plain arrays are walked element by element.

`toJson()` encodes `toArray()` with:

- `JSON_THROW_ON_ERROR`
- `JSON_UNESCAPED_UNICODE`
- `JSON_PRETTY_PRINT`
- `JSON_UNESCAPED_SLASHES`
- `JSON_INVALID_UTF8_SUBSTITUTE`

## Logging

By default `AbstractReflection` uses an `Analog\Logger`. Call `setLogger()` to capture validation failures in tests or application logging:

```php
AbstractReflection::setLogger($psrLogger);
```

Each failure is logged at error level with context keys `class`, `property` (dotted path), `expected`, and `actual_type`.

## Direct mapper use

You can hydrate an existing object without `fromArray()`:

```php
use Devcraft\Mapper\ReflectionMapper;
use Psr\Log\NullLogger;

$mapper = new ReflectionMapper(new NullLogger());
$mapper->hydrate($profile, $payload);
```

An optional second constructor argument accepts a custom `PropertyValidator`.
