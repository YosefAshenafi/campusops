# CampusOps - Unified Campus Operations & Logistics Management Portal

## Overview

CampusOps is a full-stack campus operations management portal built with PHP 8.2 + ThinkPHP 8 (backend), JavaScript + Layui 2.9 (frontend), MySQL 8, Nginx, and Docker Compose. It provides activity lifecycle management, order lifecycle with state machine, logistics tracking, violations/appeals, search, recommendations, dashboards, export, and RBAC-based security.

## Quick Start

### Prerequisites

- Docker & Docker Compose

### First-time Setup

```bash
make setup
```

This will:
1. Build Docker images
2. Start all services (nginx, php, mysql, node)
3. Install PHP dependencies via Composer
4. Download and install Layui frontend library

### Start / Stop

```bash
make up      # Start services
make down    # Stop services
make restart # Restart services
make logs    # Tail logs
```

### Database Setup

```bash
make migrate  # Run migrations
make seed     # Seed test data
```

### Access

- **Application:** http://localhost:8080
- **Login:** Use any seeded user (password: `CampusOps1`)
  - `admin` (administrator) - full access including refunds
  - `ops_staff1` / `ops_staff2` (operations_staff) - order/activity management
  - `team_lead` (team_lead) - tasks, staffing, checklists
  - `reviewer` (reviewer) - approvals, violations, address corrections
  - `user1`-`user5` (regular_user) - browse, signup, view

### Running Tests

```bash
./run_tests.sh
```

This runs PHPUnit with all configured test suites (unit, API, e2e) and outputs JUnit XML to `test-results/`.

### Shell Access

```bash
make shell-php    # PHP container shell
make shell-mysql  # MySQL CLI
```

## Architecture

```
repo/
  backend/             # ThinkPHP 8 application
    app/
      controller/      # HTTP controllers
      service/         # Business logic services
      model/           # Eloquent-style models
      middleware/       # Auth, RBAC, rate limiting, sensitive data masking
      command/         # CLI commands (auto-cancel orders, etc.)
      validate/        # Input validation rules
    config/            # App, DB, cache, route, log, middleware config
    database/
      migrations/      # Schema migrations
      seeds/           # Database seeders
    route/app.php      # All API routes
  frontend/
    public/            # HTML entry points (index.html, login.html)
    src/
      modules/         # Layui JS modules (activities, orders, shipments, etc.)
      views/           # HTML view templates
      assets/css/      # Stylesheets
      lib/layui/       # Layui library
  API_tests/           # API integration tests
  e2e_tests/           # End-to-end flow tests
  unit_tests/          # Unit tests
  docker/              # Docker configs (Nginx, PHP Dockerfile)
  phpunit.xml          # PHPUnit configuration
  docker-compose.yml   # Docker Compose stack definition
```

## Key Features

- **Activity Lifecycle:** Draft -> Published -> In Progress -> Completed -> Archived (with versioning and change log)
- **Order State Machine:** Placed -> Pending Payment (30-min auto-cancel) -> Paid -> Ticketing -> Ticketed -> Closed/Canceled
- **Refund:** Administrator-only (explicit role enforcement)
- **Closed-order Address Correction:** Request + Reviewer-approval workflow only
- **Logistics:** Shipment tracking with scan events, delivery confirmation, exception reporting
- **Violations & Appeals:** Point system with rules, multi-stage review, appeal flow
- **Search:** Full-text + Pinyin + spell correction, sort by relevance/recency/popularity, field-level highlighting
- **Recommendations:** Personalized with per-tag diversity cap (40%), cold-start fallback, multi-signal scoring
- **Export:** PNG, PDF, XLSX with watermarks
- **RBAC:** Role-based access control with object-level authorization
- **Security:** Sensitive data masking, encrypted invoice addresses, rate limiting, account lockout

## Configuration

- Backend config: `backend/config/`
- Environment: `backend/.env` (copy from `.env.example` in the repo root)
- Docker: `docker-compose.yml`

## Documentation

- [Design Specification](docs/design.md)
- [API Specification](docs/api-spec.md)
- [Assumptions & Decisions](docs/questions.md)
