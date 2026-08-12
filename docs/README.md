# Devcraft Dev Tools documentation

Devcraft Dev Tools is a PHP 8.3 library with two complementary capabilities:

1. **Fluent mutation** — annotate private properties with `#[With]` / `#[WithItem]`, extend `AbstractWith`, and get generated `with*` methods at runtime.
2. **DTO mapping** — extend `AbstractReflection` for typed public properties, hydrate from arrays, validate with attributes, and serialize back to arrays or JSON.

These base classes serve different property models and are **not** meant to be combined via inheritance.

## Architecture

```
Fluent API                         DTO Mapping
───────────                        ───────────
#[With] / #[WithItem]              public typed properties
        │                                  │
        ▼                                  ▼
 AbstractWith ──__call──► WithHandler   AbstractReflection
                                               │
                                               ▼
                                        ReflectionMapper
                                               │
                                               ▼
                                        PropertyValidator
                                               │
                          Filter / Range / Regex / ArrayOf
```

## Guides

| Guide | Contents |
| --- | --- |
| [Getting started](getting-started.md) | Requirements, Composer setup, both use cases, running tests |
| [With attributes](with-attributes.md) | `With`, `WithItem`, `AbstractWith`, naming, append vs map, errors |
| [Reflection mapper](reflection-mapper.md) | `AbstractReflection`, hydrate / serialize, nested objects, `ArrayOf` |
| [Validation](validation.md) | `Filter`, `Range`, `Regex`, `PropertyValidator`, `ValidationException` |
