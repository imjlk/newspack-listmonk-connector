# Newspack Editor Integration Contract

Last verified: 2026-05-11

Environment:

- WordPress in `@wordpress/env`.
- Newspack Newsletters installed from the WordPress.org stable ZIP.
- Reported Newspack Newsletters version: `3.32.0-alpha.1`.
- Active Newspack service provider: `listmonk`.
- Provider class: `Newspack_Listmonk_Connector_Provider`.

## Summary

Newspack recognizes `listmonk` as a supported provider and loads the provider
class correctly, but the editor expects provider-specific Newspack REST routes
that this connector does not register yet.

Before building a Listmonk editor side panel, the next backend milestone should
add a Listmonk provider controller for:

```text
GET  /newspack-newsletters/v1/listmonk/{post_id}/retrieve
POST /newspack-newsletters/v1/listmonk/{post_id}/test
GET  /newspack-newsletters/v1/{post_id}/sync-error
```

The final `sync-error` route is inherited from
`Newspack_Newsletters_Service_Provider_Controller`, so the connector needs a
controller instance even if the custom route methods only proxy to the provider.

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
/newspack-listmonk-connector/v1
/newspack-listmonk-connector/v1/listmonk-settings
/newspack-listmonk-connector/v1/listmonk-settings/item
/newspack-listmonk-connector/v1/newsletter-preview
/newspack-listmonk-connector/v1/newsletter-preview/item
```

Missing route table entries:

```text
/newspack-newsletters/v1/listmonk/{post_id}/retrieve
/newspack-newsletters/v1/listmonk/{post_id}/test
/newspack-newsletters/v1/{post_id}/sync-error
```

The provider's protected `controller` property is currently unset.

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
  "senderEmail": "Smoke Test <smoke@example.com>"
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
| `supports_multiple_test_recipients` | Missing | Enables the editor help text for comma-separated test recipients. The provider already supports multiple emails, so this should be added. |
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

Listmonk-specific controls still need connector-side editor JavaScript:

- List selector bound to `send_list_id`
- Campaign ID/status display
- Last sync error display
- Manual "Sync to Listmonk" action
- Raw HTML/payload preview

## Next Backend Tasks

1. Add `Newspack_Listmonk_Connector_Controller` extending
   `Newspack_Newsletters_Service_Provider_Controller`.
2. Instantiate the controller from `Newspack_Listmonk_Connector_Provider` so
   `sync-error` and provider-specific routes register on `rest_api_init`.
3. Register `GET /newspack-newsletters/v1/listmonk/{post_id}/retrieve`.
4. Register `POST /newspack-newsletters/v1/listmonk/{post_id}/test`.
5. Add `supports_multiple_test_recipients => true` to `retrieve()` and sync
   response data.
6. Add a smoke check for those Newspack editor routes before implementing the
   React side panel.
