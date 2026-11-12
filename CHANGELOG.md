# Changelog

## 0.1.0 - unreleased

First release. New integration, so no stability promise before 1.0.

- `Bootloader\ValueObjectBootloader`: binds `ValueObjectMapper` as a container singleton and loads `FiltersBootloader`.
- `Filter\ValueObjectCaster`: a filter property typed with a value object is filled from request input; invalid input
  is that property's error (422), named by the type label ("email address"), never the class. Composites come as JSON
  text or an array; trusted-source-only classes (`BoundedString`) are always refused; backed enums stay with Spiral's
  `EnumCaster`.
