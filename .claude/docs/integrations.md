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

## Route arguments: the interceptor

- `Router` → `AbstractTarget::getHandler()` → `CoreHandler::handle()` builds `CallContext(Target::fromPair($controller,
  $action), $matches, [ServerRequestInterface::class => $request])`. `Target::fromPair()` gives a reflection only when
  the method exists; otherwise `getReflection()` is `null` and the interceptor hands the context on unchanged (the
  handler then 404s).
- The pipeline is the target's own (`Action::withHandler($pipeline)`, per route) or, without one,
  `HandlerInterface` from the container — which `RouterBootloader` aliases to `CoreInterface` when that is also a
  `HandlerInterface`, i.e. the `DomainBootloader::domainCore()` pipeline with `INTERCEPTORS` (global). Without either,
  `AutowireHandler`.
- `AbstractCore::handle()` → `Resolver::resolveArguments()`: by name first (exact case), then by position; a value is
  validated against the parameter type — a string for `EmailAddress` → `InvalidArgumentException` → `ControllerException
  BAD_ARGUMENT` → 400. A `null` match with a default becomes the default (`/optional[/<email>]`).
- `TargetCallException` codes map in `CoreHandler::mapException()`: `NOT_FOUND`/`BAD_ACTION` → 404, others → 400/401/403/500.
  The client sees `NotFoundException('Not found')`; our message is only the previous exception.
- A class-typed parameter absent from the matches is autowired from the container by Spiral; the interceptor does not
  touch absent arguments.
