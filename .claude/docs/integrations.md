# Integrations — Spiral Framework

## Version range (verified 2026-11-12 UTC)

| Package | Highest | Lowest (`--prefer-lowest --prefer-stable`) |
|---|---|---|
| `spiral/framework` | 3.18.2 | 3.18.0 |
| `spiral/validator` | 1.8.0 | 1.8.0 |
| `nyholm/psr7` (dev) | 1.8.2 | 1.8.0 |

Both runs: 100 tests green. Spiral needs a concrete PSR-17 factory; `nyholm/psr7` is dev-only (laminas-diactoros 2.x
refuses PHP 8.4).

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

## Validation: the checker

- `ValidatorBootloader::init()` sets the `validator` config defaults (checkers, conditions, aliases);
  `addChecker()` is just `ConfiguratorInterface::modify('validator', new Append('checkers', $alias, $class))`. The
  bootloader does that itself in `boot()` when `ConfiguratorInterface::exists('validator')` — true once
  ValidatorBootloader's init ran (all inits of one `bootload()` call precede all boots) or an `app/config/validator.php`
  exists. Without it nothing is touched (`modify()` on a missing section would try to load the file and fail).
- Rules: `RuleParser::parseArgs()` drops index 0 but keeps the other indexes, so `['vo:valid', Foo::class]` gives
  `$args = [1 => Foo::class]`. `AbstractChecker::check()` `array_unshift`s the value, which reindexes: `valid($value,
  $class)`. `getMessage()` receives the un-reindexed `[1 => Foo::class]` — hence `reset()`.
- `RulesProvider` builds checkers through `Autowire` → container, so the bound mapper is injected.
- `AbstractValidator::validate()` skips a rule only when the field is **absent** and `ignoreEmpty()` says so; present
  values (including `'0'`, `''`, `null` sent explicitly) always reach the rule. `ALLOW_EMPTY_VALUES = ['valid']`
  makes absent fields reach it too, so a misconfigured class fails on the first request.
- The validator validates the raw input data (`FilterBag` → `SchematicEntity`), not the property values: the checker
  sees the client's string even when the property is typed with the value object.
- A rule's own message (`['vo:valid', Foo::class, 'error' => '...']`) replaces ours (`CheckerRule::getMessage()`).
- Messages go through `TranslatorTrait::say()`: `[[...]]` stripped, `{type}` interpolated; translated when a
  `TranslatorInterface` is bound in the current container scope.
