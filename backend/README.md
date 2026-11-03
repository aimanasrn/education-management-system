# Laravel API

See the [project README](../README.md) for Docker setup, PostgreSQL configuration, administrator creation, API endpoints and verification commands.

Docker uses Redis for cache/rate limits/sessions/queues, a queue worker, private Garage storage for new admission evidence and optional JWT API authentication. Existing browser Sanctum tokens and local documents remain supported. Run `php docker/setup-infrastructure.php` from the repository root before building/upgrading Compose; it preserves existing nonempty secrets.

From `backend/`, run `php artisan test --compact`. Regular tests use isolated in-memory SQLite and skip two opt-in live infrastructure tests. Run `vendor/bin/pint --dirty --format agent` after PHP changes. Production images omit test dependencies.

After Docker startup:

```bash
docker compose exec backend php artisan ems:check-infrastructure --queue
```

Real-service feature tests require `RUN_LIVE_INFRA=1`, an isolated PostgreSQL database whose name contains `_qa_`, a running Redis worker and Garage. Never point these tests at the working database. They remove unique temporary objects/cache keys after checking real upload/download authorization and privacy.
