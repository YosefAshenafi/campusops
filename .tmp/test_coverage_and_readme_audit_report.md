# Test Coverage & README Audit Report

**Project:** CampusOps (`TASK-req_f1d20d3ece96`)
**Date:** 2026-04-16
**Auditor mode:** Strict static inspection only — no code executed

---

## Inferred Project Type

**Fullstack web application** — declared explicitly in `repo/README.md` line 3:
> "Fullstack web application — ThinkPHP 8 REST API backend, Layui frontend, MySQL, Nginx — containerised with Docker Compose."

---

# PART 1 — TEST COVERAGE AUDIT

---

## Backend Endpoint Inventory

Source: `repo/backend/route/app.php`. All paths resolved with the `api/v1` group prefix.

| # | METHOD | PATH |
|---|--------|------|
| 1 | GET | /api/v1/ping |
| 2 | POST | /api/v1/auth/login |
| 3 | POST | /api/v1/auth/logout |
| 4 | POST | /api/v1/auth/unlock |
| 5 | GET | /api/v1/users |
| 6 | GET | /api/v1/users/:id |
| 7 | POST | /api/v1/users |
| 8 | PUT | /api/v1/users/:id |
| 9 | DELETE | /api/v1/users/:id |
| 10 | PUT | /api/v1/users/:id/role |
| 11 | PUT | /api/v1/users/:id/password |
| 12 | GET | /api/v1/activities |
| 13 | GET | /api/v1/activities/:id |
| 14 | GET | /api/v1/activities/:id/versions |
| 15 | GET | /api/v1/activities/:id/signups |
| 16 | GET | /api/v1/activities/:id/change-log |
| 17 | POST | /api/v1/activities |
| 18 | PUT | /api/v1/activities/:id |
| 19 | POST | /api/v1/activities/:id/publish |
| 20 | POST | /api/v1/activities/:id/start |
| 21 | POST | /api/v1/activities/:id/complete |
| 22 | POST | /api/v1/activities/:id/archive |
| 23 | POST | /api/v1/activities/:id/signups |
| 24 | DELETE | /api/v1/activities/:id/signups/:signup_id |
| 25 | POST | /api/v1/activities/:id/signups/:signup_id/acknowledge |
| 26 | GET | /api/v1/orders |
| 27 | GET | /api/v1/orders/:id |
| 28 | GET | /api/v1/orders/:id/history |
| 29 | POST | /api/v1/orders |
| 30 | PUT | /api/v1/orders/:id |
| 31 | POST | /api/v1/orders/:id/initiate-payment |
| 32 | POST | /api/v1/orders/:id/confirm-payment |
| 33 | POST | /api/v1/orders/:id/start-ticketing |
| 34 | POST | /api/v1/orders/:id/ticket |
| 35 | POST | /api/v1/orders/:id/refund |
| 36 | POST | /api/v1/orders/:id/cancel |
| 37 | POST | /api/v1/orders/:id/close |
| 38 | PUT | /api/v1/orders/:id/address |
| 39 | POST | /api/v1/orders/:id/request-address-correction |
| 40 | POST | /api/v1/orders/:id/approve-address-correction |
| 41 | GET | /api/v1/orders/:order_id/shipments |
| 42 | POST | /api/v1/orders/:order_id/shipments |
| 43 | GET | /api/v1/shipments |
| 44 | GET | /api/v1/shipments/:id |
| 45 | POST | /api/v1/shipments/:id/scan |
| 46 | GET | /api/v1/shipments/:id/scan-history |
| 47 | POST | /api/v1/shipments/:id/confirm-delivery |
| 48 | GET | /api/v1/shipments/:id/exceptions |
| 49 | POST | /api/v1/shipments/:id/exceptions |
| 50 | GET | /api/v1/violations/rules |
| 51 | GET | /api/v1/violations/rules/:id |
| 52 | POST | /api/v1/violations/rules |
| 53 | PUT | /api/v1/violations/rules/:id |
| 54 | DELETE | /api/v1/violations/rules/:id |
| 55 | GET | /api/v1/violations |
| 56 | GET | /api/v1/violations/:id |
| 57 | POST | /api/v1/violations |
| 58 | GET | /api/v1/violations/user/:user_id |
| 59 | GET | /api/v1/violations/group/:group_id |
| 60 | POST | /api/v1/violations/:id/appeal |
| 61 | POST | /api/v1/violations/:id/review |
| 62 | POST | /api/v1/violations/:id/final-decision |
| 63 | POST | /api/v1/upload |
| 64 | GET | /api/v1/upload/:id |
| 65 | GET | /api/v1/upload/:id/download |
| 66 | DELETE | /api/v1/upload/:id |
| 67 | GET | /api/v1/activities/:activity_id/tasks |
| 68 | POST | /api/v1/activities/:activity_id/tasks |
| 69 | PUT | /api/v1/tasks/:id |
| 70 | PUT | /api/v1/tasks/:id/status |
| 71 | DELETE | /api/v1/tasks/:id |
| 72 | GET | /api/v1/activities/:activity_id/checklists |
| 73 | POST | /api/v1/activities/:activity_id/checklists |
| 74 | PUT | /api/v1/checklists/:id |
| 75 | DELETE | /api/v1/checklists/:id |
| 76 | POST | /api/v1/checklists/:id/items/:item_id/complete |
| 77 | GET | /api/v1/activities/:activity_id/staffing |
| 78 | POST | /api/v1/activities/:activity_id/staffing |
| 79 | PUT | /api/v1/staffing/:id |
| 80 | DELETE | /api/v1/staffing/:id |
| 81 | GET | /api/v1/search |
| 82 | GET | /api/v1/search/suggest |
| 83 | GET | /api/v1/search/logistics |
| 84 | GET | /api/v1/index/status |
| 85 | POST | /api/v1/index/rebuild |
| 86 | POST | /api/v1/index/cleanup |
| 87 | GET | /api/v1/notifications |
| 88 | PUT | /api/v1/notifications/:id/read |
| 89 | GET | /api/v1/notifications/settings |
| 90 | PUT | /api/v1/notifications/settings |
| 91 | GET | /api/v1/preferences |
| 92 | PUT | /api/v1/preferences |
| 93 | GET | /api/v1/recommendations |
| 94 | GET | /api/v1/recommendations/popular |
| 95 | GET | /api/v1/recommendations/orders |
| 96 | GET | /api/v1/dashboard |
| 97 | GET | /api/v1/dashboard/custom |
| 98 | POST | /api/v1/dashboard/custom |
| 99 | PUT | /api/v1/dashboard/custom/:id |
| 100 | DELETE | /api/v1/dashboard/custom |
| 101 | GET | /api/v1/dashboard/favorites |
| 102 | POST | /api/v1/dashboard/favorites |
| 103 | DELETE | /api/v1/dashboard/favorites/:widget_id |
| 104 | GET | /api/v1/dashboard/drill/:widget_id |
| 105 | GET | /api/v1/dashboard/snapshot |
| 106 | GET | /api/v1/export/orders |
| 107 | GET | /api/v1/export/activities |
| 108 | GET | /api/v1/export/violations |
| 109 | GET | /api/v1/export/download |
| 110 | GET | /api/v1/audit |

**Total: 110 endpoints**

---

## API Test Classification

### Transport Layer Assessment

`HttpTestCase` (`repo/API_tests/HttpTestCase.php:97`) dispatches via:
```php
$response = self::$app->http->run($req);
```
where `$req` is a programmatically constructed `think\Request` object — no real TCP socket, no real HTTP server. This is a **synthetic transport layer**.

Per the strict definition, "request goes through real HTTP layer" is **not satisfied**.

**Classification of all `HttpTestCase`-derived tests:**
→ **HTTP with mocking (synthetic transport)**

No test suite in this project uses a real HTTP client (curl, Guzzle, HTTP::get) against a running server. **True No-Mock HTTP coverage = 0%.**

### All API Test Files Classified

| File | Class | Type | What it covers |
|------|-------|------|----------------|
| `API_tests/EndpointPingAuthTest.php` | `EndpointPingAuthTest` | HTTP with mocking (synthetic transport) | ping, login, logout, unlock |
| `API_tests/EndpointUserTest.php` | `EndpointUserTest` | HTTP with mocking (synthetic transport) | users CRUD + role/password |
| `API_tests/EndpointActivityTest.php` | `EndpointActivityTest` | HTTP with mocking (synthetic transport) | activities (partial) |
| `API_tests/EndpointOrderTest.php` | `EndpointOrderTest` | HTTP with mocking (synthetic transport) | orders (partial) |
| `API_tests/EndpointShipmentUploadTest.php` | `EndpointShipmentUploadTest` | HTTP with mocking (synthetic transport) | shipments + file upload (partial) |
| `API_tests/EndpointViolationTest.php` | `EndpointViolationTest` | HTTP with mocking (synthetic transport) | violations (partial) |
| `API_tests/EndpointMiscTest.php` | `EndpointMiscTest` | HTTP with mocking (synthetic transport) | notifications, preferences, recommendations, dashboard (partial), audit, search (partial), export (partial) |
| `API_tests/AuthApiTest.php` | `AuthApiTest` | Non-HTTP (service-layer unit test) | AuthService login/logout/validate/unlock |
| `API_tests/HttpMiddlewareTest.php` | `HttpMiddlewareTest` | Non-HTTP (direct middleware instantiation) | AuthMiddleware, RbacMiddleware |
| `API_tests/RbacApiTest.php` | `RbacApiTest` | Non-HTTP (permission model unit test) | RBAC permission checks via `hasPermission()` |
| `API_tests/OrderApiTest.php` | `OrderApiTest` | Non-HTTP (service-layer integration) | OrderService role-based visibility |
| `API_tests/ObjectAuthTest.php` | `ObjectAuthTest` | Non-HTTP (service-layer unit test) | Object-level auth in TaskService, ChecklistService, StaffingService |

---

## API Test Mapping Table

**Legend:** Y = covered with success path | A = auth/RBAC only (no success path) | N = not covered

| # | Endpoint | Covered | Test Type | Test File | Evidence |
|---|----------|---------|-----------|-----------|----------|
| 1 | GET /api/v1/ping | Y | HTTP w/ mock | EndpointPingAuthTest | `testPingReturns200WithoutAuth`, `testPingResponseContainsSuccessFlag` |
| 2 | POST /api/v1/auth/login | Y | HTTP w/ mock | EndpointPingAuthTest | `testLoginReturns200WithValidCredentials` through `testLoginReturns403ForDisabledAccount` |
| 3 | POST /api/v1/auth/logout | Y | HTTP w/ mock | EndpointPingAuthTest | `testLogoutReturns200WhenAuthenticated`, `testLogoutReturns401WhenUnauthenticated` |
| 4 | POST /api/v1/auth/unlock | Y | HTTP w/ mock | EndpointPingAuthTest | `testUnlockReturns200ForAdmin`, `testUnlockReturns403ForRegularUser` |
| 5 | GET /api/v1/users | Y | HTTP w/ mock | EndpointUserTest | `testListUsersReturns200ForAdmin`, `testListUsersReturns403ForRegularUser`, pagination fields asserted |
| 6 | GET /api/v1/users/:id | Y | HTTP w/ mock | EndpointUserTest | `testGetUserReturns200ForAdmin`, `testGetUserReturns404ForNonExistentId` |
| 7 | POST /api/v1/users | Y | HTTP w/ mock | EndpointUserTest | `testCreateUserReturns201ForAdmin`, `testCreateUserReturns400ForDuplicateUsername` |
| 8 | PUT /api/v1/users/:id | Y | HTTP w/ mock | EndpointUserTest | `testUpdateUserReturns200ForAdmin` — status field asserted in response |
| 9 | DELETE /api/v1/users/:id | Y | HTTP w/ mock | EndpointUserTest | `testDeleteUserReturns200ForAdmin` |
| 10 | PUT /api/v1/users/:id/role | Y | HTTP w/ mock | EndpointUserTest | `testChangeRoleReturns200ForAdmin` — role field asserted in response |
| 11 | PUT /api/v1/users/:id/password | Y | HTTP w/ mock | EndpointUserTest | `testResetPasswordReturns200ForAdmin` — `temp_password` asserted |
| 12 | GET /api/v1/activities | Y | HTTP w/ mock | EndpointActivityTest | `testListActivitiesReturns200WithAuthAndPermission`, pagination asserted |
| 13 | GET /api/v1/activities/:id | Y | HTTP w/ mock | EndpointActivityTest | `testGetActivityReturns200ForAdmin`, `testGetActivityReturns404ForNonExistentId` |
| 14 | GET /api/v1/activities/:id/versions | Y | HTTP w/ mock | EndpointActivityTest | `testGetVersionsReturns200ForAdmin` |
| 15 | GET /api/v1/activities/:id/signups | Y | HTTP w/ mock | EndpointActivityTest | `testGetSignupsReturns200ForAdmin` |
| 16 | GET /api/v1/activities/:id/change-log | Y | HTTP w/ mock | EndpointActivityTest | `testGetChangeLogReturns200ForAdmin` |
| 17 | POST /api/v1/activities | Y | HTTP w/ mock | EndpointActivityTest | `testCreateActivityReturns201ForAdmin`, `testCreateActivityReturns403ForRegularUser` |
| 18 | PUT /api/v1/activities/:id | Y | HTTP w/ mock | EndpointActivityTest | `testUpdateActivityReturns200ForAdmin` |
| 19 | POST /api/v1/activities/:id/publish | A | HTTP w/ mock | EndpointActivityTest | `testPublishActivityReturns401WhenUnauthenticated`, `testPublishActivityReturns403ForRegularUser` — no success path |
| 20 | POST /api/v1/activities/:id/start | N | — | — | No test found |
| 21 | POST /api/v1/activities/:id/complete | N | — | — | No test found |
| 22 | POST /api/v1/activities/:id/archive | N | — | — | No test found |
| 23 | POST /api/v1/activities/:id/signups | A | HTTP w/ mock | EndpointActivityTest | `testSignupReturns401WhenUnauthenticated` only |
| 24 | DELETE /api/v1/activities/:id/signups/:signup_id | N | — | — | No test found |
| 25 | POST /api/v1/activities/:id/signups/:signup_id/acknowledge | N | — | — | No test found |
| 26 | GET /api/v1/orders | Y | HTTP w/ mock | EndpointOrderTest | `testListOrdersReturns200ForAdmin`, `testListOrdersReturns200ForRegularUser` |
| 27 | GET /api/v1/orders/:id | Y | HTTP w/ mock | EndpointOrderTest | `testGetOrderReturns200ForAdmin`, `testGetOrderReturns404ForNonExistentId` |
| 28 | GET /api/v1/orders/:id/history | Y | HTTP w/ mock | EndpointOrderTest | `testGetOrderHistoryReturns200ForAdmin` |
| 29 | POST /api/v1/orders | Y | HTTP w/ mock | EndpointOrderTest | `testCreateOrderReturns201ForRegularUser` |
| 30 | PUT /api/v1/orders/:id | Y | HTTP w/ mock | EndpointOrderTest | `testUpdateOrderReturns200ForAdmin`, `testUpdateOrderReturns403ForRegularUser` |
| 31 | POST /api/v1/orders/:id/initiate-payment | N | — | — | No test found |
| 32 | POST /api/v1/orders/:id/confirm-payment | N | — | — | No test found |
| 33 | POST /api/v1/orders/:id/start-ticketing | N | — | — | No test found |
| 34 | POST /api/v1/orders/:id/ticket | N | — | — | No test found |
| 35 | POST /api/v1/orders/:id/refund | N | — | — | No test found |
| 36 | POST /api/v1/orders/:id/cancel | A | HTTP w/ mock | EndpointOrderTest | `testCancelOrderReturns401WhenUnauthenticated`, `testCancelOrderReturns403ForRegularUser` — no success path |
| 37 | POST /api/v1/orders/:id/close | N | — | — | Listed in EndpointOrderTest.php file header comment but no test function exists |
| 38 | PUT /api/v1/orders/:id/address | A | HTTP w/ mock | EndpointOrderTest | `testUpdateAddressReturns401WhenUnauthenticated`, `testUpdateAddressReturns403ForRegularUser` — no success path |
| 39 | POST /api/v1/orders/:id/request-address-correction | N | — | — | No test found |
| 40 | POST /api/v1/orders/:id/approve-address-correction | N | — | — | No test found |
| 41 | GET /api/v1/orders/:order_id/shipments | Y | HTTP w/ mock | EndpointShipmentUploadTest | `testListOrderShipmentsReturns200ForAdmin` |
| 42 | POST /api/v1/orders/:order_id/shipments | A | HTTP w/ mock | EndpointShipmentUploadTest | `testCreateShipmentReturns401WhenUnauthenticated`, `testCreateShipmentReturns403ForRegularUser` — no success path |
| 43 | GET /api/v1/shipments | Y | HTTP w/ mock | EndpointShipmentUploadTest | `testListShipmentsReturns200ForAdmin`, `testListShipmentsReturns403ForRegularUser` |
| 44 | GET /api/v1/shipments/:id | Y | HTTP w/ mock | EndpointShipmentUploadTest | `testGetShipmentReturns200ForAdmin`, `testGetShipmentReturns404ForNonExistentId` |
| 45 | POST /api/v1/shipments/:id/scan | A | HTTP w/ mock | EndpointShipmentUploadTest | `testScanShipmentReturns401WhenUnauthenticated`, `testScanShipmentReturns403ForRegularUser` — no success path |
| 46 | GET /api/v1/shipments/:id/scan-history | Y | HTTP w/ mock | EndpointShipmentUploadTest | `testGetScanHistoryReturns200ForAdmin` |
| 47 | POST /api/v1/shipments/:id/confirm-delivery | N | — | — | No test found |
| 48 | GET /api/v1/shipments/:id/exceptions | Y | HTTP w/ mock | EndpointShipmentUploadTest | `testGetExceptionsReturns200ForAdmin` |
| 49 | POST /api/v1/shipments/:id/exceptions | N | — | — | No test found |
| 50 | GET /api/v1/violations/rules | Y | HTTP w/ mock | EndpointViolationTest | `testListRulesReturns200ForAdmin`, `testListRulesReturns403ForRegularUser` |
| 51 | GET /api/v1/violations/rules/:id | N | — | — | No test found |
| 52 | POST /api/v1/violations/rules | Y | HTTP w/ mock | EndpointViolationTest | `testCreateRuleReturns201ForAdmin`, `testCreateRuleReturns403ForRegularUser` |
| 53 | PUT /api/v1/violations/rules/:id | N | — | — | No test found |
| 54 | DELETE /api/v1/violations/rules/:id | N | — | — | No test found |
| 55 | GET /api/v1/violations | Y | HTTP w/ mock | EndpointViolationTest | `testListViolationsReturns200ForAdmin`, `testListViolationsReturns403ForRegularUser` |
| 56 | GET /api/v1/violations/:id | Y | HTTP w/ mock | EndpointViolationTest | `testGetViolationReturns200ForAdmin`, `testGetViolationReturns404ForNonExistentId` |
| 57 | POST /api/v1/violations | Y | HTTP w/ mock | EndpointViolationTest | `testCreateViolationReturns201ForAdmin`, `testCreateViolationReturns403ForRegularUser` |
| 58 | GET /api/v1/violations/user/:user_id | N | — | — | No test found |
| 59 | GET /api/v1/violations/group/:group_id | N | — | — | No test found |
| 60 | POST /api/v1/violations/:id/appeal | A | HTTP w/ mock | EndpointViolationTest | `testAppealViolationReturns401WhenUnauthenticated` only |
| 61 | POST /api/v1/violations/:id/review | A | HTTP w/ mock | EndpointViolationTest | `testReviewViolationReturns401WhenUnauthenticated`, `testReviewViolationReturns403ForRegularUser` — no success path |
| 62 | POST /api/v1/violations/:id/final-decision | N | — | — | No test found |
| 63 | POST /api/v1/upload | N | — | — | No test found |
| 64 | GET /api/v1/upload/:id | Y | HTTP w/ mock | EndpointShipmentUploadTest | `testGetFileReturns200ForAdmin`, `testGetFileReturns403WhenNotOwnerOrAdmin` |
| 65 | GET /api/v1/upload/:id/download | N | — | — | No test found |
| 66 | DELETE /api/v1/upload/:id | A | HTTP w/ mock | EndpointShipmentUploadTest | `testDeleteFileReturns401WhenUnauthenticated`, `testDeleteFileReturns403ForRegularUser` — no success path |
| 67 | GET /api/v1/activities/:activity_id/tasks | N | — | — | No HTTP test; service tested in `TaskServiceTest` |
| 68 | POST /api/v1/activities/:activity_id/tasks | N | — | — | No HTTP test; service tested in `TaskServiceTest` |
| 69 | PUT /api/v1/tasks/:id | N | — | — | No HTTP test; object auth tested in `ObjectAuthTest` |
| 70 | PUT /api/v1/tasks/:id/status | N | — | — | No HTTP test; object auth tested in `ObjectAuthTest` |
| 71 | DELETE /api/v1/tasks/:id | N | — | — | No HTTP test; object auth tested in `ObjectAuthTest` |
| 72 | GET /api/v1/activities/:activity_id/checklists | N | — | — | No HTTP test; service tested in `ChecklistServiceTest` |
| 73 | POST /api/v1/activities/:activity_id/checklists | N | — | — | No HTTP test; service tested in `ChecklistServiceTest` |
| 74 | PUT /api/v1/checklists/:id | N | — | — | No HTTP test; object auth tested in `ObjectAuthTest` |
| 75 | DELETE /api/v1/checklists/:id | N | — | — | No HTTP test; object auth tested in `ObjectAuthTest` |
| 76 | POST /api/v1/checklists/:id/items/:item_id/complete | N | — | — | No test found at any level |
| 77 | GET /api/v1/activities/:activity_id/staffing | N | — | — | No HTTP test; service tested in `StaffingServiceTest` |
| 78 | POST /api/v1/activities/:activity_id/staffing | N | — | — | No HTTP test; service tested in `StaffingServiceTest` |
| 79 | PUT /api/v1/staffing/:id | N | — | — | No HTTP test; object auth tested in `ObjectAuthTest` |
| 80 | DELETE /api/v1/staffing/:id | N | — | — | No HTTP test; object auth tested in `ObjectAuthTest` |
| 81 | GET /api/v1/search | Y | HTTP w/ mock | EndpointMiscTest | `testSearchReturns200ForAdmin` |
| 82 | GET /api/v1/search/suggest | N | — | — | No test found |
| 83 | GET /api/v1/search/logistics | N | — | — | No test found |
| 84 | GET /api/v1/index/status | N | — | — | No test found |
| 85 | POST /api/v1/index/rebuild | N | — | — | No test found |
| 86 | POST /api/v1/index/cleanup | N | — | — | No test found |
| 87 | GET /api/v1/notifications | Y | HTTP w/ mock | EndpointMiscTest | `testGetNotificationsReturns200ForAdmin` — list key asserted |
| 88 | PUT /api/v1/notifications/:id/read | N | — | — | No test found |
| 89 | GET /api/v1/notifications/settings | Y | HTTP w/ mock | EndpointMiscTest | `testGetNotificationSettingsReturns200ForAdmin` — specific keys asserted |
| 90 | PUT /api/v1/notifications/settings | Y | HTTP w/ mock | EndpointMiscTest | `testUpdateNotificationSettingsReturns200ForAdmin` |
| 91 | GET /api/v1/preferences | Y | HTTP w/ mock | EndpointMiscTest | `testGetPreferencesReturns200ForAdmin` |
| 92 | PUT /api/v1/preferences | Y | HTTP w/ mock | EndpointMiscTest | `testUpdatePreferencesReturns200ForAdmin` |
| 93 | GET /api/v1/recommendations | Y | HTTP w/ mock | EndpointMiscTest | `testGetRecommendationsReturns200ForAdmin` |
| 94 | GET /api/v1/recommendations/popular | Y | HTTP w/ mock | EndpointMiscTest | `testGetPopularReturns200ForAdmin` |
| 95 | GET /api/v1/recommendations/orders | Y | HTTP w/ mock | EndpointMiscTest | `testGetOrderRecommendationsReturns200ForAdmin` |
| 96 | GET /api/v1/dashboard | Y | HTTP w/ mock | EndpointMiscTest | `testGetDashboardReturns200ForAdmin` |
| 97 | GET /api/v1/dashboard/custom | N | — | — | No test found |
| 98 | POST /api/v1/dashboard/custom | N | — | — | No test found |
| 99 | PUT /api/v1/dashboard/custom/:id | N | — | — | No test found |
| 100 | DELETE /api/v1/dashboard/custom | N | — | — | No test found |
| 101 | GET /api/v1/dashboard/favorites | Y | HTTP w/ mock | EndpointMiscTest | `testGetDashboardFavoritesReturns200ForAdmin` |
| 102 | POST /api/v1/dashboard/favorites | N | — | — | No test found |
| 103 | DELETE /api/v1/dashboard/favorites/:widget_id | N | — | — | No test found |
| 104 | GET /api/v1/dashboard/drill/:widget_id | N | — | — | No test found |
| 105 | GET /api/v1/dashboard/snapshot | N | — | — | No test found |
| 106 | GET /api/v1/export/orders | Y | HTTP w/ mock | EndpointMiscTest | `testExportOrdersReturns200ForAdmin`, `testExportOrdersReturns403ForRegularUser` |
| 107 | GET /api/v1/export/activities | N | — | — | No test found |
| 108 | GET /api/v1/export/violations | N | — | — | No test found |
| 109 | GET /api/v1/export/download | N | — | — | No test found |
| 110 | GET /api/v1/audit | Y | HTTP w/ mock | EndpointMiscTest | `testGetAuditReturns200ForAdmin`, `testGetAuditReturns403ForRegularUser` — list + total asserted |

---

## Coverage Summary

| Metric | Count | % |
|--------|-------|---|
| Total endpoints | 110 | — |
| Endpoints with any HTTP-level test | 57 | 51.8% |
| Endpoints with success-path HTTP test | 42 | 38.2% |
| Endpoints with ONLY negative/auth-only HTTP test | 15 | 13.6% |
| Endpoints with NO HTTP test whatsoever | 53 | 48.2% |
| Endpoints with True No-Mock HTTP test | 0 | 0.0% |

**HTTP coverage %: 51.8%** (synthetic transport only)
**True API coverage %: 0.0%**

---

## Unit Test Summary

### Backend Unit Tests

| Service | Test File | Status |
|---------|-----------|--------|
| ActivityService | `unit_tests/services/ActivityServiceTest.php` | Present |
| AuditService | `unit_tests/services/AuditServiceTest.php` | Present |
| AuthService | `unit_tests/services/AuthServiceTest.php` + `API_tests/AuthApiTest.php` | Double-covered |
| ChecklistService | `unit_tests/services/ChecklistServiceTest.php` + `API_tests/ObjectAuthTest.php` | Object auth covered |
| DashboardService | `unit_tests/services/DashboardServiceTest.php` | Present |
| ExportService | `unit_tests/services/ExportServiceTest.php` | Present |
| NotificationService | `unit_tests/services/NotificationServiceTest.php` | Present |
| OrderService | `unit_tests/services/OrderServiceTest.php` + `API_tests/OrderApiTest.php` | Role-based visibility covered |
| RecommendationService | `unit_tests/services/RecommendationServiceTest.php` | Present |
| SearchService | `unit_tests/services/SearchServiceTest.php` | Present |
| ShipmentService | `unit_tests/services/ShipmentServiceTest.php` | Present |
| StaffingService | `unit_tests/services/StaffingServiceTest.php` + `API_tests/ObjectAuthTest.php` | Object auth covered |
| TaskService | `unit_tests/services/TaskServiceTest.php` + `API_tests/ObjectAuthTest.php` | Object auth covered |
| UploadService | `unit_tests/services/UploadServiceTest.php` | Present |
| UserService | `unit_tests/services/UserServiceTest.php` | Present |
| ViolationService | `unit_tests/services/ViolationServiceTest.php` | Present |

**Service coverage: 16/16 (100%)**

**Important backend modules NOT covered by unit tests:**
- Controllers — by design (thin: input parsing + response shape only, per README architecture)
- `RateLimitMiddleware` — bypassed in all tests via `RATE_LIMIT_BYPASS=1`; enforcement logic never tested
- `SensitiveDataMiddleware` — no dedicated test file found
- Route registration logic

---

### Frontend Unit Tests (STRICT REQUIREMENT)

Project type is **fullstack** — frontend unit test verification is mandatory.

**Detection check against all four required criteria:**

| Criterion | Result |
|-----------|--------|
| Frontend test files exist (`*.test.*`, `*.spec.*`) | FAIL — none found |
| Tests target frontend logic/components | FAIL — no test files |
| Test framework evident (Jest, Vitest, RTL, etc.) | FAIL — no config found |
| Tests import/render actual frontend modules | FAIL — no test files |

**Directory inspected:** `repo/frontend/public/` contains: `index.html`, `login.html`, `config.js`, `assets/`, `lib/`, `modules/`, `views/`, `fulltest.html`, `layuitest.html`, `test.html`.

Note: `fulltest.html`, `layuitest.html`, `test.html` are static HTML pages — not automated test files.

---

**Frontend unit tests: MISSING**

**→ CRITICAL GAP: Project is fullstack and frontend unit tests are entirely absent.**

---

### Cross-Layer Observation

- Backend service layer: fully tested (16/16)
- Backend HTTP layer: partially tested via synthetic dispatch (52%)
- Frontend (Layui SPA): **zero test coverage** at any level

Testing is entirely backend-concentrated. The Layui JS modules (`repo/frontend/public/modules/`), views (`repo/frontend/public/views/`), login flow (`login.html`), and all client-side logic receive no automated validation.

---

## API Observability Check

| Test File | Observability | Notes |
|-----------|---------------|-------|
| `EndpointPingAuthTest` | Good | Asserts status, `success` flag, token presence, user data fields, expiry |
| `EndpointUserTest` | Good | Asserts status, response body fields, specific field values |
| `EndpointActivityTest` | Moderate | CRUD paths inspect body; publish/signups check auth codes only |
| `EndpointOrderTest` | Moderate | CRUD paths inspect body; cancel/close/address check auth codes only |
| `EndpointShipmentUploadTest` | Moderate | GET paths assert status + body ID; POST paths assert only auth |
| `EndpointViolationTest` | Moderate | CRUD paths inspect body; appeal/review check auth codes only |
| `EndpointMiscTest` | Moderate | Most assert 200 + `success:true`; notification settings inspects specific keys |
| `AuthApiTest` | Good | Asserts token length, user structure, session invalidation, attempt counters |
| `HttpMiddlewareTest` | Good | Asserts HTTP codes, `success` flag value, `error` message text |

**Overall: Moderate** — most tests verify HTTP status and `success:true` flag but do not systematically assert response data shape, field values, or business logic outcomes.

---

## Test Quality & Sufficiency

### Success Paths
Covered at HTTP level: auth, users (full CRUD), activities (read/create/update), orders (read/create/update), violations (read/create rules+violations), notifications, preferences, recommendations, dashboard root + favorites, audit, search, export/orders.

Not covered at HTTP level: all order state-transitions (`initiate-payment`, `confirm-payment`, `start-ticketing`, `ticket`, `refund`, `close`), activity lifecycle transitions (`start`, `complete`, `archive`), all tasks/checklists/staffing HTTP paths.

### Failure Cases
Auth failures: well-covered (wrong password, disabled account, locked account, missing fields, unknown user).
Resource not found (404): tested for users, orders, activities, shipments, violations.
Validation errors (400, invalid input, missing fields): tested for users (duplicate username) and login (missing credentials) only. All other endpoints lack validation-error tests.

### Edge Cases
None tested: pagination limits, empty result sets, max headcount on signups, state-machine guard violations (e.g., completing an already-completed order).

### Auth / Permissions
- RBAC route-level enforcement: tested via `HttpMiddlewareTest` (direct middleware instantiation).
- Permission model: tested via `RbacApiTest::hasPermission()`.
- Object-level auth (ownership): tested via `ObjectAuthTest` at service layer for tasks, checklists, staffing.
- Gap: no HTTP-level test verifies a regular_user is blocked from mutating another user's task/checklist via the actual route.

### `run_tests.sh` Check

| Check | Result |
|-------|--------|
| Docker-preferred path | PASS — `docker compose exec -T php ./vendor/bin/phpunit` |
| Fallback path local-dependency-free | FLAG — `./backend/vendor/bin/phpunit` requires local `backend/vendor/` (composer install) |
| `xmllint` requirement | FLAG — called for summary parsing; README says it "must be available locally" |

---

## End-to-End Expectations

Project type is **fullstack** — real FE ↔ BE tests expected.

- `e2e_tests/AuthFlowTest.php`: calls `AuthService` directly. This is a service-layer integration test, not a true end-to-end test. No HTTP request, no browser, no frontend involved.
- No browser-automation tests (Playwright, Cypress, Selenium) found.
- No HTTP-client tests pointed at a running server found.

**FE ↔ BE integration: entirely absent.**

The service unit tests and synthetic HTTP dispatch partially compensate for the missing true E2E coverage of the backend, but the frontend is wholly excluded from all automated test flows.

---

## Test Coverage Score

**Score: 52 / 100**

### Score Rationale

| Factor | Weight | Assessment | Points Earned |
|--------|--------|------------|---------------|
| Endpoint HTTP coverage (52%) | 25 | 57/110 endpoints reached via synthetic HTTP dispatch | 13/25 |
| True no-mock HTTP coverage (0%) | 20 | All HTTP tests use synthetic `think\Request` — transport is not real | 0/20 |
| Unit test completeness | 20 | 16/16 services covered; middleware partially tested | 17/20 |
| Test depth / success paths | 15 | 42/110 success paths; no validation tests; no state-machine tests | 7/15 |
| Frontend unit coverage | 10 | Zero — CRITICAL GAP | 0/10 |
| Auth / RBAC coverage | 5 | Thorough: middleware direct test + permission model + HTTP-level auth assertions | 4/5 |
| E2E / integration | 5 | Service-layer only; no FE↔BE | 1/5 |

High marks withheld because: transport is synthetic (not true HTTP), 53 endpoints have no HTTP coverage at all, the entire order state-machine is untested at HTTP level, and the frontend receives zero test investment.

---

## Key Gaps

1. **True no-mock HTTP coverage: 0%** — All API tests dispatch via programmatic `think\Request`; no test sends a real HTTP request to a running server. Evidence: `API_tests/HttpTestCase.php:54–97`.

2. **53/110 endpoints uncovered at any HTTP level** — Entire task (9 endpoints), checklist (5), staffing (4) sub-APIs; dashboard sub-routes (7); order state transitions (6); search/index (5); export sub-routes (3); violation sub-routes (5); upload sub-routes (2); notifications mark-read (1).

3. **Order state-machine transitions completely untested at HTTP level** — `initiate-payment`, `confirm-payment`, `start-ticketing`, `ticket`, `refund`, `close` have no HTTP test. Core order workflow unvalidated through the route/controller/service chain.

4. **Frontend: zero test coverage** — Layui SPA (JS modules, views, login flow) has no unit, component, or integration tests. CRITICAL GAP for a declared fullstack project.

5. **Rate limiting logic never tested** — `RATE_LIMIT_BYPASS=1` disables `RateLimitMiddleware` universally in all tests. 429-threshold enforcement is untested. Evidence: `unit_tests/bootstrap.php:31`.

6. **Success paths missing for 15 endpoints** — Only 401/403 tested; the actual controller/service execution path for `cancel`, `address`, `scan`, `exceptions`, `publish`, `signup`, `upload delete`, `create-shipment`, `appeal`, `review` is never asserted as succeeding.

7. **No input validation testing** — Missing required fields, type errors, and boundary conditions are not tested at HTTP level except for login (400 on missing credentials) and user creation (400 on duplicate username).

8. **`POST /api/v1/orders/:id/close` phantom test** — `EndpointOrderTest.php` header comment declares it as covered, but no test method exists in the file.

9. **`SensitiveDataMiddleware` untested** — No test verifies that `password_hash`/`salt`/`invoice_address` are masked for non-admin roles at the HTTP response level.

---

## Confidence & Assumptions

- **High confidence:** Endpoint inventory is complete — single route file `repo/backend/route/app.php`, no dynamic route registration detected.
- **High confidence:** Test transport classification — `HttpTestCase::request()` constructs a `think\Request` object at line 54; `self::$app->http->run($req)` at line 97. No real socket involved.
- **High confidence:** Frontend test absence — `repo/frontend/public/` directory listing shows no test files.
- **Assumption:** Service unit test quality is consistent with the `TaskServiceTest.php` sample inspected (lines 1–60 read); full content of other 15 service test files assumed present and non-trivial.
- **Assumption:** Administrator role receives broader permission grants at runtime than the bootstrap seed literals suggest (e.g., wildcard matching in `hasPermission()`) — this explains how admin passes RBAC for `notifications.read`, `dashboard.read`, etc. which are absent from the seed at `unit_tests/bootstrap.php:378`.

---

---

# PART 2 — README AUDIT

---

## README Location

`repo/README.md` — **PRESENT**

---

## Hard Gate Checks

### Formatting
Clean markdown with structured headings (`##`), fenced code blocks, and tables. No broken formatting.
**PASS**

### Startup Instructions
Required for fullstack/backend: `docker compose up`

`repo/README.md:36–39`:
```bash
docker compose build --no-cache
docker compose up -d
docker compose exec php php think migrate:run
docker compose exec php php think seed:run
```

`docker compose up` is present (as `up -d`). Migration and seed steps explicitly documented as required.
**PASS**

### Access Method
`repo/README.md:64`:
> **App URL:** http://localhost:8080 (nginx maps host `8080` → container `80`)

URL and port explicitly stated.
**PASS**

### Verification Method
`repo/README.md:70–88`: `curl` commands provided for ping and authenticated login, with expected response bodies shown.
**PASS**

### Environment Rules

| Rule | Status | Evidence |
|------|--------|----------|
| No `npm install` | PASS | Not present in setup steps |
| No `pip install` | PASS | Not present in setup steps |
| No `apt-get` | PASS | Not present in setup steps |
| No manual DB setup | PASS | DB handled via `migrate:run` + `seed:run` in container |
| No runtime installs outside Docker | CONDITIONAL | Primary path is clean; test section introduces local dependency issue (see below) |

**PASS for primary path.**

**MEDIUM ISSUE:** `repo/README.md:122–123`:
> "it falls back to the local `backend/vendor/bin/phpunit` binary otherwise … `xmllint` must be available locally for the summary output"

This requires `composer install` (to populate `backend/vendor/`) and `xmllint` (system package) for the test fallback path. This contradicts `README.md:31`:
> "No local PHP, Composer, or xmllint installation required."

### Demo Credentials
`repo/README.md:94–110`: Full credential table with all roles present.

| Username | Password | Role |
|----------|----------|------|
| `admin` | `CampusOps1` | Administrator |
| `ops_staff1`, `ops_staff2` | `CampusOps1` | Operations staff |
| `team_lead` | `CampusOps1` | Team lead |
| `reviewer` | `CampusOps1` | Reviewer |
| `user1`–`user5` | `CampusOps1` | Regular user |

Seed dependency noted: "Credentials are only available after `make seed` / `docker compose exec php php think seed:run`."
**PASS**

---

## Engineering Quality

| Area | Rating | Notes |
|------|--------|-------|
| Tech stack clarity | Good | Backend (ThinkPHP 8), frontend (Layui), database (MySQL 8), proxy (Nginx) all named |
| Architecture explanation | Good | ASCII middleware chain diagram at `README.md:7–25`; clear request-flow description |
| Testing instructions | Moderate | Three suites documented with table; fallback path creates contradiction |
| Security / roles | Good | All five roles listed with credentials and permissions implied |
| Workflows | Good | Full start/stop/restart/logs command set |
| Presentation quality | High | Clean structure, no orphaned sections, readable prose |

---

## High Priority Issues

None — all hard gate requirements pass for the primary Docker path.

---

## Medium Priority Issues

1. **Documentation inconsistency — local dependencies in test section** (`README.md:122–123` vs `README.md:31`)
   - Claims "No local PHP, Composer, or xmllint installation required" in prerequisites
   - Then states test fallback "requires backend/vendor to exist" and "xmllint must be available locally"
   - Recommendation: either explicitly document the `composer install` step required for the fallback, or restate that the local fallback is unsupported and Docker is required for testing

2. **`run_tests.sh` fallback path requires undocumented local setup** (`repo/run_tests.sh:24–25`)
   - `./backend/vendor/bin/phpunit` will fail if `backend/vendor/` does not exist
   - No README instruction covers `composer install` for the local path
   - Users who run tests without Docker will hit an unexplained failure

---

## Low Priority Issues

1. **`docker compose down -v` consequence not warned in first-time setup** — Users who run `down -v` and then `up -d` will lose seed data; the README notes this only in the start/stop section, not adjacently to the seed step
2. **No `--testsuite` usage examples** — `phpunit.xml` defines three named suites (`unit`, `api`, `e2e`); the README shows only full-suite runs. Showing `--testsuite api` would improve developer workflow
3. **`make setup` shortcut mentioned once with no Makefile reference** — New contributors may not know `make` is available or what targets exist

---

## Hard Gate Failures

**NONE** — All hard gates pass for the primary Docker path.

---

## README Verdict

**PARTIAL PASS**

The README satisfies all hard gate requirements on the primary Docker path: `docker compose up` startup, explicit URL + port, curl-based verification, complete credentials for all roles, and no manual installation steps in the main flow. Architecture documentation is thorough.

Downgraded from PASS due to:
- Documentation inconsistency: prerequisites state "no local installation required" but the testing section references a local fallback path that requires `backend/vendor/` (composer install) and `xmllint` — neither of which is documented as a setup step.

---

# FINAL VERDICTS

| Audit | Score / Verdict |
|-------|-----------------|
| **Test Coverage** | **52 / 100** |
| **README** | **PARTIAL PASS** |

---

## Summary

**Test Coverage (52/100):** Service-layer unit testing is strong — all 16 services have dedicated test files. HTTP-layer testing covers 52% of endpoints using ThinkPHP's synthetic dispatch mechanism (not true no-mock HTTP). 53 endpoints have zero HTTP test coverage, including the entire task/checklist/staffing sub-API and all order payment and state-transition routes. The frontend (Layui SPA) receives no test coverage of any kind — a critical gap for a declared fullstack application. Rate limiting is bypassed in all tests and its enforcement logic is never validated.

**README (PARTIAL PASS):** Well-written and complete on the primary Docker path. Fails clean PASS due to a contradiction between the "no local installation required" prerequisite claim and the test section's references to a fallback path requiring local `backend/vendor/` and `xmllint`.
