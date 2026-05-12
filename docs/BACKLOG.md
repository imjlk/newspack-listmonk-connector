# Newspack Listmonk Connector Backlog

Last updated: 2026-05-11

Status legend:

- `[x]` Done
- `[~]` In progress or partially implemented
- `[ ]` Not started

## Progress Snapshot

| Phase | Area | Status |
| ---: | --- | --- |
| 0 | Technical validation and design | `[x]` |
| 1 | wp-typia scaffold | `[x]` |
| 2 | Listmonk API client | `[~]` |
| 3 | Newspack provider adapter | `[~]` |
| 4 | Raw HTML rendering pipeline | `[~]` |
| 5 | Campaign sync/send flow | `[~]` |
| 6 | Admin and editor UI | `[~]` |
| 7 | Subscriber/list integration | `[x]` |
| 8 | Test strategy and QA | `[~]` |
| 9 | Packaging and beta release | `[~]` |

## Phase 0: Technical Validation And Design

Goal: Confirm the Newspack provider extension path and lock the MVP contracts.

- `[x]` Confirm `newspack_newsletters_registered_providers` registration path.
- `[x]` Confirm provider must extend `Newspack_Newsletters_Service_Provider`.
- `[x]` Confirm Listmonk campaign API is the right raw HTML target.
- `[x]` Define initial post meta keys.
- `[x]` Define initial settings, preview, and newsletter payload contracts.
- `[x]` Document Newspack method to Listmonk endpoint mapping.
- `[x]` Validate against an installed Newspack Newsletters version in wp-env.
- `[x]` Record compatibility notes for current Newspack release.

## Phase 1: wp-typia Scaffold

Goal: Create the plugin workspace with published `wp-typia` CLI.

- `[x]` Create `/Users/imjlk/repos/newspack-listmonk-connector`.
- `[x]` Use published `wp-typia@0.22.10`.
- `[x]` Scaffold with `--template workspace`.
- `[x]` Use `pnpm`.
- `[x]` Add `listmonk-settings` REST resource.
- `[x]` Add `newsletter-preview` REST resource.
- `[x]` Add shared TypeScript contracts in `src/types.ts`.
- `[x]` Generate REST schemas, clients, and OpenAPI artifacts.

## Phase 2: Listmonk API Client

Goal: Provide a thin PHP client around the Listmonk APIs needed for MVP.

- `[x]` Store base URL, API user, and API token settings.
- `[x]` Support Basic Auth request flow.
- `[x]` Normalize JSON responses and API errors into `WP_Error`.
- `[x]` `test_connection()` using `GET /api/lists`.
- `[x]` `get_lists()` using `GET /api/lists`.
- `[x]` `create_campaign()` using `POST /api/campaigns`.
- `[x]` `update_campaign()` using `PUT /api/campaigns/{id}`.
- `[x]` `get_campaign()` using `GET /api/campaigns/{id}`.
- `[x]` `send_test()` using `POST /api/campaigns/{id}/test`.
- `[x]` `set_status()` using `PUT /api/campaigns/{id}/status`.
- `[x]` Add pagination handling beyond MVP list fetch.
- `[x]` Add subscriber endpoints.
- `[~]` Add webhook/analytics endpoints; campaign analytics read endpoint is done, webhook event receiver policy is deferred.
- `[x]` Add integration tests against Docker Listmonk.

## Phase 3: Newspack Provider Adapter

Goal: Make Listmonk appear as a Newspack Newsletters ESP provider.

- `[x]` Register provider via `newspack_newsletters_registered_providers`.
- `[x]` Add `Newspack_Listmonk_Connector_Provider`.
- `[x]` Implement credentials methods.
- `[x]` Implement `test_connection()`.
- `[x]` Implement `get_lists()`.
- `[x]` Implement `get_send_lists()`.
- `[x]` Implement `list()`.
- `[x]` Implement `retrieve()`.
- `[x]` Implement `sync()`.
- `[x]` Implement `test()`.
- `[x]` Implement `send()`.
- `[~]` Implement subscriber methods; keep tag methods as explicit MVP stubs.
- `[x]` Confirm editor-side Newspack data expectations in a live Newspack site.
- `[x]` Add Listmonk Newspack provider REST controller for retrieve/test/sync-error routes.
- `[x]` Add `supports_multiple_test_recipients` to editor response payloads.
- `[x]` Add editor-route smoke checks for retrieve/test response shape.
- `[x]` Confirm status transitions for publish and schedule in wp-env.
- `[x]` Add compatibility fallback if Newspack changes provider method shape.

## Phase 4: Raw HTML Rendering Pipeline

Goal: Convert a Newspack newsletter post into Listmonk-ready HTML and plain text.

- `[x]` Use `Newspack_Newsletters_Renderer::retrieve_email_html()` when available.
- `[x]` Add WordPress content fallback renderer.
- `[x]` Wrap fallback content in a minimal HTML document.
- `[x]` Convert root-relative `href` and `src` attributes to absolute URLs.
- `[x]` Generate `altbody` with plain text builder.
- `[x]` Add CSS inlining.
- `[x]` Add email-safe cleanup pass.
- `[x]` Add unsubscribe/footer placeholder policy.
- `[x]` Add Listmonk merge tag helper policy.
- `[x]` Add visual regression fixtures for representative newsletter blocks.

## Phase 5: Campaign Sync And Send Flow

Goal: Keep Newspack newsletter posts synced with Listmonk campaigns.

- `[x]` Build Listmonk campaign payload with `content_type: "html"`.
- `[x]` Create draft campaign when no campaign ID exists.
- `[x]` Update existing campaign when campaign ID exists.
- `[x]` Store payload hash.
- `[x]` Skip update when payload hash is unchanged.
- `[x]` Store campaign ID and UUID.
- `[x]` Store last sync timestamp.
- `[x]` Store last status.
- `[x]` Store last error.
- `[x]` Send test campaign after sync.
- `[x]` Set status to `running` for immediate sends.
- `[x]` Set status to `scheduled` when post status is `future`.
- `[x]` Verify Listmonk `send_at` behavior with scheduled Newspack posts.
- `[x]` Add retry/error UX around failed scheduled sends.
- `[x]` Add campaign deletion/archive policy.

## Phase 6: Admin And Editor UI

Goal: Let operators manage the connection and preview/sync campaigns without code.

- `[x]` Add Settings page under `Settings > Newspack Listmonk`.
- `[x]` Save API URL, user, token, From email, template ID, and default list IDs.
- `[x]` Add connection test action.
- `[x]` Add typed settings REST resource.
- `[x]` Add typed newsletter preview REST resource.
- `[x]` Add typed newsletter sync REST resource.
- `[x]` Build React settings screen.
- `[x]` Add Newspack editor side panel.
- `[x]` Add list selector in editor.
- `[x]` Add raw HTML preview in editor.
- `[x]` Add payload preview in editor.
- `[x]` Add "Sync to Listmonk" action.
- `[x]` Add "Send test" action.
- `[x]` Show campaign ID/status/error in editor.
- `[x]` Preserve display-name From email values such as `Newsroom <news@example.com>` through settings sanitization and campaign payloads.

## Phase 7: Subscriber And List Integration

Goal: Sync Newspack signup/contact flows into Listmonk subscribers.

- `[x]` Implement subscriber lookup.
- `[x]` Implement subscriber create/update.
- `[x]` Implement list membership add/remove.
- `[x]` Handle double opt-in list behavior.
- `[x]` Map Reader Activation metadata to Listmonk attributes.
- `[x]` Reflect blocklist/bounce state where useful.
- `[x]` Add subscriber sync tests.

## Phase 8: Test Strategy And QA

Goal: Prove the plugin works across unit, integration, and E2E flows.

- `[x]` Run PHP syntax check.
- `[x]` Run `pnpm run sync`.
- `[x]` Run `pnpm run lint`.
- `[x]` Run `pnpm run typecheck`.
- `[x]` Run `pnpm run build`.
- `[~]` Run `wp-typia doctor`; workspace checks pass, Bun environment check fails when Bun is missing.
- `[x]` Add PHPUnit tests for payload mapping.
- `[x]` Add unit tests for HTML/plain text builders.
- `[x]` Add unit tests for settings sanitization.
- `[x]` Add mocked Listmonk client tests.
- `[x]` Add wp-env with Newspack Newsletters.
- `[x]` Add Docker Listmonk integration.
- `[x]` Add local smoke flow for draft sync, publish transition, and schedule transition.
- `[x]` Add editor-side E2E coverage for Listmonk panel preview, sync, and test send.
- `[x]` Add settings screen E2E coverage for hydrate, save, token preservation, and connection test.
- `[x]` Add E2E flow for create, preview, test, publish, and schedule.

## Phase 9: Packaging And Beta Release

Goal: Prepare a beta plugin build for staging.

- `[x]` Add release packaging script.
- `[x]` Exclude development-only files from distribution.
- `[x]` Add plugin readme suitable for beta testers.
- `[x]` Add setup guide with required Listmonk permissions.
- `[x]` Add staging checklist.
- `[x]` Build beta zip.
- `[~]` Run staging smoke test; automated beta zip smoke workflow exists, actual
  staging execution still needs environment credentials.

## Next Recommended Work

1. Run `pnpm run smoke:staging:zip` against a real staging site and mark Phase 9 complete.
2. Define webhook event receiver policy.
3. Add editor analytics panel.
