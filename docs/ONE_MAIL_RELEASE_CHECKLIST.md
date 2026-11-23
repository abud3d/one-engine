# ONE Mail — release preparation checklist (v001)
**Current result:** planning branch only; DO NOT install on Server12 or send email.

## Required user flows
1. Superadmin adds two isolated sites with unique authenticated connector credentials.
2. Registers distinct sender accounts and tests local and external SMTP using verified test recipients.
3. Chooses global, site, account, destination-domain, campaign, and per-recipient caps (minute/hour/day/month), schedules, and minimum spacing.
4. Compose page offers text + sanitized HTML, template library, draft, preview, test, schedule, pause, resume.
5. Site admin toggles registration messages, expiry reminders, weekly city offers and monthly city offers independently; configures city/segment/top N/day/time.
6. Weekly digest uses subscriber-selected city and includes only current publicly listed offers; empty digest skipped. Monthly digest independently deduplicated.
7. Customer opts out; all pending marketing jobs suppressed before send; service-critical messages remain separate.
8. Reports show queued, claimed, accepted by outbound server, hard bounce, soft bounce, unknown, suppressed, complaint; never call accepted 'delivered'.
9. Operator manually switches PHP mail/local transport to SMTP; auto failover only after unambiguous pre-acceptance failure.

## Test acceptance
- No external requests on idle page, no polling or daemon.
- Batch limit is respected under concurrent worker starts and cron reentry.
- Two sites cannot access each other's recipients, campaigns or credentials.
- Suppressed/unsubscribed users receive zero marketing messages.
- Cap exhaustion defers mail without losing queue items; quotas remain honored across transports.
- One city digest per period/user, even with duplicate event/cron.
- SMTP timeout after DATA is marked unknown, not automatically resent.
- Sanitized HTML, escaped placeholders, CSRF/RBAC and expiring opt-out HMAC.
- Installation defaults to all marketing campaigns disabled.
- CLI tests/backup/rollback and no secrets or customer data in artifacts.

## Suggested incremental delivery
M1: migrations, site/sender registry, settings/RBAC, transports, queue, cron and tests.
M2: compose+templates, preview/test, campaigns, opt-out/consent, reporting.
M3: MBUBH connector, registration messages, listing expiry, weekly/monthly city digest.
M4: expand connectors to other sites, production hardening and independent SMTP failover.

The ONE Engine default README states version 0.4.0 is preview and MariaDB adapter is not yet implemented. ONE Mail must not claim a deployable production release before these integration gaps are resolved.
