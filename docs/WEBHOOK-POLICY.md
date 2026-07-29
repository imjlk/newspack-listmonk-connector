# Webhook Receiver Policy

The connector does not register a public WordPress webhook receiver for the MVP.
Bounce and complaint events should be delivered to Listmonk, and the connector
then reads the resulting subscriber and campaign state from the Listmonk API.

## Event Direction

Listmonk is the source of truth for bounce processing.

- Configure bounce processing in Listmonk under Settings > Bounces.
- Point supported SMTP provider webhooks at Listmonk's inbound service
  endpoints, such as `/webhooks/service/ses`, `/webhooks/service/sendgrid`, or
  `/webhooks/service/postmark`.
- For custom bounce processors, post bounce events to Listmonk's
  `/webhooks/bounce` endpoint.
- Do not point provider bounce or complaint webhooks at WordPress; this plugin
  intentionally exposes no webhook URL for that purpose.

This follows Listmonk's documented bounce model: Listmonk receives bounce events
from POP3 scanning, service-specific inbound webhooks, or its generic bounce
webhook API, then stores bounce records and can blocklist subscribers according
to the Listmonk bounce settings.

## Connector Behavior

The connector reflects Listmonk state after Listmonk has processed events:

- `get_contact_data()` reads subscriber status and bounce records.
- Blocklisted subscribers are not re-enabled or resubscribed by the connector.
- The campaign analytics REST resource reads bounce analytics from Listmonk.
- No unauthenticated inbound event mutates WordPress or subscriber state.

If a future Listmonk version adds an outbound event registration API that is
useful for WordPress, the receiver should be designed as a separate authenticated
feature instead of silently opening a public endpoint in this MVP connector.

## API Permissions

The connector API user should have read access to bounce data:

- `bounces:get`

The connector API user does not need `webhooks:post_bounce`. That permission is
for posting bounce notifications to Listmonk's inbound webhook endpoint, not for
WordPress-to-Listmonk campaign sync or subscriber reflection.

## Local Verification

Run `pnpm run listmonk:start` to start the local Listmonk, Postgres, and Mailpit
stack, then run:

```bash
pnpm run smoke:listmonk:bounces:local
```

The smoke temporarily enables the generic `/webhooks/bounce` endpoint, posts a
hard bounce, verifies Listmonk blocklists the fixture subscriber, and confirms
the connector reads the bounce state. It restores the original bounce settings
and removes the fixture afterward. This does not validate provider-specific
complaint signatures; that requires the intended SMTP provider and its public
webhook endpoint.

References:

- [Listmonk Bounce processing](https://listmonk.app/docs/bounces/)
- [Listmonk User roles and permissions](https://listmonk.app/docs/roles-and-permissions/)
