# local_esmed_compliance

Moodle plugin that covers the French regulatory obligations that no community
plugin addresses today:

- **Certifiable session tracking** — heartbeat AJAX, explicit open/close
  timestamps, timeout detection.
- **Multi-source aggregation** — sessions, activities, assessments, completion
  into denormalised tables optimised for reporting.
- **Attendance certificates** (French *attestation d'assiduité*, article
  D.6353-4) — sealed PDF with SHA-256 fingerprint and verification QR code.
- **Funder statements** (CPF, France Travail, OPCO) — PDF and CSV.
- **WORM archiving** (Write Once Read Many) with a 5-year retention window.
- **Real-time admin dashboard** with dropout alerts.
- **REST webservices** for integration with external TMS/HRIS (Digiforma,
  Dendreo, Ypareo).

The plugin is designed to coexist with `block_dedication`,
`block_configurable_reports`, `mod_customcert`, `mod_attendance` and
`Edwiser Reports` without overlapping their scope.

> Regulatory distinction: `mod_customcert` already covers the *attestation de
> fin de formation* (article L.6353-1). This plugin produces the detailed
> *attestation d'assiduité* required by article D.6353-4 for FOAD training
> funded by public or mutualised funds.

## Requirements

- Moodle 4.3.0 or later (tested with 4.3.9).
- PHP 8.1 or later.
- MariaDB 10.6+, MySQL 8+ or PostgreSQL 13+.
- Cron enabled (several scheduled tasks run from every 5 minutes to monthly).
- Optional: S3-compatible object storage (AWS S3, OVH, Scaleway) with Object
  Lock support for WORM archival.

## Installation

### From Git

```bash
cd /path/to/moodle/local
git clone https://github.com/kkarroumi/local_esmed_compliance.git esmed_compliance
```

Then visit **Site administration → Notifications** to trigger the installer,
or run the CLI upgrader:

```bash
php admin/cli/upgrade.php
```

### From a ZIP release

Upload the release archive through **Site administration → Plugins →
Install plugins** and follow the prompts.

## Configuration

The settings page is delivered in iteration 2. In the meantime the installer
seeds sensible defaults (heartbeat 30 s, session timeout 10 min, per-module
delta cap 15 min, retention 5 years, local filesystem adapter).

## Development

A ready-to-run development stack is provided.

```bash
docker compose up -d
# Moodle web UI: http://localhost:8080
# Database:      localhost:3306 (user: moodle, password: moodle)
```

The plugin directory is bind-mounted at
`/bitnami/moodle/local/esmed_compliance`. Edit files on the host and reload
the browser.

### Running tests

Tests are added progressively:

```bash
# PHP lint of every file shipped by the plugin.
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l

# PHPUnit (once iteration 3 lands).
vendor/bin/phpunit local/esmed_compliance/tests/

# Behat (once iteration 5 lands).
vendor/bin/behat --config .../behat/behatconfig.php --tags=@local_esmed_compliance
```

## Roadmap

| Iteration | Scope |
|-----------|-------|
| 1 (current) | Plugin skeleton, XMLDB schema, lang files, dev stack. |
| 2 | Capabilities, settings UI, Privacy API. |
| 3 | Session tracker + heartbeat + timeout task. |
| 4 | Activity aggregation and assessment categorisation. |
| 5 | Attendance certificate PDF + sealing + public verification endpoint. |
| 6 | Funder statements (PDF + CSV). |
| 7 | WORM archiving and real-time dashboard. |
| 8 | REST webservices and CI pipeline. |

## License

GNU General Public License v3.0 or later. See [LICENSE](LICENSE).
