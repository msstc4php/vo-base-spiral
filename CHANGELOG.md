# Changelog

## 0.1.0 - 2026-11-12

First release. New integration, so no stability promise before 1.0.

- `Bootloader\ValueObjectBootloader`: binds `ValueObjectMapper` as a container singleton and loads `FiltersBootloader`.
- `Filter\ValueObjectCaster`: a filter property typed with a value object is filled from request input; invalid input
  is that property's error (422), named by the type label ("email address"), never the class. Composites come as JSON
  text or an array; trusted-source-only classes (`BoundedString`) are always refused; backed enums stay with Spiral's
  `EnumCaster`.
- `Interceptor\ValueObjectArgumentsInterceptor`: a route parameter becomes the scalar-backed value object the action
  argument is typed with (`/users/<email>` into `EmailAddress $email`); invalid → 404. A composite or trusted-source-only
  argument type is a `LogicException`. Registered by the application, globally (domain core) or per route.
- `Validation\ValueObjectChecker`: the `spiral/validator` rule `['vo:valid', EmailAddress::class]`; `null` and `''`
  pass, a trusted-source-only or unsupported class is a `LogicException`. Registered under `vo` when
  `ValidatorBootloader` is loaded.
- Requires `msstc4php/vo-base` ^1.1, `spiral/framework` ^3.18, PHP >= 8.4; `spiral/validator` ^1.8 optional.
