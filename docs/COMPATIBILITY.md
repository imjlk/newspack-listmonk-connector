# Compatibility Notes

Last verified: 2026-05-11

## Newspack Newsletters

The wp-env smoke test installs Newspack Newsletters from the WordPress.org
stable ZIP URL:

```text
https://downloads.wordpress.org/plugin/newspack-newsletters.zip
```

The local verification environment reported:

```text
newspack-newsletters 3.32.0-alpha.1
```

Verified contract points:

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

## Listmonk

The local Docker smoke test uses `listmonk/listmonk:latest` with Postgres 17.
The helper starts Listmonk with `./listmonk --install --idempotent`, captures
the generated API token for `LISTMONK_ADMIN_API_USER`, and writes it to the
ignored `.listmonk.env` file.

Verified contract points:

- `GET /api/lists?per_page=1` connection check
- `GET /api/lists?status=active&per_page=all` active list fetch
- `POST /api/campaigns` draft campaign creation with `content_type: html`
- `GET /api/campaigns/{id}` status confirmation
- `PUT /api/campaigns/{id}/status` transition to `running`
- `PUT /api/campaigns/{id}/status` transition to `scheduled`
- scheduled campaign payload includes `send_at`

Local Docker note: WordPress runs inside wp-env, so `.listmonk.env` points
WordPress at `http://host.docker.internal:9000` instead of `localhost`.
