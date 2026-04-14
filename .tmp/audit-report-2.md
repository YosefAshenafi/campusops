# Delivery Acceptance and Project Architecture Audit (Static-Only)

## 1. Verdict
- **Overall conclusion: Partial Pass**

The repository is a substantial implementation of the requested on-prem campus operations portal, but it has material requirement and security-aligned defects, including one **Blocker** in order lifecycle enforcement and multiple **High** issues in object-level authorization and requirement fit.

## 2. Scope and Static Verification Boundary
- **What was reviewed**
  - Documentation, startup/config manifests, env examples, routing, middleware, controllers, services, migrations, seeders, frontend Layui modules/views, and test suites.
  - Key files: `README.md:1-68`, `backend/route/app.php:6-319`, `backend/app/service/*.php`, `frontend/src/views/**/*.html`, `frontend/src/modules/*.js`, `phpunit.xml:1-34`, `unit_tests/bootstrap.php:1-240`.
- **What was not reviewed**
  - Runtime behavior under real HTTP server/browser/session timing/network and any external integrations.
- **What was intentionally not executed**
  - Project startup, Docker, tests, migrations, cron jobs, or any runtime command.
- **Claims requiring manual verification**
  - Browser-level UX correctness and responsive behavior under real rendering.
  - Actual cron execution timing and persistence behavior in deployed environment.
  - File export rendering fidelity for PNG/PDF/XLSX in target environment.

## 3. Repository / Requirement Mapping Summary
- **Prompt core goal**: Unified offline campus operations + logistics portal with role-based access, strict activity/order lifecycles, search/recommendations, dashboards, violation workflows, and security/audit controls.
- **Mapped implementation areas**:
  - Role/routing/auth/RBAC: `backend/route/app.php`, auth + middleware stack.
  - Core business modules: activities/orders/shipments/violations/search/recommendations/dashboard/export.
  - Frontend role navigation and Layui views: `frontend/public/index.html:102-139` and modules.
  - Persistence and schema: `backend/database/migrations/*`.
  - Static test evidence: `unit_tests`, `API_tests`, `e2e_tests`, `backend/tests`.

## 4. Section-by-section Review

### 4.1 Hard Gates
#### 4.1.1 Documentation and static verifiability
- **Conclusion: Pass**
- **Rationale**: Startup, migration/seed, and test instructions exist; route/module structure is statically navigable and consistent.
- **Evidence**: `README.md:5-35`, `README.md:56-64`, `Makefile` targets referenced in README, `backend/route/app.php:6-319`, `phpunit.xml:18-27`.

#### 4.1.2 Material deviation from prompt
- **Conclusion: Partial Pass**
- **Rationale**: Project is centered on the requested business domain, but several explicit prompt constraints are weakened or only partially implemented (paid-order refund rule bypass; dashboard snapshot export formats; search UI requirement fit).
- **Evidence**: `backend/app/service/OrderService.php:309-323`, `backend/database/seeds/DatabaseSeeder.php:35-40`, `backend/app/controller/DashboardController.php:84-103`, `frontend/src/modules/search.js:106-109`.

### 4.2 Delivery Completeness
#### 4.2.1 Coverage of explicit core requirements
- **Conclusion: Partial Pass**
- **Rationale**: Many core requirements are implemented (role navigation, activity/order states, uploads with SHA-256/type/size checks, appeal workflow, local indexing + cleanup), but several explicit requirements are incomplete or inconsistent.
- **Evidence**:
  - Implemented: `frontend/public/index.html:102-139`, `backend/app/service/ActivityService.php:301-395`, `backend/app/service/UploadService.php:9-38`, `backend/app/service/ViolationService.php:252-335`, `backend/app/service/SearchService.php:304-323`.
  - Gaps: `backend/app/service/OrderService.php:316-323`, `backend/app/controller/DashboardController.php:102-103`, `frontend/src/views/activities/detail.html:42-59`, `frontend/src/modules/search.js:106-109`.

#### 4.2.2 End-to-end 0→1 deliverable vs demo/fragment
- **Conclusion: Pass**
- **Rationale**: Repository includes full backend/frontend structure, migrations, seeders, tests, route wiring, and docs; not a single-file demo.
- **Evidence**: `README.md:1-68`, `backend/route/app.php:6-319`, `backend/database/migrations/20260414100001_create_roles_table.php` (and peer files), `frontend/public/index.html:1-204`.

### 4.3 Engineering and Architecture Quality
#### 4.3.1 Module decomposition and structure
- **Conclusion: Pass**
- **Rationale**: Services/controllers/middleware/models are separated by domain with clear route mapping and command jobs.
- **Evidence**: `backend/app/service/*`, `backend/app/controller/*`, `backend/app/middleware/*`, `backend/config/console.php:5-8`.

#### 4.3.2 Maintainability/extensibility
- **Conclusion: Partial Pass**
- **Rationale**: Architecture is generally maintainable, but key business-rule centralization is inconsistent (state-machine bypass path; missing create-time object authorization checks in multiple services).
- **Evidence**: `backend/app/service/OrderService.php:283-327`, `backend/app/service/TaskService.php:27-43`, `backend/app/service/ChecklistService.php:36-58`, `backend/app/service/StaffingService.php:16-32`.

### 4.4 Engineering Details and Professionalism
#### 4.4.1 Error handling/logging/validation/API design
- **Conclusion: Partial Pass**
- **Rationale**: Basic error handling and logging exist, but there are validation/authorization blind spots and uneven API semantics.
- **Evidence**:
  - Positive: `backend/app/middleware/AuthMiddleware.php:19-53`, `backend/app/middleware/RbacMiddleware.php:34-43`, `backend/config/log.php:4-27`.
  - Gaps: `backend/app/service/TaskService.php:27-43`, `backend/app/service/ChecklistService.php:36-58`, `backend/app/service/StaffingService.php:16-32`, `backend/app/service/OrderService.php:316-323`.

#### 4.4.2 Product-like delivery vs teaching sample
- **Conclusion: Pass**
- **Rationale**: Overall shape resembles a real application (multi-role UI, domain modules, migrations, seed data, scheduled tasks, export pipeline).
- **Evidence**: `frontend/public/index.html:102-139`, `backend/app/command/AutoCancelOrders.php:14-37`, `docker/crontab:1-6`.

### 4.5 Prompt Understanding and Requirement Fit
#### 4.5.1 Business goal/scenario/constraints fit
- **Conclusion: Partial Pass**
- **Rationale**: Core business scenario is largely understood, but strict constraints are violated or only partially met: paid-order restriction, dashboard snapshot export formats, and UI-level requirement completeness for activity rule visibility/search controls.
- **Evidence**: `backend/app/service/OrderService.php:294-299`, `backend/app/service/OrderService.php:316-323`, `backend/app/controller/DashboardController.php:84-103`, `frontend/src/views/activities/detail.html:42-59`, `frontend/src/modules/search.js:106-109`.

### 4.6 Aesthetics (frontend-only / full-stack)
#### 4.6.1 Visual and interaction quality
- **Conclusion: Partial Pass**
- **Rationale**: UI has coherent structure, spacing, badges, and interaction cues, but requirement-specific information hierarchy is incomplete (missing eligibility tags and lifecycle timestamps in activity detail), and search highlighting is not surfaced in UI.
- **Evidence**: `frontend/src/assets/css/app.css:91-142`, `frontend/src/views/activities/detail.html:42-59`, `frontend/src/modules/activities.js:163-175`, `frontend/src/modules/search.js:127-134`.
- **Manual verification note**: Cross-browser responsiveness and final rendering quality require manual browser validation.

## 5. Issues / Suggestions (Severity-Rated)

### Blocker
1. **Paid-order refund restriction bypass via cancel path**
- **Conclusion**: Fail
- **Evidence**: `backend/app/service/OrderService.php:316-323`, `backend/database/seeds/DatabaseSeeder.php:35-40`, `backend/route/app.php:99-100`
- **Impact**: Non-admin users with `orders.cancel` (operations staff) can cancel `paid` orders directly, bypassing prompt rule “Paid orders can be refunded only by an Administrator before Ticketed.”
- **Minimum actionable fix**: In `cancel()`, explicitly block `paid` and require `refund()` path; or enforce role/state guard so paid->canceled is only through admin refund endpoint.

### High
2. **Missing object-level authorization on create operations (task/checklist/staffing)**
- **Conclusion**: Fail
- **Evidence**: `backend/app/service/TaskService.php:27-43`, `backend/app/service/ChecklistService.php:36-58`, `backend/app/service/StaffingService.php:16-32`
- **Impact**: Team leads can create records under activities they do not own (update/delete are protected, create is not), violating ownership boundaries.
- **Minimum actionable fix**: Reuse `assertActivityAccess()` in each create method before insert.

3. **Dashboard snapshot export does not support PDF/Excel formats**
- **Conclusion**: Fail
- **Evidence**: `backend/app/controller/DashboardController.php:84-103`
- **Impact**: Prompt requires snapshot exports to PNG/PDF/Excel; current snapshot endpoint hardcodes PNG only.
- **Minimum actionable fix**: Add `format` parameter and route to `ExportService::exportToPdf`/`exportToExcel` for dashboard snapshot data.

4. **Global search requirement only partially exposed in UI (missing core filters/sort + highlight rendering)**
- **Conclusion**: Fail
- **Evidence**: `backend/app/service/SearchService.php:29-80`, `backend/app/controller/SearchController.php:28-33`, `frontend/src/modules/search.js:106-109`, `frontend/src/views/search/results.html:14-32`, `frontend/src/modules/search.js:127-134`
- **Impact**: Backend supports richer global filtering/sort/highlight, but frontend global search does not expose author/tag/reply-count/sort controls and does not render highlight fields; requirement fit is incomplete.
- **Minimum actionable fix**: Add global filter/sort controls and render `highlights.title/body/author/tags` when present.

### Medium
5. **Archived activity edit path does not enforce “post-publish edits create new version” rule**
- **Conclusion**: Partial Fail
- **Evidence**: `backend/app/service/ActivityService.php:215-218`
- **Impact**: Once activity reaches `archived`, update may mutate current version directly rather than creating a new version, conflicting with strict immutable-history interpretation.
- **Minimum actionable fix**: Include `STATE_ARCHIVED` in post-publish createNewVersion condition.

6. **Activity detail view omits required “eligibility tags” and lifecycle transition timestamps**
- **Conclusion**: Partial Fail
- **Evidence**: `frontend/src/views/activities/detail.html:42-59`, `frontend/src/modules/activities.js:163-175`, `backend/app/service/ActivityService.php:533-538`
- **Impact**: Prompt requires clear publish-time rules and visible lifecycle timestamps; backend returns data but UI does not present these fields in detail view.
- **Minimum actionable fix**: Add eligibility tag section and explicit published/started/completed/archived timestamp display using `common.formatDateTime`.

7. **Recommendation policy only partially matches prompt diversity/dedup semantics**
- **Conclusion**: Partial Fail
- **Evidence**: `backend/app/service/RecommendationService.php:115-127`, `backend/app/service/RecommendationService.php:172-182`, `backend/app/service/RecommendationService.php:233-239`
- **Impact**: Cold-start uses “family” cap instead of explicit per-tag cap behavior; order recommendations dedup by order id only (not clear order-family dedup), risking mismatch with required feed constraints.
- **Minimum actionable fix**: Apply explicit per-tag 40% cap in cold start and define/order-family dedup key for order recommendations.

### Low
8. **Legacy trivial test file has low assurance value**
- **Conclusion**: Improvement suggested
- **Evidence**: `backend/tests/AuthTest.php:19-47`
- **Impact**: Test suite contains simplistic assertions not tied to application behavior; can create false confidence.
- **Minimum actionable fix**: Replace or de-prioritize legacy test file in CI and expand behavior-driven tests.

## 6. Security Review Summary
- **Authentication entry points**: **Pass**
  - Evidence: `backend/app/controller/AuthController.php:21-61`, `backend/app/middleware/AuthMiddleware.php:19-45`, `backend/app/service/AuthService.php:30-48`, `backend/app/model/User.php:48-54`
  - Reasoning: Bearer token validation, lockout, salted hashing, inactive-account blocking are implemented.

- **Route-level authorization**: **Pass**
  - Evidence: `backend/route/app.php:18-317`, `backend/config/middleware.php:9-14`, `backend/app/middleware/RbacMiddleware.php:17-45`
  - Reasoning: Protected routes use auth + permission middleware systematically.

- **Object-level authorization**: **Partial Pass**
  - Evidence: Positive in orders/violations/signups: `backend/app/service/OrderService.php:45-47`, `backend/app/service/ViolationService.php:111-114`, `backend/app/service/ActivityService.php:129-132`; gap in create operations: `TaskService.php:27-43`, `ChecklistService.php:36-58`, `StaffingService.php:16-32`.
  - Reasoning: Coverage is inconsistent; create paths miss checks.

- **Function-level authorization**: **Fail**
  - Evidence: `backend/app/service/OrderService.php:316-323` with route permission `orders.cancel` for operations staff `DatabaseSeeder.php:39`
  - Reasoning: Business-critical function constraint (paid cancellation/refund) can be bypassed.

- **Tenant / user data isolation**: **Partial Pass**
  - Evidence: `OrderService.php:45-47,95-97`, `ActivityService.php:129-132`, `ViolationService.php:112-114,155-157`.
  - Reasoning: Multiple modules isolate regular users to own data; still weakened by object-level create gaps.

- **Admin / internal / debug endpoint protection**: **Pass**
  - Evidence: index/export/audit/admin-sensitive routes require RBAC (`backend/route/app.php:237-316`); only health ping is open (`backend/route/app.php:11-13`).
  - Reasoning: No obvious unguarded debug/admin endpoint found.

## 7. Tests and Logging Review
- **Unit tests**: **Partial Pass**
  - Evidence: `unit_tests/services/*.php`, `phpunit.xml:19-21`.
  - Reasoning: Good service coverage in several domains, but major requirement gaps remain untested (e.g., paid cancel bypass, create-time object auth gaps, dashboard snapshot format requirements).

- **API / integration tests**: **Partial Pass**
  - Evidence: `API_tests/HttpMiddlewareTest.php:47-175`, `API_tests/ObjectAuthTest.php:68-181`, `API_tests/RbacApiTest.php:38-77`, `phpunit.xml:22-27`.
  - Reasoning: Middleware and selected auth patterns are tested; endpoint-level coverage is incomplete for high-risk flows.

- **Logging categories / observability**: **Pass**
  - Evidence: `backend/config/log.php:4-27`, logs in auth/order/rbac/violation services (`AuthService.php:21,33,52`, `OrderService.php:477`, `RbacMiddleware.php:37`, `ViolationService.php:191`).
  - Reasoning: Structured logging exists across critical flows.

- **Sensitive-data leakage risk in logs/responses**: **Partial Pass**
  - Evidence: masking middleware `SensitiveDataMiddleware.php:10-13,19-21`; encrypted placeholder response `OrderService.php:499`; RBAC/auth logs include user identifiers `RbacMiddleware.php:37`, `AuthService.php:39`.
  - Reasoning: Core secrets are mostly protected, but sensitive-field masking is narrow and identifier-rich logs still need policy review.

## 8. Test Coverage Assessment (Static Audit)

### 8.1 Test Overview
- Unit tests exist: `unit_tests/services/*` (`phpunit.xml:19-21`)
- API/integration-style tests exist: `API_tests/*` (`phpunit.xml:22-24`)
- E2E folder exists: `e2e_tests/AuthFlowTest.php` (`phpunit.xml:25-27`)
- Test bootstrap uses in-memory SQLite: `unit_tests/bootstrap.php:12-27`
- Test command documented: `README.md:56-64`, script `run_tests.sh:13`

### 8.2 Coverage Mapping Table

| Requirement / Risk Point | Mapped Test Case(s) | Key Assertion / Fixture / Mock | Coverage Assessment | Gap | Minimum Test Addition |
|---|---|---|---|---|---|
| Auth lockout after 5 attempts, 15 min | `API_tests/AuthApiTest.php:84-94`; `unit_tests/services/AuthServiceTest.php` | Expects 429 on fifth failure (`AuthApiTest.php:90-94`) | basically covered | No direct middleware+controller integration assertion | Add API-level auth route contract tests (login responses + lockout payload semantics) |
| 401 unauthenticated and 403 unauthorized middleware behavior | `API_tests/HttpMiddlewareTest.php:47-175` | Asserts Auth/RBAC status codes | sufficient | None major | Keep, add route sampling tests |
| Order state machine strictness (paid refund admin-only) | `unit_tests/services/OrderServiceTest.php:65-80` | Refund role checks only | insufficient | No test preventing `cancel()` on paid orders; bypass undetected | Add test asserting cancel on paid is rejected for non-admin and admin unless refund path |
| Pending payment auto-cancel path | `unit_tests/services/OrderServiceTest.php:107-135` | Checks `cancelBySystem` transition/history | basically covered | Does not verify scheduler wiring | Add command test for `AutoCancelOrders` query boundaries |
| Object-level auth for task/checklist/staffing updates/deletes | `API_tests/ObjectAuthTest.php:68-181` | 403 for non-owner update/delete | basically covered | No create-path object auth tests | Add tests for create under another user's activity (expect 403) |
| Activity versioning on post-publish edits | `unit_tests/services/ActivityServiceTest.php:254-265` | New draft version created after publish | basically covered | No archived-state update versioning check | Add test: update archived activity must create new version |
| Search highlight/filter/sort behavior | `unit_tests/services/SearchServiceTest.php:87-118,323-335` | Sort flag assertions, highlight presence | basically covered (backend) | No frontend/UI test that highlights/filters are surfaced | Add frontend static/unit tests for search UI params and highlight rendering |
| Violation appeals with decision notes | `unit_tests/services/ViolationServiceTest.php:304-340` | Review notes required, permission checks | sufficient | Limited API route-level validation tests | Add controller/API tests for 400/403 branches |
| Export watermark behavior | none found for export services/controllers | N/A | missing | No tests for watermark inclusion or format branching | Add unit tests for `ExportService` outputs + controller format routing |
| Dashboard snapshot export format requirements | none found | N/A | missing | No tests for snapshot format support | Add tests for `snapshot` accepting png/pdf/xlsx and returning correct download metadata |

### 8.3 Security Coverage Audit
- **Authentication**: **Basically covered**
  - Evidence: `AuthApiTest.php`, `HttpMiddlewareTest.php`.
- **Route authorization**: **Basically covered**
  - Evidence: `HttpMiddlewareTest.php:116-175`, `RbacApiTest.php:38-63`.
- **Object-level authorization**: **Insufficient**
  - Evidence: tests focus on update/delete (`ObjectAuthTest.php`), not create paths where real gaps exist.
- **Tenant/data isolation**: **Basically covered**
  - Evidence: order/violation ownership tests exist; coverage not comprehensive across all entities.
- **Admin/internal protection**: **Insufficient**
  - Evidence: no dedicated tests for audit/export/index-management endpoint protections.

### 8.4 Final Coverage Judgment
- **Partial Pass**
- Major auth and some object-auth paths are covered, but severe defects could still pass tests today (paid-order cancel bypass, create-time object auth gaps, snapshot export format gaps, and search UI requirement drift).

## 9. Final Notes
- This is a static-only assessment with evidence-backed findings.
- The most urgent acceptance blocker is the paid-order state-machine bypass (`cancel` path).
- After fixing blocker/high issues, rerun a focused static re-audit plus manual verification for UI requirement fit and export behavior.
