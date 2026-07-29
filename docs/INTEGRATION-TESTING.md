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

## Smoke: Beta Zip On Staging

The staging smoke builds a fresh beta zip, verifies the connector and Newspack
Newsletters are installed/active on the staging site, saves Listmonk settings,
and runs draft sync, optional test send, publish, schedule, and archive checks
through authenticated REST requests.

Set the required environment variables in your shell or in an ignored
`.staging.env` file:

```bash
STAGING_WP_URL=https://staging.example.com
STAGING_WP_USER=admin
STAGING_WP_APPLICATION_PASSWORD="xxxx xxxx xxxx xxxx xxxx xxxx"
STAGING_LISTMONK_BASE_URL=https://listmonk-staging.example.com
STAGING_LISTMONK_API_USER=api_user
STAGING_LISTMONK_API_TOKEN=replace-me
STAGING_LISTMONK_DEFAULT_LIST_IDS=1
STAGING_LISTMONK_FROM_EMAIL="Newsroom <news@example.com>"
STAGING_LISTMONK_TEMPLATE_ID=0
STAGING_SMOKE_TEST_EMAIL=smoke@example.com
```

Then run:

```bash
pnpm run smoke:staging:zip
```

The script uses WordPress Application Passwords for REST authentication. Core
WordPress REST can activate an already-installed plugin and can install
WordPress.org plugin slugs, but it does not upload arbitrary beta plugin zips.
Upload `artifacts/newspack-listmonk-connector-0.1.0.zip` through WP Admin or
install it with WP-CLI before running the smoke.

If the Newspack provider option is registered in REST, the script sets
`newspack_newsletters_service_provider=listmonk` automatically. If staging does
not expose that option through `/wp/v2/settings`, set it once with:

```bash
wp option update newspack_newsletters_service_provider listmonk
```

The test send step is skipped unless `STAGING_SMOKE_TEST_EMAIL` is set. Publish
and schedule use the configured staging Listmonk list IDs, so use only
staging-safe lists.

When the test email already exists in Listmonk, the smoke adds any missing
configured staging list memberships while preserving its other memberships. It
refuses to change a blocklisted subscriber or an existing unconfirmed or
unsubscribed staging-list membership.

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

Start a disposable local Listmonk + Postgres + Mailpit stack:

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
- Mailpit SMTP: `localhost:1125`
- Mailpit UI: `http://localhost:8026`

The start helper configures the local Listmonk SMTP messenger to deliver into
the dedicated Mailpit instance. No external SMTP credentials are required for
local send tests.

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

Run the generic bounce webhook and connector reflection smoke:

```bash
pnpm run smoke:listmonk:bounces:local
```

Run the browser E2E for the Newspack editor Listmonk panel:

```bash
pnpm run e2e:editor:local
```

Run the browser E2E for publish and schedule transitions from the editor:

```bash
pnpm run e2e:publish-schedule:local
```

Run the browser E2E for the React settings screen:

```bash
pnpm run e2e:settings:local
```

Run the browser visual regression fixture for representative rendered newsletter
blocks:

```bash
pnpm run e2e:visual:local
```

The editor E2E builds the editor bundle, starts local Listmonk, starts/reuses
wp-env, creates a draft newsletter fixture, and verifies list selection, raw
HTML preview, payload preview, analytics empty/loaded states, sync, and test
send in Chromium.

The publish/schedule E2E builds the editor bundle, starts local Listmonk,
starts/reuses wp-env, creates two draft newsletter fixtures, saves one as an
immediate publish and one with a future date through the editor data store, then
polls WordPress and Listmonk until campaign status and post meta match
`running` and `scheduled`.

The settings E2E builds the admin bundle, starts local Listmonk, starts/reuses
wp-env, opens `Settings > Newspack Listmonk`, verifies REST hydration, saves
without replacing the stored token, tests the Listmonk connection, and confirms
saved values persist after reload.

The visual E2E builds the plugin assets, starts/reuses wp-env, renders a stable
newsletter fixture with representative blocks through the raw HTML builder, and
compares the Chromium screenshot against the committed baseline.

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
- the scheduled fixture campaign is restored to `draft` after verification.
- WordPress `_wtnl_listmonk_last_status` matches the Listmonk campaign status.
- the dedicated Mailpit instance captures at least one message from the run.

The archive smoke is also local-only. It verifies that draft campaigns are
preserved remotely but detached locally, scheduled campaigns are reverted to
draft, and running campaigns are preserved with a preserved-running archive
policy.

The subscriber smoke is local-only as well. It verifies that the Newspack
provider can create a Listmonk subscriber, retrieve subscriber data, read list
memberships, add a secondary list membership, and remove that secondary
membership without touching an external Listmonk server. It also creates or
reuses a local double opt-in list and verifies that new and existing subscribers
enter that list with `unconfirmed` membership status. Finally, it blocklists the
fixture subscriber and verifies that provider contact/list updates refuse to
resubscribe it.

The bounce smoke is local-only and does not require an SMTP provider. It
temporarily enables Listmonk's generic bounce webhook with a one-hard-bounce
blocklist policy, creates a subscriber through the Newspack provider, posts a
hard-bounce event, and verifies the Listmonk bounce record and blocklist state.
It then verifies `get_contact_data()` reflects `is_blocklisted`, `bounce_count`,
`has_bounces`, and the raw bounce record before deleting the fixture subscriber
and restoring the original Listmonk bounce settings.

This deterministic generic-webhook smoke does not validate provider-specific
complaint signatures or delivery events. SES, SendGrid, Postmark, or another
supported provider still requires real provider credentials and its public
webhook configuration for that final staging check.

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
`listmonk/listmonk:latest`, Postgres, and `./listmonk --install --idempotent`,
with a pinned Mailpit instance for local SMTP capture. See the official
installation docs at <https://listmonk.app/docs/installation/> and bounce docs
at <https://listmonk.app/docs/bounces/>.

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
