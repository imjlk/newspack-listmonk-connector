# Newspack Listmonk Connector

Newspack Newsletters ESP provider for Listmonk campaign delivery.

This plugin was scaffolded with the published `wp-typia` CLI and keeps the
settings, preview, editor sync, and analytics REST contracts in TypeScript:

- `src/types.ts`
- `src/rest/listmonk-settings/api-types.ts`
- `src/rest/newsletter-preview/api-types.ts`
- `src/rest/newsletter-sync/api-types.ts`
- `src/rest/campaign-analytics/api-types.ts`

## MVP Scope

- Registers `listmonk` through `newspack_newsletters_registered_providers`.
- Stores Listmonk API URL, API user, token, default From email, template ID,
  and default list IDs.
- Exposes typed REST resources for settings and raw newsletter preview.
- Exposes typed campaign analytics reads for synced Listmonk campaigns.
- Adds a Newspack editor side panel for list selection, campaign status, raw
  HTML/payload preview, sync, and test send.
- Renders Newspack newsletter HTML through `Newspack_Newsletters_Renderer`
  when available, with a WordPress content fallback.
- Creates or updates Listmonk draft campaigns with `content_type: "html"`.
- Sends Listmonk test campaigns through `/api/campaigns/{id}/test`.
- Starts or schedules campaigns with `/api/campaigns/{id}/status`.
- Syncs Newspack contacts into Listmonk subscribers and list memberships while
  preserving Listmonk double opt-in, blocklist, and bounce state.
- Stores Listmonk campaign ID, UUID, payload hash, last sync time, last status,
  and last error in newsletter post meta.

The connector intentionally does not expose a WordPress webhook receiver.
Configure bounce and complaint webhooks in Listmonk, then let the connector read
the resulting bounce/blocklist state through the Listmonk API.

Track phase-by-phase progress in [docs/BACKLOG.md](docs/BACKLOG.md).

## Settings

Admin screen:

```text
Settings > Newspack Listmonk
```

Optional constants can override stored credentials:

```php
define( 'NEWSPACK_LISTMONK_CONNECTOR_BASE_URL', 'https://listmonk.example.com' );
define( 'NEWSPACK_LISTMONK_CONNECTOR_API_USER', 'api_user' );
define( 'NEWSPACK_LISTMONK_CONNECTOR_API_TOKEN', 'token' );
```

## REST Resources

Namespace:

```text
newspack-listmonk-connector/v1
```

Routes:

- `GET /listmonk-settings/item`
- `POST /listmonk-settings`
- `GET /newsletter-preview/item?postId=123`
- `POST /newsletter-preview`
- `POST /newsletter-sync`
- `GET /campaign-analytics/item?postId=123&from=2026-05-01&to=2026-05-12`

The connector also registers Newspack editor provider routes under
`newspack-newsletters/v1`:

- `GET /listmonk/{post_id}/retrieve`
- `POST /listmonk/{post_id}/test`

## Development

```bash
pnpm run sync
pnpm run test:php
pnpm run typecheck
pnpm run lint
pnpm run build
pnpm run release:zip
```

The beta zip is written to
`artifacts/newspack-listmonk-connector-0.1.0.zip`. See
[docs/SETUP.md](docs/SETUP.md) and
[docs/STAGING-CHECKLIST.md](docs/STAGING-CHECKLIST.md) for staging validation.
Webhook direction and bounce ownership are documented in
[docs/WEBHOOK-POLICY.md](docs/WEBHOOK-POLICY.md).

## Integration Smoke Tests

```bash
pnpm run env:start
pnpm run smoke:newspack
pnpm run listmonk:start
pnpm run smoke:listmonk:local
pnpm run smoke:listmonk:send:local
pnpm run smoke:listmonk:archive:local
pnpm run smoke:listmonk:subscribers:local
pnpm run smoke:staging:zip
pnpm run e2e:settings:local
pnpm run e2e:editor:local
pnpm run e2e:publish-schedule:local
pnpm run e2e:visual:local
```

Local Listmonk runs at `http://localhost:9000`; wp-env reaches it through the
generated `.listmonk.env` file. See
[docs/INTEGRATION-TESTING.md](docs/INTEGRATION-TESTING.md) for the full flow.

Use `pnpm run doctor:wp-typia` to verify the `wp-typia@0.22.10` workspace
diagnostics. The wrapper accepts the documented Bun-only readiness failure in
Node fallback mode while still failing on workspace drift. See
[docs/WP-TYPIA-DOCTOR.md](docs/WP-TYPIA-DOCTOR.md).
