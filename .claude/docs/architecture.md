# Architecture

Areas under `src/`, one deptrac layer each (the core and Spiral are not layers, so every area may use them):

| Area | Class | Depends on |
|---|---|---|
| `Bootloader/` | `ValueObjectBootloader` | Filter, Validation |
| `Filter/` | `ValueObjectCaster` (`CasterInterface`), registered by the bootloader | Internal |
| `Interceptor/` | `ValueObjectArgumentsInterceptor` (`InterceptorInterface`), registered by the application | Internal |
| `Validation/` | `ValueObjectChecker` (`spiral/validator` `AbstractChecker`), alias `vo`, registered by the bootloader when the validator config exists | Internal |
| `Internal/` | `TypeLabel` (`@internal`): "email address" for `EmailAddress` | — |

The mapper is a container singleton bound by the bootloader (`SINGLETONS`).
