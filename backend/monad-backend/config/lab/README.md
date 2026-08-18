# Lab bundle

`lab.json` is the operator-authored description of the physical rig, served to the mobile
instrument at `GET /api/lab/config`. Copy `lab.example.json` to `lab.json` and edit.

It is **not** committed: `access_points[].password` is a real credential, and the collector address
describes a private network. `lab.example.json` is the shape; `lab.json` is the deployment.

Fields worth understanding rather than copying:

- `collector.host` must be a **literal IPv4 address**. An experiment AP normally carries no DNS, and
  the phone's socket is pinned to that interface, so a hostname would fail to resolve on exactly
  the path the data takes.
- `beacons.uuid` is the anchors' shared iBeacon proximity UUID. It is what a backgrounded iOS app
  filters on; without it the app cannot witness anything, because iOS never surfaces beacons by
  device name.
- `beacons.majors` groups anchors into CoreLocation regions. iOS monitors at most **20 regions per
  app**, so use `major` for floors or zone clusters and let ranging resolve the individual anchor.
- `advertise.namespace_uuid` is the base of the phone's identity broadcast (a 128-bit service
  UUID). The phone replaces the **last four bytes** with its 16-bit participant key and 16-bit
  session key, so keep those bytes zero in the bundle. The fleet's passive scan matches on the
  first twelve bytes. An empty string disables broadcasting on this deployment. `interval_ms` is
  the *commanded* interval — Android rounds it onto its advertising buckets and iOS cannot set
  one at all; the accepted value is recorded per session.
- `traffic_profiles[].rate_hz` is the *commanded* pace. The delivered pace is measured separately
  and reported per session — a source that does not report its realised rate is not usable as a
  sampling axis.
- Bump `version` whenever you change anything. The app caches the last good bundle and uses the
  version to decide it has something newer.
