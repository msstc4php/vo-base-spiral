# Architecture

Areas under `src/`, one deptrac layer each (the core and Spiral are not layers, so every area may use them):

| Area | Class | Depends on |
|---|---|---|
| `Bootloader/` | `ValueObjectBootloader` | Filter, Validation |
| `Filter/` | `ValueObjectCaster` (`CasterInterface`), registered by the bootloader | Internal |
| `Internal/` | `TypeLabel` (`@internal`): "email address" for `EmailAddress` | — |

The mapper is a container singleton bound by the bootloader (`SINGLETONS`).
