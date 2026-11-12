# Conventions

## Messages

Client-facing texts name the type label (`Internal\TypeLabel::of()`, the Laravel bridge's algorithm), never a class:
"The value is not a valid email address.", "A bounded string cannot be read from request input." The core's
exception stays as the previous exception for logs.

## Null and `''`

`null` never reaches the caster (Spiral skips it). `''` on a nullable property is `null` (an empty form field);
`''` on a non-nullable one is invalid. An instance of the declared class passes through.

## Unbindable route arguments

`LogicException` naming `$arg` and `Class::method()` with the reason; checked only when the argument is present and
not already an instance (an absent one is Spiral's to resolve). Trust is checked before compositeness, so
`BoundedString` gets the "carries its own constraints" reason.

## Tests

- `tests/Unit` — each class with real Spiral objects but no kernel; this is what Infection runs (`--testsuite=unit`).
- `tests/Integration` — a booted `Tests\Fixture\Kernel\TestKernel` (`TestKernel::boot([...bootloaders])` returns the
  container). Its exception handler rethrows boot failures; the default one prints to STDERR and `run()` returns null.
- HTTP tests go through `Http::handle()` in the `http` scope with `ErrorHandlerMiddleware` and
  `ValidationHandlerMiddleware` (`Tests\Fixture\Http\HttpTestBootloader`), so status codes are the ones an
  application answers. Each test boots a fresh kernel (~5 ms).
- Infection's initial coverage run covers the integration suite too; mutants are killed by the unit suite, so every
  branch of `src/` has a unit test.
