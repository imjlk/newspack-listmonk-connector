# wp-typia Doctor Notes

This workspace tracks the published `wp-typia@0.25.0` toolchain, but the CLI
binary is not installed as a direct project dependency. Use the pinned doctor
wrapper instead of `pnpm exec wp-typia`:

```bash
pnpm run doctor:wp-typia
```

The wrapper runs:

```bash
pnpm dlx wp-typia@0.25.0 doctor --format json
```

## Toolchain Matrix

`@wp-typia/block-runtime@0.8.0` enforces a supported toolchain matrix via
`assertTypiaWebpackCompatibility`, checked on every `pnpm run build`:

- `typia` 13.x
- `ttsc` 0.23.x
- `typescript` 7.x
- `@ttsc/unplugin` 0.23.x (replaces the former `@typia/unplugin`)
- `@wordpress/scripts` 30.x with webpack 5.x

A mismatch in any of these raises before webpack runs. The matrix is satisfied
by the pinned `package.json` devDependencies; do not silently downgrade any of
them.

## Known Limitation: lint:js on TypeScript 7

TypeScript 7 removed the `require('typescript')` main entry point — it now
returns only `{ version, versionMajorMinor }`, and the compiler API is exposed
only through the `./unstable/sync`, `./unstable/async`, and `./unstable/ast`
subpaths. `@wordpress/scripts@30` resolves `@typescript-eslint@6.21.0`, whose
`ts-api-utils` dependency reads `ts.TypeFlags.Intrinsic` off the legacy main
entry, so the `@typescript-eslint` plugin fails to load and `lint:js` aborts
before any file is checked.

No released `@typescript-eslint` (latest 8.65.0, canary included) targets the
TS 7 entry points yet, and the failure is structural rather than a version pin
that a pnpm override can fix (verified: forcing `ts-api-utils@2.5.0` does not
help). Until `@typescript-eslint/typescript-estree` migrates to the TS 7
`./unstable/*` API, `lint:js` cannot run on this toolchain.

Mitigation in place:

- `pnpm run lint` runs `lint:css` only; `lint:js` is kept as a standalone
  script (`pnpm run lint:js`) that will start passing again once the upstream
  eslint stack supports TS 7.
- Correctness for TypeScript source is enforced by `pnpm run typecheck`
  (`sync --check` + `tsc --noEmit`, the same compiler the linter would use)
  and `pnpm run build`. Both are green on TS 7.

Restore `lint:js` by reverting `package.json` `lint` to
`pnpm run lint:js && pnpm run lint:css` once `@typescript-eslint` ships TS 7
support.

## Expected Local Result

On a machine without Bun available to `wp-typia`, upstream doctor returns exit
code `1` because the environment readiness check reports:

```text
Bun: Not available
```

That is expected for this repository as long as the Node fallback runtime runs
and all workspace diagnostics pass. The wrapper treats this exact Bun-only
readiness failure as documented and exits successfully.

The following checks must still pass:

- package metadata
- workspace inventory
- REST resource bootstrap
- all configured REST resource references
- Node, git, current directory, and temp directory readiness

Any non-Bun failure remains a real failure. Install Bun 1.3.11+ if you need the
full Bunli/OpenTUI runtime or Bun-only wp-typia commands such as `skills`,
`completions`, or `mcp`.
