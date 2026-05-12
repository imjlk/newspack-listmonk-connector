# WordPress.org Review Checklist

Use this checklist before submitting a release to WordPress.org.

## Automated Checks

```bash
pnpm run review:plugin-check
pnpm run release:wporg
```

`review:plugin-check` starts wp-env, installs or activates the official Plugin
Check plugin, and runs:

```bash
wp plugin check newspack-listmonk-connector --require=./wp-content/plugins/plugin-check/cli.php --mode=new
```

The submission target is no Plugin Repo category errors. Warnings should either
be fixed or documented below before submission.

## Current Notes

- The plugin declares `Requires Plugins: newspack-newsletters`.
- The WordPress.org source package includes `src/`, build scripts, `package.json`,
  `pnpm-lock.yaml`, `webpack.config.js`, and `tsconfig.json` so built assets can
  be traced back to human-readable source.
- External Listmonk data flow is documented in `readme.txt` and `docs/PRIVACY.md`.
- Uninstall removes local credential settings and sync-error transients only.
  Remote Listmonk data and newsletter post meta are intentionally preserved.

## Warnings To Review

- None recorded yet.
