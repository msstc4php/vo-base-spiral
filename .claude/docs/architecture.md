# Architecture

Areas under `src/`, one deptrac layer each (the core and Spiral are not layers, so every area may use them):

| Area | Class | Depends on |
|---|---|---|
| `Bootloader/` | `ValueObjectBootloader` | Filter, Validation |
| `Filter/` | `ValueObjectCaster` (`CasterInterface`), registered by the bootloader | Internal |
| `Interceptor/` | `ValueObjectArgumentsInterceptor` (`InterceptorInterface`), registered by the application | Internal |
| `Validation/` | `ValueObjectChecker` (`spiral/validator` `AbstractChecker`), alias `vo`, registered by the bootloader when the validator config exists | Internal |
| `Internal/` | `TypeLabel` (`@internal`): "email address" for `EmailAddress` | — |

## The mapper

A container singleton bound by the bootloader (`SINGLETONS`, i.e. in the init phase). The caster is built in `boot()`
with the container's mapper; the interceptor and the checker are autowired with it. An application rebinds it in a
bootloader listed **after** `ValueObjectBootloader` (pinned by
`BootloaderTest::testAMapperReboundByALaterBootloaderReachesTheCasterAndTheChecker`); listed before, ours overwrites it.

## Trust and input paths

All three input paths call `fromUntrustedPayload()`. Trusted-source-only classes (`BoundedString`): the caster claims
and refuses them (a field error), the interceptor and the checker treat them as a programmer error (`LogicException`).
Composites: caster and checker decode JSON text; the interceptor refuses them as argument types.
