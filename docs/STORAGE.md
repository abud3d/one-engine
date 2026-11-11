# Storage contract and limits

`DocumentStore` exposes `read(siteId, key)` and atomic
`update(siteId, key, callback)`. An absent document returns `[]`. Corruption,
unsafe paths, unsupported shapes, encoding errors, lock timeout and I/O failure
throw. Never interpret malformed storage as a new empty database.

Callbacks accept/return JSON-compatible arrays, must be pure and non-reentrant,
and must not call another store operation. Both adapters validate site/key IDs,
limit encoded documents to 2 MiB and JSON nesting to 64. Documents are scoped by
site; storage isolation is not authorization. Future services must enforce the
permission engine before calling adapters.

## JSON

- Private existing directory, mode 0700, outside the real public document root.
- Per-document stable `.lock` files, retained across replacements. Readers use
  shared locks; read-modify-write uses exclusive locks.
- Nonblocking `flock` with a monotonic bounded deadline, default 1000 ms.
- Temporary file in the same directory/filesystem, mode 0600; checked writes,
  flush and file fsync before atomic rename. Failed callbacks leave old data.
- Traversal, unsafe names, symlink files and non-file entries are refused.
- No HTTP-visible storage fallback. No multi-file transaction promise.

The parent directory must be trusted and owned by the service account; protection
against a malicious process running under that same OS identity is out of scope.
Ancestor aliases/other virtual hosts cannot be discovered reliably by PHP and
must be excluded by deployment configuration. Network filesystems and
multiple-writer hosts are unsupported. Atomic rename avoids partial reads, but
no directory fsync is performed: strict durability across sudden power loss is
not guaranteed. An interrupted process can leave an unused `.tmp`; remove it
only offline after checking that no writer is active. Do not delete live locks.

## SQLite

Optional `PDO_SQLite`. A fixed private `documents.sqlite` contains a composite
primary key `(site_id, document_key)`. Prepared statements avoid SQL construction
from IDs. `BEGIN IMMEDIATE` serializes read/modify/write, and failures roll back.
Busy timeout is 1000 ms. This foundation stores JSON documents in SQLite; it is
not a normalized content schema or query engine. Use a local filesystem and one
service account. The directory must be private, including journals and backups.

## MariaDB extension

No MariaDB adapter is shipped or advertised as passing tests. A later adapter
must use prepared statements, a unique composite site/key index, transactions
with row locking/upsert race handling, bounded lock waits, least-privilege DB
credentials and the same contract tests. Domain/application code must not depend
on SQL dialects. A future relational API requires its own contract/versioning
decision, not silent changes to this document interface.

## Recovery and migration

Before changing storage formats: freeze writes, snapshot the private directory,
verify checksums, restore to an isolated location, run consistency checks, then
switch configuration. Do not mix live JSON and SQLite writes. No automatic
database importer or production migration is included here.

For multi-object content deletion, initially store a small aggregate in one
document or introduce a tested transaction abstraction. Do not chain independent
file writes and call the result atomic. SQLite's document update still covers
one document through this interface; broader transactions need an explicit API.
