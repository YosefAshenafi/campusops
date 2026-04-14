# Questions, Assumptions, and Solutions

## 1. Activity Versioning & Existing Signups
**The Question:** When a "Published" activity is edited and a new version is created, does the system automatically migrate existing signups, or are they invalidated?  
**Assumption:** To avoid disrupting users, signups remain valid under the same activity, but users must be informed if published rules changed.  
**Solution:** Implement an **Activity Group + Version model**. Signups remain linked to the root activity ID, while each published edit creates a new version record. When a new version is published, existing signups are marked as **“Pending Acknowledgement”** until users view the highlighted change log.

---

## 2. On-Premise "Paid" Status Verification
**The Question:** Since the system is fully offline with no external services, how is the `Paid` order state triggered?  
**Assumption:** Payments are handled manually (cash, campus credit, or offline internal process) and must be confirmed by staff.  
**Solution:** Add a **Manual Payment Confirmation** flow where Operations Staff can confirm payment and transition the order state from `Pending Payment` → `Paid`. The confirmation is written into the audit trail with operator ID and timestamp.

---

## 3. Local Full-Text + Pinyin Search Implementation
**The Question:** Without Elasticsearch or cloud services, how can the portal support tokenization, optional synonym matching, pinyin matching, and basic spell correction?  
**Assumption:** The dataset is manageable in size (campus-scale usage), so local indexing and MySQL-based search is sufficient.  
**Solution:** Maintain a **local search index table** that stores normalized searchable text (original text + tokenized terms + optional pinyin expansions). Incrementally update the index on create/update/delete events. Spell correction is implemented as a lightweight “Did you mean?” suggestion using local similarity matching against existing tags/names.

---

## 4. Offline Scan Events Input Method
**The Question:** The prompt mentions offline tracking lookups based on locally entered scan events, but how are scan events actually captured?  
**Assumption:** Staff will use barcode scanners (keyboard-emulated USB scanners) or a mobile intranet view to input tracking scans.  
**Solution:** Provide a **Fast Scan page/module** in Layui that accepts scanner input and immediately logs a scan event through an AJAX call to ThinkPHP. Each scan updates shipment status history and provides instant success/failure feedback in the UI.

---

## 5. Group-Level Point Aggregation Rules
**The Question:** The prompt says points auto-aggregate per individual and per group, but how exactly does an individual violation affect the group score?  
**Assumption:** Group points are the cumulative sum of all member points, and group alerts should trigger based on total aggregated score.  
**Solution:** Implement aggregation in the **ThinkPHP service layer**: when a violation is approved, update the user’s total score and recompute the linked group totals inside the same transaction. The alert system monitors both individual totals and group totals for threshold triggers (25 and 50 points).

---

## 6. System Naming and Branding
**The Question:** The prompt refers to a "Unified Campus Operations & Logistics Management Portal," but the application lacks a concise internal name for the UI, documentation, and user communication.  
**Assumption:** A professional, easy-to-remember name is needed to differentiate the portal from generic IT tickets and to reflect its "all-in-one" campus nature.  
**Solution:** Adopt the internal name **"CampusOps"** (or **"OmniCore"**). This name will be used in the top-left logo area of the Layui sidebar, as the sender name for in-app arrival reminders, and as the default header for all watermarked PDF/Excel exports. This centralizes the brand identity across Administrators and Regular Users.
