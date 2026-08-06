# Sampo Changesets

This repository uses Sampo to prepare SemVer releases for the WordPress plugin.
The Composer package is the version source. The private npm package is
synchronized for build tooling and is never published to npm.

Add a user-facing changeset for each releasable pull request:

```bash
pnpm run changeset:add
```

Use the canonical package id
`packagist/imjlk/wp-typia-newsletter-connector`. Pending changesets live
in `.sampo/changesets/` and are consumed by the release preparation workflow.

See `docs/RELEASING.md` for the complete WordPress release flow.
