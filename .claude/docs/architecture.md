# Architecture

Areas under `src/`, one deptrac layer each (the core and Spiral are not layers, so every area may use them):

| Area | Class | Depends on |
|---|---|---|
| `Bootloader/` | `ValueObjectBootloader` | Filter, Validation |
| `Filter/` | `ValueObjectCaster` (`CasterInterface`), registered by the bootloader | Internal |
| `Interceptor/` | `ValueObjectArgumentsInterceptor` (`InterceptorInterface`), registered by the application | Internal |
| `Validation/` | `ValueObjectChecker` (`spiral/validator` `AbstractChecker`), alias `vo`, registered by the bootloader when the validator config exists | Internal |
| `Internal/` | `TypeLabel` (`@internal`, private constructor): "email address" for `EmailAddress` | — |

## Public surface (1.0)

Frozen by the README "Backward compatibility" section: the four classes (three with a constructor), `CHECKER_ALIAS`,
the rule `vo:valid`, the checker's `MESSAGES` key `[[The value is not a valid {type}.]]` with `{type}` and
`ALLOW_EMPTY_VALUES`. `ValueObjectCaster` stays public on purpose (owner ruling O5: an application may build its own
caster registry); only `Internal\TypeLabel` is `@internal`. Message wording other than that key is not covered.

## The mapper

A container singleton bound by the bootloader (`SINGLETONS`, i.e. in the init phase). The caster is built in `boot()`
with the container's mapper; the interceptor and the checker are autowired with it. An application rebinds it in a
bootloader listed **after** `ValueObjectBootloader` (pinned by
`BootloaderTest::testAMapperReboundByALaterBootloaderReachesTheCasterAndTheChecker`); listed before, ours overwrites it.
A mapper built from a `ProvidesCodecs` set reaches all three components the same way
(`testAMapperBuiltFromAProvidesCodecsSetInALaterBootloaderReachesEveryComponent`; the interceptor is probed with a
`QueryParameter` argument, left alone by the default mapper and unbindable once a codec makes it a composite).

An abstract `ResolvesConcreteClass` type (vo-base >= 1.4, e.g. vo-documents' MRZ) needs no code here: the mapper's
`supports()`/`isScalarBacked()` accept it and `from()` picks the subclass; `class_exists()` is true for abstract classes.

## Trust and input paths

All three input paths call `fromUntrustedPayload()`. Trusted-source-only classes (`BoundedString`): the caster claims
and refuses them (a field error), the interceptor and the checker treat them as a programmer error (`LogicException`).
Composites: caster and checker decode JSON text; the interceptor refuses them as argument types.

Why the caster gives a 422 and not a `LogicException` (final-review ruling, as the spec says "claimed and refused as a
field error"): a filter is the request body's contract, so a refused field is reported like any other invalid field,
next to the other fields' errors, and the Symfony bundle's normalizer does the same. A route argument or a `vo:valid`
rule naming `BoundedString` can never succeed for any request, so it is a configuration mistake, surfaced on the first
request. All three check trust before `null`/`''` shortcuts, so no input reaches a `BoundedString`.
