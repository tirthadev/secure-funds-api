# Secure Funds API

A small Symfony 8.1 API for moving funds between accounts with transactional integrity, idempotent retries, MySQL persistence, and Redis-backed idempotency response caching.

## What is included

- `POST /api/transfers` moves money between two accounts.
- `GET /api/accounts/{id}` returns account balance details.
- API-key authentication with the `X-API-Key` header.
- Required `Idempotency-Key` header for transfer creation.
- MySQL row-level pessimistic locks around account debit/credit.
- Database unique constraint on idempotency keys to protect concurrent retries.
- Redis cache for fast replay of completed idempotent transfer responses.
- Integration tests covering success, replay, idempotency conflict, insufficient funds, and authentication.

## Requirements

- Docker and Docker Compose, or PHP 8.4+, Composer, MySQL 8.4, and Redis 7.4.

## Run with Docker

```bash
docker compose up --build -d
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec app php bin/console app:seed-accounts
```

The API will be available at `http://localhost:8000`.

Seeded accounts:

- `018f7f2e-4f0d-7b31-a932-111111111111` with USD 1000.00
- `018f7f2e-4f0d-7b31-a932-222222222222` with USD 250.00

## Example transfer

```bash
curl -i -X POST http://localhost:8000/api/transfers \
  -H 'Content-Type: application/json' \
  -H 'X-API-Key: dev-secret' \
  -H 'Idempotency-Key: demo-transfer-001' \
  -d '{
    "from_account_id": "018f7f2e-4f0d-7b31-a932-111111111111",
    "to_account_id": "018f7f2e-4f0d-7b31-a932-222222222222",
    "amount_cents": 1250,
    "currency": "USD"
  }'
```

Check an account:

```bash
curl -i http://localhost:8000/api/accounts/018f7f2e-4f0d-7b31-a932-111111111111 \
  -H 'X-API-Key: dev-secret'
```

## Run locally without Docker

```bash
composer install
cp .env .env.local
# Edit DATABASE_URL, REDIS_DSN, APP_SECRET, and API_KEY in .env.local
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed-accounts
php -S 127.0.0.1:8000 -t public
```

## Tests

```bash
php bin/phpunit
```

The integration test suite uses SQLite under `APP_ENV=test` for fast local execution. Production/runtime configuration uses MySQL and Redis through `compose.yaml`.

## Design notes

Balances are stored as integer cents to avoid floating-point money errors. Transfers are recorded in a ledger table and keyed by `Idempotency-Key`, so safe client retries do not move money twice. During a transfer, both account rows are locked in deterministic UUID order before any balance changes, reducing deadlock risk under concurrent load.

Redis is deliberately best-effort: the database remains the source of truth for idempotency and ledger history, while Redis speeds up completed replay responses. If Redis is temporarily unavailable, transfers still proceed and the failure is logged.

## Production improvements I would add next

- Replace simple API-key auth with OAuth2/JWT or mTLS depending on the caller model.
- Add request signing for high-trust financial integrations.
- Add structured audit logging and immutable ledger export.
- Add rate limiting and anomaly detection per account/client.
- Add a real load test profile with MySQL isolation/deadlock monitoring.
- Add static analysis and mutation testing in CI.

## Time spent

Time spent: ~2 hours.

## AI tools and prompts used

AI assistance was used through ChatGPT/Codex to scaffold the Symfony project, implement the transfer domain, write tests, and prepare documentation.
