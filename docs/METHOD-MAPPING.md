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
| `trash( $post_id )` | Archives the active campaign reference without hard-deleting Listmonk data. |
| `delete( $post_id )` | Applies the same archive policy before permanent post deletion. |
| `add_contact( $contact, $list_id )` | Looks up by email, then `POST /api/subscribers` or `PATCH /api/subscribers/{id}` plus list membership add. |
| `get_contact_data( $email )` | `GET /api/subscribers?per_page=all` followed by local exact email matching. |
| `get_contact_lists( $email )` | Returns list IDs from the Listmonk subscriber response. |
| `update_contact_lists( $email, $add, $remove )` | `PUT /api/subscribers/lists` with `add` and `remove` actions. |

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

Trash/delete never hard-deletes Listmonk campaigns. Scheduled campaigns are
reverted to `draft`; paused campaigns are moved to `cancelled`; draft campaigns
are preserved as remote drafts and detached locally because Listmonk does not
cancel inactive drafts. Running campaigns are preserved for operator inspection.

## Subscriber Sync

Newspack contacts map to Listmonk subscribers:

- `email` is lowercased and validated before any remote call.
- `name` is sanitized from `name` or `first_name` / `last_name`.
- `metadata` is recursively sanitized into Listmonk `attribs`.
- Email lookup deliberately avoids Listmonk SQL query endpoints so the API user
  does not need `subscribers:sql_query`.
- Missing subscribers are created with `status: "enabled"`.
- Existing subscribers are patched without replacing their full list set.
- Existing subscriber list additions use `unconfirmed` by default, preserving
  Listmonk's double opt-in posture unless a site changes the
  `newspack_listmonk_connector_subscriber_list_add_status` filter.
- `preconfirm_subscriptions` defaults to `false` and can be overridden with the
  `newspack_listmonk_connector_preconfirm_subscriptions` filter.

## Stored Post Meta

| Meta key | Purpose |
| --- | --- |
| `_wtnl_listmonk_campaign_id` | Listmonk campaign ID. |
| `_wtnl_listmonk_campaign_uuid` | Listmonk campaign UUID when returned. |
| `_wtnl_listmonk_payload_hash` | Hash used to skip unchanged syncs. |
| `_wtnl_listmonk_last_synced_at` | Last successful sync timestamp. |
| `_wtnl_listmonk_last_error` | Last sync/test/send error message. |
| `_wtnl_listmonk_last_status` | Last known Listmonk campaign status. |
| `_wtnl_listmonk_archived_campaign_id` | Last archived Listmonk campaign ID. |
| `_wtnl_listmonk_archived_campaign_uuid` | Last archived Listmonk campaign UUID. |
| `_wtnl_listmonk_archived_status` | Last archived Listmonk campaign status. |
| `_wtnl_listmonk_archived_at` | Archive timestamp. |
| `_wtnl_listmonk_archive_policy` | Archive policy applied during trash/delete. |

## Deferred Methods

Tag methods remain present to satisfy the Newspack provider interface, but they
intentionally return MVP "not implemented" errors or empty results.
