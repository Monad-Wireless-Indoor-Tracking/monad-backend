# Monad

Mobile app backend for indoor wireless tracking.

## Stack

- Backend: Symfony 7.3 (PHP 8.3)
- Database: PostgreSQL 16
- Infrastructure: Hetzner CCX33 (docker compose on the shared `monad` network) + Hetzner Object Storage
- CI/CD: GitHub Actions

## Deployment

Push to `main` builds and publishes the image to GHCR (`.github/workflows/docker.yml`).
Deployment is an Ansible play run against the project host — see `deploy/README.md` and
`infra/ansible/roles/monad_api` in the monad-knowledge repository. Database migrations run from the
container entrypoint on boot.

## Domain

- API: https://api.monad.dubec.dev
- Terms & Conditions: https://api.monad.dubec.dev/terms
- Privacy Policy: https://api.monad.dubec.dev/privacy-policy
