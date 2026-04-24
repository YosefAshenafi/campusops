# CampusOps API Specification

## Base Configuration

- **Base URL:** `/api/v1`
- **Content-Type:** `application/json`
- **Authentication:** Bearer token (local session-based)
- **Response Format:** REST-style JSON

---

## Common Headers

```http
Authorization: Bearer {access_token}
Content-Type: application/json
X-Request-ID: {uuid}
X-Timestamp: {unix_timestamp}
```

---

## Common Responses

### Success Response
```json
{
  "success": true,
  "code": 200,
  "data": { },
  "message": "Success"
}
```

### Error Response
```json
{
  "success": false,
  "code": 400,
  "error": "Error message"
}
```

### Paginated Response
```json
{
  "success": true,
  "code": 200,
  "data": [ ],
  "pagination": {
    "page": 1,
    "page_size": 20,
    "total": 100,
    "total_pages": 5
  }
}
```

---

## Health

### GET /ping
**Description:** Health check (no auth required)

---

## Authentication Endpoints

### POST /auth/login
**Description:** Local username/password login (no auth required)

**Request:**
```json
{
  "username": "string",
  "password": "string"
}
```

**Response:**
```json
{
  "success": true,
  "code": 200,
  "data": {
    "access_token": "string",
    "expires_at": "timestamp",
    "user": {
      "id": "integer",
      "username": "string",
      "role": "string"
    }
  }
}
```

### POST /auth/logout
**Description:** Invalidate session

### POST /auth/unlock
**Description:** Unlock a locked account (requires `users.password` permission)

**Request:**
```json
{
  "user_id": "integer"
}
```

---

## User Endpoints

### GET /users
**Description:** List users (requires `users.read`)

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| role | string | Filter by role |
| status | string | Filter by status (active, locked) |
| keyword | string | Search by name or username |
| page | int | Page number |
| limit | int | Page size |

### GET /users/{id}
**Description:** Get user details (requires `users.read`)

### POST /users
**Description:** Create user (requires `users.create`)

### PUT /users/{id}
**Description:** Update user (requires `users.update`)

### DELETE /users/{id}
**Description:** Delete user (requires `users.delete`)

### PUT /users/{id}/role
**Description:** Update user role (requires `users.update`)

**Request:**
```json
{
  "role": "string"
}
```

### PUT /users/{id}/password
**Description:** Reset password (requires `users.password`)

---

## Activity Endpoints

### GET /activities
**Description:** List activities (requires `activities.read`)

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| state | string | Filter: draft, published, in_progress, completed, archived |
| tag | string | Filter by tag |
| keyword | string | Full-text keyword search |
| page | int | Page number |
| limit | int | Page size |

### GET /activities/{id}
**Description:** Get activity details with versions (requires `activities.read`)

### GET /activities/{id}/versions
**Description:** Get version history (requires `activities.read`)

### GET /activities/{id}/signups
**Description:** Get activity signups (requires `activities.read`)

### GET /activities/{id}/change-log
**Description:** Get highlighted change log between versions (requires `activities.read`)

### POST /activities
**Description:** Create activity as draft (requires `activities.create`)

**Request:**
```json
{
  "title": "string",
  "body": "string",
  "tags": ["string"],
  "max_headcount": "integer",
  "signup_window": {
    "start": "datetime",
    "end": "datetime"
  },
  "eligibility_tags": ["string"],
  "required_supplies": ["string"]
}
```

### PUT /activities/{id}
**Description:** Update activity; creates a new version if already published (requires `activities.update`)

### POST /activities/{id}/publish
**Description:** Publish activity (Draft → Published) (requires `activities.publish`)

### POST /activities/{id}/start
**Description:** Start activity (Published → In Progress) (requires `activities.transition`)

### POST /activities/{id}/complete
**Description:** Complete activity (In Progress → Completed) (requires `activities.transition`)

### POST /activities/{id}/archive
**Description:** Archive activity (Completed → Archived) (requires `activities.transition`)

### POST /activities/{id}/signups
**Description:** Sign up for activity (requires `activities.signup`)

### DELETE /activities/{id}/signups/{signup_id}
**Description:** Cancel signup (requires `activities.signup`)

### POST /activities/{id}/signups/{signup_id}/acknowledge
**Description:** Acknowledge change log (Pending Acknowledgement → Acknowledged) (requires `activities.signup`)

---

## Order Endpoints

### GET /orders
**Description:** List orders; results filtered by role and ownership (requires `orders.read`)

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| state | string | Filter: placed, pending_payment, paid, ticketing, ticketed, canceled, closed |
| activity_id | int | Filter by activity |
| page | int | Page number |
| limit | int | Page size |

### GET /orders/{id}
**Description:** Get order details (requires `orders.read`)

### GET /orders/{id}/history
**Description:** Get order state history (requires `orders.read`)

### POST /orders
**Description:** Create order (Placed) (requires `orders.create`)

**Request:**
```json
{
  "activity_id": "integer",
  "team_lead_id": "integer",
  "items": [
    {
      "type": "string",
      "description": "string",
      "quantity": "integer"
    }
  ],
  "notes": "string"
}
```

### PUT /orders/{id}
**Description:** Update order (requires `orders.update`)

### POST /orders/{id}/initiate-payment
**Description:** Initiate payment process (Placed → Pending Payment). Starts the 30-minute auto-cancel timer. (requires `orders.payment`)

### POST /orders/{id}/confirm-payment
**Description:** Confirm payment (Pending Payment → Paid) (requires `orders.payment`)

**Request:**
```json
{
  "payment_method": "string",
  "amount": "decimal"
}
```

### POST /orders/{id}/start-ticketing
**Description:** Begin ticketing process (Paid → Ticketing) (requires `orders.ticketing`)

### POST /orders/{id}/ticket
**Description:** Complete ticketing (Ticketing → Ticketed) (requires `orders.ticketing`)

**Request:**
```json
{
  "ticket_number": "string"
}
```

### POST /orders/{id}/refund
**Description:** Refund order (before Ticketed state) (requires `orders.refund`)

### POST /orders/{id}/cancel
**Description:** Cancel order (requires `orders.cancel`)

### POST /orders/{id}/close
**Description:** Close order (requires `orders.close`)

### PUT /orders/{id}/address
**Description:** Update invoice address directly (requires `orders.update`)

**Request:**
```json
{
  "address": "string"
}
```

### POST /orders/{id}/request-address-correction
**Description:** Request a reviewer-approved address correction (requires `orders.request_correction`)

**Request:**
```json
{
  "address": "object"
}
```

### POST /orders/{id}/approve-address-correction
**Description:** Approve a pending address correction request (requires `orders.approve`)

---

## Fulfillment Endpoints

### GET /shipments
**Description:** List all shipments (requires `shipments.read`)

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| status | string | Filter by shipment status |
| page | int | Page number |
| limit | int | Page size |

### GET /orders/{order_id}/shipments
**Description:** List shipments for a specific order (requires `shipments.read`)

### POST /orders/{order_id}/shipments
**Description:** Create shipment for an order; package splitting supported (requires `shipments.create`)

**Request:**
```json
{
  "carrier": "string",
  "tracking_number": "string",
  "package_contents": ["string"],
  "weight": "decimal"
}
```

### GET /shipments/{id}
**Description:** Get shipment details (requires `shipments.read`)

### POST /shipments/{id}/scan
**Description:** Log offline scan event — Fast Scan module (requires `shipments.update`)

**Request:**
```json
{
  "scan_code": "string",
  "location": "string"
}
```

### GET /shipments/{id}/scan-history
**Description:** Get scan event history (requires `shipments.read`)

### POST /shipments/{id}/confirm-delivery
**Description:** Confirm delivery (requires `shipments.deliver`)

### GET /shipments/{id}/exceptions
**Description:** Get exception receipts (requires `shipments.read`)

### POST /shipments/{id}/exceptions
**Description:** Log exception (requires `shipments.exception`)

---

## Search Endpoints

### GET /search
**Description:** Global full-text search (requires `search.read`)

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| q | string | Search query (min 2 chars) |
| type | string | Filter: activity, order |
| author | string | Filter by author |
| tags | string | Filter by tag |
| reply_count_min | int | Minimum reply count filter |
| sort | string | Sort: recency, popularity, reply_count, relevance |
| highlight | boolean | Enable result highlighting |
| page | int | Page number |
| limit | int | Page size |

### GET /search/suggest
**Description:** Search suggestions (requires `search.read`)

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| q | string | Partial query (min 1 char) |
| limit | int | Max suggestions to return |

### GET /search/logistics
**Description:** Logistics/order search with tracking number tokenization (requires `search.read`)

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| q | string | Search query |
| status | string | Filter by order/shipment state |
| carrier | string | Filter by carrier |
| sort | string | Sort: recency, popularity, reply_count, relevance |
| page | int | Page number |
| limit | int | Page size |

---

## Task & Checklist Endpoints

### GET /activities/{id}/tasks
**Description:** Get task breakdown for activity (requires `tasks.read`)

### POST /activities/{id}/tasks
**Description:** Create task for activity (requires `tasks.create`)

**Request:**
```json
{
  "title": "string",
  "description": "string",
  "assigned_to": "integer",
  "due_date": "datetime"
}
```

### PUT /tasks/{id}
**Description:** Update task (requires `tasks.update`)

### PUT /tasks/{id}/status
**Description:** Update task status (requires `tasks.update`)

### DELETE /tasks/{id}
**Description:** Delete task (requires `tasks.delete`)

### GET /activities/{id}/checklists
**Description:** Get checklists for activity (requires `checklists.read`)

### POST /activities/{id}/checklists
**Description:** Create checklist for activity (requires `tasks.create`)

### PUT /checklists/{id}
**Description:** Update checklist (requires `tasks.update`)

### DELETE /checklists/{id}
**Description:** Delete checklist (requires `tasks.delete`)

### POST /checklists/{checklist_id}/items/{item_id}/complete
**Description:** Mark checklist item complete (requires `checklists.update`)

---

## Staffing Endpoints

### GET /activities/{id}/staffing
**Description:** Get staffing plan for activity (requires `staffing.read`)

### POST /activities/{id}/staffing
**Description:** Create staffing entry (requires `staffing.create`)

**Request:**
```json
{
  "role": "string",
  "required_count": "integer",
  "assigned_users": ["integer"],
  "notes": "string"
}
```

### PUT /staffing/{id}
**Description:** Update staffing entry (requires `staffing.update`)

**Request:**
```json
{
  "role": "string",
  "required_count": "integer",
  "assigned_users": ["integer"],
  "notes": "string"
}
```

### DELETE /staffing/{id}
**Description:** Remove staffing entry (requires `staffing.delete`)

---

## Violation / Demerit Endpoints

### GET /violations/rules
**Description:** List violation rules (requires `violations.read`)

### GET /violations/rules/{id}
**Description:** Get rule details (requires `violations.read`)

### POST /violations/rules
**Description:** Create violation rule (requires `violations.rules`)

**Request:**
```json
{
  "name": "string",
  "description": "string",
  "points": "integer",
  "category": "string"
}
```

### PUT /violations/rules/{id}
**Description:** Update violation rule (requires `violations.rules`)

**Request:**
```json
{
  "name": "string",
  "description": "string",
  "points": "integer",
  "category": "string"
}
```

### DELETE /violations/rules/{id}
**Description:** Delete violation rule (requires `violations.rules`)

### GET /violations
**Description:** List violations, paginated and filtered (requires `violations.read`)

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| user_id | string | Filter by user |
| group_id | string | Filter by group |
| page | int | Page number |
| limit | int | Page size |

### GET /violations/{id}
**Description:** Get violation details (requires `violations.read`)

### GET /violations/user/{user_id}
**Description:** Get user's violations and point total (requires `violations.read`)

### GET /violations/group/{group_id}
**Description:** Get group's aggregated points (requires `violations.read`)

### POST /violations
**Description:** Create violation record (requires `violations.create`)

**Request:**
```json
{
  "user_id": "integer",
  "rule_id": "integer",
  "evidence": [
    {
      "filename": "string",
      "sha256": "string"
    }
  ],
  "notes": "string"
}
```

### POST /violations/{id}/appeal
**Description:** Submit appeal (requires `violations.appeal`)

**Request:**
```json
{
  "notes": "string",
  "evidence": ["string"]
}
```

### POST /violations/{id}/review
**Description:** Reviewer's decision (requires `violations.review`)

**Request:**
```json
{
  "decision": "approve|reject",
  "notes": "string"
}
```

### POST /violations/{id}/final-decision
**Description:** Record final decision (requires `violations.review`)

**Request:**
```json
{
  "notes": "string"
}
```

---

## Dashboard Endpoints

### GET /dashboard
**Description:** Get default dashboard (requires `dashboard.read`)

### GET /dashboard/custom
**Description:** List custom dashboards (requires `dashboard.read`)

### POST /dashboard/custom
**Description:** Create custom dashboard (requires `dashboard.create`)

**Request:**
```json
{
  "name": "string",
  "widgets": [
    {
      "type": "string",
      "config": {},
      "position": {
        "x": "integer",
        "y": "integer",
        "w": "integer",
        "h": "integer"
      }
    }
  ]
}
```

### PUT /dashboard/custom/{id}
**Description:** Update custom dashboard (requires `dashboard.update`)

### DELETE /dashboard/custom/{id}
**Description:** Delete custom dashboard (requires `dashboard.update`)

### GET /dashboard/favorites
**Description:** Get favorite widgets (requires `dashboard.read`)

### POST /dashboard/favorites
**Description:** Add widget to favorites (requires `dashboard.update`)

**Request:**
```json
{
  "widget_id": "string"
}
```

### DELETE /dashboard/favorites/{widget_id}
**Description:** Remove widget from favorites (requires `dashboard.update`)

### GET /dashboard/drill/{widget_id}
**Description:** Get drill-down data for a widget (requires `dashboard.read`)

### GET /dashboard/snapshot
**Description:** Export dashboard snapshot (requires `dashboard.export`)

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| format | string | Format: png, pdf, xlsx |

---

## Export Endpoints

### GET /export/orders
**Description:** Export orders (requires `dashboard.export`; max 500 records)

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| format | string | Format: csv, xlsx, pdf, png |
| state | string | Filter by order state |

### GET /export/activities
**Description:** Export activities (requires `dashboard.export`; max 500 records)

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| format | string | Format: csv, xlsx, pdf, png |

### GET /export/violations
**Description:** Export violations (requires `dashboard.export`; max 500 records)

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| format | string | Format: csv, xlsx, pdf, png |

### GET /export/download
**Description:** Download a previously generated export file (requires `dashboard.export`)

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| file | string | Export filename (basename only; directory traversal is rejected) |

---

## Recommendation Endpoints

### GET /recommendations
**Description:** Get personalized activity recommendations (requires `activities.read`)

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| context | string | Context: list, detail |
| limit | int | Max results |

### GET /recommendations/popular
**Description:** Get popular activities (30-day window) (requires `activities.read`)

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| limit | int | Max results |

### GET /recommendations/orders
**Description:** Get order recommendations for the current user (requires `orders.read`)

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| limit | int | Max results |

---

## User Preferences Endpoints

### GET /preferences
**Description:** Get user preferences (requires `preferences.read`)

### PUT /preferences
**Description:** Update user preferences (requires `preferences.update`)

**Request:**
```json
{
  "arrival_reminders": true,
  "activity_update_alerts": true,
  "order_status_alerts": true,
  "dashboard_layout": {}
}
```

---

## File Upload Endpoints

### POST /upload
**Description:** Upload file (requires `uploads.create`)

**Headers:** `Content-Type: multipart/form-data`

**Form Data:**
| Field | Type | Description |
|-------|------|-------------|
| file | file | File (JPG, PNG, PDF; max 10 MB) |
| category | string | Category: evidence, supply, etc. |

**Response:**
```json
{
  "success": true,
  "code": 200,
  "data": {
    "filename": "string",
    "sha256": "string",
    "url": "string",
    "size": "integer"
  }
}
```

### GET /upload/{id}
**Description:** Get file metadata (requires `files.read`)

### GET /upload/{id}/download
**Description:** Download file (requires `files.read`)

### DELETE /upload/{id}
**Description:** Delete file (requires `uploads.delete`)

---

## Indexing Endpoints

### GET /index/status
**Description:** Get index status (requires `index.manage`)

### POST /index/rebuild
**Description:** Rebuild search index (requires `index.manage`)

### POST /index/cleanup
**Description:** Trigger nightly cleanup job — removes orphaned index entries older than 7 days (requires `index.manage`)

---

## Notification Endpoints

### GET /notifications
**Description:** Get notifications for current user (requires `notifications.read`)

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| page | int | Page number |
| limit | int | Page size |

### PUT /notifications/{id}/read
**Description:** Mark notification as read (requires `notifications.read`)

### GET /notifications/settings
**Description:** Get notification settings (requires `notifications.read`)

### PUT /notifications/settings
**Description:** Update notification settings (requires `notifications.read`)

---

## Audit Trail Endpoints

### GET /audit
**Description:** Query audit trail (requires `audit.read`)

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| entity_type | string | Entity type |
| entity_id | int | Entity ID |
| action | string | Action type |
| date_from | date | Date range start |
| date_to | date | Date range end |
| page | int | Page number |
| limit | int | Page size |

---

## State Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 201 | Created |
| 204 | No Content |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 409 | Conflict |
| 422 | Unprocessable Entity |
| 429 | Rate Limited |
| 500 | Internal Server Error |
| 503 | Service Unavailable |
