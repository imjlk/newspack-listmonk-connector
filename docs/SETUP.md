# Newspack Listmonk Connector Setup

This guide is for beta validation on a staging WordPress site.

## Requirements

- WordPress 6.7 or later.
- PHP 8.0 or later.
- Newspack Newsletters installed and active.
- A reachable Listmonk server.
- The beta zip built with `pnpm run release:zip`.

## Listmonk API User

Create a Listmonk API user for the connector instead of reusing an administrator
login.

Minimum MVP permissions:

- `lists:get_all`
- `campaigns:manage`
- `campaigns:send`
- `subscribers:get`
- `subscribers:manage`

Keep `subscribers:sql_query` disabled. The connector only uses subscriber list
fetch, local exact email matching, subscriber create/update, and list membership
APIs.

## Install The Plugin

1. In WordPress admin, install and activate Newspack Newsletters.
2. Upload `artifacts/newspack-listmonk-connector-0.1.0.zip`.
3. Activate Newspack Listmonk Connector.
4. Open Settings > Newspack Listmonk.
5. Enter:
   - Listmonk API URL, for example `https://listmonk.example.com`
   - API user
   - API token
   - Default From email, for example `Newsroom <news@example.com>`
   - Default template ID, or `0` to use raw campaign HTML without forcing a template
   - Default list IDs as comma-separated numeric IDs
6. Click Save and test connection.

Credentials may also be supplied through constants:

```php
define( 'NEWSPACK_LISTMONK_CONNECTOR_BASE_URL', 'https://listmonk.example.com' );
define( 'NEWSPACK_LISTMONK_CONNECTOR_API_USER', 'api_user' );
define( 'NEWSPACK_LISTMONK_CONNECTOR_API_TOKEN', 'token' );
```

## Select The Newspack Provider

Set `listmonk` as the active Newspack Newsletters service provider. If the site
does not expose a provider selector in admin, use WP-CLI:

```bash
wp option update newspack_newsletters_service_provider listmonk
```

## Smoke Check

1. Create a draft Newspack newsletter.
2. Open the editor document settings sidebar.
3. Confirm the Listmonk panel appears.
4. Select a Listmonk list.
5. Confirm raw HTML preview and payload preview render.
6. Click Sync to Listmonk and confirm a campaign ID appears.
7. In Listmonk admin, confirm the campaign is still `draft`.
8. Click Send test and confirm the editor shows a success notice.
9. Publish a low-risk staging newsletter and confirm Listmonk status becomes `running`.
10. Schedule a separate staging newsletter and confirm Listmonk status becomes `scheduled`.

For local automated checks, see `docs/INTEGRATION-TESTING.md` in the source
repository.
