# Monad backend

API for the MonadCount mobile instrument. Symfony 7.3 (PHP 8.3) + PostgreSQL 16.

- **Deployment**: `api.monad.dubec.dev` — project host (Hetzner CCX33), behind the host nginx that
  already terminates TLS for `monad.dubec.dev`. Container binds loopback only; see
  `docker-compose.deploy.yml`.
- **Storage**: Hetzner Object Storage via `async-aws/s3` (no AWS SDK, no AWS anything), the project's bucket — the same tenancy as
  the `csid` fleet CSI captures and the simulation artefacts. Configured by `HETZNER_S3_ENDPOINT` +
  `HETZNER_S3_USE_PATH_STYLE`; Hetzner has no wildcard certificate, so path-style addressing is required.
- **Session key layout**: `datasets/monad-app-sessions/{participantId}/{sessionId}/{filename}`,
  mirroring the fleet convention so a phone session and a radio capture are siblings in one bucket
  and joinable by session rather than by upload date.

## Surfaces

| Route | Purpose |
|---|---|
| `POST /api/storage/session-upload` | Stream one lab-session artefact to object storage. Streams first, `metadata.json` last — its presence marks the session complete. |
| `GET /api/lab/config` | The lab bundle: collector endpoint, access points, beacon plan, traffic profiles, clock policy. Authenticated (it carries AP credentials). |
| `GET /api/lab/time` | Coarse four-timestamp fallback. The real clock discipline runs over the collector's UDP socket, on the same path the data takes. |
| `POST /api/lab/ground-truth` | Ground-truth check-in/out scans from participant devices, single or batched. Idempotent on `scan_nonce`. |
| `GET /api/lab/ground-truth/{labSessionId}` | Live room-wide people tally for one session, per zone and overall. Cheap to poll. |
| `/api/auth/*`, `/api/quest*` | Accounts and the quest schedule engine. |

## Ground truth (the people channel)

Every other stream counts *phones*. This one counts *people* — it only advances when a human points
a camera at a code taped to a doorframe — and phone-vs-person bias is itself the quantity a later
experiment sets out to measure, so the two must never be derived from each other.

The wire fields are the pre-registered `ground_truth.tsv` columns verbatim, in snake_case
(`mono_ns, wall_ms, lab_session_id, participant_token, zone_id, direction, site, scan_nonce,
recording_session_id`) — deliberately unlike the camelCase quest DTOs, so one spelling runs from the
printed code through SQLite, TSV, this API and the analysis join.

- **Idempotency** is a `UNIQUE` index on `scan_nonce`, not application-level checking. Ten to twelve
  handsets each re-upload their complete set on every flush, concurrently. Duplicates keep the
  earliest `mono_ns`.
- **Contradictions** — same nonce, different `(participant_token, zone_id, direction)` — are
  pre-registration exclusion **E3**. The stored row is never overwritten; the refused claim is
  persisted to `ground_truth_conflicts` and surfaced in the aggregate, because the only moment a
  human can still find out what happened is while the session is running. Logged, never reconciled
  by judgement.
- **Privacy posture is count-without-identify.** `participant_token` is an opaque pseudonym. There
  is no foreign key to `users`, so a join from a scan to an account is not expressible in the
  schema. No names, e-mails, MACs or device identifiers are accepted, stored or returned.

Occupancy is reported two ways because they disagree informatively: `checked_in` (per participant,
latest scan wins — idempotent under a double tap) and `net_sum` (the literal cumulative sum the
pre-registration defines). At room level, `overall.checked_in` counts each participant once by their
latest scan anywhere, while `overall.zone_sum` adds the zones; the gap is exactly the set of people
who entered a new zone without scanning out of the old one.

## Tests

```bash
docker compose up -d postgres
cd backend/monad-backend
APP_ENV=test php bin/console doctrine:database:create --if-not-exists
APP_ENV=test php bin/console doctrine:migrations:migrate --no-interaction
php vendor/bin/phpunit
```

Integration tests run against a real PostgreSQL on purpose: the idempotency guarantee *is* a unique
index, and a double that returns whatever it was told cannot fail the way the database can.

## Lab bundle

`config/lab/lab.json` (gitignored; `lab.example.json` is the shape). Operator-authored, not a
Doctrine entity — it describes physical reality and is edited next to the hardware. See
`config/lab/README.md` for the fields that are easy to get wrong (`collector.host` must be a
literal IPv4; iOS monitors at most 20 beacon regions).

## Legal pages

- Terms & Conditions: `/terms`
- Privacy Policy: `/privacy-policy`
