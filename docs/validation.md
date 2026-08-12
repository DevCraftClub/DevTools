# Validation

Validation rules are PHP attributes that implement `Devcraft\Interfaces\ValidationRule`. They return `null` when valid, or a short message when invalid. `null` input always passes (requiredness is handled by the mapper's null fallback).

## ValidationRule contract

```php
interface ValidationRule
{
    /** Returns null when valid, otherwise a value-free error message. */
    public function validate(mixed $value): ?string;
}
```

Messages describe expectation only (`must be numeric`), not the actual value. Paths and actual types are attached by `ReflectionMapper` / `ValidationException`.

## Attributes

All validation attributes target properties and are repeatable.

### `Filter`

Wraps PHP's `filter_var`:

```php
use Devcraft\Attributes\Filter;

#[Filter(FILTER_VALIDATE_EMAIL)]
public string $email;

#[Filter(FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])]
public int $quantity;
```

`FILTER_VALIDATE_BOOLEAN` automatically adds `FILTER_NULL_ON_FAILURE` so invalid booleans are rejected instead of coerced to `false`.

Failure message: `must pass filter {id}`.

### `Range`

Numeric bounds for `int` or `float` values:

```php
use Devcraft\Attributes\Range;

#[Range(min: 1, max: 65535)]
public int $port;

#[Range(min: 0.0)]
public float $ratio;
```

Either bound may be omitted. Non-numeric values fail with `must be numeric`.

### `Regex`

String pattern match:

```php
use Devcraft\Attributes\Regex;

#[Regex('/^[0-9a-f-]{36}$/i')]
public string $id;
```

The constructor validates the pattern with `preg_match` and throws `InvalidArgumentException` if the expression is invalid. Non-string values fail with `must be a string`.

### `ArrayOf`

Ensures the value is a list whose elements match a type name:

```php
use Devcraft\Attributes\ArrayOf;

#[ArrayOf('string')]
public array $tags;

#[ArrayOf(Address::class)]
public array $locations;
```

Supported builtins: `mixed`, `string`, `int`/`integer`, `float`/`double`, `bool`/`boolean`, `array`, `object`. Other names are treated as class/interface checks via `instanceof`.

In `ReflectionMapper`, `ArrayOf` also drives nested conversion before validation runs.

## PropertyValidator

`Devcraft\Validation\PropertyValidator` runs attribute rules without hydration:

```php
use Devcraft\Validation\PropertyValidator;
use ReflectionProperty;

$validator = new PropertyValidator();
$errors = $validator->validateValue(
    new ReflectionProperty(Profile::class, 'port'),
    0,
);
// ['must be greater than or equal to 1']

$objectErrors = $validator->validateObject($profile);
// ['port' => [...], ...]
```

`validateObject()` inspects only public, non-static, initialized properties. Non-`ValidationRule` attributes are ignored. Multiple rules on one property contribute multiple messages.

## ValidationException

Thrown by `ReflectionMapper` when one or more required property paths fail:

```php
use Devcraft\Exceptions\ValidationException;

try {
    Profile::fromArray($payload);
} catch (ValidationException $exception) {
    $exception->getMessage();
    // "Validation failed for: port, address.city"

    $exception->getErrors();
    // [
    //   'port' => ['must be greater than or equal to 1'],
    //   'address.city' => ['must be string'],
    // ]
}
```

`getErrors()` returns `array<string, list<string>>` keyed by dotted property paths.
