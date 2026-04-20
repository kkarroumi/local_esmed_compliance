# Changelog

All notable changes to `local_esmed_compliance` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
