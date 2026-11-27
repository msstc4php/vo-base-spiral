# Changelog

## [1.0.0] - unreleased

First stable release: from here on a breaking change needs 2.0. What 1.x covers is listed in the README,
"Backward compatibility": the four classes, the constructors of the three that have one, with their parameter names, the `vo` alias and the
`vo:valid` rule, the rule's translation key and `{type}` placeholder, the input forms, and which exception each path
throws. Not covered: message wording otherwise and anything `@internal`. `spiral/validator` below 1.8 now conflicts
(it was never supported).

### Changed

- Requires `msstc4php/vo-base` ^1.4 (was ^1.1): an abstract class implementing `ResolvesConcreteClass` is a valid
  filter property, route argument and `vo:valid` class, and a mapper built from a `ProvidesCodecs` set
  (`new ValueObjectMapper(Codecs::all())`) in a later bootloader reaches the caster, the interceptor and the checker;
  both are tested.
- `Filter\ValueObjectCaster` stays public and is covered by 1.x, so that an application may register it in a caster
  registry of its own.

### Upgrading from 0.x

- No code change: the classes, constructors, alias, rule and messages are those of 0.1.
- Update `msstc4php/vo-base` to ^1.4 if it is pinned lower.
- Enum filter errors are still Spiral's `EnumCaster` text, which names the enum class; put
  `#[CastingErrorMessage('...')]` on an enum property whose errors reach clients.

## 0.1.0 - 2026-11-12

First release. New integration, so no stability promise before 1.0.

- `Bootloader\ValueObjectBootloader`: binds `ValueObjectMapper` as a container singleton and loads `FiltersBootloader`.
- `Filter\ValueObjectCaster`: a filter property typed with a value object is filled from request input; invalid input
  is that property's error (422), named by the type label ("email address"), never the class. Composites come as JSON
  text or an array; trusted-source-only classes (`BoundedString`) are always refused; backed enums stay with Spiral's
  `EnumCaster`.
- `Interceptor\ValueObjectArgumentsInterceptor`: a route parameter becomes the scalar-backed value object the action
  argument is typed with (`/users/<email>` into `EmailAddress $email`), and a backed enum case; invalid → 404. A
  composite or trusted-source-only argument type is a `LogicException`. Registered by the application, globally (domain
  core) or per route.
- `Validation\ValueObjectChecker`: the `spiral/validator` rule `['vo:valid', EmailAddress::class]`; `null` and `''`
  pass, a trusted-source-only or unsupported class is a `LogicException`. Registered under `vo` when
  `ValidatorBootloader` is loaded, in the same bootloader list or in the kernel's app bootloaders.
- Requires `msstc4php/vo-base` ^1.1, `spiral/framework` ^3.18, PHP >= 8.4; `spiral/validator` ^1.8 optional.
