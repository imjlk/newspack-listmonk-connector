# Privacy And Data Retention

This plugin connects WordPress and Newspack Newsletters to the Listmonk API URL
configured by a site administrator. Listmonk may be self-hosted or operated by a
third party depending on that configured URL.

## Data Sent To Listmonk

The connector sends the following data to the configured Listmonk API when an
authorized editor or administrator uses the related feature:

- Newsletter campaign data: title, subject, rendered HTML body, plain-text
  alternative body, sender details, selected Listmonk list IDs, template ID,
  tags, and scheduling time.
- Subscriber data: email address, name when provided, selected list membership,
  and sanitized subscriber attributes mapped from Newspack contact metadata.
- Test-send data: the test recipient email addresses entered by an authorized
  user.
- Analytics lookups: synced Listmonk campaign IDs and requested date ranges.

The connector reads Listmonk list, campaign, subscriber, bounce, blocklist, and
analytics data to display status in WordPress. Bounce and complaint webhooks
should terminate at Listmonk, not WordPress.

## Suggested Privacy Policy Text

This site uses Listmonk to manage newsletter campaigns and subscriber lists.
When you subscribe to a newsletter or when an editor sends a newsletter, this
site may send your email address, name, subscription list choices, newsletter
campaign content, and related delivery metadata to the configured Listmonk
server. Listmonk processes unsubscribe, bounce, blocklist, and campaign
analytics data according to the Listmonk deployment configured by the site
owner.

## Uninstall Behavior

Uninstalling the plugin deletes the local WordPress option that stores the
Listmonk API URL, API user, API token, default sender, template ID, and default
list IDs. It also deletes connector sync-error transients.

Uninstalling the plugin does not delete remote Listmonk campaigns, subscribers,
lists, bounces, or analytics data. It also preserves connector post meta on
newsletter posts so campaign history remains auditable if the plugin is
reinstalled. Review and remove remote data from the Listmonk admin if your data
retention policy requires it.
