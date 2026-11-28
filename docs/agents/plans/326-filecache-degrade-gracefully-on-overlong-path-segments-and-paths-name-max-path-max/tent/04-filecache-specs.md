# FileCache specs

New `FileCacheOverlongPathTest`, modelled on `FileCacheUnsafePathTest`: temp root, `CapturingLoggerInstance`, a `listTree()` before/after comparison, and `PhpWarningCatcher` started in `setUp()` and stopped in `tearDown()`. Every test asserts that `warnings()` is empty.

**Data provider (overlong cases)**
- a 256-byte segment;
- an overlong method (256 bytes);
- an overlong full path: several 200-byte segments, sized against `strlen($this->cacheDir)` so the longest derived path is 4096 bytes;
- an overlong leaf name: a custom `RequestHasher` (see `tests/support/cache/DummyRequestHasher.php`) returning a 225-char hash.

For each case:
- `exists()` is false, `timestamp()` is null, and `content()` returns `''`;
- `store()` leaves the tree unchanged;
- `remove()` does nothing (a planted file at the target survives);
- exactly one `warn` is logged, ending in `reason: path too long`.

**Boundaries (stored and retrievable)**
- a 255-byte segment;
- a longest derived path of exactly 4095 bytes;
- a 224-char hash (the longest leaf, `.meta.json` + 21, is then 255).

**Check order**: a path with both a `..` segment and a 300-byte segment logs one `warn`, with reason `unsafe path`.

**Truncation**: for a 3000-byte path, the warn contains the first 200 bytes followed by `… (<N> bytes)` (with the full length), and does not contain the full path.

**mkdir backstop**: a regular file is created where the entry directory (or one of its ancestors) must go. `store()` then:
- raises no PHP warning;
- logs one `cache store skipped — …` warn carrying the OS reason;
- writes nothing, and `exists()` stays false.

This setup works even when the tests run as root, unlike permission-based setups.

## Files to Change
- `source/tests/unit/lib/models/FileCache/FileCacheOverlongPathTest.php` — new spec (overlong cases, boundaries, check order, truncation).
- `source/tests/unit/lib/models/FileCache/FileCacheMkdirFailureTest.php` — new spec for the backstop (or a section of the file above, if it reads better).
