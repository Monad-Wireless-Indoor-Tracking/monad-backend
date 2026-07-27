# Monad backend

API for the MonadCount mobile instrument. Symfony 7.3 (PHP 8.3) + PostgreSQL 16.

- **Deployment**: `api.monad.dubec.dev` — project host (Hetzner CCX33), behind the host nginx that
  already terminates TLS for `monad.dubec.dev`. Container binds loopback only; see
  `docker-compose.deploy.yml`.
- **Storage**: Hetzner Object Storage (S3-compatible), the project's bucket — the same tenancy as
  the `csid` fleet CSI captures and the simulation artefacts. Configured by `S3_ENDPOINT` +
  `S3_USE_PATH_STYLE`; Hetzner has no wildcard certificate, so path-style addressing is required.
- **Session key layout**: `datasets/monad-app-sessions/{participantId}/{sessionId}/{filename}`,
  mirroring the fleet convention so a phone session and a radio capture are siblings in one bucket
  and joinable by session rather than by upload date.

## Surfaces

| Route | Purpose |
|---|---|
| `POST /api/storage/session-upload` | Stream one lab-session artefact to object storage. Streams first, `metadata.json` last — its presence marks the session complete. |
| `GET /api/lab/config` | The lab bundle: collector endpoint, access points, beacon plan, traffic profiles, clock policy. Authenticated (it carries AP credentials). |
| `GET /api/lab/time` | Coarse four-timestamp fallback. The real clock discipline runs over the collector's UDP socket, on the same path the data takes. |
| `/api/auth/*`, `/api/quest*` | Accounts and the quest schedule engine. |

## Lab bundle

`config/lab/lab.json` (gitignored; `lab.example.json` is the shape). Operator-authored, not a
Doctrine entity — it describes physical reality and is edited next to the hardware. See
`config/lab/README.md` for the fields that are easy to get wrong (`collector.host` must be a
literal IPv4; iOS monitors at most 20 beacon regions).

## Legal pages

- Terms & Conditions: `/terms`
- Privacy Policy: `/privacy-policy`
