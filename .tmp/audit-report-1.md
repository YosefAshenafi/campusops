# CampusOps — Static Delivery Acceptance & Architecture Audit

## 1. Verdict

**Overall conclusion: Partial Pass**

The repository under `repo/` contains a substantial ThinkPHP 8 backend, Layui frontend, Docker/Makefile workflow, migrations, seeds, and PHPUnit suites aligned with the Campus Operations prompt at a **structural** level. However, static review finds **material gaps and defects**: documentation/path inconsistencies, **RBAC vs object-level authorization mismatches for orders and violations**, **activity listing semantics** (multiple versions per group), a **likely SQL injection vector** in search relevance sorting, **incomplete test/RBAC alignment** (e2e role names), and **prompt-level shortcuts** (e.g. `reply_count` sorting as `view_count` proxy). Runtime behavior, Docker startup, and test execution were **not** verified (per audit boundary).

---

## 2. Scope and Static Verification Boundary

### What was reviewed

- Documentation: `repo/README.md`, `repo/docs/design.md`, `repo/docs/api-spec.md`, `repo/docs/questions.md` (sampled; full API spec not line-audited end-to-end).
- Backend: routes `repo/backend/route/app.php`, middleware registration `repo/backend/config/middleware.php`, selected controllers and services (auth, orders, activities, violations, search, export, dashboard, uploads).
- Data: `repo/backend/database/seeds/DatabaseSeeder.php`, migrations presence (not every migration line).
- Frontend: `repo/frontend/src/config.js`, `repo/frontend/src/modules/common.js`, `repo/frontend/src/modules/dashboard.js`, `repo/docker/nginx/default.conf`.
- Tests: `repo/phpunit.xml`, `repo/run_tests.sh`, `repo/unit_tests/`, `repo/API_tests/`, `repo/e2e_tests/`, `repo/backend/tests/`.
- Logging: `repo/backend/config/log.php`.

### What was not reviewed

- Full line-by-line read of every controller, view, migration, and `docs/api-spec.md` vs every handler.
- Composer lock vulnerability audit, PHP runtime configuration beyond referenced files.
- Binary assets and all 2000+ workspace files outside `repo/`.

### Intentionally not executed

- Application run, Docker Compose, PHPUnit, or any network calls (per instructions).

### Claims requiring manual verification

- Any statement that flows “work end-to-end” in the browser or that tests pass in CI.
- Performance under load, race conditions on file-based rate limiting, and PDF rendering in non-PHP clients.
- Whether Layui screens fully implement drag-and-drop dashboards, drill-down charts, and all export buttons (backend APIs exist; UI is only spot-checked).

---

## 3. Repository / Requirement Mapping Summary

### Prompt core (condensed)

- **Goal:** On-premise unified portal for campus events, logistics, accountability, RBAC (Administrator, Operations Staff, Team Lead, Reviewer, Regular User).
- **Activities:** Lifecycle Draft → Published → In Progress → Completed → Archived; published edits versioned with user-visible change log and acknowledgment; signup rules (window, headcount, eligibility, supplies).
- **Orders:** State machine Placed → Pending Payment (30 min auto-cancel) → Paid → Ticketing → Ticketed → Canceled/Closed; admin-only refund before ticketed; closed orders immutable except invoice address correction with reviewer approval.
- **Logistics:** Shipments, splits, tracking, scans, exceptions, reminders, local alert preferences.
- **Violations:** Rules, points, evidence (JPG/PNG/PDF ≤10 MB, SHA-256), appeals, aggregation, alerts at 25 and 50 points.
- **Search:** Global full-text + logistics (tokenization, synonyms, pinyin, spell correction), filters, sorts including reply count and relevance.
- **Recommendations:** Signals, cold start, dedup, 40% per-tag diversity.
- **Dashboards:** Custom widgets, drill-down, favorites, export PNG/PDF/Excel with watermarks.
- **Security:** Local auth, 10+ char passwords, lockout, salted hashing, RBAC, masking/encryption, watermarked exports.
- **Ops:** Incremental indexing, nightly cleanup of orphaned index entries >7 days.

### Implementation mapping (evidence-based)

- **Stack:** README documents PHP 8.2 + ThinkPHP 8 + Layui + MySQL + Docker (`repo/README.md:5-6`, `repo/README.md:68-96`).
- **Routes:** Central API v1 group with `auth`, `rbac`, `rate_limit`, `sensitive_data` (`repo/backend/route/app.php:6-315`).
- **Orders:** `OrderService` defines states including `pending_payment`, `canceled`, `closed` (`repo/backend/app/service/OrderService.php:20-28`, `repo/backend/app/service/OrderService.php:260-330`); auto-cancel CLI (`repo/backend/app/command/AutoCancelOrders.php:10-40`).
- **Activities:** Versioning and change log in `ActivityService` (`repo/backend/app/service/ActivityService.php:207-261`, `repo/backend/app/service/ActivityService.php:123-135`).
- **Search / index cleanup:** `SearchService::cleanup` with 7-day stale orphan removal (`repo/backend/app/service/SearchService.php:270-287`).
- **Recommendations:** 40% constant and cold-start window (`repo/backend/app/service/RecommendationService.php:11-12`, `repo/backend/app/service/RecommendationService.php:105-112`).
- **Frontend date config:** MM/DD/YYYY and 12h (`repo/frontend/src/config.js:13-16`).

---

## 4. Section-by-section Review

### Hard Gates

#### 1.1 Documentation and static verifiability

- **Conclusion:** Partial Pass  
- **Rationale:** README gives Docker `make setup`, migrate/seed, test script, and URL (`repo/README.md:15-57`). Structure matches tree (`repo/README.md:68-96`). **Inconsistency:** README says copy `.env` to `backend/.env` from `.env.example` (`repo/README.md:114-115`), while `.env.example` lives at `repo/.env.example` (`repo/.env.example:1-3`). `run_tests.sh` assumes `backend/vendor/bin/phpunit` (`repo/run_tests.sh:13`) — static consistency depends on `composer install` having been run (documented via `make install` / `make setup`).  
- **Evidence:** `repo/README.md:114-115`, `repo/.env.example:1-3`, `repo/run_tests.sh:13`

#### 1.2 Deviation from Prompt

- **Conclusion:** Partial Pass  
- **Rationale:** Core domain modules exist (activities, orders, shipments, violations, search, recommendations, dashboard, export). Several prompt details are **implemented with documented shortcuts** (e.g. `reply_count` sort uses `view_count` — `repo/backend/app/service/SearchService.php:59-74`) or **UI gaps** (dashboard module renders tables, no static evidence of drag-and-drop composition — `repo/frontend/src/modules/dashboard.js:10-31`, `repo/frontend/src/views/dashboard/home.html:8-30`).  
- **Evidence:** `repo/docs/questions.md:17-19`, `repo/backend/app/service/SearchService.php:59-74`, `repo/frontend/src/modules/dashboard.js:10-31`

---

### Delivery Completeness

#### 2.1 Core functional requirements (explicit)

- **Conclusion:** Partial Pass  
- **Rationale:** Large surface area is present in code. **Gaps vs prompt include:** activity **list** appears to iterate `ActivityVersion` rows without collapsing to one row per activity group, which can duplicate activities in listings (`repo/backend/app/service/ActivityService.php:33-56`); **global search** `reply_count` is not true reply count (`repo/backend/app/service/SearchService.php:72-74`); **reviewer** role is seeded with `orders.read` (`repo/backend/database/seeds/DatabaseSeeder.php:56-63`) but `OrderService::listOrders` / `getOrder` only broaden visibility for `administrator` and `operations_staff` (`repo/backend/app/service/OrderService.php:45-47`, `repo/backend/app/service/OrderService.php:75-77`), conflicting with reviewer workflows. **Violation listing** accepts `group_id` but service does not apply it (`repo/backend/app/controller/ViolationController.php:88-91`, `repo/backend/app/service/ViolationService.php:108-119`).  
- **Manual verification:** End-to-end flows in browser.

#### 2.2 End-to-end deliverable vs fragment

- **Conclusion:** Pass (for “not a single-file demo”)  
- **Rationale:** Full project layout, migrations, seeds, multi-layer services, frontend modules — not a fragment (`repo/README.md:68-96`).  
- **Evidence:** `repo/README.md:68-96`, `repo/backend/route/app.php:1-50`

---

### Engineering and Architecture Quality

#### 3.1 Structure and module decomposition

- **Conclusion:** Pass  
- **Rationale:** Controllers, services, middleware, commands, validate classes — reasonable decomposition (`repo/README.md:70-78`).  
- **Evidence:** `repo/README.md:70-78`

#### 3.2 Maintainability / extensibility

- **Conclusion:** Partial Pass  
- **Rationale:** Services are separated, but some authorization logic is split inconsistently (RBAC middleware vs service checks; orders vs violations). Search uses raw SQL fragments with embedded user query (`repo/backend/app/service/SearchService.php:76-79`) — fragile and risky.  
- **Evidence:** `repo/backend/app/service/SearchService.php:76-79`

---

### Engineering Details and Professionalism

#### 4.1 Error handling, logging, validation, API design

- **Conclusion:** Partial Pass  
- **Rationale:** Structured JSON errors in `AuthMiddleware` (`repo/backend/app/middleware/AuthMiddleware.php:19-44`); ThinkPHP log config present (`repo/backend/config/log.php:1-28`); `UserValidate` enforces password length (`repo/backend/app/validate/UserValidate.php:11-22`). **Gaps:** `ViolationController` does not pass `currentUserId` / `currentRole` into `ViolationService::listViolations` / `getViolation`, so service-layer object checks for `regular_user` never run with real role (`repo/backend/app/controller/ViolationController.php:84-103`, `repo/backend/app/service/ViolationService.php:108-145`). **Search relevance** `orderRaw` embeds `$query` unsafely (`repo/backend/app/service/SearchService.php:76-79`).  
- **Manual verification:** Injection payloads against search endpoint.

#### 4.2 Product-like vs demo

- **Conclusion:** Partial Pass  
- **Rationale:** Seeds, RBAC matrix, exports, cron commands suggest product intent (`repo/backend/database/seeds/DatabaseSeeder.php:17-75`, `repo/Makefile:49-54`). Test quality and some shortcuts (e2e role names, “API tests” hitting DB) reduce confidence (`repo/e2e_tests/AuthFlowTest.php:127-147`, `repo/API_tests/AuthApiTest.php:136-147`).  
- **Evidence:** `repo/e2e_tests/AuthFlowTest.php:127-147`

---

### Prompt Understanding and Requirement Fit

#### 5.1 Business goal and constraints

- **Conclusion:** Partial Pass  
- **Rationale:** `docs/questions.md` records explicit requirement decisions (refunds, address workflow, auto-cancel, diversity) (`repo/docs/questions.md:1-43`). Remaining misalignments include order visibility for reviewers vs seed (`repo/backend/database/seeds/DatabaseSeeder.php:56-63` vs `repo/backend/app/service/OrderService.php:33-47`), and activity list semantics (`repo/backend/app/service/ActivityService.php:33-56`).  
- **Evidence:** `repo/docs/questions.md:1-12`, `repo/backend/app/service/OrderService.php:33-47`

---

### Aesthetics (frontend)

#### 6.1 Visual and interaction design

- **Conclusion:** Cannot Confirm Statistically  
- **Rationale:** Static HTML/Layui cards and tables are present (`repo/frontend/src/views/dashboard/home.html:8-30`); no browser render was performed. Config defines date formats (`repo/frontend/src/config.js:13-16`).  
- **Manual verification:** Visual review in browser at `http://localhost:8080` per README.

---

## 5. Issues / Suggestions (Severity-Rated)

### Blocker

*None conclusively rated Blocker on static evidence alone without assuming a deployed permission misconfiguration. The closest candidate is SQL injection risk in search (below, High) — elevation to Blocker is environment-dependent.*

### High

1. **Title:** Search relevance sort may embed user query in raw SQL  
   **Conclusion:** User-controlled `$query` is concatenated into `orderRaw`, which is a classic SQL injection pattern unless the ORM escapes it (not evidenced here).  
   **Evidence:** `repo/backend/app/service/SearchService.php:76-79`  
   **Impact:** Potential data breach or DB manipulation via search API for any role with `search.read`.  
   **Minimum fix:** Use parameter binding, strict allowlist for sort modes, or escape/`quote` via database layer; add tests for malicious `query` strings.

2. **Title:** Reviewer cannot list/view arbitrary orders despite seeded `orders.read`  
   **Conclusion:** `listOrders` / `getOrder` restrict non–ops/admin users to `created_by = current user`, excluding `reviewer` from org-wide order visibility implied by seed and prompt (approvals, address corrections).  
   **Evidence:** `repo/backend/app/service/OrderService.php:33-47`, `repo/backend/app/service/OrderService.php:68-77`, `repo/backend/database/seeds/DatabaseSeeder.php:56-63`  
   **Impact:** Reviewer UX/API may be unusable for reviewing others’ orders; contradicts RBAC seed.  
   **Minimum fix:** Extend object-level rules explicitly (e.g. reviewer/administrator/operations_staff scopes) and align `DatabaseSeeder` with `OrderService`.

3. **Title:** Activity list likely shows duplicate rows per activity (per version)  
   **Conclusion:** `listActivities` queries `ActivityVersion` without grouping by `group_id` or selecting only the latest version per group.  
   **Evidence:** `repo/backend/app/service/ActivityService.php:33-56`  
   **Impact:** List pages can show multiple cards per logical activity; conflicts with “browse activities” expectation.  
   **Minimum fix:** Subquery or `GROUP BY group_id` with max version, or view table.

4. **Title:** Violation service object-level checks inactive from HTTP layer  
   **Conclusion:** `ViolationController` calls `listViolations` and `getViolation` without passing `currentUserId` / `currentRole`, so `ViolationService`’s `regular_user` restrictions default to no-op. Today `regular_user` lacks `violations.read` in seed (`repo/backend/database/seeds/DatabaseSeeder.php:66-73`), so RBAC may mask this — defense-in-depth fails.  
   **Evidence:** `repo/backend/app/controller/ViolationController.php:84-103`, `repo/backend/app/service/ViolationService.php:108-145`  
   **Impact:** Future permission changes could expose violations broadly without additional code.  
   **Minimum fix:** Pass `$request->user->id` and `$request->user->role` into service methods; add tests.

5. **Title:** `group_id` filter ignored in violation list  
   **Conclusion:** Controller passes `group_id`; service never applies it to the query.  
   **Evidence:** `repo/backend/app/controller/ViolationController.php:88-91`, `repo/backend/app/service/ViolationService.php:108-119`  
   **Impact:** Broken filter for group-scoped violation views.  
   **Minimum fix:** Join `violations` to user/group membership or add `group_id` column filter per schema.

### Medium

6. **Title:** Published activity edit creates new **draft** version — “current” detail may show Draft  
   **Conclusion:** `createNewVersion` sets `state` to `draft` (`repo/backend/app/service/ActivityService.php:224-236`); `getActivity` loads latest version (`repo/backend/app/service/ActivityService.php:76-78`), so users may see `draft` as the headline state after an edit.  
   **Impact:** Ambiguous vs prompt “published edits create new version” while users still browse published content.  
   **Minimum fix:** Define canonical “public” version (e.g. latest published) vs draft fork; document in API.

7. **Title:** README / feature bullets vs export implementation  
   **Conclusion:** README Key Features list says “CSV” (`repo/README.md:108`); `ExportService` implements PNG, PDF, XLSX (`repo/backend/app/service/ExportService.php:9-203`).  
   **Impact:** Documentation drift.  
   **Minimum fix:** Align README with actual formats.

8. **Title:** E2E tests use non-canonical roles (`admin`, `client`)  
   **Conclusion:** `testRoleBasedAccessInSession` seeds `admin` / `client` (`repo/e2e_tests/AuthFlowTest.php:127-141`) while application roles are `administrator`, `regular_user`, etc. (`repo/backend/database/seeds/DatabaseSeeder.php:19-74`). Permissions depend on `roles` table rows for those names — **not** created in this test — so `hasPermission` may not match test intent.  
   **Impact:** Tests may be misleading or fail depending on DB state.  
   **Minimum fix:** Seed matching `roles` rows or use canonical role names.

9. **Title:** Auto-cancel command bypasses `OrderService` / audit consistency  
   **Conclusion:** CLI sets state and writes history directly (`repo/backend/app/command/AutoCancelOrders.php:20-34`) without calling `OrderService` helpers that also log audit/search side effects (if any).  
   **Impact:** Possible inconsistent audit trails or search index vs order state.  
   **Minimum fix:** Route through `OrderService` or shared domain function.

### Low

10. **Title:** README `.env` path vs repository layout  
    **Conclusion:** README references `backend/.env` from `.env.example` (`repo/README.md:114-115`); example file is `repo/.env.example`.  
    **Impact:** Setup friction.  
    **Minimum fix:** Point README to `repo/.env.example` or add `backend/.env.example` symlink/copy.

11. **Title:** README order lifecycle summary omits `Canceled`  
    **Conclusion:** README Key Features line simplifies flow (`repo/README.md:100-101`) vs full machine in code (`repo/backend/app/service/OrderService.php:20-28`).  
    **Impact:** Doc accuracy only.

---

## 6. Security Review Summary

| Area | Conclusion | Evidence & reasoning |
|------|------------|----------------------|
| **Authentication entry points** | Partial Pass | Login posts JSON to `AuthController::login` (`repo/backend/route/app.php:14-15`, `repo/backend/app/controller/AuthController.php:21-61`); `AuthMiddleware` requires `Bearer` token (`repo/backend/app/middleware/AuthMiddleware.php:15-34`). Password policy on **create** via `UserValidate` (`repo/backend/app/validate/UserValidate.php:11-22`); **login** does not enforce min length (`repo/backend/app/controller/AuthController.php:26-35`) — acceptable if policy is account-level only; prompt wording is slightly ambiguous. |
| **Route-level authorization** | Partial Pass | Inner route group uses `auth` + `rbac` with permission strings (`repo/backend/route/app.php:315-316`, `repo/backend/route/app.php:24-41`). |
| **Object-level authorization** | Fail (specific modules) | Orders: restrictive creator filter omits reviewer (`repo/backend/app/service/OrderService.php:33-47`). Violations: service supports `regular_user` scoping but controller omits user context (`repo/backend/app/controller/ViolationController.php:84-103`). Uploads: uploader vs admin/ops check (`repo/backend/app/service/UploadService.php:74-76`). |
| **Function-level authorization** | Partial Pass | Refund checks `administrator` in service (`repo/backend/app/service/OrderService.php:270-276`); address approval checks reviewer/admin (`repo/backend/app/service/OrderService.php:396-400`). |
| **Tenant / user isolation** | Cannot Confirm Statistically | Single-tenant on-prem assumption; no tenant ID in reviewed snippets. |
| **Admin / internal / debug protection** | Partial Pass | `GET api/v1/ping` and `POST auth/login` outside `auth` (`repo/backend/route/app.php:11-15`); rebuild/cleanup behind `index.manage` (`repo/backend/route/app.php:237-243`). No extra IP allowlist evidenced — **Manual verification** for exposure in deployment. |

---

## 7. Tests and Logging Review

### Unit tests

- **Conclusion:** Partial Pass  
- **Evidence:** `repo/phpunit.xml:10-19`, `repo/unit_tests/services/AuthServiceTest.php:1-120` — exercises `User` password helpers, not full `AuthService` login.  
- **Gap:** Limited coverage of domain services (orders, activities, violations).

### API / integration tests

- **Conclusion:** Partial Pass (misleading naming)  
- **Evidence:** `repo/API_tests/AuthApiTest.php` uses `AuthService` and **database-backed** `User`/`Session` (`repo/API_tests/AuthApiTest.php:136-147`), not HTTP client tests.  
- **Gap:** No static evidence of HTTP-level contract tests for routes/middleware.

### Logging / observability

- **Conclusion:** Partial Pass  
- **Evidence:** `Log::warning` / `Log::info` in auth and RBAC (`repo/backend/app/service/AuthService.php:21-52`, `repo/backend/app/middleware/RbacMiddleware.php:34-35`); log channel config (`repo/backend/config/log.php:1-28`).  
- **Sensitive-data leakage risk:** Logs include usernames and paths; no passwords logged in reviewed snippets — **Cannot Confirm Statistically** for all code paths.

---

## 8. Test Coverage Assessment (Static Audit)

### 8.1 Test Overview

- **Unit tests:** Present under `repo/unit_tests/` (`repo/phpunit.xml:11-12`).  
- **API / e2e suites:** Directories `repo/API_tests/`, `repo/e2e_tests/` (`repo/phpunit.xml:14-18`).  
- **Framework:** PHPUnit 10 (`repo/phpunit.xml:1-8`, `repo/backend/composer.json:14-15`).  
- **Entry point:** `repo/run_tests.sh` invokes `./backend/vendor/bin/phpunit --configuration phpunit.xml` (`repo/run_tests.sh:13`).  
- **Documentation:** README documents `./run_tests.sh` (`repo/README.md:51-57`).  
- **Bootstrap:** `unit_tests/bootstrap.php` loads backend vendor and two models (`repo/unit_tests/bootstrap.php:5-10`).  
- **Legacy / duplicate:** `repo/backend/tests/AuthTest.php` contains generic assertions not wired to application classes (`repo/backend/tests/AuthTest.php:7-46`).

### 8.2 Coverage Mapping Table

| Requirement / Risk Point | Mapped Test Case(s) | Key Assertion / Fixture | Coverage Assessment | Gap | Minimum Test Addition |
|--------------------------|---------------------|-------------------------|----------------------|-----|------------------------|
| Password hashing & lockout mechanics | `AuthServiceTest`, `e2e_tests/AuthFlowTest.php` | `User::verifyPassword`, lockout loop (`repo/e2e_tests/AuthFlowTest.php:88-106`) | Basically covered for model/e2e auth service | HTTP 401/403/429 from `AuthController` not asserted | PHPUnit with HTTP kernel or route tests |
| RBAC permission matrix | `e2e_tests/AuthFlowTest.php:127-147` | `hasPermission('users.read')` | Insufficient | Uses non-canonical roles `admin`/`client` without seeding `roles` rows (`repo/e2e_tests/AuthFlowTest.php:129-141`) | Seed roles or use `administrator` / DB fixture |
| Order state machine (refund, cancel, close) | *None found in reviewed tests* | — | Missing | Transitions, ticketed-only close | `OrderService` unit tests with mocked models |
| Reviewer order visibility | *None* | — | Missing | Known service/seed mismatch | Controller + service tests for `reviewer` |
| Search SQL safety | *None* | — | Missing | `orderRaw` risk | Tests with malicious `query` strings; assert safe SQL or rejection |
| Violation object-level auth | *None* | — | Missing | Controller omits user context | Integration test ensuring `regular_user` cannot read others’ violations if granted read |
| Activity list de-duplication | *None* | — | Missing | Version rows | Test list endpoint returns one row per `group_id` |
| Incremental indexing / cleanup | *None* | — | Cannot confirm | `SearchService::cleanup` | Unit test with fixture index rows |

### 8.3 Security Coverage Audit

| Risk | Test coverage conclusion |
|------|----------------------------|
| Authentication | Partial: service login covered; **not** middleware/`AuthController` JSON contracts. |
| Route authorization | **Missing** static evidence of tests for `rbac` middleware denial (403). |
| Object-level authorization | **Missing** for orders (reviewer) and violations (user context). |
| Tenant isolation | Not applicable / not tested. |
| Admin / index rebuild | **Missing** — privileged routes not exercised in reviewed tests. |

**Severe defects could remain undetected** even if PHPUnit passes (e.g. search SQL injection, reviewer order access, violation scoping).

### 8.4 Final Coverage Judgment

**Fail**

**Explanation:** Tests exist and exercise **auth-related model/service behavior**, but there is **insufficient static evidence** of automated coverage for core business invariants (order machine, RBAC at HTTP layer, search safety, reviewer workflows, violation scoping). Several tests depend on database state and **non-canonical role names**, reducing reliability.

---

## 9. Final Notes

This audit is **static-only**: “Pass” for any behavior means “consistent with code paths read,” not “verified running.” The highest-priority follow-ups for a human reviewer are: **confirm and fix search SQL construction**, **align order visibility with reviewer/seed RBAC**, **fix activity listing semantics**, **wire violation controllers to service authorization parameters**, and **replace or repair e2e RBAC tests** to use seeded role names and `roles` table data.

**Evidence traceability:** All file paths are relative to the workspace root `campusops/` unless noted as `repo/...` for the deliverable subtree.
