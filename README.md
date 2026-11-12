# msstc4php/vo-base-spiral

[Spiral Framework](https://spiral.dev) 3.x integration for [`msstc4php/vo-base`][core]: request input becomes value
objects the way Spiral already does it, and invalid input is a field error or a 404 — never a 500.

| Need | What this package gives |
|---|---|
| Filter properties typed `EmailAddress`, `Port`, `GeoCoordinates`, ... | a filter caster: filled from input, invalid → 422 with the field |
| Route parameters into typed action arguments (`/users/<email>` → `EmailAddress $email`) | an interceptor: invalid → 404 |
| Validation rules on raw input | the `spiral/validator` rule `['vo:valid', EmailAddress::class]` |
| Storage in Cycle ORM entities | not here: [`msstc4php/vo-base-cycle`][cycle] |

Every conversion goes through the core's `ValueObjectMapper::fromUntrustedPayload()`, the same path the Symfony bundle
and the other bridges use. Client-facing messages name the value object's label ("email address"), never a PHP class.

## Install

Neither this package nor the core is on Packagist yet, so register both GitHub repositories:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/msstc4php/vo-base" },
        { "type": "vcs", "url": "https://github.com/msstc4php/vo-base-spiral" }
    ],
    "require": {
        "msstc4php/vo-base-spiral": "^0.1"
    }
}
```

Requires PHP >= 8.4 and `spiral/framework` ^3.18. `spiral/validator` ^1.8 is optional (the `vo:valid` rule).

Spiral has no package auto-discovery: add the bootloader to your kernel, after `ValidatorBootloader` if you use it:

```php
use MSSTC4PHP\ValueObject\Spiral\Bootloader\ValueObjectBootloader;

final class Kernel extends \Spiral\Framework\Kernel
{
    protected function defineBootloaders(): array
    {
        return [
            // ... the framework bootloaders, AttributesBootloader among them (filters need it)
            \Spiral\Validator\Bootloader\ValidatorBootloader::class,
            ValueObjectBootloader::class,
            // ... your application bootloaders
        ];
    }
}
```

`ValueObjectBootloader` loads `FiltersBootloader`, binds `ValueObjectMapper` as a singleton, registers the filter
caster and — when `ValidatorBootloader` is loaded in the same list — the `vo` checker.

### Custom codecs

A composite of your own needs a `PayloadCodec` (see the core). Rebind the mapper in a bootloader listed **after**
`ValueObjectBootloader`; Spiral runs every bootloader's bindings before any `boot()`, so the caster, the interceptor
and the checker all get yours:

```php
final class AppBootloader extends Bootloader
{
    public function defineSingletons(): array
    {
        return [ValueObjectMapper::class => static fn (): ValueObjectMapper => new ValueObjectMapper([new MoneyCodec()])];
    }
}
```

## Filters

Type the property with the value object; nothing else to configure:

```php
use MSSTC4PHP\ValueObject\Geo\GeoCoordinates;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Network\Port;
use Spiral\Filters\Attribute\Input\Post;
use Spiral\Filters\Model\Filter;

final class SignupFilter extends Filter
{
    #[Post]
    public EmailAddress $email;

    #[Post]
    public ?Port $port = null;

    #[Post]
    public ?GeoCoordinates $location = null;
}

final class SignupController
{
    public function signup(SignupFilter $filter): array
    {
        return ['email' => (string) $filter->email];
    }
}
```

With Spiral's `ValidationHandlerMiddleware` in the pipeline, `{"email": "nope", "port": "70000"}` answers:

```
422 {"errors": {"email": "The value is not a valid email address.", "port": "The value is not a valid port."}}
```

- Scalar-backed value objects read a string or number (`"8080"` for a `Port`); an array where a string is expected is a
  field error.
- A composite reads an array (a JSON body) or JSON text (a form field: `location={"lat":52.52,"lng":13.405}`). A
  form-encoded array (`location[lat]=52.52`) carries strings and is refused by the core's codec.
- `null` or a missing key leaves the property at its default — give a nullable property `= null`. `''` makes a
  nullable property `null` and is invalid for a non-nullable one. An instance of the class is kept as it is.
- `BoundedString` is always refused ("A bounded string cannot be read from request input."): its payload would let
  the client choose the bounds. Type the property `string` and validate the length instead, or use `NonEmptyString`.
- Backed enums (`CountryCode`, ...) stay with Spiral's own `EnumCaster`.
- `#[CastingErrorMessage('...')]` on a property replaces the message, as for any Spiral caster.

## Route arguments

Register `ValueObjectArgumentsInterceptor`, and a route parameter becomes the value object the action argument is
typed with:

```php
final class UserController
{
    public function show(EmailAddress $email): array { /* ... */ }
}

$router->setRoute('user', new Route('/users/<email>', new Action(UserController::class, 'show')));
```

`/users/jane@example.com` calls `show(EmailAddress::from('jane@example.com'))`; `/users/nope` is a 404.

Globally, through the domain core of your application:

```php
use MSSTC4PHP\ValueObject\Spiral\Interceptor\ValueObjectArgumentsInterceptor;
use Spiral\Bootloader\DomainBootloader;
use Spiral\Core\CoreInterface;

final class AppBootloader extends DomainBootloader
{
    protected const SINGLETONS = [CoreInterface::class => [self::class, 'domainCore']];

    protected const INTERCEPTORS = [
        ValueObjectArgumentsInterceptor::class,
        // ... your other interceptors
    ];
}
```

Or for one route, with its own pipeline:

```php
use Spiral\Interceptors\Handler\AutowireHandler;
use Spiral\Interceptors\PipelineBuilder;

$pipeline = new PipelineBuilder()
    ->withInterceptors($container->get(ValueObjectArgumentsInterceptor::class))
    ->build(new AutowireHandler($container));

$router->setRoute('user', new Route('/users/<email>', new Action(UserController::class, 'show')->withHandler($pipeline)));
// or with the routing configurator: $routes->add('user', '/users/<email>')->action(...)->core($pipeline);
```

- Arguments bind by name, exactly as Spiral binds route parameters. Only scalar-backed value objects: a route
  parameter is one string. An argument typed with a composite (`GeoCoordinates`) or a trusted-source-only class
  (`BoundedString`) is a `LogicException` naming the argument — a programmer error, found on the first request.
- `null` (an absent optional segment, `/users[/<email>]`) stays `null`, so `?EmailAddress $email = null` works; `''`
  is `null` for a nullable argument and a 404 otherwise. An argument that already is an instance passes through.
- Union types (`EmailAddress|string $id`), builtins and backed enums are left to Spiral. Spiral itself does not
  convert a string into a backed enum argument (it answers 400).

## Validation rule

With `spiral/validator`, `vo:valid` validates raw input against a value object without building it:

```php
use Spiral\Filters\Model\FilterDefinitionInterface;
use Spiral\Filters\Model\HasFilterDefinition;
use Spiral\Validator\FilterDefinition;

final class ContactFilter extends Filter implements HasFilterDefinition
{
    #[Post]
    public string $email = '';

    public function filterDefinition(): FilterDefinitionInterface
    {
        return new FilterDefinition([
            'email' => ['required', ['vo:valid', EmailAddress::class]],
        ]);
    }
}
```

- `null` and `''` pass: emptiness is `required`'s job. A valid payload or an instance passes; anything else is
  "The value is not a valid email address." (or the rule's own `'error' => '...'`).
- Composites accept an array or JSON text, as the caster does.
- A rule for `BoundedString` or a class the mapper cannot read is a `LogicException`, even when the field is absent.

## Storage

Persisting value objects in Cycle ORM entities is [`msstc4php/vo-base-cycle`][cycle]'s job; Spiral's container injects
the same bound `ValueObjectMapper` into its typecast handler.

## Development

```
COMPOSER=composer-local.json composer update --ignore-platform-req=php   # against ../vo-base
make check   # php -l, PHPStan level max, cs-fixer, composer validate/audit, Rector, deptrac
make test    # PHPUnit: unit suite plus a booted Spiral kernel with a real HTTP stack
make infection
```

[core]: https://github.com/msstc4php/vo-base
[cycle]: https://github.com/msstc4php/vo-base-cycle
