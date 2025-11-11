# Monad Backend API

## Generate OpenAPI Specification

```bash
docker exec monad_symfony php bin/console api:openapi:export --output=/var/www/html/openapi.json
```

Edit `src/OpenApi/AuthDecorator.php` to add or modify endpoints.
