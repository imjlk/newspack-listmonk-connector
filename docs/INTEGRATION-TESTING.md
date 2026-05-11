# Integration Testing

This project includes wp-env smoke checks for the Newspack provider contract and
live Listmonk campaign sync. The live Listmonk target can be either a real
server supplied through environment variables or a local Docker Compose stack.

## Start WordPress

```bash
pnpm run env:start
```

The wp-env setup installs:

- this plugin
- Newspack Newsletters from WordPress.org stable ZIP

The wp-env config uses the direct stable ZIP URL
`https://downloads.wordpress.org/plugin/newspack-newsletters.zip` so the local
plugin slug matches the normal WordPress.org slug, `newspack-newsletters`.

Useful commands:

```bash
pnpm run env:cli -- plugin list
pnpm run env:cli -- option get newspack_newsletters_service_provider
pnpm run env:stop
pnpm run env:destroy
```

## Smoke: Newspack Provider Contract

This smoke test does not require real Listmonk credentials.

```bash
pnpm run smoke:newspack
```

It verifies:

- Newspack Newsletters and this plugin activate.
- `listmonk` is registered in Newspack providers.
- `listmonk` can be selected as the active provider.
- the provider instance has the expected class.
- plugin REST routes are registered.
- missing Listmonk credentials return a `WP_Error`, not a fatal.

## Smoke: Live Listmonk Sync

### Option A: Local Docker Listmonk

Start a disposable local Listmonk + Postgres stack:

```bash
pnpm run listmonk:start
```

This uses `docker-compose.listmonk.yml`, creates a local super admin user, and
creates a local API user named `wp_typia_smoke` on first install. Listmonk
prints the generated API token during installation; the helper captures it and
writes `.listmonk.env` for the smoke test.

Local defaults:

- browser URL: `http://localhost:9000`
- admin login: `admin` / `listmonk123`
- API user: `wp_typia_smoke`
- API URL for wp-env: `http://host.docker.internal:9000`
- default smoke list ID: `1`

Run the live smoke against the local stack:

```bash
pnpm run smoke:listmonk:local
```

Useful local Listmonk commands:

```bash
pnpm run listmonk:logs
pnpm run listmonk:env
pnpm run listmonk:stop
pnpm run listmonk:destroy
```

Use `listmonk:destroy` when you want a clean database and a new generated API
token. The generated `.listmonk.env` file is ignored by git.

The compose file follows Listmonk's documented Docker flow:
`listmonk/listmonk:latest`, Postgres, and `./listmonk --install --idempotent`.
See the official installation docs at <https://listmonk.app/docs/installation/>.

### Option B: Existing Listmonk Server

Copy `.env.example` to `.env` or export the variables in your shell:

```bash
LISTMONK_BASE_URL=https://listmonk.example.com
LISTMONK_API_USER=api_user
LISTMONK_API_TOKEN=replace-me
LISTMONK_DEFAULT_LIST_IDS=1
LISTMONK_FROM_EMAIL=Newsroom <news@example.com>
```

Then run:

```bash
pnpm run smoke:listmonk
```

It verifies:

- Listmonk credentials are saved into WordPress options.
- `test_connection()` succeeds.
- `get_lists()` returns at least one active list.
- a `newspack_nl_cpt` draft post can sync into a Listmonk draft campaign.
- `_wtnl_listmonk_campaign_id`, `_wtnl_listmonk_payload_hash`, and
  `_wtnl_listmonk_last_synced_at` are stored.
- the synced Listmonk campaign remains in `draft` status.

The live smoke test creates a draft campaign in Listmonk and leaves it there for
inspection.
