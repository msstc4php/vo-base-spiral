# Conventions

## Tests

- `tests/Unit` — each class with real Spiral objects but no kernel; this is what Infection runs (`--testsuite=unit`).
- `tests/Integration` — a booted `Tests\Fixture\Kernel\TestKernel` (`TestKernel::boot([...bootloaders])` returns the
  container). Its exception handler rethrows boot failures; the default one prints to STDERR and `run()` returns null.
