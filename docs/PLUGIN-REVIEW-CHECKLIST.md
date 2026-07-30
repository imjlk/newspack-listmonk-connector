# WordPress.org Review Checklist

Use this checklist before submitting a release to WordPress.org.

## Automated Checks

```bash
pnpm run review:plugin-check
pnpm run release:wporg
```

`review:plugin-check` starts wp-env, installs the latest official Plugin Check
plugin, and evaluates its strict JSON output so any finding fails the command:

```bash
wp plugin check connector-for-newspack-newsletters-and-listmonk --require=./wp-content/plugins/plugin-check/cli.php --mode=new --format=strict-json
```

The submission target is no findings. Any new warning must be fixed before
submission.

## Current Notes

- Plugin Check 2.0.0 completed on WordPress 7.0.2 on 2026-07-30 with no
  findings.
- The plugin header and `readme.txt` both declare `Tested up to: 7.0`.
- The plugin declares `Requires Plugins: newspack-newsletters`.
- The WordPress.org source package includes `src/`, build scripts, `package.json`,
  `pnpm-lock.yaml`, `webpack.config.js`, and `tsconfig.json` so built assets can
  be traced back to human-readable source.
- The source package copies only Git-tracked files from `docs/`, preventing
  local review drafts and assets from leaking into release artifacts.
- `.DS_Store`, `.staging.env`, `AGENTS.md`, and `CLAUDE.md` are excluded from
  the source-tree Plugin Check command because they are local or
  development-only and are not copied into either release package.
- External Listmonk data flow is documented in `readme.txt` and `docs/PRIVACY.md`.
- Uninstall removes local credential settings and sync-error transients only.
  Remote Listmonk data and newsletter post meta are intentionally preserved.

## Unresolved Findings

- None.
