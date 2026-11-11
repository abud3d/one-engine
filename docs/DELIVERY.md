# Foundation delivery

## Scope delivered

Fresh PHP 8.3+ modular foundation, objects/relations/capabilities/permissions,
JSON and SQLite document adapters, a read-only RTL homepage, local static/hybrid/
dynamic build proofs, synthetic site catalog, per-site SEO, secure response
defaults, source audit, inventory and phased migration/provenance ledger.

The source repository was reviewed read-only at
`1b5c44b21bc328fef7bc4645ce3140dba0ed13d1`. Target access was initially 404;
after the user reviewed integration access, GitHub reported push/admin rights
and the existing target main commit was fetched. All implementation work is on
`foundation/one-engine`, based on target commit
`2a6b801b8a8840b993d68eb1b1256026e152b261`.

## Migration outcome

- **Ported verbatim:** none; no legacy source files or history copied wholesale.
- **Reimplemented:** atomic JSON pattern, security primitives and policy concepts,
  with attribution in NOTICE and MIGRATION.
- **New:** typed core, default-deny site-scoped permissions, SQLite, release
  builder, independent runtime, synthetic central catalog, SEO and tests.
- **Remaining in arbnt:** accounts/mail, posts/comments/communities, moderation,
  favorites/notifications/reports, admin dashboard, branding and hosting tools.
- **Excluded:** secrets, user records, production databases and all VPS actions.

MariaDB, persistent content applications, real central management and remote
publishing are not implemented. Hybrid demonstrates a static homepage and one
dynamic health route; it is not a personalized application yet.

## Validation record

Local validation uses official PHP CLI containers (PHP 8.3.36 on Debian and
PHP 8.4.27 on Alpine), with the repository mounted read-only and synthetic data
in temporary directories. All **28 tests passed on both versions**.
Behavioral checks cover permission denial/precedence, site isolation, unsafe
paths, corrupt JSON, bounded locks, parallel increments for both adapters,
callback rollback, escaping, route/HTTP restrictions, independent artifacts and
manifest contents. Source PHP and JavaScript syntax checks also passed; two
legacy workflow assumptions failed and are recorded in SOURCE_AUDIT.

An additional local Chromium/Playwright smoke check passed at desktop 1440px
and mobile 390px widths: homepage rendered, no horizontal overflow or JavaScript
page errors, and the health navigation returned the expected JSON. Browser tools
are validation-only and are not dependencies of the product or its CI suite.

The target GitHub Actions workflow is configured for the same suite on PHP 8.3
and 8.4, plus lint, structure checks and builds of all three modes.

## Remote delivery blocker

GitHub repository metadata reports the user's push/admin rights, but actual
integration writes returned HTTP 403 `Resource not accessible by integration`
for branch and tree creation. A separate Git push dry-run also returned 403.
The work is committed locally; no remote branch, PR or Actions run has been
created. The user was asked to review the integration's Contents, Pull requests
and Workflows write access. After access is restored, push the existing branch,
open a PR against main, and record its actual Actions result here.

No deployment was performed, and no PR was merged.
