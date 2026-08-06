# Releasing

Sampo manages the release version, changelog, Git tag, and GitHub release.
`composer.json` is the version source for the WordPress plugin. `package.json`
is synchronized to the same version for JavaScript build tooling and remains
private, so it is never published to npm.

Sampo's Packagist adapter validates the Composer package and creates the short
`v<version>` tag used by GitHub and WordPress.org. Registering the package on
Packagist is optional; if it is registered later, Packagist will consume the
same VCS tags without changing this workflow.

## Add a changeset

Every user-facing change should include a Sampo changeset:

```bash
pnpm run changeset:add
```

Select
`packagist/imjlk/connector-for-newspack-newsletters-and-listmonk`, choose the
appropriate SemVer bump, and use one of the configured changelog categories.
Commit the generated file under `.sampo/changesets/` with the implementation.

## Prepare a release

When a changeset reaches `main`, the Release workflow:

1. Collects every pending changeset.
2. Runs `sampo release`.
3. Synchronizes the resulting Composer version into `package.json`, the plugin
   PHP header, the runtime version constant, and the WordPress.org `Stable tag`.
4. Updates `CHANGELOG.md` and the current `readme.txt` changelog entry.
5. Creates or refreshes the `release/main` pull request.

Review all generated version and changelog changes in that pull request. New
changesets merged while it is open will refresh the same release branch.
Sampo also refreshes `composer.lock` after updating the Composer package
version.

## Publish a release

Merging the `release/main` pull request starts publication from its merge
commit. The workflow runs type checks, lint, PHP tests, Plugin Check, and the
WordPress.org packaging validation before it:

1. Creates the `v<version>` Git tag with Sampo.
2. Creates a GitHub release and attaches the tested plugin ZIP.
3. Deploys the exact validated build directory to WordPress.org SVN when
   WordPress.org deployment is enabled.

The workflow can be retried safely. Sampo skips existing Git tags, and the 10up
deploy action skips versions already present in WordPress.org SVN.

## Enable WordPress.org deployment

Leave SVN deployment disabled until WordPress.org approves the plugin and
creates the repository for
`connector-for-newspack-newsletters-and-listmonk`. Then configure:

- Repository variable `WPORG_DEPLOY_ENABLED` with value `true`.
- Repository secret `SVN_USERNAME` with the WordPress.org account name.
- Repository secret `SVN_PASSWORD` with that account's WordPress.org password.

The workflow publishes GitHub releases without these values and reports that
the SVN step was skipped. Use the Release workflow's `publish` manual operation
to retry the current version after enabling the repository or rotating
credentials.

Use `prepare` from the same manual workflow only when the automatic release PR
needs to be refreshed.

## Local checks

Preview the next version without changing files:

```bash
sampo release --dry-run
```

Verify the current version metadata:

```bash
pnpm run release:check-version
```

Build the same package used for WordPress.org:

```bash
pnpm run release:wporg
```
