# Changelog

All notable changes to `local_esmed_compliance` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added (iteration 8 — REST webservices + CI pipeline)
- `classes/external/verify_token.php`: anonymous-safe webservice
  endpoint that wraps the existing `archive\verifier` and returns the
  integrity status (`valid`, `tampered`, `missing`, `unknown`) plus
  sealed/computed hashes for a public token. Callable by any
  authenticated user — the token itself is the authority.
- `classes/external/get_dashboard_metrics.php`: mirrors the Mustache
  dashboard context over webservice, gated by
  `local/esmed_compliance:viewdashboard` at the system context so UI
  and API consumers see the same data under identical authorisation.
- `classes/external/get_learner_summary.php`: (userid, courseid)
  summary combining session seconds, activity dwell time, module
  views, modules touched and sealed-attestation count. Authorises
  both learners (via `viewownreports` on their own user context) and
  managers (via `viewdashboard` at course context) with a single
  explicit OR, so learners cannot peek at each other.
- `db/services.php`: registers the three functions and defines the
  `local_esmed_compliance_service` restricted-users webservice so
  deployments can provision per-funder API tokens.
- `tests/external/verify_token_test.php`,
  `tests/external/get_dashboard_metrics_test.php`,
  `tests/external/get_learner_summary_test.php`: parameter-validation
  and capability-gate coverage, using `external_api::clean_returnvalue`
  to verify the declared return structures round-trip cleanly.
- `.github/workflows/ci.yml`: `moodle-plugin-ci` matrix running
  phplint / phpcpd / phpmd / codechecker / phpdoc / validate /
  savepoints / mustache / grunt / phpunit across PHP 8.1–8.3 and
  Moodle 4.03–4.05 (LTS) on both pgsql and mariadb. Plugin version
  bumped to `2026042007`.

### Added (iteration 7 — WORM integrity + compliance dashboard)
- `db/install.xml` + `db/upgrade.php`: new `local_esmed_integrity_event`
  table — an append-only log of archive integrity verdicts (`valid`,
  `tampered`, `missing`) so the history itself constitutes an audit
  trail. Plugin version bumped to `2026042006`.
- `classes/archive/integrity_checker.php`: re-hashes the least recently
  verified archives in configurable batches and writes one event per
  check. Uses a portable `COALESCE(...,0)` + ASC ordering so never-
  checked rows are picked first, and a self-join `NOT EXISTS` query to
  count the latest-status per archive without relying on a single
  column of cached state.
- `classes/task/verify_archive_integrity_task.php` + `db/tasks.php`:
  scheduled every six hours (offset to minute 37) to drain the archive
  index without competing with session or aggregation tasks.
- `classes/dashboard/metrics_provider.php`: collects point-in-time
  counters — open sessions, sessions closed in the last 24h, hours
  recorded today, archive totals split by type, unacknowledged / 7-day
  alerts, archives in each integrity status — and returns a single
  ready-to-render bundle.
- `classes/output/renderer.php` + `templates/dashboard.mustache`:
  Mustache-based dashboard with four counter cards and `data-field`
  attributes so the JS can update values in place. Cards with alerts
  or integrity problems pick up an `esmed-card-warn` modifier.
- `dashboard.php` (guarded by `viewdashboard` capability) registers the
  AMD module with endpoint, sesskey and refresh interval, then renders
  the dashboard with current metrics.
- `ajax/metrics.php`: JSON endpoint (session-cookie + sesskey) returning
  the same context the renderer consumes, so polling updates the page
  without a full re-render.
- `amd/src/dashboard.js` (+ minified build): polls the metrics
  endpoint at the configured cadence while the tab is visible, walks
  every `data-field` path and updates `.textContent` in place.
- `settings.php`: adds two new settings (`dashboard_refresh_seconds`,
  `integrity_batch_size`) and registers the dashboard as an
  `admin_externalpage` under the plugin category.
- `tests/{integrity_checker,metrics_provider}_test.php`: 9 PHPUnit
  tests covering intact / tampered / missing detection, unknown-adapter
  handling, never-checked-first batch ordering, empty-install zeroes,
  session counters across the 24h window, archive type splits and
  alert filtering.
- Lang FR/EN extended with 20 dashboard + task + settings strings.

### Added (iteration 6 — funder statements: bordereau financeur PDF + CSV)
- `classes/funder/funder_link_repository.php`: CRUD over
  `local_esmed_funder_link` with whitelisted funder types
  (`CPF`, `FT`, `OPCO`, `REGION`, `AUTRE`). `upsert` preserves previously
  stored attributes when the caller omits them, so partial edits behave
  like patches.
- `classes/funder/{bordereau_payload, bordereau_builder}.php`: immutable
  payload value object plus builder that rolls up every enrolled learner
  on a course, summing closed-session durations clipped to the funder
  period (`[session, session] ∩ [periodstart, periodend]`). Learners are
  stably sorted by `[lastname, firstname, userid]` so regenerating a
  bordereau on unchanged data produces byte-identical output.
- `classes/funder/{bordereau_renderer, csv_bordereau_renderer,
  tcpdf_bordereau_renderer}.php`: renderer contract plus two concrete
  implementations. The CSV renderer is RFC 4180 compliant (UTF-8 BOM,
  CRLF line endings, RFC-style quoting) with thirteen learner columns
  and optional verification-token footer rows. The TCPDF renderer emits
  a landscape A4 document with organisation, funder, course and learners
  blocks plus a QR-coded verification URL.
- `classes/funder/bordereau_service.php`: orchestrator sealing the PDF
  and CSV renditions as an atomic pair — one payload snapshot, two
  unique verification tokens, a shared `bordereau_group` id recorded in
  `metadata_json` so an audit can pull both formats of the same
  reconciliation event. Filename layout
  `bordereau/YYYY/MM/fX_cY_<tokenprefix>.<ext>`.
- `tests/fixtures/fake_bordereau_renderer.php` +
  `tests/{funder_link_repository, bordereau_builder, bordereau_service,
  csv_bordereau_renderer}_test.php`: 21 PHPUnit tests covering funder
  link CRUD (create/update/remove/reject), period clipping with
  straddling sessions, open-session exclusion, PDF+CSV pair sealing
  (group id, distinct tokens, stored-bytes hash match, per-renderer URL
  wiring), retention defaulting and filename layout. CSV tests verify
  the BOM, RFC 4180 quoting, deterministic output and token footer.
- Lang FR/EN extended with 24 bordereau headings, column labels and
  metadata strings plus `funder_link_notfound`.
- Plugin version bumped to `2026042005`.

### Added (iteration 5 — attestation d'assiduité and public verification)
- `classes/archive/storage_adapter.php` interface + `local_storage_adapter`
  implementation: write-once filesystem backend with safe relative-path
  normalisation, atomic `.tmp` + `rename` writes and idempotent store
  (identical bytes under the same name succeed; divergent bytes raise).
- `classes/archive/archive_repository.php`: sealed document persistence
  keyed by `verification_token` (unique), with a hardened
  `generate_unique_token()` that retries on collision before failing.
- `classes/attestation/{attestation_payload, attestation_builder,
  attestation_renderer, tcpdf_attestation_renderer}.php`: payload value
  object, evidence gatherer summing closed sessions and indexed
  assessments for a (user, course), renderer interface and default
  TCPDF-backed implementation producing an A4 attestation d'assiduité
  with organisation block, learner block, session table, assessment
  table, signatory and a QR-coded verification URL.
- `classes/attestation/attestation_service.php`: end-to-end sealing —
  build → allocate unique token → render → `sha256` → store → index.
  Retention window frozen at seal time (default 5 years). Filename
  layout `attestation/YYYY/MM/uX_cY_<tokenprefix>.pdf`.
- `classes/archive/verifier.php` + `verify.php`: public, unauthenticated
  endpoint (`NO_MOODLE_COOKIES`) returning one of four statuses:
  `unknown`, `missing`, `tampered`, `valid`. Reveals only archive type,
  seal timestamp and both hashes — never learner identity.
- `tests/fixtures/fake_{storage_adapter,attestation_renderer}.php` +
  `tests/{archive_repository,attestation_service,verifier}_test.php`:
  13 PHPUnit tests covering insert / token round-trip, unique token
  format, find-by-user-course filtering, end-to-end seal with
  hash-to-stored-bytes consistency, duration capture from closed
  sessions, retention default, filename layout, and the four verifier
  outcomes (unknown, valid, tampered, missing, unknown adapter).
- Lang FR/EN extended with verification-UI and token-generation strings.
- Plugin version bumped to `2026042004`.

### Added (iteration 4 — activity aggregation and assessment indexing)
- `db/install.xml` + `db/upgrade.php`: new `local_esmed_assessment_tag`
  table letting compliance managers explicitly classify each cmid as
  `quiz_pedago`, `devoir_formatif`, `examen_blanc` or
  `evaluation_sommative`. Plugin version bumped to `2026042003`.
- `classes/activity/time_calculator.php`: pure, framework-free function
  turning an ordered module-view stream into per-cmid aggregates, with a
  configurable transition cap (defuses pages left open overnight) and
  tail credit so a single view is not counted as zero.
- `classes/activity/activity_repository.php`: incremental upsert on
  `{local_esmed_activity_log}` preserving `first_access` / `last_access`
  bounds and a separate `set_completion_state` path that creates a bare
  row when completion fires before any view.
- `classes/activity/aggregator.php`: resumable driver reading new
  `target=course_module, action=viewed` entries from
  `{logstore_standard_log}` strictly above a checkpoint stored in plugin
  config, grouping per-user and folding into the activity log.
- `classes/assessment/{tag_repository,assessment_repository,indexer}.php`:
  tag CRUD with a whitelisted category set, deduplicated index on
  (source table, source attempt id), and an indexer that refuses to
  record attempts on untagged modules (no implicit classification).
- `classes/task/aggregate_activity_task.php` + `db/tasks.php`:
  scheduled every fifteen minutes to drain the logstore.
- `classes/observer.php` + `db/events.php`: subscribe to
  `\core\event\course_module_completion_updated`,
  `\mod_quiz\event\attempt_submitted` and
  `\mod_assign\event\submission_graded` to reflect completion state and
  index graded attempts in real time.
- `tests/time_calculator_test.php`, `tests/activity_aggregator_test.php`
  and `tests/assessment_indexer_test.php`: 15 PHPUnit tests covering
  cap / tail rules, accumulation across passes, completion without view,
  untagged-module short-circuit, zero-max-score percent safety, attempt
  deduplication and category whitelisting.
- Lang FR/EN extended with the new scheduled task name and the four
  assessment category labels.

### Added (iteration 3 — session tracking)
- `classes/session/session_repository.php`: data access layer for
  `{local_esmed_sessions}` with atomic heartbeat/close (`WHERE session_end
  IS NULL`) to defuse races between the AJAX beacon and the timeout task.
- `classes/session/tracker.php`: business logic exposing
  `open_session` (idempotent), `record_heartbeat`, `close_session` and
  `close_stale_sessions`. Distinguishes `timeout` from `crash` based on
  whether the session ever received a heartbeat.
- `classes/observer.php` + `db/events.php`: subscribe to
  `\core\event\user_loggedin` and `\core\event\user_loggedout` to open
  and close certifiable sessions alongside the Moodle session.
- `classes/task/session_timeout_task.php` + `db/tasks.php`: scheduled
  every five minutes to close sessions that exceed the configured idle
  window.
- `ajax/heartbeat.php`: secured endpoint (session cookie + sesskey)
  accepting `heartbeat` and `close` actions; releases the Moodle session
  lock early via `\core\session\manager::write_close()` to avoid
  contention with concurrent user requests.
- `amd/src/heartbeat.js` + matching build artefact: AMD module sending a
  keep-alive beacon every 30 seconds while `document.visibilityState ===
  'visible'`, and a `navigator.sendBeacon` close on `beforeunload` /
  `pagehide`.
- `lib.php`: `local_esmed_compliance_before_standard_top_of_body_html`
  callback injecting the AMD module on every page for authenticated
  non-guest users.
- `tests/session_tracker_test.php`: 9 PHPUnit tests covering open
  (idempotency), heartbeat, close, stale categorisation (timeout vs
  crash) and WHERE-guard on closed sessions.
- Lang FR/EN extended to 113 keys each (task name, closure types).

### Added (iteration 2 — capabilities, settings, privacy)
- `db/access.php` with six capabilities:
  `viewdashboard`, `generateattestation`, `manageconfig`,
  `viewownreports`, `exportfundedata`, `managearchive`.
- Admin settings UI split into three pages (General, Branding, Archive)
  covering tracking cadence, retention, training-provider identity
  (logo, legal name, SIRET, NDA, address, signatory) and storage adapter
  (local filesystem or S3 with endpoint / region / bucket / credentials).
- `classes/privacy/provider.php` implementing the metadata, plugin and
  core-userlist providers. Deletion policy redacts direct identifiers on
  session evidence (IP, user-agent) to reconcile GDPR with the legal
  retention window under article L.6353-1; alerts are fully deleted.
- PHPUnit test suite `tests/privacy_provider_test.php` covering metadata,
  contexts-for-userid, users-in-context, export and both deletion paths.
- Lang FR/EN extended to 107 keys each (settings, privacy metadata and
  subcontexts).

### Added (iteration 1 — skeleton)
- Plugin metadata (`version.php`) targeting Moodle 4.3+ and PHP 8.1+.
- XMLDB schema (`db/install.xml`) with six tables:
  `local_esmed_funder_link`, `local_esmed_sessions`, `local_esmed_activity_log`,
  `local_esmed_assessment_index`, `local_esmed_archive_index`, `local_esmed_alerts`.
- Post-install hook seeding default configuration values.
- French and English language files covering plugin name, settings,
  capabilities and Privacy API metadata.
- Plugin icon, base stylesheet, GPL v3 license and documentation stubs.
- `docker-compose.yml` targeting a Moodle 4.3 + MariaDB 10.6 dev stack.
