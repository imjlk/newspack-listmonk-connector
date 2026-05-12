# Staging Checklist

Use this checklist before handing the beta build to editors or production
operators.

## Preflight

- Staging has a recent database backup.
- Newspack Newsletters is installed and active.
- Newspack Listmonk Connector activates without PHP errors.
- Listmonk API credentials belong to a dedicated API user.
- The API user has `lists:get_all`, `campaigns:manage`, and `campaigns:send`.
- The configured From email is allowed by the Listmonk mail setup.
- The target Listmonk list IDs are staging-safe.

## Settings Validation

- Settings > Newspack Listmonk loads the React settings screen.
- Save without entering a new token preserves the stored token.
- Save and test connection succeeds.
- Reloading the page shows the saved API URL, API user, From email, template ID,
  and default list IDs.
- `listmonk` is the active Newspack Newsletters service provider.

## Automated Smoke

- Build the beta zip with `pnpm run release:zip`.
- Upload `artifacts/newspack-listmonk-connector-0.1.0.zip` through WP Admin or
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

## Rollback

- Deactivate Newspack Listmonk Connector.
- Restore the previous Newspack Newsletters service provider.
- Pause or cancel any staging Listmonk campaigns created during validation.
- Keep the WordPress post meta in place unless a full cleanup is explicitly
  required; it is useful for diagnosing campaign sync history.

## Exit Criteria

- Draft sync, test send, publish, and schedule all pass on staging.
- No fatal errors or uncaught REST errors appear in PHP logs.
- Editors can identify the Listmonk campaign ID/status from the editor panel.
- Any remaining issues are documented before production use.
