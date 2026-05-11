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

## PHPUnit Unit Tests

Run the PHP unit suite:

```bash
pnpm run test:php
```

The script starts/reuses wp-env, installs Composer dependencies inside the
plugin directory, and runs the Composer-installed PHPUnit 9 binary in the
`tests-cli` container.

It verifies:

- settings sanitization and API token masking
- display-name From email preservation
- raw HTML fallback rendering and root-relative URL rewriting
- plain text `altbody` generation
- newsletter preview payload mapping and payload hash stability
- Newspack compatibility helper fallbacks for namespace, constants, REST
  responses, and guarded provider registration
- mocked Listmonk HTTP request, response, and error normalization
- failed scheduled send error persistence and `retrySend` recovery behavior

## Build A Beta Zip

Create a staging-ready plugin zip:

```bash
pnpm run release:zip
```

The release script rebuilds assets, validates version consistency, copies only
runtime files into a staging directory, runs PHP syntax checks on the staged
package, creates `artifacts/newspack-listmonk-connector-0.1.0.zip`, and verifies
that development-only files are excluded.

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
- the provider has a `Newspack_Listmonk_Connector_Controller`.
- connector compatibility helpers resolve the current Newspack namespace, CPT,
  meta key, active provider, and provider instance.
- Newspack editor routes for retrieve, test send, and sync errors are registered.
- plugin REST routes for settings, preview, and newsletter sync are registered.
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

Run the send/schedule transition smoke against the local stack:

```bash
pnpm run smoke:listmonk:send:local
```

Run the campaign archive policy smoke against the local stack:

```bash
pnpm run smoke:listmonk:archive:local
```

Run the subscriber sync smoke against the local stack:

```bash
pnpm run smoke:listmonk:subscribers:local
```

Run the browser E2E for the Newspack editor Listmonk panel:

```bash
pnpm run e2e:editor:local
```

Run the browser E2E for the React settings screen:

```bash
pnpm run e2e:settings:local
```

The editor E2E builds the editor bundle, starts local Listmonk, starts/reuses
wp-env, creates a draft newsletter fixture, and verifies list selection, raw
HTML preview, payload preview, sync, and test send in Chromium.

The settings E2E builds the admin bundle, starts local Listmonk, starts/reuses
wp-env, opens `Settings > Newspack Listmonk`, verifies REST hydration, saves
without replacing the stored token, tests the Listmonk connection, and confirms
saved values persist after reload.

The send/schedule smoke is intentionally local-only. It reads only
`.listmonk.env` and refuses to run unless `LISTMONK_BASE_URL` points to
`host.docker.internal`, `localhost`, or `127.0.0.1`.

It verifies:

- draft sync still creates a `draft` Listmonk campaign.
- the Newspack editor retrieve route returns the expected campaign payload shape.
- local smoke fixture subscribers are created in Listmonk so the Newspack
  editor test route returns a success message.
- immediate `provider->send()` changes Listmonk status to `running`.
- scheduled `provider->send()` changes Listmonk status to `scheduled`.
- scheduled payload includes `send_at`.
- WordPress `_wtnl_listmonk_last_status` matches the Listmonk campaign status.

The archive smoke is also local-only. It verifies that draft campaigns are
preserved remotely but detached locally, scheduled campaigns are reverted to
draft, and running campaigns are preserved with a preserved-running archive
policy.

The subscriber smoke is local-only as well. It verifies that the Newspack
provider can create a Listmonk subscriber, retrieve subscriber data, read list
memberships, add a secondary list membership, and remove that secondary
membership without touching an external Listmonk server. It also blocklists the
fixture subscriber and verifies that provider contact/list updates refuse to
resubscribe it.

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
- the Newspack editor retrieve route returns the expected campaign payload shape.
- the connector editor sync route returns a typed response with the nested
  retrieve payload.
- `_wtnl_listmonk_campaign_id`, `_wtnl_listmonk_payload_hash`, and
  `_wtnl_listmonk_last_synced_at` are stored.
- the synced Listmonk campaign remains in `draft` status.

The live smoke test creates a draft campaign in Listmonk and leaves it there for
inspection.
