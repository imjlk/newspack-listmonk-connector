# wp-typia Doctor Notes

This workspace was scaffolded with the published `wp-typia@0.22.10` CLI, but
the CLI binary is not installed as a direct project dependency. Use the pinned
doctor wrapper instead of `pnpm exec wp-typia`:

```bash
pnpm run doctor:wp-typia
```

The wrapper runs:

```bash
pnpm dlx wp-typia@0.22.10 doctor --format json
```

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
