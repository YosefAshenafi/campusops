# CampusOps

**Project type:** Fullstack web application — ThinkPHP 8 REST API backend, Layui frontend, MySQL, Nginx — containerised with Docker Compose.

## Architecture overview

```
Browser / API client
        │
        ▼
  Nginx (port 8080)
        │  proxy_pass
        ▼
  PHP-FPM (ThinkPHP 8)
   ├─ Middleware chain
   │   ├─ RateLimitMiddleware   — per-IP throttle, 429 on excess
   │   ├─ AuthMiddleware        — Bearer token validation → attaches $request->user
   │   ├─ RbacMiddleware        — permission string checked against role table
   │   └─ SensitiveDataMiddleware — masks password_hash/salt/invoice_address for non-admins
   ├─ Controllers (thin — input parsing + response shape only)
   └─ Services (all business logic, throw \Exception with HTTP status codes)
        │
        ▼
  MySQL 8 (campusops DB)
```

All application state lives in MySQL. The PHP container has no persistent filesystem state — uploaded files are stored at a path configured via environment variable.

## Run with Docker

**Prerequisites:** [Docker](https://docs.docker.com/get-docker/) with Compose plugin (`docker compose version` ≥ 2). No local PHP, Composer, or xmllint installation required.

### First-time setup

```bash
docker compose build --no-cache
docker compose up -d
docker compose exec php php think migrate:run
docker compose exec php php think seed:run
```

Verify the stack is healthy before proceeding:

```bash
docker compose ps                     # all services should show "running"
curl -s http://localhost:8080/api/v1/ping
# → {"success":true,"code":200,...}
```

> **If you have `make`:** `make setup && make migrate && make seed` is equivalent.

### Start / stop

```bash
docker compose up -d        # start all services in the background
docker compose down         # stop and remove containers (data is preserved in volumes)
docker compose down -v      # stop and remove containers AND wipe all data volumes (full reset)
docker compose restart      # restart running containers
docker compose logs -f      # tail logs from all services
docker compose logs php     # PHP / application errors only
docker compose logs mysql   # database errors only
```

**App URL:** [http://localhost:8080](http://localhost:8080) (nginx maps host `8080` → container `80`).

## Verify the stack

After startup, confirm the API is reachable and the database is connected:

```bash
curl -s http://localhost:8080/api/v1/ping
```

Expected response:

```json
{"success":true,"code":200,"message":"pong"}
```

Then verify authentication works end-to-end:

```bash
curl -s -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"CampusOps1"}'
```

A successful response contains `data.access_token`. A `401` or connection refused error indicates a setup problem — check `docker compose logs php` and `docker compose logs mysql`.

> **Credentials are only available after `make seed` / `docker compose exec php php think seed:run`.** Without seeding, no user accounts exist.

## Default credentials

**Application** (after seeding; all accounts share the same password)

| Username | Password | Role |
|----------|----------|------|
| `admin` | `CampusOps1` | Administrator |
| `ops_staff1`, `ops_staff2` | `CampusOps1` | Operations staff |
| `team_lead` | `CampusOps1` | Team lead |
| `reviewer` | `CampusOps1` | Reviewer |
| `user1`–`user5` | `CampusOps1` | Regular user |

**MySQL** (defaults from `docker-compose.yml`)

| User | Password |
|------|----------|
| `root` | `root_secret` |
| `campusops` | `campusops_secret` |

## Tests

All tests run against an **SQLite in-memory database** — no running containers or external services required.

### Recommended: run_tests.sh wrapper

From the repository root:

```bash
./run_tests.sh
```

The script automatically prefers the Docker PHP container when it is running; it falls back to the local `backend/vendor/bin/phpunit` binary otherwise. Writes JUnit XML and a log to `test-results/`. `xmllint` must be available locally for the summary output (it is pre-installed in the PHP container; on macOS/Linux install via your package manager).

### Manual: direct PHPUnit

```bash
# Inside the Docker container
docker compose exec php ./vendor/bin/phpunit --configuration /var/www/phpunit.xml --testdox

# Without Docker (requires backend/vendor to exist)
./backend/vendor/bin/phpunit --configuration phpunit.xml --testdox
```

Three suites are defined in `phpunit.xml`:

| Suite | Directory | What it covers |
|-------|-----------|----------------|
| `unit` | `unit_tests/` | Service-layer logic (database via SQLite) |
| `api`  | `API_tests/`  | HTTP endpoint tests via ThinkPHP dispatcher + middleware |
| `e2e`  | `e2e_tests/`  | End-to-end scenario flows (service + model layer) |

---

More detail: [Design](docs/design.md) · [API](docs/api-spec.md) · [Assumptions](docs/questions.md)
