# CampusOps Implementation Plan

## Overview

This document is the master implementation plan for CampusOps — a Unified Campus Operations & Logistics Management Portal. Every phase runs inside Docker. No local PHP/MySQL/Node installs are needed. The plan is designed for iterative execution: each phase produces a working, testable increment.

**Tech Stack:** PHP 8.2 + ThinkPHP 8 | JavaScript + Layui 2.9 | MySQL 8 | Nginx | Docker Compose

**Source Documents:**
- `docs/design.md` — Full design specification
- `docs/api-spec.md` — REST API contract
- `docs/questions.md` — Assumptions and solutions for ambiguous requirements
- `metadata.json` — Technology choices and constraints

---

## Phase 0: Docker Environment & Project Scaffolding

**Goal:** A running Docker stack with ThinkPHP serving a "Hello CampusOps" page via Nginx, connected to MySQL. This is the foundation everything else builds on.

### 0.1 Docker Compose Stack

Create `docker-compose.yml` with four services:

| Service | Image / Build | Port | Purpose |
|---------|---------------|------|---------|
| `nginx` | nginx:1.25-alpine | 8080:80 | Reverse proxy, serves static assets |
| `php` | Custom Dockerfile (PHP 8.2-FPM + extensions) | 9000 (internal) | ThinkPHP application |
| `mysql` | mysql:8.0 | 3306 (internal) | Database |
| `node` | node:20-alpine | — | Frontend asset build (Layui, JS bundling) |

**Files to create:**
```
repo/
├── docker-compose.yml
├── docker/
│   ├── nginx/
│   │   └── default.conf          # Nginx vhost config
│   ├── php/
│   │   └── Dockerfile            # PHP-FPM + composer + extensions (pdo_mysql, mbstring, gd, openssl, bcmath)
│   │   └── php.ini               # Upload limits, timezone, error reporting
│   └── mysql/
│       └── init.sql              # Initial DB creation: campusops database + utf8mb4
├── .env                          # DB credentials, app key, debug mode
└── .dockerignore
```

**Validation:** `docker compose up -d` → all containers healthy. `curl localhost:8080` returns a response.

### 0.2 ThinkPHP Project Init

Inside the `php` container, install ThinkPHP via Composer:

```
repo/
├── backend/
│   ├── composer.json              # ThinkPHP 8.x + dependencies
│   ├── app/
│   │   ├── controller/
│   │   │   └── Index.php          # "Hello CampusOps" test controller
│   │   ├── model/
│   │   ├── service/
│   │   ├── middleware/
│   │   ├── validate/
│   │   └── common.php
│   ├── config/
│   │   ├── app.php
│   │   ├── database.php           # MySQL connection from .env
│   │   ├── route.php
│   │   └── middleware.php
│   ├── route/
│   │   └── app.php                # API route definitions
│   ├── public/
│   │   └── index.php              # Entry point
│   └── runtime/                   # Cache/logs (gitignored)
```

**Validation:** `curl localhost:8080/api/v1/ping` returns `{"success":true,"message":"CampusOps alive"}`.

### 0.3 Frontend Scaffolding (Layui Admin)

```
repo/
├── frontend/
│   ├── public/
│   │   ├── index.html             # Main SPA shell (admin layout)
│   │   ├── login.html             # Login page
│   │   └── favicon.ico
│   ├── src/
│   │   ├── assets/
│   │   │   ├── css/
│   │   │   │   └── app.css        # Custom styles
│   │   │   └── images/
│   │   │       └── logo.png       # CampusOps logo placeholder
│   │   ├── lib/
│   │   │   └── layui/             # Layui framework files (CSS + JS)
│   │   ├── modules/               # Layui custom modules (one per feature)
│   │   │   └── common.js          # Shared utilities (API calls, token management)
│   │   ├── views/                 # HTML template fragments loaded via Layui
│   │   │   └── home.html          # Default dashboard view
│   │   └── config.js              # Layui.config + base API URL
│   └── package.json               # Minimal: dev server, optional build tooling
```

Nginx serves `frontend/public/` as root and proxies `/api/*` to the PHP-FPM backend.

**Validation:** Browser at `localhost:8080` shows the Layui Admin sidebar shell with "CampusOps" branding.

### 0.4 Development Workflow Setup

- **Hot reload (backend):** PHP-FPM auto-detects file changes (no restart needed with volume mounts).
- **Hot reload (frontend):** Nginx serves from a mounted volume; browser refresh picks up changes.
- **Database access:** MySQL exposed on localhost:3306 for local DB tools if needed.
- **Makefile / shell helpers:**
  ```
  repo/
  ├── Makefile                     # Convenience targets
  ```
  - `make up` → `docker compose up -d`
  - `make down` → `docker compose down`
  - `make logs` → `docker compose logs -f`
  - `make shell-php` → `docker compose exec php bash`
  - `make shell-mysql` → `docker compose exec mysql mysql -u root -p`
  - `make migrate` → run ThinkPHP migrations inside container
  - `make seed` → run seeders inside container

**Phase 0 exit criteria:** Docker stack runs. ThinkPHP API responds. Layui admin shell renders in browser. MySQL is reachable from PHP.

---

## Phase 1: Database Schema & Migrations

**Goal:** All tables created via ThinkPHP migrations, runnable with `make migrate`. Seed data for development.

### 1.1 Core Tables

| Table | Key Columns | Notes |
|-------|-------------|-------|
| `users` | id, username, password_hash, salt, role, status, failed_attempts, locked_until, created_at, updated_at | Salted hashing, lockout fields |
| `roles` | id, name, description, permissions (JSON) | RBAC role definitions |
| `sessions` | id, user_id, token, expires_at, created_at | Local bearer token sessions |

### 1.2 Activity Tables

| Table | Key Columns | Notes |
|-------|-------------|-------|
| `activity_groups` | id, created_by, created_at | Root entity for versioned activities |
| `activity_versions` | id, group_id, version_number, title, body, tags (JSON), state, max_headcount, signup_start, signup_end, eligibility_tags (JSON), required_supplies (JSON), published_at, created_at | Version model per design |
| `activity_signups` | id, group_id, user_id, status, acknowledged_at, created_at | Linked to group, not version |
| `activity_change_logs` | id, group_id, from_version, to_version, changes (JSON), created_at | Highlighted diffs |

### 1.3 Order & Fulfillment Tables

| Table | Key Columns | Notes |
|-------|-------------|-------|
| `orders` | id, activity_id, created_by, team_lead_id, state, items (JSON), notes, payment_method, amount, ticket_number, auto_cancel_at, closed_at, created_at, updated_at | State machine states |
| `order_state_history` | id, order_id, from_state, to_state, changed_by, notes, created_at | Audit of transitions |
| `shipments` | id, order_id, carrier, tracking_number, package_contents (JSON), weight, status, created_at | Multi-package per order |
| `scan_events` | id, shipment_id, scan_code, location, scanned_by, result, created_at | Fast Scan logging |
| `shipment_exceptions` | id, shipment_id, description, reported_by, created_at | Exception receipts |

### 1.4 Violation Tables

| Table | Key Columns | Notes |
|-------|-------------|-------|
| `violation_rules` | id, name, description, points, category, created_by, created_at, updated_at | Configurable point rules |
| `violations` | id, user_id, rule_id, points, notes, status, created_by, created_at | Individual records |
| `violation_evidence` | id, violation_id, filename, sha256, file_path, created_at | SHA-256 fingerprinted |
| `violation_appeals` | id, violation_id, appellant_notes, reviewer_id, decision, reviewer_notes, final_notes, created_at, decided_at | Full appeal workflow |
| `user_groups` | id, name, created_at | Groups for aggregation |
| `user_group_members` | id, group_id, user_id | Many-to-many |

### 1.5 Task & Staffing Tables

| Table | Key Columns | Notes |
|-------|-------------|-------|
| `tasks` | id, activity_id, title, description, assigned_to, status, due_date, created_at, updated_at | Per-activity task breakdown |
| `checklists` | id, activity_id, title, created_at | Checklists per activity |
| `checklist_items` | id, checklist_id, label, completed, completed_by, completed_at | Individual items |
| `staffing` | id, activity_id, role, required_count, assigned_users (JSON), notes, created_by, created_at, updated_at | Team Lead staffing |

### 1.6 Search, Dashboard & System Tables

| Table | Key Columns | Notes |
|-------|-------------|-------|
| `search_index` | id, entity_type, entity_id, title, body, tags, author, normalized_text, pinyin_text, created_at, updated_at | Local full-text index |
| `dashboards` | id, user_id, name, widgets (JSON), is_default, created_at, updated_at | Custom dashboards |
| `dashboard_favorites` | id, user_id, dashboard_id, created_at | Favorites |
| `notifications` | id, user_id, type, title, body, entity_type, entity_id, read_at, created_at | In-app notifications |
| `user_preferences` | id, user_id, arrival_reminders, activity_alerts, order_alerts, dashboard_layout (JSON) | Per-user settings |
| `audit_trail` | id, user_id, entity_type, entity_id, action, old_state, new_state, metadata (JSON), created_at | Universal audit log |
| `file_uploads` | id, uploaded_by, filename, original_name, sha256, file_path, size, category, created_at | Central file registry |

### 1.7 Seed Data

- **Users:** 1 Administrator, 2 Operations Staff, 1 Team Lead, 1 Reviewer, 5 Regular Users (all with known test passwords)
- **Roles:** All 5 roles with permission JSON
- **Violation Rules:** 5 sample rules (positive/negative points)
- **Activities:** 3 sample activities in various states (Draft, Published, Completed)
- **Orders:** 5 sample orders in various states

**Validation:** `make migrate && make seed` completes without errors. Tables confirmed via `make shell-mysql`.

---

## Phase 2: Authentication & RBAC

**Goal:** Working login/logout, session management, role-based middleware, and account lockout.

### 2.1 Backend — Auth Module

**Files:**
- `backend/app/controller/AuthController.php` — login, logout, unlock
- `backend/app/service/AuthService.php` — password verification, session creation, lockout logic
- `backend/app/middleware/AuthMiddleware.php` — bearer token validation on every `/api/v1/*` request
- `backend/app/middleware/RbacMiddleware.php` — role + permission checks per route
- `backend/app/model/User.php` — User model with salted hashing helpers
- `backend/app/model/Session.php` — Session model

**Key logic:**
- Password: `password_hash()` with `PASSWORD_BCRYPT` + unique salt column
- Lockout: Track `failed_attempts`; if >= 5, set `locked_until = now + 15min`
- Session token: `bin2hex(random_bytes(32))`, stored in `sessions` table with expiry
- RBAC middleware reads `roles.permissions` JSON and checks against route annotation

**API routes:**
```
POST /api/v1/auth/login
POST /api/v1/auth/logout
POST /api/v1/auth/unlock
```

### 2.2 Frontend — Login Page

**Files:**
- `frontend/public/login.html` — Layui form: username + password
- `frontend/src/modules/auth.js` — AJAX login, token storage in `localStorage`, redirect to main app

**Behavior:**
- On success: store token, redirect to `index.html`
- On failure: show error message via `layer.msg()`
- On lockout: show countdown timer
- All subsequent AJAX requests include `Authorization: Bearer {token}` header

### 2.3 Frontend — Role-Based Navigation

**Files:**
- `frontend/src/modules/nav.js` — Build sidebar menu dynamically based on `user.role` returned from login
- `frontend/src/config.js` — Menu structure definition per role

**Navigation per role (from design.md):**

| Role | Menu Items |
|------|------------|
| Administrator | Dashboard, Users, Activities, Orders, Violations, Search, Reports, Audit, Settings |
| Operations Staff | Dashboard, Activities, Orders, Shipments, Search |
| Team Lead | Dashboard, Activities, Tasks, Staffing, Checklists |
| Reviewer | Dashboard, Approvals, Violations, Audit |
| Regular User | Dashboard, Activities (browse/signup), Orders (view), Notifications |

**Validation:** Login with each seeded user → correct sidebar navigation appears. Invalid credentials show error. 5 failed attempts → lockout for 15 min.

---

## Phase 3: User Management (Admin)

**Goal:** Administrators can CRUD users and manage roles.

### 3.1 Backend

**Files:**
- `backend/app/controller/UserController.php` — CRUD + role assignment + password reset
- `backend/app/service/UserService.php` — Business logic with RBAC enforcement
- `backend/app/model/User.php` — (extend from Phase 2)
- `backend/app/validate/UserValidate.php` — Input validation (min 10 char password, etc.)

**API routes:**
```
GET    /api/v1/users
GET    /api/v1/users/{id}
POST   /api/v1/users
PUT    /api/v1/users/{id}
PUT    /api/v1/users/{id}/role
PUT    /api/v1/users/{id}/password
```

### 3.2 Frontend

**Files:**
- `frontend/src/views/users/list.html` — Layui table with pagination, role/status filters
- `frontend/src/views/users/form.html` — Create/edit user form
- `frontend/src/modules/users.js` — CRUD operations, table rendering

**UI details:**
- Layui `table` component with server-side pagination
- Filter dropdowns for role and status
- Role assignment via dropdown
- Password reset generates a temporary password shown once
- Sensitive field masking (partially mask username in certain views if needed)

**Validation:** Admin can create, edit, list, filter, change role, and reset password for users. Non-admin users get 403.

---

## Phase 4: Activity Management

**Goal:** Full activity lifecycle with versioning, signups, and change log acknowledgement.

### 4.1 Backend

**Files:**
- `backend/app/controller/ActivityController.php` — CRUD + lifecycle transitions + signup management
- `backend/app/service/ActivityService.php` — Version management, signup logic, change log diffing
- `backend/app/model/ActivityGroup.php`
- `backend/app/model/ActivityVersion.php`
- `backend/app/model/ActivitySignup.php`
- `backend/app/model/ActivityChangeLog.php`
- `backend/app/validate/ActivityValidate.php`

**Key logic:**
- **Create:** Always creates an `activity_groups` row + first `activity_versions` row in Draft state
- **Edit (Published):** Creates a new `activity_versions` row, increments version number. Existing signups marked "Pending Acknowledgement". Change log auto-generated by diffing JSON fields.
- **State transitions:** Draft → Published → In Progress → Completed → Archived. Each transition validated and audit-logged.
- **Signups:** Linked to `group_id`. Validated against headcount, eligibility tags, and signup window.
- **Search indexing:** On create/update, push to `search_index` table (incremental).

**API routes:**
```
GET    /api/v1/activities
GET    /api/v1/activities/{id}
GET    /api/v1/activities/{id}/versions
GET    /api/v1/activities/{id}/signups
GET    /api/v1/activities/{id}/change-log
POST   /api/v1/activities
PUT    /api/v1/activities/{id}
POST   /api/v1/activities/{id}/publish
POST   /api/v1/activities/{id}/start
POST   /api/v1/activities/{id}/complete
POST   /api/v1/activities/{id}/archive
POST   /api/v1/activities/{id}/signups
DELETE /api/v1/activities/{id}/signups/{signup_id}
POST   /api/v1/activities/{id}/signups/{signup_id}/acknowledge
```

### 4.2 Frontend

**Files:**
- `frontend/src/views/activities/list.html` — Table with state/tag/author filters, sorting
- `frontend/src/views/activities/detail.html` — Full detail view with version tabs
- `frontend/src/views/activities/form.html` — Create/edit form (title, body, tags, headcount, window, eligibility, supplies)
- `frontend/src/views/activities/signups.html` — Signup list (for Operations Staff)
- `frontend/src/views/activities/changelog.html` — Highlighted change log with acknowledge button
- `frontend/src/modules/activities.js` — All activity AJAX operations + UI logic

**UI details:**
- State badges (color-coded): Draft=gray, Published=blue, In Progress=green, Completed=orange, Archived=dark
- Timestamps: MM/DD/YYYY 12-hour format throughout
- Version history as a collapsible timeline
- Signup button with real-time headcount remaining
- "Pending Acknowledgement" banner for users with unacknowledged changes
- Tag input with autocomplete

**Validation:** Create activity → edit in Draft → Publish → edit again (new version created, signups get "Pending Acknowledgement") → user acknowledges → complete lifecycle. All timestamps formatted correctly.

---

## Phase 5: Order & Fulfillment Management

**Goal:** Full order state machine, payment confirmation, shipment management, and fast scan module.

### 5.1 Backend — Orders

**Files:**
- `backend/app/controller/OrderController.php` — CRUD + all state transitions
- `backend/app/service/OrderService.php` — State machine logic, auto-cancel timer, refund logic
- `backend/app/model/Order.php`
- `backend/app/model/OrderStateHistory.php`
- `backend/app/validate/OrderValidate.php`

**Key logic:**
- **State machine:** Placed → Pending Payment (starts 30-min timer) → Paid → Ticketing → Ticketed → Closed
- **Auto-cancel:** A background cron (ThinkPHP command, run via Docker cron or a scheduled container job) checks every minute for orders in `Pending Payment` state where `auto_cancel_at <= now()` and transitions them to `Canceled`.
- **Refund:** Admin only, only from `Paid` state and only before `Ticketed`.
- **Closed:** Immutable except invoice address with Reviewer approval.
- **Manual Payment Confirmation:** Operations Staff can confirm with payment method + amount. Audit logged.

**API routes:**
```
GET    /api/v1/orders
GET    /api/v1/orders/{id}
GET    /api/v1/orders/{id}/history
POST   /api/v1/orders
PUT    /api/v1/orders/{id}
POST   /api/v1/orders/{id}/initiate-payment
POST   /api/v1/orders/{id}/confirm-payment
POST   /api/v1/orders/{id}/start-ticketing
POST   /api/v1/orders/{id}/ticket
POST   /api/v1/orders/{id}/refund
POST   /api/v1/orders/{id}/cancel
POST   /api/v1/orders/{id}/close
PUT    /api/v1/orders/{id}/address
```

### 5.2 Backend — Fulfillment

**Files:**
- `backend/app/controller/ShipmentController.php` — Shipment CRUD + scan + delivery + exceptions
- `backend/app/service/ShipmentService.php` — Scan event processing, status updates
- `backend/app/model/Shipment.php`
- `backend/app/model/ScanEvent.php`
- `backend/app/model/ShipmentException.php`

**API routes:**
```
GET    /api/v1/orders/{order_id}/shipments
POST   /api/v1/orders/{order_id}/shipments
GET    /api/v1/shipments/{id}
POST   /api/v1/shipments/{id}/scan
GET    /api/v1/shipments/{id}/scan-history
POST   /api/v1/shipments/{id}/confirm-delivery
GET    /api/v1/shipments/{id}/exceptions
POST   /api/v1/shipments/{id}/exceptions
```

### 5.3 Backend — Auto-Cancel Cron

**Files:**
- `backend/app/command/AutoCancelOrders.php` — ThinkPHP console command
- Docker: Add a cron entry in the PHP container (or a dedicated cron container) to run every minute

### 5.4 Frontend — Orders

**Files:**
- `frontend/src/views/orders/list.html` — Table with state/activity/creator filters
- `frontend/src/views/orders/detail.html` — Full detail with state timeline, action buttons per state
- `frontend/src/views/orders/form.html` — Create order form
- `frontend/src/views/orders/payment.html` — Payment confirmation form (Operations Staff)
- `frontend/src/modules/orders.js`

**UI details:**
- State machine visualization (horizontal progress bar showing current state)
- Action buttons visible only for valid transitions based on current state + user role
- 30-minute countdown timer visible in `Pending Payment` state
- State history timeline on detail page
- Invoice address edit form (only for Closed orders, with Reviewer approval flow)

### 5.5 Frontend — Shipments & Fast Scan

**Files:**
- `frontend/src/views/shipments/list.html` — Shipments per order
- `frontend/src/views/shipments/detail.html` — Scan history, delivery status, exceptions
- `frontend/src/views/shipments/scan.html` — Fast Scan module (full-screen focus input)
- `frontend/src/modules/shipments.js`

**Fast Scan UI:**
- Full-screen input field that auto-focuses (optimized for USB barcode scanners)
- On scan (Enter key): AJAX POST to `/shipments/{id}/scan`
- Instant success (green flash) / failure (red flash + error sound) feedback
- Running log of recent scans below the input

**Validation:** Complete order lifecycle from Placed → Closed. Auto-cancel fires after 30 min. Shipments created, scanned, delivered. Exception logged. Fast Scan works with keyboard input simulating barcode scanner.

---

## Phase 6: Violation / Demerit System

**Goal:** Configurable violation rules, evidence uploads, appeal workflow, point aggregation with alerts.

### 6.1 Backend

**Files:**
- `backend/app/controller/ViolationController.php` — Rules CRUD + violations CRUD + appeal/review
- `backend/app/service/ViolationService.php` — Point aggregation, group totals, alert triggers
- `backend/app/model/ViolationRule.php`
- `backend/app/model/Violation.php`
- `backend/app/model/ViolationEvidence.php`
- `backend/app/model/ViolationAppeal.php`
- `backend/app/model/UserGroup.php`
- `backend/app/model/UserGroupMember.php`
- `backend/app/validate/ViolationValidate.php`

**Key logic:**
- **Point aggregation:** On violation approval, update user total in the same transaction. Recompute linked group totals.
- **Alert thresholds:** 25 points → create notification for Manager. 50 points → create notification for Administrator.
- **Evidence:** Upload validates file type (JPG/PNG/PDF), size (<=10MB), generates SHA-256 hash, stores file.
- **Appeal workflow:** User submits appeal → Reviewer reviews with decision notes → final decision recorded.

**API routes:**
```
GET    /api/v1/violations/rules
GET    /api/v1/violations/rules/{id}
POST   /api/v1/violations/rules
PUT    /api/v1/violations/rules/{id}
DELETE /api/v1/violations/rules/{id}
POST   /api/v1/violations
GET    /api/v1/violations/{id}
GET    /api/v1/violations/user/{user_id}
GET    /api/v1/violations/group/{group_id}
POST   /api/v1/violations/{id}/appeal
POST   /api/v1/violations/{id}/review
POST   /api/v1/violations/{id}/final-decision
```

### 6.2 Frontend

**Files:**
- `frontend/src/views/violations/rules.html` — Rules table (Admin CRUD)
- `frontend/src/views/violations/list.html` — Violations list with user/group filter
- `frontend/src/views/violations/detail.html` — Detail with evidence gallery, appeal form, review form
- `frontend/src/views/violations/user-summary.html` — User point summary with threshold alerts
- `frontend/src/views/violations/group-summary.html` — Group aggregated view
- `frontend/src/modules/violations.js`

**UI details:**
- Evidence gallery with thumbnail preview (JPG/PNG) or PDF icon
- Point totals with color coding: green (<25), yellow (25-49), red (>=50)
- Appeal form with file upload
- Reviewer decision panel with required notes field
- Alert banners when thresholds are hit

**Validation:** Create rule → create violation with evidence → user appeals → reviewer decides → points aggregate correctly for user and group → alert triggers at 25 and 50.

---

## Phase 7: File Upload System

**Goal:** Centralized file upload with type validation, size limits, and SHA-256 fingerprinting.

### 7.1 Backend

**Files:**
- `backend/app/controller/UploadController.php` — File upload endpoint
- `backend/app/service/UploadService.php` — Validation, SHA-256 hash, storage
- `backend/app/model/FileUpload.php`

**Key logic:**
- Validate MIME type (JPG, PNG, PDF only)
- Validate size (<= 10 MB)
- Compute SHA-256 hash
- Store in `uploads/` directory (Docker volume)
- Return filename, hash, URL, size

**API route:**
```
POST /api/v1/upload (multipart/form-data)
```

### 7.2 Frontend

**Files:**
- `frontend/src/modules/upload.js` — Reusable Layui upload component with drag-drop, progress bar, type/size validation on client side

**Validation:** Upload JPG, PNG, PDF → success. Upload .exe or >10MB → rejection. SHA-256 matches file content.

---

## Phase 8: Task & Checklist Management

**Goal:** Team Leads can break down activities into tasks and checklists with assignments.

### 8.1 Backend

**Files:**
- `backend/app/controller/TaskController.php` — Task CRUD + status updates
- `backend/app/controller/ChecklistController.php` — Checklist CRUD + item completion
- `backend/app/service/TaskService.php`
- `backend/app/model/Task.php`
- `backend/app/model/Checklist.php`
- `backend/app/model/ChecklistItem.php`
- `backend/app/validate/TaskValidate.php`

**API routes:**
```
GET    /api/v1/activities/{id}/tasks
POST   /api/v1/activities/{id}/tasks
PUT    /api/v1/tasks/{id}
PUT    /api/v1/tasks/{id}/status
GET    /api/v1/activities/{id}/checklists
POST   /api/v1/activities/{id}/checklists
PUT    /api/v1/checklists/{id}
POST   /api/v1/checklists/{id}/items/{item_id}/complete
```

### 8.2 Frontend

**Files:**
- `frontend/src/views/tasks/list.html` — Task board per activity (Kanban-style or table)
- `frontend/src/views/tasks/form.html` — Create/edit task with assignee + due date
- `frontend/src/views/checklists/list.html` — Checklists per activity with inline completion
- `frontend/src/modules/tasks.js`
- `frontend/src/modules/checklists.js`

**Validation:** Create task → assign → update status. Create checklist → mark items complete. Only Team Lead+ can manage.

---

## Phase 9: Staffing Management

**Goal:** Team Leads can plan staffing for activities.

### 9.1 Backend

**Files:**
- `backend/app/controller/StaffingController.php`
- `backend/app/service/StaffingService.php`
- `backend/app/model/Staffing.php`
- `backend/app/validate/StaffingValidate.php`

**API routes:**
```
GET    /api/v1/activities/{id}/staffing
POST   /api/v1/activities/{id}/staffing
PUT    /api/v1/staffing/{id}
DELETE /api/v1/staffing/{id}
```

### 9.2 Frontend

**Files:**
- `frontend/src/views/staffing/list.html` — Staffing plan table per activity
- `frontend/src/views/staffing/form.html` — Role, required count, assign users
- `frontend/src/modules/staffing.js`

**Validation:** Create staffing entry → assign users → update → delete. Only Team Lead+ can manage.

---

## Phase 10: Search System

**Goal:** Global full-text search with highlighting, logistics search with tokenization/pinyin/synonym matching, and spell correction.

### 10.1 Backend — Search Index

**Files:**
- `backend/app/service/SearchIndexService.php` — Incremental indexing on CRUD events
- `backend/app/service/SearchService.php` — Query processing, ranking, highlighting
- `backend/app/service/PinyinService.php` — Pinyin conversion for Chinese character support
- `backend/app/service/SpellCorrectionService.php` — Levenshtein-based "Did you mean?" suggestions
- `backend/app/command/SearchCleanup.php` — Nightly cleanup: remove orphaned entries >7 days

**Key logic:**
- **Incremental indexing:** Every create/update/delete in activities and orders triggers an index update via service hooks in the respective services.
- **Normalized text:** Original text + tokenized terms + pinyin expansions stored in `search_index`.
- **Full-text search:** MySQL FULLTEXT index on `normalized_text` column. MATCH...AGAINST for ranking.
- **Highlighting:** Wrap matched terms in `<em>` tags in response.
- **Logistics search:** Optional synonym matching (configurable synonym table), pinyin matching, Levenshtein spell correction.
- **Nightly cleanup:** Cron job removes entries where referenced entity no longer exists and entry is >7 days old.

### 10.2 Backend — Endpoints

**Files:**
- `backend/app/controller/SearchController.php`
- `backend/app/controller/IndexController.php` — Admin: rebuild index, trigger cleanup, view status

**API routes:**
```
GET    /api/v1/search
GET    /api/v1/search/suggest
GET    /api/v1/search/logistics
GET    /api/v1/index/status
POST   /api/v1/index/rebuild
POST   /api/v1/index/cleanup
```

### 10.3 Frontend

**Files:**
- `frontend/src/views/search/results.html` — Search results with highlighted matches, multi-dimensional filters, sorting
- `frontend/src/views/search/logistics.html` — Logistics search with toggles for synonym/pinyin/spell correction
- `frontend/src/modules/search.js` — Search input with debounced suggest, results rendering

**UI details:**
- Global search bar in the top navigation header
- Autocomplete dropdown with suggestions
- Results page with filters: type, author, tag, date range
- Sort options: recency, popularity, reply count, relevance
- "Did you mean?" link for spell corrections
- Highlighted matched terms in results

**Validation:** Search returns relevant results. Pinyin search finds Chinese-named items. Synonym matching works. Spell correction suggests corrections. Nightly cleanup runs. Admin can rebuild index.

---

## Phase 11: Notifications & User Preferences

**Goal:** In-app notifications for arrival reminders, order status changes, activity updates. Configurable preferences.

### 11.1 Backend

**Files:**
- `backend/app/controller/NotificationController.php`
- `backend/app/controller/PreferenceController.php`
- `backend/app/service/NotificationService.php` — Create, dispatch, check preferences before sending
- `backend/app/model/Notification.php`
- `backend/app/model/UserPreference.php`

**Key logic:**
- Notifications generated from: order state changes, activity updates, violation alerts, arrival reminders
- Before creating a notification, check user preferences to see if the type is enabled
- Unread count available via API for badge display

**API routes:**
```
GET    /api/v1/notifications
PUT    /api/v1/notifications/{id}/read
GET    /api/v1/notifications/settings
PUT    /api/v1/notifications/settings
GET    /api/v1/preferences
PUT    /api/v1/preferences
```

### 11.2 Frontend

**Files:**
- `frontend/src/views/notifications/list.html` — Notification list with read/unread status
- `frontend/src/views/preferences/form.html` — Toggle preferences (arrival reminders, activity alerts, order alerts)
- `frontend/src/modules/notifications.js` — Badge counter in header, polling for new notifications

**UI details:**
- Bell icon in top navigation with unread badge count
- Notification dropdown showing recent items
- Full notification list page
- Click notification → navigate to related entity
- Preferences page with toggle switches

**Validation:** Trigger actions that generate notifications → notifications appear. Toggle preferences off → no new notifications for that type. Mark as read → count updates.

---

## Phase 12: Recommendations

**Goal:** Behavioral recommendation engine based on local signals.

### 12.1 Backend

**Files:**
- `backend/app/controller/RecommendationController.php`
- `backend/app/service/RecommendationService.php` — Signal collection, scoring, diversity/dedup rules

**Key logic:**
- **Signals:** Track views, saves, signups, tags per user in a lightweight `user_signals` table
- **Scoring:** Weight signals (signup > save > view). Combine with tag affinity.
- **Cold-start:** When insufficient user data, fallback to top-performing tags in last 30 days
- **Deduplication:** Skip activities from same activity group already shown
- **Diversity:** Cap any single tag at 40% of results on a page
- **Contexts:** "list" (sidebar widget) and "detail" (related items at bottom)

**API routes:**
```
GET /api/v1/recommendations
GET /api/v1/recommendations/popular
```

### 12.2 Frontend

**Files:**
- `frontend/src/views/recommendations/widget.html` — Recommendation card widget
- `frontend/src/modules/recommendations.js` — Load recommendations on list and detail pages

**Validation:** User interacts with activities → recommendations reflect behavior. New user sees popular tags. No duplicate activity families. No single tag >40% of feed.

---

## Phase 13: Dashboard & Reporting

**Goal:** Custom dashboards with drag-and-drop widget builder, drill-down charts, exports with watermarking.

### 13.1 Backend

**Files:**
- `backend/app/controller/DashboardController.php` — CRUD dashboards, get widget data, exports
- `backend/app/service/DashboardService.php` — Aggregate data for widgets, chart data computation
- `backend/app/service/ExportService.php` — PNG/PDF/Excel generation with watermark (username + timestamp)
- `backend/app/model/Dashboard.php`
- `backend/app/model/DashboardFavorite.php`

**Key logic:**
- **Widget types:** Order count by state, activity count by state, violation summary, recent signups, timeline charts
- **Data aggregation:** SQL queries with GROUP BY for chart data
- **Export watermark:** PhpSpreadsheet for Excel (header/footer watermark), TCPDF/DomPDF for PDF (diagonal watermark text), GD for PNG (text overlay)
- **Favorites:** User can bookmark dashboard configurations

**API routes:**
```
GET    /api/v1/dashboard
GET    /api/v1/dashboard/custom
POST   /api/v1/dashboard/custom
PUT    /api/v1/dashboard/custom/{id}
GET    /api/v1/dashboard/custom/{id}/data
GET    /api/v1/dashboard/custom/{id}/export?format=png|pdf|excel
POST   /api/v1/dashboard/favorites
GET    /api/v1/dashboard/export?format=png|pdf|excel
```

### 13.2 Frontend

**Files:**
- `frontend/src/views/dashboard/home.html` — Default dashboard with preset widgets
- `frontend/src/views/dashboard/builder.html` — Drag-and-drop widget builder
- `frontend/src/views/dashboard/view.html` — Custom dashboard viewer with drill-down
- `frontend/src/modules/dashboard.js` — Widget rendering, drag-drop (Layui + custom grid)
- `frontend/src/modules/charts.js` — Chart rendering (ECharts or similar lightweight library)

**UI details:**
- Widget builder: grid layout, drag to reposition, resize handles
- Widget types: bar chart, line chart, pie chart, table, counter card
- Drill-down: Click on chart segment → opens filtered detail view
- Export buttons: PNG, PDF, Excel in top toolbar
- Favorites star icon

**Validation:** Create custom dashboard → add widgets → arrange with drag-drop → data displays correctly → drill-down works → export all 3 formats with watermark visible.

---

## Phase 14: Audit Trail

**Goal:** Complete audit logging across all modules with searchable admin view.

### 14.1 Backend

**Files:**
- `backend/app/controller/AuditController.php` — Query audit trail
- `backend/app/service/AuditService.php` — Centralized `log()` method called from all services
- `backend/app/model/AuditTrail.php`

**Key logic:**
- Every state transition, CRUD operation, login/logout, and sensitive action calls `AuditService::log()`
- Logged data: operator ID, timestamp, entity type, entity ID, action, old state, new state, metadata JSON
- Query with filters: entity type, entity ID, user ID, action, date range
- Accessible to Administrator and Reviewer roles only

**API routes:**
```
GET /api/v1/audit
```

### 14.2 Frontend

**Files:**
- `frontend/src/views/audit/list.html` — Audit log table with filters
- `frontend/src/modules/audit.js`

**Validation:** Perform actions across all modules → audit entries appear. Filter by entity, user, action, date. Only Admin/Reviewer can access.

---

## Phase 15: Security Hardening

**Goal:** Implement all security features specified in the design.

### 15.1 Sensitive Data Protection

- **UI Masking:** Middleware or response transformer that masks sensitive fields (passenger identifiers, invoice contacts) in API responses for non-admin roles
- **Encryption at rest:** AES-256 encryption for sensitive DB fields (using ThinkPHP model mutators). Encryption key stored in `.env`.

**Files:**
- `backend/app/middleware/SensitiveDataMiddleware.php` — Mask fields in response based on role
- `backend/app/service/EncryptionService.php` — AES-256 encrypt/decrypt helpers
- Update models to use encryption mutators for sensitive fields

### 15.2 Export Watermarking

Already addressed in Phase 13 ExportService. This phase verifies:
- All exported PDFs have diagonal watermark with username + timestamp
- All exported Excel files have header/footer with username + timestamp
- All exported PNGs have text overlay watermark

### 15.3 File Security

Already addressed in Phase 7. This phase verifies:
- File type validation prevents uploading disallowed types
- SHA-256 fingerprint stored and verifiable
- Upload directory is not directly web-accessible (served through PHP controller)

### 15.4 Input Validation & Protection

- **CSRF:** ThinkPHP built-in CSRF middleware on state-changing endpoints
- **XSS:** Output encoding in Layui templates, `htmlspecialchars` in API responses where needed
- **SQL Injection:** ThinkPHP ORM parameterized queries (already enforced by framework)
- **Rate limiting:** Simple middleware that tracks request counts per IP per minute

**Files:**
- `backend/app/middleware/RateLimitMiddleware.php`
- `backend/app/middleware/CsrfMiddleware.php` (or configure ThinkPHP built-in)

**Validation:** Attempt XSS payloads → sanitized. Attempt SQL injection → parameterized. Rate limit triggers after threshold. Sensitive fields masked for non-admin. Encrypted fields unreadable in raw DB.

---

## Phase 16: Testing & Polish

**Goal:** End-to-end validation, UI polish, and performance checks.

### 16.1 Backend Testing

**Files:**
- `backend/tests/` directory with PHPUnit tests
- Test categories: Unit tests (services), Integration tests (controllers), Auth tests

**Priority test scenarios:**
1. Auth: login, lockout, session expiry
2. Activity: full lifecycle, versioning, signup acknowledgement
3. Order: full state machine, auto-cancel, refund guard
4. Violation: point aggregation, alert thresholds, appeal workflow
5. Search: full-text results, pinyin matching, spell correction
6. RBAC: verify each role can only access permitted routes

### 16.2 Frontend Polish

- Consistent Layui theme across all views
- Responsive behavior for common screen sizes
- Loading spinners on all AJAX calls
- Error handling with user-friendly messages (layer.msg)
- Confirm dialogs for destructive actions
- Breadcrumb navigation
- Page titles match current view

### 16.3 Docker Optimization

- Multi-stage build for PHP image (smaller final image)
- MySQL data volume for persistence
- Health checks for all services
- Proper logging configuration (JSON logs to stdout)

**Validation:** All tests pass in Docker. UI is consistent and functional. No console errors. All API endpoints return proper error codes.

---

## Phase Summary & Dependency Graph

```
Phase 0: Docker + Scaffolding
   │
   ▼
Phase 1: Database Schema
   │
   ▼
Phase 2: Auth & RBAC ──────────────────────────┐
   │                                            │
   ▼                                            │
Phase 3: User Management                       │
   │                                            │
   ├──────────┬──────────┬──────────┐          │
   ▼          ▼          ▼          ▼          │
Phase 4    Phase 5    Phase 6    Phase 7       │
Activity   Orders     Violations  Uploads      │
   │          │          │          │          │
   ├──────────┴──────────┴──────────┘          │
   ▼                                            │
Phase 8: Tasks & Checklists                    │
   │                                            │
   ▼                                            │
Phase 9: Staffing                              │
   │                                            │
   ▼                                            │
Phase 10: Search (depends on Phases 4-6 data)  │
   │                                            │
   ▼                                            │
Phase 11: Notifications & Preferences          │
   │                                            │
   ▼                                            │
Phase 12: Recommendations                      │
   │                                            │
   ▼                                            │
Phase 13: Dashboard & Reporting                │
   │                                            │
   ▼                                            │
Phase 14: Audit Trail (integrated throughout) ◄┘
   │
   ▼
Phase 15: Security Hardening
   │
   ▼
Phase 16: Testing & Polish
```

**Notes:**
- Phases 4, 5, 6, and 7 can be developed in parallel once Phase 3 is complete.
- Phase 14 (Audit Trail) is integrated incrementally — the service is created early, but the admin UI comes last.
- Each phase is designed to produce a runnable, testable increment inside Docker.

---

## How to Execute

1. Start each phase by reading this plan section carefully.
2. Run `make up` (or `docker compose up -d`) to start the Docker environment.
3. Implement backend changes → test with `curl` or API client.
4. Implement frontend changes → test in browser at `localhost:8080`.
5. Run `make migrate` when schema changes are needed.
6. Mark the phase complete when all validation criteria are met.
7. Move to the next phase.

---

## File Structure (Final State)

```
repo/
├── docker-compose.yml
├── docker/
│   ├── nginx/
│   │   └── default.conf
│   ├── php/
│   │   ├── Dockerfile
│   │   ├── php.ini
│   │   └── cron/
│   │       └── crontab
│   └── mysql/
│       └── init.sql
├── backend/
│   ├── composer.json
│   ├── app/
│   │   ├── controller/
│   │   │   ├── AuthController.php
│   │   │   ├── UserController.php
│   │   │   ├── ActivityController.php
│   │   │   ├── OrderController.php
│   │   │   ├── ShipmentController.php
│   │   │   ├── ViolationController.php
│   │   │   ├── TaskController.php
│   │   │   ├── ChecklistController.php
│   │   │   ├── StaffingController.php
│   │   │   ├── SearchController.php
│   │   │   ├── IndexController.php
│   │   │   ├── NotificationController.php
│   │   │   ├── PreferenceController.php
│   │   │   ├── RecommendationController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── UploadController.php
│   │   │   └── AuditController.php
│   │   ├── service/
│   │   │   ├── AuthService.php
│   │   │   ├── UserService.php
│   │   │   ├── ActivityService.php
│   │   │   ├── OrderService.php
│   │   │   ├── ShipmentService.php
│   │   │   ├── ViolationService.php
│   │   │   ├── TaskService.php
│   │   │   ├── SearchIndexService.php
│   │   │   ├── SearchService.php
│   │   │   ├── PinyinService.php
│   │   │   ├── SpellCorrectionService.php
│   │   │   ├── NotificationService.php
│   │   │   ├── RecommendationService.php
│   │   │   ├── DashboardService.php
│   │   │   ├── ExportService.php
│   │   │   ├── UploadService.php
│   │   │   ├── EncryptionService.php
│   │   │   └── AuditService.php
│   │   ├── model/
│   │   │   ├── User.php
│   │   │   ├── Session.php
│   │   │   ├── ActivityGroup.php
│   │   │   ├── ActivityVersion.php
│   │   │   ├── ActivitySignup.php
│   │   │   ├── ActivityChangeLog.php
│   │   │   ├── Order.php
│   │   │   ├── OrderStateHistory.php
│   │   │   ├── Shipment.php
│   │   │   ├── ScanEvent.php
│   │   │   ├── ShipmentException.php
│   │   │   ├── ViolationRule.php
│   │   │   ├── Violation.php
│   │   │   ├── ViolationEvidence.php
│   │   │   ├── ViolationAppeal.php
│   │   │   ├── UserGroup.php
│   │   │   ├── UserGroupMember.php
│   │   │   ├── Task.php
│   │   │   ├── Checklist.php
│   │   │   ├── ChecklistItem.php
│   │   │   ├── Staffing.php
│   │   │   ├── SearchIndex.php
│   │   │   ├── Dashboard.php
│   │   │   ├── DashboardFavorite.php
│   │   │   ├── Notification.php
│   │   │   ├── UserPreference.php
│   │   │   ├── AuditTrail.php
│   │   │   └── FileUpload.php
│   │   ├── middleware/
│   │   │   ├── AuthMiddleware.php
│   │   │   ├── RbacMiddleware.php
│   │   │   ├── SensitiveDataMiddleware.php
│   │   │   └── RateLimitMiddleware.php
│   │   ├── validate/
│   │   │   ├── UserValidate.php
│   │   │   ├── ActivityValidate.php
│   │   │   ├── OrderValidate.php
│   │   │   ├── ViolationValidate.php
│   │   │   ├── TaskValidate.php
│   │   │   └── StaffingValidate.php
│   │   ├── command/
│   │   │   ├── AutoCancelOrders.php
│   │   │   └── SearchCleanup.php
│   │   └── common.php
│   ├── config/
│   ├── route/
│   ├── database/
│   │   ├── migrations/
│   │   └── seeds/
│   ├── public/
│   │   └── index.php
│   └── runtime/
├── frontend/
│   ├── public/
│   │   ├── index.html
│   │   ├── login.html
│   │   └── favicon.ico
│   ├── src/
│   │   ├── assets/
│   │   │   ├── css/
│   │   │   └── images/
│   │   ├── lib/
│   │   │   └── layui/
│   │   ├── modules/
│   │   │   ├── common.js
│   │   │   ├── auth.js
│   │   │   ├── nav.js
│   │   │   ├── users.js
│   │   │   ├── activities.js
│   │   │   ├── orders.js
│   │   │   ├── shipments.js
│   │   │   ├── violations.js
│   │   │   ├── tasks.js
│   │   │   ├── checklists.js
│   │   │   ├── staffing.js
│   │   │   ├── search.js
│   │   │   ├── notifications.js
│   │   │   ├── recommendations.js
│   │   │   ├── dashboard.js
│   │   │   ├── charts.js
│   │   │   ├── upload.js
│   │   │   └── audit.js
│   │   ├── views/
│   │   │   ├── home.html
│   │   │   ├── users/
│   │   │   ├── activities/
│   │   │   ├── orders/
│   │   │   ├── shipments/
│   │   │   ├── violations/
│   │   │   ├── tasks/
│   │   │   ├── checklists/
│   │   │   ├── staffing/
│   │   │   ├── search/
│   │   │   ├── notifications/
│   │   │   ├── preferences/
│   │   │   ├── recommendations/
│   │   │   ├── dashboard/
│   │   │   └── audit/
│   │   └── config.js
│   └── package.json
├── Makefile
├── .env
├── .env.example
├── .gitignore
└── PLAN.md
```
