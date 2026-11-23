# TD-ONE-MAIL-001 — ONE Mail (v001 specification)
Status: SPECIFIED / NOT DEPLOYABLE — 2026-11-23
Scope: multi-site messaging hub, native PHP 8.3+, MariaDB/PDO, CLI cron, SMTP and local sendmail transport, no daemon/polling.

## Non-negotiables
- All capabilities and toggles administered through an authenticated, authorized, CSRF-protected control panel; server-enforced roles: super-admin, site-admin, campaign-editor, read-only.
- Default globally disabled until an operator explicitly finishes setup; no production email triggered by migration/install/tests.
- Site isolation: site-scoped contact IDs, consents, templates, campaigns, sender accounts and delivery logs; central super-admin alone sees aggregate views.
- No marketing sends without recorded lawful consent per site, topic, purpose, and timestamp/source. Withdrawal effective before dispatch; transactional mail is separate from promotional mail and cannot be used to bypass opt-outs.
- Single-worker, bounded CLI jobs; no visitor-triggered cron, heartbeat, polling, long-running service, or Redis requirement.
- Cannot assume a successful PHP mail() call proves delivery, or that a failed/timeout SMTP operation proves nonacceptance. Ambiguous handoffs are quarantined, never automatically resent.

## Admin navigation
Overview (total, hourly/daily/monthly, pending, sent to MTA, bounced, blocked, complaints), sites, sender accounts, compose, campaigns, templates, contact lists, consent/preference center, automation rules, queue, rate limits, scheduling windows, delivery errors, suppression, audit/events, permissions, backups/health.
All settings support global defaults and per-site overrides. Every switch shows current effective setting, last editor and timestamp.

## New-message composer
- Select originating site, sender identity/transport, recipient segment, purpose (transactional/marketing), campaign title, subject, preheader, text alternative, HTML mode or safe ready-made template.
- HTML is sanitized (no script, forms, event handlers, unsafe URLs, external active embeds); preview in sandbox; plain-text alternative always available; email-compatibility styling.
- Preview as sample recipient, test-send to a verified administrator address, save draft, schedule, pause/resume, confirm final eligible count (exclude unsubscribed/suppressed/duplicates).
- Templates: welcome, registration verification, password reset (transactional), listing created, listing expiry warning, weekly offers, monthly offers, city digest, announcement, blank.
- Personalization only from approved site-scoped placeholders (display name, city, listing, opt-out URL), escaped in appropriate context. No arbitrary PHP execution.

## Built-in automation rules (each independent, off by default)
| Rule | Controls |
| --- | --- |
| New registration | enabled, site, template, trigger delay, max events per user, transactional vs marketing classification |
| Registration verification | enabled, sender/template, short expiry, priority queue |
| Listing published | enabled, sender/template, dedupe per listing |
| Listing about to expire | enabled, days before expiry, max reminders, cancel on renewal |
| Weekly city offers | enabled, site, opt-in segment, weekday/time/timezone, city source, top N, max age, fallback if empty, exclude poster's own offers, frequency cap |
| Monthly city offers | enabled, day/time/timezone, same filters and recipient caps |
| Inactive-user reminder | enabled, inactivity threshold, cadence, max lifetime sends, cancel on login |
| Custom campaign | enabled, audience rules, recurrence or one-shot, cap |

City must come from verified user preference or explicit selection; no inference from IP; if unknown, skip city-targeted send or use separately subscribed general digest. Weekly/monthly offers include only eligible published listings and canonical links, deduplicated by listing and recipient. Generate snapshot once per site/city/period; not one database query per recipient. If there are no relevant offers, do not send an empty digest. Activation is contingent on legal permission.

## All dashboard limits
Global + site + sender account + campaign + recipient + destination domain: per minute/hour/day/month caps; per-recipient minimum gap, marketing-only cap, burst ceiling, sending weekdays/hours/timezone, priority buckets for security mail, HTML maximum bytes, batch max, max worker duration, max SMTP concurrency, message retry count/backoff, rolling failure and bounce thresholds, list segment size, complaint threshold, maximum emails per campaign and lifetime per user.
Limits evaluated atomically at reservation/dispatch; the most restrictive applicable cap wins. Use time-zone aware calendar periods and explicit reset times. Daily/monthly counters must not be bypassed by rotating transports.

## Transport controls
Per site: default transport (local sendmail/PHP mail or SMTP), secondary transport, manual switch, verified test send, credentials stored outside public web root, TLS certificate verification, SMTP timeouts, optional automatic failover solely after *proven pre-acceptance* failure. For uncertain outcomes mark UNKNOWN and require review; no immediate retry. External SMTP recommended as an independent backup when local PHP Mail uses same Postfix. DKIM/SPF/DMARC alignment and PTR checked before enabling production campaigns.

## Sending lifecycle
Events or site connector enqueue idempotently -> worker locks via DB advisory/row lock -> fetches small indexed due batch -> rechecks latest consent, suppression, site/campaign switches, schedule and caps -> claims quota -> transport -> records ACCEPTED_BY_MTA / FAILED_BEFORE_ACCEPTANCE / UNKNOWN -> updates queue and closes.
Use unique idempotency keys (site, automation rule, user, listing or digest period). Backoffs for temporary failures only; permanent failures suppress address. Cancellation takes effect immediately. Bounce/complaint webhooks or mailbox parsing only if provider supports authenticated delivery evidence.

## Security
- PDO prepared statements, input validation, parameterized segments (no raw SQL user filters), HTML/XSS sanitization, CSRF, RBAC and audit records.
- HMAC-signed expiring unsubscribe links and List-Unsubscribe / List-Unsubscribe-Post for promotional mail; one-click opt-out; no authentication needed for unsubscribing.
- No secrets, real address lists, or dumps in GitHub; no live domain/IP in test fixtures. Rate limit APIs, authenticated site-to-hub integration, replay protection.
- Data minimization, retention schedule, recipient request/withdrawal handling, legal review against Saudi PDPL and applicable anti-spam/provider policies.

## MariaDB proposed tables
one_mail_sites, one_mail_sender_accounts, one_mail_templates, one_mail_campaigns, one_mail_automations, one_mail_contacts (site-scoped), one_mail_consents, one_mail_preferences, one_mail_suppression, one_mail_queue, one_mail_delivery_attempts, one_mail_quotas, one_mail_settings, one_mail_audit.
Foreign keys/site_id and unique idempotency keys; indexed (status, scheduled_at, site_id) and (site_id, email_hash); encrypted SMTP passwords, not plaintext. No invasive changes to existing ClassicPress or ONE Engine tables until integration mapping is audited.

## Deployment gates (must pass before Server12)
1. Inspect existing ONE Engine authentication/deployment boundaries and MBUBH user/listing schema, city taxonomies and consent history read-only.
2. Implement with tests (worker race, idempotency, caps, pause, opt-out, failover unknown, transport error, multi-site isolation, XSS, CSRF) and check PHP 8.3.
3. Create migration + rollback, sample config, installer; no real sender credentials in repository.
4. Create signed/verified release ZIP, operator guide and dry run with fake recipients; production defaults disabled.
5. On Server12: take backup, inspect Postfix/php-fpm/crons/SPF/DKIM/DMARC/PTR and provider quotas; staging, smoke tests; explicit admin activation. Never change existing production directly from this spec.

## Scope/status
This document is an approved development specification, **not a claim that a working admin UI, database schema, integration, mail sender or deployable version already exists**.
