# CampusOps Design Specification

## Project Overview

**Project Name:** CampusOps (Unified Campus Operations & Logistics Management Portal)  
**Project Type:** On-Premise Web Application  
**Deployment:** Fully Offline  
**Internal Branding:** CampusOps

---

## Technology Stack

### Frontend
- **Language:** JavaScript
- **Framework:** Layui
- **Theme:** Layui Admin UI with role-based navigation
- **UI Pattern:** Admin-style sidebar layout

### Backend
- **Language:** PHP
- **Framework:** ThinkPHP
- **API Style:** REST-style endpoints
- **Database:** MySQL

### Search Engine
- **Global Full-Text Search:** Local indexing with highlighting across titles, body text, authors, and tags
- **Logistics Search:** Tokenization, optional synonym matching, pinyin matching, basic spell correction
- **Indexing Strategy:** Incremental local indexing triggered on create/update/delete events + nightly cleanup job (removes orphaned entries older than 7 days)

---

## User Roles & Navigation

| Role | Navigation Access |
|------|-------------------|
| Administrator | Full system access, user management, export controls, refund approvals |
| Operations Staff | Order management, activity creation, fulfillment tracking |
| Team Lead | Task breakdowns, staffing, checklists |
| Reviewer | Approval workflows, violation appeals |
| Regular User | Browse activities, sign up, view dashboard |

---

## Activity Management

### Activity Lifecycle
```
Draft → Published → In Progress → Completed → Archived
```

### Versioning Model
- **Activity Group + Version:** Signups remain linked to root activity ID
- **New Version on Edit:** When a Published activity is edited, a new version record is created
- **User Notification:** Existing signups marked "Pending Acknowledgement" until users view highlighted change log

### Activity Rules (at Publish Time)
- Signup window (start/end datetime)
- Max headcount
- Eligibility tags
- Required supplies list

### Timestamps
- Format: MM/DD/YYYY and 12-hour time (e.g., 01:30 PM)
- Visible on state transitions and detail views

---

## Order & Fulfillment Management

### Order Lifecycle (State Machine)
```
                         ┌──────────────┐
                         │    Placed    │
                         └──────┬───────┘
                                │
                                ▼
                     ┌──────────────────────┐
              ┌──────│  Pending Payment     │──────┐
              │      │ (auto-cancel @ 30m)  │      │
              │      └──────────┬───────────┘      │
              │                 │                   │
              │                 ▼                   │
              │         ┌──────────────┐            │
              │    ┌────│     Paid     │────┐       │
              │    │    └──────┬───────┘    │       │
              │    │           │            │       │
              │    │ (refund)  ▼            │       │
              │    │   ┌─────────────┐     │       │
              │    │   │  Ticketing  │     │       │
              │    │   └──────┬──────┘     │       │
              │    │          │            │       │
              │    │          ▼            │       │
              │    │   ┌─────────────┐     │       │
              │    │   │  Ticketed   │     │       │
              │    │   └──────┬──────┘     │       │
              │    │          │            │       │
              │    ▼          ▼            ▼       │
              │  ┌──────────────────────────────┐  │
              └─▶│          Canceled            │◀─┘
                 └──────────────────────────────┘
                          ▼ (normal close)
                 ┌──────────────────────────────┐
                 │           Closed              │
                 │ (immutable except invoice     │
                 │  address w/ Reviewer approval)│
                 └──────────────────────────────┘
```

**Branching Notes:**
- `Canceled` is reachable from Placed, Pending Payment (auto after 30 min), and Paid (manual cancel)
- `Closed` is the terminal state reached from Ticketed via normal completion
- Refund from `Paid` is Admin-only and only before reaching `Ticketed`

### State Transition Rules
- **Pending Payment** auto-cancels after 30 minutes
- **Paid** orders refundable only by Administrator (before Ticketed state)
- **Closed** records immutable except for invoice address corrections (requires Reviewer approval)

### Fulfillment Features
- **Shipment Creation:** One order can split into multiple packages
- **Package Tracking:** Carrier name + tracking number entry
- **Offline Tracking:** Local scan event input via fast-scan module (barcode scanner support)
- **Delivery Confirmation:** Scan event logging with success/failure feedback
- **Exception Receipts:** Captured and displayed in-app
- **Arrival Reminders:** In-app alerts with configurable local subscription preferences

---

## Violation / Demerit System

### Configurable Rules
- Point values (e.g., +5 reward for on-time completion, -10 for missed shift)
- Evidence attachments: JPG, PNG, PDF (max 10 MB each)
- File validation: File-type + SHA-256 fingerprinting

### Appeal Workflow
1. User submits appeal with decision notes
2. Reviewer re-reviews with required decision notes
3. Final decision recorded in audit trail

### Point Aggregation
- Individual totals: Auto-aggregate per user
- Group totals: Cumulative sum of all member points
- **Alert Thresholds:**
  - 25 points: Manager review trigger
  - 50 points: Administrative action trigger

---

## Search Experience

### Global Full-Text Search
- **Search Targets:** Titles, body text, authors, tags
- **Features:** Highlighted results
- **Filters:** Multi-dimensional
- **Sorting:** Recency, popularity, reply count, relevance

### Logistics / Order Search
- **Tokenization:** Yes
- **Synonym Matching:** Optional
- **Pinyin Matching:** Optional (for Chinese names)
- **Spell Correction:** Basic ("Did you mean?" suggestion)
- **Filters:** Multi-dimensional
- **Sorting:** Recency, popularity, reply count, relevance

---

## Recommendations

### Signal Sources (Local Behavior)
- Views
- Saves
- Signups
- Tags

### Cold-Start Defaults
- Top-performing tags in the last 30 days

### Deduplication Rules
- Avoid repeating same activity/order family

### Diversity Rules
- Any single tag capped at 40% of a feed page

### Display Locations
- List pages
- Detail pages

---

## Dashboard & Reporting

### Custom Dashboards
- Drag-and-drop widget builder
- Drill-down into linked charts
- Favorite common views

### Export Formats
- PNG
- PDF
- Excel (with watermark: username + timestamp)

---

## Security Features

### Authentication
- **Method:** Local username and password
- **Password Requirements:** Minimum 10 characters
- **Lockout Policy:** 5 failed attempts → 15-minute lockout
- **Hashing:** Salted password hashing

### Authorization
- **Model:** RBAC (Role-Based Access Control)

### Sensitive Data Protection
- **UI Masking:** Passenger identifiers, invoice contacts masked in display
- **Encryption:** At-rest encryption for sensitive fields
- **Export Watermarking:** Username + timestamp on all exports

### File Uploads
- **Supported Types:** JPG, PNG, PDF
- **Max Size:** 10 MB per file
- **Validation:** File-type + SHA-256 fingerprinting

---

## Audit Trail

All state transitions, order confirmations, violation decisions, and user actions are logged with:
- Operator ID
- Timestamp
- Previous state / New state

---

## External System Name

**Brand Name:** CampusOps (or OmniCore)

### Usage Locations
- Top-left logo area in Layui sidebar
- Sender name for in-app arrival reminders
- Default header for watermarked PDF/Excel exports