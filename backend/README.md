# Monad Backend API

## Generate OpenAPI Specification

```bash
docker exec monad_symfony php bin/console api:openapi:export --output=/var/www/html/openapi.json
```

## Run Fixtures (seeders)

```bash
docker exec monad_symfony php bin/console doctrine:fixtures:load
```
