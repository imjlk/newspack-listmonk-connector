# Staging Checklist

Use this checklist before handing the beta build to editors or production
operators.

## Latest Automated Result

Completed on 2026-07-16 against WordPress 7.0.1, Newspack Newsletters 3.36.0,
Listmonk 6.0.0, and Mailpit SMTP capture exposed temporarily through separate
Cloudflare Quick Tunnels.

- `pnpm run smoke:staging:zip` rebuilt the 0.1.0 beta ZIP and passed against the
  release-matching installed package.
- WordPress REST and Listmonk API traffic crossed public DNS, valid edge TLS,
  HTTP/2, and the Cloudflare reverse proxy. Both temporary hostnames resolved
  over IPv4 and IPv6 and presented a Google Trust Services certificate.
- WordPress posts `48`, `49`, `51`, `53`, `55`, and `57` covered draft sync,
  publish, schedule, draft archive, scheduled archive, and running archive.
- Listmonk campaigns `129` through `134` reached the expected states. Campaigns
  `130` and `134` each delivered `1/1` message, and Mailpit received the test,
  publish, and running-archive messages.
- The remaining scheduled post was trashed after validation and campaign `131`
  returned to `draft`. WordPress and connector URLs were then restored to their
  local staging values before both public tunnels were removed.
- No WordPress PHP or Listmonk application errors appeared during the run. One
  tunnel request ended when a WordPress cron client disconnected, with no
  corresponding application failure.

This extends the 2026-07-15 isolated-stack result with external DNS, TLS, and
reverse-proxy coverage. SMTP still terminated in Mailpit, so a low-volume run
through the intended mail provider remains necessary to validate provider
delivery plus bounce and complaint webhooks.

## Preflight

- Staging has a recent database backup.
- Newspack Newsletters is installed and active.
- Connector for Newspack Newsletters and Listmonk activates without PHP errors.
- Listmonk API credentials belong to a dedicated API user.
- The API user has the permissions listed in `docs/SETUP.md`, including
  `bounces:get` for bounce reflection.
- The configured From email is allowed by the Listmonk mail setup.
- The target Listmonk list IDs are staging-safe.
- Bounce and complaint processing is configured in Listmonk for the staging
  mail provider; no WordPress webhook URL is required.

## Settings Validation

- Settings > Newsletter Connector loads the React settings screen.
- Save without entering a new token preserves the stored token.
- Save and test connection succeeds.
- Reloading the page shows the saved API URL, API user, From email, template ID,
  and default list IDs.
- `listmonk` is the active Newspack Newsletters service provider.

## Automated Smoke

- Build the beta zip with `pnpm run release:zip`.
- Upload `artifacts/connector-for-newspack-newsletters-and-listmonk-0.1.0.zip` through WP Admin or
  install it with WP-CLI.
- Export the `STAGING_*` variables documented in
  `docs/INTEGRATION-TESTING.md`.
- Run `pnpm run smoke:staging:zip`.
- Review the printed WordPress post IDs and Listmonk campaign IDs.
- If `STAGING_SMOKE_TEST_EMAIL` is not set, perform the test-send check
  manually from the editor.

## Newsletter Validation

- A draft Newspack newsletter opens without editor errors.
- The Listmonk editor panel appears.
- List selection persists after save/reload.
- Raw HTML preview contains the expected newsletter body.
- Payload preview contains `sendMode: "campaign"` and the chosen list IDs.
- Sync to Listmonk creates or updates a draft campaign.
- Test send returns a success notice in the editor.
- Publishing a staging newsletter changes the Listmonk campaign status to
  `running`.
- Scheduling a staging newsletter changes the Listmonk campaign status to
  `scheduled` and includes a future send time.
- Trashing a draft staging newsletter preserves the remote Listmonk draft,
  clears the active campaign link, and records archive metadata in WordPress.
- Trashing a scheduled staging newsletter reverts the Listmonk campaign status
  to `draft`, clears the active campaign link, and records archive metadata in
  WordPress.
- Trashing a running staging newsletter leaves the Listmonk campaign `running`
  and records a preserved-running archive policy.
- Listmonk bounce processing settings point supported provider webhooks at
  Listmonk, not WordPress.

## Rollback

- Deactivate Connector for Newspack Newsletters and Listmonk.
- Restore the previous Newspack Newsletters service provider.
- Pause or cancel any staging Listmonk campaigns created during validation.
- Keep the WordPress post meta in place unless a full cleanup is explicitly
  required; it is useful for diagnosing campaign sync history.

## Exit Criteria

- Draft sync, test send, publish, and schedule all pass on staging.
- No fatal errors or uncaught REST errors appear in PHP logs.
- Editors can identify the Listmonk campaign ID/status from the editor panel.
- Any remaining issues are documented before production use.
