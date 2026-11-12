# Integrations — Spiral Framework

## Bootloader

`ValueObjectBootloader` (`DEPENDENCIES = [FiltersBootloader::class]`). Users list it in `Kernel::defineBootloaders()`;
Spiral core has no package auto-discovery.

## Filters: the caster

- `AttributeMapper::map()` reads each `#[Post]`/`#[Query]`/... property, skips `null` (the property keeps its default,
  so a nullable property needs `= null`), then `Mapper::setValue()` asks `CasterRegistryInterface::getCasters()` in
  registration order — first `supports(ReflectionNamedType)` wins — and falls back to `DefaultCaster`. Union and
  intersection types never reach a caster.
- `FiltersBootloader::initCasterRegistry()` builds the registry with `EnumCaster` and `UuidCaster`; `register()` keys
  by class and appends, so `ValueObjectCaster` comes last. It never claims enums, so order does not matter.
- A `SetterException` becomes the property's error (message = exception message, unless the property has
  `#[CastingErrorMessage]`); the property stays uninitialized. With errors, `ValidateFilterInterceptor` throws
  `ValidationException` (code 422) — with or without a `FilterDefinition` — and `ValidationHandlerMiddleware` +
  `JsonErrorsRenderer` answer `422 {"errors": {"email": "..."}}`.
