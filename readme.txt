=== Newspack Listmonk Connector ===
Contributors: imjlk
Requires at least: 6.7
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connects Newspack Newsletters to Listmonk campaigns.

== Description ==

Newspack Listmonk Connector adds a Listmonk ESP provider for Newspack Newsletters.
It turns Newspack newsletter editor output into raw HTML Listmonk campaigns, then
supports draft sync, test sends, immediate sends, and scheduled sends.

This is a beta build intended for controlled staging validation before broader
use. Subscriber sync, analytics, and webhook handling are not included yet.

== Requirements ==

* WordPress 6.7 or later.
* PHP 8.0 or later.
* Newspack Newsletters installed and active.
* A reachable Listmonk server.
* A Listmonk API user with `lists:get_all`, `campaigns:manage`, and `campaigns:send`.

== Installation ==

1. Install and activate Newspack Newsletters.
2. Upload and activate `newspack-listmonk-connector.zip`.
3. Open Settings > Newspack Listmonk.
4. Enter the Listmonk API URL, API user, API token, default From email, template ID, and list IDs.
5. Use Save and test connection.
6. Select `listmonk` as the active Newspack Newsletters service provider.
7. Create a Newspack newsletter and verify the Listmonk editor panel can preview, sync, and send a test.

See `docs/SETUP.md` and `docs/STAGING-CHECKLIST.md` in the plugin package for
the full beta validation flow.

== Changelog ==

= 0.1.0 =
* Beta release packaging for the Newspack/Listmonk MVP.
* Adds Listmonk provider registration, settings, campaign sync, test send, send/schedule transitions, editor panel, and compatibility fallbacks.
