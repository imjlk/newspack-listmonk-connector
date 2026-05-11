# Newspack To Listmonk Method Mapping

This document records the MVP provider contract used by the connector.

## Provider Registration

Newspack discovers the connector through the
`newspack_newsletters_registered_providers` filter. The provider slug is
`listmonk`, and the provider class is `Newspack_Listmonk_Connector_Provider`.

The active Newspack provider is stored in the
`newspack_newsletters_service_provider` option.

## Method Mapping

| Newspack provider method | Listmonk behavior |
| --- | --- |
| `api_credentials()` | Reads `newspack_listmonk_connector_settings` and optional constants. |
| `set_api_credentials()` | Saves base URL, API user, and token into plugin settings. |
| `has_api_credentials()` | Requires base URL, API user, and API token. |
| `test_connection()` | `GET /api/lists?per_page=1`. |
| `get_lists()` | `GET /api/lists?status=active&per_page=all`. |
| `get_send_lists()` | Converts active Listmonk lists into Newspack `Send_List` values. |
| `list( $post_id, $list_id )` | Stores Newspack `send_list_id` post meta. |
| `retrieve( $post_id )` | Returns campaign ID/status/error meta and selected lists for the editor. |
| `sync( $post )` | Creates or updates a Listmonk draft campaign. |
| `test( $post_id, $emails )` | Syncs first, then `POST /api/campaigns/{id}/test`. |
| `send( $post )` | Syncs first, then `PUT /api/campaigns/{id}/status`. |
| `save( $meta_id, $post_id, $meta_key )` | Syncs after Newspack refreshes stored email HTML. |
| `trash( $post_id )` | Clears local transient errors and preserves remote drafts. |

## Campaign Payload

The connector sends Newspack rendered HTML as a Listmonk campaign payload:

```json
{
	"type": "regular",
	"content_type": "html",
	"messenger": "email",
	"body": "<html>...</html>",
	"altbody": "Plain text fallback"
}
```

The provider keeps draft campaigns as drafts during `sync()`. It only changes
status during `send()`:

- immediate publish: `running`
- scheduled post: `scheduled`

## Stored Post Meta

| Meta key | Purpose |
| --- | --- |
| `_wtnl_listmonk_campaign_id` | Listmonk campaign ID. |
| `_wtnl_listmonk_campaign_uuid` | Listmonk campaign UUID when returned. |
| `_wtnl_listmonk_payload_hash` | Hash used to skip unchanged syncs. |
| `_wtnl_listmonk_last_synced_at` | Last successful sync timestamp. |
| `_wtnl_listmonk_last_error` | Last sync/test/send error message. |
| `_wtnl_listmonk_last_status` | Last known Listmonk campaign status. |

## Deferred Methods

Subscriber and tag methods are present to satisfy the Newspack provider
interface, but they intentionally return MVP "not implemented" errors or empty
results. Subscriber sync is tracked as a later backlog phase.
