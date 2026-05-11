# Newspack Editor Integration Contract

Last verified: 2026-05-11

Environment:

- WordPress in `@wordpress/env`.
- Newspack Newsletters installed from the WordPress.org stable ZIP.
- Reported Newspack Newsletters version: `3.32.0-alpha.1`.
- Active Newspack service provider: `listmonk`.
- Provider class: `Newspack_Listmonk_Connector_Provider`.

## Summary

Newspack recognizes `listmonk` as a supported provider, loads the provider class,
and now receives provider-specific REST routes for the built-in editor flow.
The connector keeps these routes in the Newspack namespace because the editor
bundle calls them directly:

```text
GET  /newspack-newsletters/v1/listmonk/{post_id}/retrieve
POST /newspack-newsletters/v1/listmonk/{post_id}/test
GET  /newspack-newsletters/v1/{post_id}/sync-error
```

The connector's own editor panel uses the typed connector namespace for manual
sync:

```text
POST /newspack-listmonk-connector/v1/newsletter-sync
```

The final `sync-error` route is inherited from
`Newspack_Newsletters_Service_Provider_Controller`, so the connector needs a
controller instance even though the custom route methods only proxy to the
provider.

## Localized Editor Data

Newspack localizes common email editor data into `newspack_email_editor_data`.
Relevant fields for this connector:

```text
email_html_meta
mjml_handling_post_types
newsletter_post_type
current_post_type
conditional_tag_support
supported_social_icon_services
supported_esps
```

For newsletter posts, Newspack also localizes `newspack_newsletters_data`:

```text
is_service_provider_configured
service_provider
user_test_emails
labels
```

In the local wp-env site, `Newspack_Newsletters::get_supported_providers()`
returns:

```text
mailchimp,constant_contact,active_campaign,listmonk,manual
```

That means the editor treats `listmonk` as a supported ESP and will attempt the
Newspack provider REST flow.

## Editor REST Flow

The minified Newspack editor bundle uses these provider routes:

| Flow | Request | Notes |
| --- | --- | --- |
| Campaign retrieve | `GET /newspack-newsletters/v1/${provider}/${postId}/retrieve` | Runs on editor load and when the user retries campaign retrieval. |
| Test send | `POST /newspack-newsletters/v1/${provider}/${postId}/test` | Request body uses `test_email`. Comma-separated addresses are supported by built-in providers. |
| Sync error | `GET /newspack-newsletters/v1/${postId}/sync-error` | Reads a transient message through the provider controller. |
| Send lists | `GET /newspack-newsletters/v1/send-lists?provider=${provider}&type=list...` | Calls the active provider's `get_send_lists( $args, true )`. |
| MJML preview | `POST /newspack-newsletters/v1/post-mjml` | Converts current editor content to MJML/HTML for preview. |
| Connector sync | `POST /newspack-listmonk-connector/v1/newsletter-sync` | Type-validated route used by the connector side panel's "Sync" action. |
| Connector retry send | `POST /newspack-listmonk-connector/v1/newsletter-sync` | Sends `{ "retrySend": true }` for failed published/scheduled sends and reuses the same typed response shape. |

The current route table with `listmonk` active includes:

```text
/newspack-newsletters/v1
/newspack-newsletters/v1/layouts
/newspack-newsletters/v1/settings
/newspack-newsletters/v1/color-palette
/newspack-newsletters/v1/post-mjml
/newspack-newsletters/v1/lists_config
/newspack-newsletters/v1/lists
/newspack-newsletters/v1/send-lists
/newspack-newsletters/v1/{post_id}/sync-error
/newspack-newsletters/v1/listmonk/{post_id}/retrieve
/newspack-newsletters/v1/listmonk/{post_id}/test
/newspack-listmonk-connector/v1
/newspack-listmonk-connector/v1/listmonk-settings
/newspack-listmonk-connector/v1/listmonk-settings/item
/newspack-listmonk-connector/v1/newsletter-preview
/newspack-listmonk-connector/v1/newsletter-preview/item
/newspack-listmonk-connector/v1/newsletter-sync
```

The provider's protected `controller` property is set to
`Newspack_Listmonk_Connector_Controller`.

## Retrieve Response Shape

`Newspack_Listmonk_Connector_Provider::retrieve()` currently returns a usable
base shape for the editor store:

```json
{
  "campaign": true,
  "campaign_id": "7",
  "listmonk_campaign_id": "7",
  "listmonk_campaign_uuid": "0f2d69d2-4de2-424a-818a-d9b5e02cf334",
  "listmonk_last_status": "draft",
  "listmonk_last_synced_at": "2026-05-11T01:32:26+00:00",
  "listmonk_last_error": "",
  "listmonk_last_error_code": "",
  "listmonk_last_error_at": "",
  "send_list_id": "1",
  "lists": [
    {
      "provider": "listmonk",
      "type": "list",
      "entity_type": "list",
      "id": "1",
      "name": "Default list",
      "count": 1,
      "label": "[LIST] Default list (1 contact)",
      "value": "1"
    }
  ],
  "senderName": "Smoke Test",
  "senderEmail": "Smoke Test <smoke@example.com>",
  "supports_multiple_test_recipients": true
}
```

Required or useful fields for editor compatibility:

| Key | Current status | Purpose |
| --- | --- | --- |
| `campaign` | Present | Allows the publish modal and sidebar to treat the campaign as retrieved. |
| `send_list_id` | Present | Required by Newspack validation before sending. |
| `lists` | Present | Used by generic pre-send/list display paths and cached list lookup. |
| `senderEmail` | Present | Required by Newspack validation before sending. Prefer a plain email value in editor meta. |
| `senderName` | Present | Required by Newspack validation before sending. |
| `supports_multiple_test_recipients` | Present | Enables the editor help text for comma-separated test recipients. |
| `link` | Missing | Optional. Enables "View Campaign in {Provider}" if we later build a Listmonk admin campaign URL. |

The editor also calls `/send-lists` independently, so the `lists` array in
`retrieve()` is helpful but not the only list source.

## Test Response Shape

The test email UI sends:

```json
{
  "test_email": "one@example.com,two@example.com"
}
```

The editor displays `response.message` on success. On failure it displays
`error.message`, then `error.data.message`, then a generic fallback.

The current `test()` method already returns:

```json
{
  "message": "Listmonk test message sent to one@example.com, two@example.com.",
  "result": {}
}
```

Errors should remain `WP_Error` values so the controller can wrap them with a
REST status using `Newspack_Newsletters_Service_Provider_Controller::get_api_response()`.

## List Selector Expectations

The editor's list lookup calls:

```text
GET /newspack-newsletters/v1/send-lists?provider=listmonk&type=list
```

This route is registered by Newspack's `Send_Lists` class when the active
provider is configured. It calls:

```php
$provider->get_send_lists( $args, true );
```

The connector's current `get_send_lists()` output matches the shared Newspack
shape: `provider`, `type`, `entity_type`, `id`, `name`, `count`, `label`, and
`value`.

## Provider Sidebar Expectations

Newspack's bundled provider map includes built-in sidebar behavior for bundled
providers such as Mailchimp and Constant Contact. External provider slugs are
not automatically given a custom React sidebar.

For `listmonk`, the built-in editor can still render generic controls:

- Campaign Name
- Subject
- Preview text
- Sender Name
- Sender Email

The connector now adds a `Listmonk` document settings panel with:

- List selector bound to `send_list_id`
- Campaign ID/status/last sync display
- Last sync error display
- Manual sync through the typed `newsletter-sync` route
- Test send through the Newspack `listmonk/{post_id}/test` route
- Raw HTML and Listmonk payload preview

## Next Backend Tasks

1. Add an editor E2E smoke that opens a newsletter post and verifies the
   Listmonk panel renders against the local Listmonk stack.
2. Keep the Newspack namespace REST routes as thin controller proxies; new
   typed connector-specific APIs should continue to live under
   `newspack-listmonk-connector/v1`.
3. Keep the Newspack editor wire shapes mirrored in `src/types.ts` so wp-typia
   type checks can catch contract drift even though these Newspack routes are
   manually registered.
