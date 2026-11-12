# Known issues

## Bootloader order decides who owns the mapper binding

`SINGLETONS` of a later bootloader overwrite earlier ones (no error). An application binding of `ValueObjectMapper`
listed before `ValueObjectBootloader` is silently replaced by the plain mapper. The README says "after".

## The `vo` checker needs ValidatorBootloader in the same bootloader list

Registration checks `ConfiguratorInterface::exists('validator')` in `boot()`. ValidatorBootloader in a later
`bootload()` call (the kernel's `APP` list while ours is in `defineBootloaders()`) is not yet initialized then, so
`vo` is not registered and the rule fails with "Undefined checker `vo`". List both in the same method.

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

## Spiral's `DefaultCaster` leaks class names

Without this caster a value-object property gets "Unable to set value. Cannot assign string to property
App\Filter\X::$email of type App\...\EmailAddress" as its client-facing error. `EnumCaster` messages are
"Unable to set enum value. ..." followed by PHP's own `from()` message (it names the enum class).

## Composite payloads are strict about numbers

`GeoCoordinatesCodec` wants numeric `lat`/`lng`: a form-encoded array (`location[lat]=52.5`) gives strings and is
refused. Composites work from a JSON body (numbers) or a JSON text field (`location={"lat":52.5,"lng":13.4}`).

## Spiral does not bind backed enums from route parameters

`/countries/<country>` into `CountryCode $country` is a 400 (the resolver refuses the string). The interceptor leaves
enums alone as the plan says (filters convert enums through `EnumCaster`; actions do not). Pinned by
`InterceptorTest::testAnEnumParameterIsLeftToSpiral`.

## `Http::handle()` needs the `http` scope

Outside `Container::runScope(new Scope('http'), ...)` the request-scoped bindings (`InputInterface` for filters) are
missing. `TestKernel::handle()` does this, as the RoadRunner dispatcher does.
