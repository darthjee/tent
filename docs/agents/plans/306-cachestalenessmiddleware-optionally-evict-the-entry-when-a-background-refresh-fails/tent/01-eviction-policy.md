# EvictionPolicy and loader
Create `Tent\Content\EvictionPolicy` (constructor taking `array $codes = []`, `static build(?array $codes, array $default = [])`, `evicts(int $httpCode): bool` delegating to `HttpCodeMatcher`, `isEmpty()`). Add build-time validation: non-array or invalid entries throw `\InvalidArgumentException`; `[]` is valid. Register it in `loader.php` after the `HttpCodeMatcher` require (dependency-first order).

## Files to Change
- `source/source/lib/content/EvictionPolicy.php` — new class.
- `source/source/loader.php` — `require_once __DIR__ . '/lib/content/EvictionPolicy.php';` after `lib/utils/HttpCodeMatcher.php`.
- `source/tests/unit/lib/content/EvictionPolicyTest.php` — new: empty never evicts, exact int, wildcard family, `build` default vs explicit empty list, `isEmpty`, invalid entries throw.
