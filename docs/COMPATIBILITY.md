# Compatibility Notes

Last verified: 2026-05-11

## Newspack Newsletters

The connector is a companion plugin for Newspack Newsletters and declares the
WordPress plugin dependency header `Requires Plugins: newspack-newsletters`.
The broader Newspack platform plugin is optional for the MVP provider flow.

The wp-env smoke test installs Newspack Newsletters from the WordPress.org
stable ZIP URL:

```text
https://downloads.wordpress.org/plugin/newspack-newsletters.zip
```

The local verification environment reported:

```text
newspack-newsletters 3.32.0-alpha.1
```

Verified direct contract points:

- provider registration through `newspack_newsletters_registered_providers`
- active provider option `newspack_newsletters_service_provider`
- provider instance resolution through
  `Newspack_Newsletters::get_service_provider_instance( 'listmonk' )`
- editor REST route pattern
  `/newspack-newsletters/v1/{provider}/{post_id}/retrieve`
- editor REST route pattern
  `/newspack-newsletters/v1/{provider}/{post_id}/test`
- shared provider sync-error route
  `/newspack-newsletters/v1/{post_id}/sync-error`
- newsletter CPT constant `Newspack_Newsletters::NEWSPACK_NEWSLETTERS_CPT`
- stored HTML meta constant `Newspack_Newsletters::EMAIL_HTML_META`
- hookable provider methods `save()`, `send()`, and `trash()`

Fallback policy:

- Provider registration is skipped when Newspack's provider/controller base
  classes are unavailable, preventing class-load fatals.
- REST namespace resolution prefers
  `Newspack_Newsletters_Service_Provider::BASE_NAMESPACE`, then
  `Newspack_Newsletters::API_NAMESPACE`, then `newspack-newsletters/v1`.
- Newsletter CPT and stored HTML meta helpers prefer Newspack constants and
  fall back to `newspack_nl_cpt` and `newspack_email_html`.
- Active provider and provider instance resolution prefer Newspack static
  methods and fall back to the active provider option plus a cached Listmonk
  provider instance when safe.
- Newspack editor route permissions, ID validation, REST response wrapping,
  sync-error transient naming, and campaign naming are routed through connector
  compatibility helpers.

## Listmonk

The local Docker smoke test uses `listmonk/listmonk:latest` with Postgres 17.
The helper starts Listmonk with `./listmonk --install --idempotent`, captures
the generated API token for `LISTMONK_ADMIN_API_USER`, and writes it to the
ignored `.listmonk.env` file.

Verified contract points:

- `GET /api/lists?per_page=1` connection check
- `GET /api/lists?status=active&page=N&per_page=100` active list fetch
- `GET /api/subscribers?page=N&per_page=100` subscriber lookup without SQL query permissions
- `GET /api/subscribers/{id}/bounces?page=N&per_page=100` bounce lookup
- `POST /api/campaigns` draft campaign creation with `content_type: html`
- `GET /api/campaigns/{id}` status confirmation
- `PUT /api/campaigns/{id}/status` transition to `running`
- `PUT /api/campaigns/{id}/status` transition to `scheduled`
- scheduled campaign payload includes `send_at`

Local Docker note: WordPress runs inside wp-env, so `.listmonk.env` points
WordPress at `http://host.docker.internal:9000` instead of `localhost`.
