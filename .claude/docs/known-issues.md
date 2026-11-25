# Known issues

## Bootloader order decides who owns the mapper binding

`SINGLETONS` of a later bootloader overwrite earlier ones (no error). An application binding of `ValueObjectMapper`
listed before `ValueObjectBootloader` is silently replaced by the plain mapper. The README says "after".

## ValidatorBootloader may sit in another bootloader list

`boot()` sees `ConfiguratorInterface::exists('validator')` only if ValidatorBootloader was initialized by the same or
an earlier `bootload()` call. When it is not yet (ours in `defineBootloaders()`, ValidatorBootloader in
`defineAppBootloaders()`), registration is deferred to `Kernel::appBooted()` (`AbstractKernel::booted()` for a kernel
without app bootloaders), the hook `RouterBootloader` uses too. Both orders pinned by
`BootloaderTest::testTheCheckerIsRegisteredWhenTheValidatorIsBootedInAnotherList`.

## Validator config delivered before the deferred registration

If an app bootloader takes `ValidatorConfig` in its `boot()` (injection creates the config object), the config is
frozen before `appBooted()` runs and `modify()` throws `ConfigDeliveredException`. The bootloader turns that into a
`LogicException` with the remedy: list `ValueObjectBootloader` in the same list as `ValidatorBootloader`, or call
`ValidatorBootloader::addChecker('vo', ValueObjectChecker::class)` yourself. Pinned by
`BootloaderTest::testAValidatorConfigDeliveredBeforeTheDeferredRegistrationIsALogicExceptionWithTheRemedy`.
`ConfigDeliveredException` is itself a `LogicException` subclass (via `PatchException`).

## A trusted-source-only argument on an optional route segment

Before the final-review fix, `?BoundedString $name = null` on `/x[/<name>]` passed silently without the segment
(`null` returned before the trust check) and was a 500 with it. Trust is now checked first: the argument is a
`LogicException` on every request (`InterceptorTest`, "optional segment absent").

## `git archive` drops the tests

`.gitattributes` export-ignores `tests/`, `phpunit.xml.dist` and the tool configs: a lowest-deps copy made with
`git archive` cannot run PHPUnit. Copy `git ls-files` instead.

## `spiral/composer-publish-plugin`

`spiral/framework` requires a Composer plugin; `allow-plugins` sets it to `false` (a library publishes nothing).
Without an explicit entry `composer update` aborts.

## `FiltersBootloader` needs `AttributesBootloader` but does not declare it

Without `Spiral\Bootloader\Attributes\AttributesBootloader` the filter injection fails with "Can't autowire
`Spiral\Attributes\ReaderInterface`", which `CoreHandler` turns into a 400. Every skeleton app has it; the test
fixture `HttpTestBootloader` lists it. `ValueObjectBootloader` does not, to leave the application's attribute
reader configuration alone.

## Enum filter errors are Spiral's, and name the enum class

`EnumCaster` is registered first (`initCasterRegistry()`, private) and `CasterRegistry::register()` only appends —
keyed by class, an overwrite keeps the position, there is no prepend or remove. Claiming enums before it would need
rebinding the `CasterRegistryInterface` singleton, which an application may own, so we do not (fix-round ruling).
Workaround documented in the README: `#[CastingErrorMessage]` on the property.

## Spiral's `DefaultCaster` leaks class names

Without this caster a value-object property gets "Unable to set value. Cannot assign string to property
App\Filter\X::$email of type App\...\EmailAddress" as its client-facing error. `EnumCaster` messages are
"Unable to set enum value. ..." followed by PHP's own `from()` message (it names the enum class).

## Composite payloads are strict about numbers

`GeoCoordinatesCodec` wants numeric `lat`/`lng`: a form-encoded array (`location[lat]=52.5`) gives strings and is
refused. Composites work from a JSON body (numbers) or a JSON text field (`location={"lat":52.5,"lng":13.4}`).

## Spiral does not bind backed enums from route parameters

`/countries/<country>` into `CountryCode $country` is a 400 without the interceptor (the resolver refuses the
string). Controller ruling (fix round 1): the interceptor converts backed enums too; an unknown case is a 404.
Pinned by `InterceptorTest::testABackedEnumParameterBecomesTheCase` / `testAnUnknownEnumCaseIsNotFound`.

## `Http::handle()` needs the `http` scope

Outside `Container::runScope(new Scope('http'), ...)` the request-scoped bindings (`InputInterface` for filters) are
missing. `TestKernel::handle()` does this, as the RoadRunner dispatcher does.

## 1.0 policy: floors and the bc-check

Raising the floor of a third-party dependency (Spiral, `spiral/validator`, PHP) needs a major; a `msstc4php/*` floor
may rise in a minor (controller ruling O10, same wording in all five bridges). 1.0 changed no `src/` signature, so the
first Roave bc-check on the 1.0.0 commit (base v0.1.0) has nothing to report. composer-local.json must alias the
sibling as `dev-main as 1.4.1` (or later): with the old `as 1.1.0` the `^1.4` floor does not resolve.
