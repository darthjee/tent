# Bump version to 1.0.0

Oak is planning around `tent:1.0.0`. Run the version-bump script. Don't edit the files it touches by hand.

```bash
scripts/bump_version.sh 1.0.0
```

It updates `README.md` (Current Version `1.0.0`, Next Release `1.0.1`), `docs/guides/how-to-use-tent.md` (Minimum version), `Makefile` (`VERSION?=1.0.0`) and `source/composer.json` (`"version": "1.0.0"`). Check the diff contains only those changes. The script uses BSD `sed -i ''`, so run it on macOS or adjust for GNU sed in the environment. After merging, tagging `1.0.0` triggers the release (not part of the PR).

## Files to Change
- `README.md`, `docs/guides/how-to-use-tent.md`, `Makefile`, `source/composer.json` — version fields only, written by `scripts/bump_version.sh 1.0.0`.
