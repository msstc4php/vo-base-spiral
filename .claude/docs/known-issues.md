# Known issues

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
