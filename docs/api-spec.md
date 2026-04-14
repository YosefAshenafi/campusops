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
  "error": "Error message",
  "details": { }
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

## Authentication Endpoints

### POST /auth/login
**Description:** Local username/password login

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
**Description:** Unlock account after lockout

---

## User Endpoints

### GET /users
**Description:** List users (Admin only)

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| role | string | Filter by role |
| status | string | Filter by status (active, locked) |
| page | int | Page number |
| page_size | int | Page size |

### GET /users/{id}
**Description:** Get user details

### POST /users
**Description:** Create user (Admin only)

### PUT /users/{id}
**Description:** Update user

### PUT /users/{id}/role
**Description:** Update user role (Admin only)

### PUT /users/{id}/password
**Description:** Reset password (Admin only)

---

## Activity Endpoints

### GET /activities
**Description:** List activities

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| state | string | Filter: draft, published, in_progress, completed, archived |
| author_id | int | Filter by author |
| tag | string | Filter by tag |
| page | int | Page number |
| page_size | int | Page size |
| sort | string | Sort: created_at, updated_at, popularity |

### GET /activities/{id}
**Description:** Get activity details with versions

### GET /activities/{id}/versions
**Description:** Get version history

### GET /activities/{id}/signups
**Description:** Get activity signups

### GET /activities/{id}/change-log
**Description:** Get highlighted change log between versions

### POST /activities
**Description:** Create activity (draft)

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
**Description:** Update activity

### POST /activities/{id}/publish
**Description:** Publish activity (Draft → Published)

### POST /activities/{id}/start
**Description:** Start activity (Published → In Progress)

### POST /activities/{id}/complete
**Description:** Complete activity (In Progress → Completed)

### POST /activities/{id}/archive
**Description:** Archive activity (Completed → Archived)

### POST /activities/{id}/signups
**Description:** Sign up for activity

### DELETE /activities/{id}/signups/{signup_id}
**Description:** Cancel signup

### POST /activities/{id}/signups/{signup_id}/acknowledge
**Description:** Acknowledge change log (Pending Acknowledgement → Acknowledged)

---

## Order Endpoints

### GET /orders
**Description:** List orders

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| state | string | Filter: placed, pending_payment, paid, ticketing, ticketed, canceled, closed |
| activity_id | int | Filter by activity |
| team_lead_id | int | Filter by team lead |
| created_by | int | Filter by creator |
| page | int | Page number |
| page_size | int | Page size |

### GET /orders/{id}
**Description:** Get order details

### GET /orders/{id}/history
**Description:** Get order state history

### POST /orders
**Description:** Create order (Placed)

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
**Description:** Update order

### POST /orders/{id}/initiate-payment
**Description:** Initiate payment process (Placed → Pending Payment). Starts the 30-minute auto-cancel timer.

### POST /orders/{id}/confirm-payment
**Description:** Confirm payment (Pending Payment → Paid)

**Request:**
```json
{
  "payment_method": "string",
  "amount": "decimal"
}
```

### POST /orders/{id}/start-ticketing
**Description:** Begin ticketing process (Paid → Ticketing)

### POST /orders/{id}/ticket
**Description:** Complete ticketing (Ticketing → Ticketed)

**Request:**
```json
{
  "ticket_number": "string"
}
```

### POST /orders/{id}/refund
**Description:** Refund order (Admin only, before Ticketed)

### POST /orders/{id}/cancel
**Description:** Cancel order

### POST /orders/{id}/close
**Description:** Close order

### PUT /orders/{id}/address
**Description:** Update invoice address (Closed + Reviewer approval)

**Request:**
```json
{
  "address": "string",
  "reviewer_id": "integer",
  "reviewer_notes": "string"
}
```

---

## Fulfillment Endpoints

### GET /orders/{order_id}/shipments
**Description:** List shipments for order

### POST /orders/{order_id}/shipments
**Description:** Create shipment (package splitting supported)

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
**Description:** Get shipment details

### POST /shipments/{id}/scan
**Description:** Log offline scan event (Fast Scan module)

**Request:**
```json
{
  "scan_code": "string",
  "location": "string"
}
```

### GET /shipments/{id}/scan-history
**Description:** Get scan event history

### POST /shipments/{id}/confirm-delivery
**Description:** Confirm delivery

### GET /shipments/{id}/exceptions
**Description:** Get exception receipts

### POST /shipments/{id}/exceptions
**Description:** Log exception

---

## Search Endpoints

### GET /search
**Description:** Global full-text search

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| q | string | Search query |
| type | string | Filter: activity, order |
| author_id | int | Filter by author |
| tag | string | Filter by tag |
| date_from | date | Filter date range |
| date_to | date | Filter date range |
| sort | string | Sort: recency, popularity, reply_count, relevance |
| page | int | Page number |
| page_size | int | Page size |

### GET /search/suggest
**Description:** Search suggestions

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| q | string | Partial query |
| type | string | Type: activity, order |

### GET /search/logistics
**Description:** Logistics/order search with tokenization

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| q | string | Search query |
| enable_synonym | boolean | Enable synonym matching |
| enable_pinyin | boolean | Enable pinyin matching |
| correct_spell | boolean | Enable spell correction |
| order_state | string | Filter by order state |
| date_from | date | Filter date range |
| date_to | date | Filter date range |
| sort | string | Sort: recency, popularity, reply_count, relevance |
| page | int | Page number |
| page_size | int | Page size |

---

## Task & Checklist Endpoints

### GET /activities/{id}/tasks
**Description:** Get task breakdown

### POST /activities/{id}/tasks
**Description:** Create task

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
**Description:** Update task

### PUT /tasks/{id}/status
**Description:** Update task status

### GET /activities/{id}/checklists
**Description:** Get checklists

### POST /activities/{id}/checklists
**Description:** Create checklist

### PUT /checklists/{id}
**Description:** Update checklist

### POST /checklists/{id}/items/{item_id}/complete
**Description:** Mark checklist item complete

---

## Staffing Endpoints

### GET /activities/{id}/staffing
**Description:** Get staffing plan for activity (Team Lead)

### POST /activities/{id}/staffing
**Description:** Create staffing entry (Team Lead)

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
**Description:** Update staffing entry (Team Lead)

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
**Description:** Remove staffing entry (Team Lead)

---

## Violation / Demerit Endpoints

### GET /violations/rules
**Description:** List violation rules

### GET /violations/rules/{id}
**Description:** Get rule details

### POST /violations/rules
**Description:** Create violation rule (Admin only)

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
**Description:** Update violation rule (Admin only)

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
**Description:** Delete violation rule (Admin only)

### POST /violations
**Description:** Create violation record

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

### GET /violations/{id}
**Description:** Get violation details

### GET /violations/user/{user_id}
**Description:** Get user's violations and points

### GET /violations/group/{group_id}
**Description:** Get group's aggregated points

### POST /violations/{id}/appeal
**Description:** Submit appeal

**Request:**
```json
{
  "notes": "string",
  "evidence": ["string"]
}
```

### POST /violations/{id}/review
**Description:** Reviewer's decision (Reviewer only)

**Request:**
```json
{
  "decision": "approve|reject",
  "notes": "string"
}
```

### POST /violations/{id}/final-decision
**Description:** Final decision (Reviewer only)

**Request:**
```json
{
  "notes": "string"
}
```

---

## Dashboard & Export Endpoints

### GET /dashboard
**Description:** Get default dashboard

### GET /dashboard/custom
**Description:** List custom dashboards

### POST /dashboard/custom
**Description:** Create custom dashboard

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
**Description:** Update custom dashboard

### GET /dashboard/custom/{id}/data
**Description:** Get widget data

### GET /dashboard/custom/{id}/export
**Description:** Export dashboard

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| format | string | Format: png, pdf, excel |

### POST /dashboard/favorites
**Description:** Favorite dashboard view

### GET /dashboard/export
**Description:** Export snapshot

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| format | string | Format: png, pdf, excel |

---

## Recommendation Endpoints

### GET /recommendations
**Description:** Get recommendations

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| context | string | Context: list, detail |
| entity_id | int | Current entity ID |
| page | int | Page number |
| page_size | int | Page size |

### GET /recommendations/popular
**Description:** Get popular tags (30 days)

---

## User Preferences Endpoints

### GET /preferences
**Description:** Get user preferences

### PUT /preferences
**Description:** Update user preferences

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
**Description:** Upload file

**Headers:** `Content-Type: multipart/form-data`

**Form Data:**
| Field | Type | Description |
|-------|------|-------------|
| file | file | File (JPG, PNG, PDF max 10MB) |
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

---

## Indexing Endpoints

### GET /index/status
**Description:** Get index status

### POST /index/rebuild
**Description:** Rebuild search index (Admin only)

### POST /index/cleanup
**Description:** Trigger nightly cleanup job — removes orphaned index entries older than 7 days (Admin only)

---

## Notification Endpoints

### GET /notifications
**Description:** Get notifications

### PUT /notifications/{id}/read
**Description:** Mark as read

### GET /notifications/settings
**Description:** Get notification settings

### PUT /notifications/settings
**Description:** Update notification settings

---

## Audit Trail Endpoints

### GET /audit
**Description:** Get audit trail (Admin/Reviewer)

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| entity_type | string | Entity type |
| entity_id | int | Entity ID |
| user_id | int | User ID |
| action | string | Action type |
| date_from | date | Date range |
| date_to | date | Date range |
| page | int | Page number |
| page_size | int | Page size |

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