# CampusOps Audit Remediation Prompt

You are working on the CampusOps project located at `repo/` (relative to your working directory). This is a ThinkPHP 8 + Layui campus operations portal. A static audit has identified **6 High-severity failures**, **2 Medium-severity partial failures**, and several systemic partial-pass areas. Your job is to fix all of them.

Read the relevant files before making any changes. Do not add features or refactor beyond what is described. Work through each fix sequentially, verifying the change is consistent across all layers (migration, model, service, seed, controller, frontend).

---

## FIX 1: Violation evidence validation bypass (High)

**Problem:** `ViolationService::createViolation()` (line ~191) accepts raw `evidence` array payloads with arbitrary `filename`, `sha256`, and `file_path` fields. It does not verify these correspond to previously uploaded and validated files via `UploadService`. This bypasses the JPG/PNG/PDF type check, 10MB size limit, and server-computed SHA-256 fingerprint.

**Files to modify:**
- `repo/backend/app/service/ViolationService.php` — `createViolation()` method

**Required change:**
Replace the evidence block (lines ~191-199) that blindly saves raw metadata. Instead:
1. The `evidence` array must contain `file_upload_ids` — an array of integer IDs referencing records in the `file_uploads` table.
2. For each ID, look up the `FileUpload` model. If not found, throw a 400 error.
3. Create `ViolationEvidence` records using the validated `FileUpload` data (`filename`, `sha256`, `file_path`) — not from user input.

The new logic should look like:
```php
if (!empty($data['evidence_file_ids'])) {
    foreach ($data['evidence_file_ids'] as $fileId) {
        $fileUpload = \app\model\FileUpload::find($fileId);
        if (!$fileUpload) {
            throw new \Exception("Evidence file ID {$fileId} not found or not uploaded", 400);
        }
        $evidence = new ViolationEvidence();
        $evidence->violation_id = $violation->id;
        $evidence->filename = $fileUpload->filename;
        $evidence->sha256 = $fileUpload->sha256;
        $evidence->file_path = $fileUpload->file_path;
        $evidence->save();
    }
}
```

Do NOT remove the `FileUpload` import if it is not already present — add `use app\model\FileUpload;` at the top.

---

## FIX 2: Group-level demerit aggregation alerts missing (High)

**Problem:** `ViolationService::checkAlerts()` (line ~345) only checks individual user points against 25/50 thresholds. The requirement calls for **group-level** aggregation alerts as well — when the sum of violation points across all members of a user's group(s) hits 25 or 50, a notification should be sent to group managers/admins.

**Files to modify:**
- `repo/backend/app/service/ViolationService.php`

**Required changes:**

1. Add a new method `checkGroupAlerts(User $user)` that:
   - Finds all groups the user belongs to via `UserGroupMember::where('user_id', $user->id)->column('group_id')`
   - For each group, gets all member IDs via `UserGroupMember::where('group_id', $groupId)->column('user_id')`
   - Sums non-rejected violation points for all those members
   - If the group total >= 50, creates a notification with type `group_violation_alert` and title `"Group Violation Alert: 50 Points"` targeting the group (send to all admin users: `User::where('role', 'administrator')->column('id')`)
   - Else if >= 25, creates a notification with threshold 25
   - To avoid duplicate alerts, check if a notification with the same type and matching body already exists before creating

2. Call `$this->checkGroupAlerts($user)` in `createViolation()` right after the existing `$this->checkAlerts($user)` call (line ~186).

3. Also call `$this->checkGroupAlerts($user)` at the end of `finalDecision()` after the point recalculation on rejection (line ~326).

---

## FIX 3: Logistics search missing pinyin/spell-correction (High)

**Problem:** `SearchService::searchLogistics()` (line ~113) implements tokenization and synonym expansion but does NOT include pinyin-normalized matching or "did-you-mean" spell correction, both of which exist in the general `search()` method and are required for logistics search as well.

**Files to modify:**
- `repo/backend/app/service/SearchService.php` — `searchLogistics()` method

**Required changes:**

1. Add pinyin conversion for the query:
   ```php
   $pinyinSearch = PinyinService::toPinyin($query);
   ```
   Add `$pinyinSearch` to the `$expandedTerms` array.

2. Add pinyin_text matching in the where clause — add to the `function($q)` closure:
   ```php
   $q->whereOr('pinyin_text', 'like', "%{$term}%");
   ```
   for each expanded term (already loops over them, just add this line inside the loop).

3. Add did-you-mean to the return array:
   ```php
   $didYouMean = $this->getDidYouMean($query);
   ```
   And include `'did_you_mean' => $didYouMean` in the returned array.

---

## FIX 4: Activity signup status model inconsistency (High)

**Problem:** There is a status enum mismatch across three layers:
- **Migration** (`20260414100006_create_activity_signups_table.php` line 14): default is `'active'`, comment says `active, pending_acknowledgement, canceled`
- **Seed** (`DatabaseSeeder.php` line ~240): inserts with `status => 'active'`
- **Service** (`ActivityService.php`): uses `'confirmed'` (line 448 for new signups), `'pending_acknowledgement'` (line 283 for version change ack), and `'cancelled'` (line 474 for cancel). The headcount query (line 438) counts `['confirmed', 'pending_acknowledgement']`.

Since `active` is never queried for headcount, seeded signups with `active` status will be silently excluded from capacity checks. The `confirmed` value used in the service is the correct canonical value.

**Files to modify:**
1. `repo/backend/database/migrations/20260414100006_create_activity_signups_table.php`
2. `repo/backend/database/seeds/DatabaseSeeder.php`

**Required changes:**

1. In the migration file, change the `status` column definition:
   - Change `'default' => 'active'` to `'default' => 'confirmed'`
   - Change the comment from `'active, pending_acknowledgement, canceled'` to `'confirmed, pending_acknowledgement, cancelled'`

2. In the seeder, change all `'status' => 'active'` to `'status' => 'confirmed'` in the `seedActivities()` method (lines ~240-244, there are 5 signup rows).

---

## FIX 5: Dashboard favorites schema/code mismatch (High)

**Problem:** `DashboardService` writes/queries `widget_id` (lines 80-87, 97-99, 111) but the `dashboard_favorites` table and `DashboardFavorite` model only define `dashboard_id`. The favorites feature is completely non-functional because queries reference a nonexistent column.

The service's intent is to favorite individual **widgets** (string identifiers like `orders_by_state`), not dashboard records. The schema needs to match this intent.

**Files to modify:**
1. `repo/backend/database/migrations/20260414100025_create_dashboard_favorites_table.php`
2. `repo/backend/app/model/DashboardFavorite.php`

**Required changes:**

1. In the migration file:
   - Change `'dashboard_id'` column to `'widget_id'` with type `'string'` and `'limit' => 50` (since widget IDs are strings like `orders_by_state`).
   - Remove the foreign key to `dashboards` table (widget_id is a string key, not a FK).
   - Update the unique index from `['user_id', 'dashboard_id']` to `['user_id', 'widget_id']`.

2. In the model file:
   - Change `'dashboard_id' => 'integer'` to `'widget_id' => 'string'` in the `$type` array.

---

## FIX 6: Test bootstrap — ThinkPHP app initialization broken (High)

**Problem:** `unit_tests/bootstrap.php` tries to initialize the ThinkPHP app but the bootstrap fails at runtime because the DB connection is not available/configured. This causes 48 out of 60 tests to error before any assertions run (only `AuthServiceTest` passes because it doesn't touch the DB).

**Files to modify:**
1. `repo/unit_tests/bootstrap.php`

**Required changes:**

Replace the bootstrap with a version that:
1. Defines `ROOT_PATH` pointing to `backend/`
2. Requires the autoloader
3. Creates and initializes the ThinkPHP App
4. Sets up a test database configuration override using environment variables with fallback defaults:
   ```php
   <?php
   declare(strict_types=1);

   define('ROOT_PATH', dirname(__DIR__) . '/backend/');

   require ROOT_PATH . 'vendor/autoload.php';

   $app = new \think\App(ROOT_PATH);
   $app->initialize();

   // Override database config for test environment
   $dbConfig = [
       'type'     => env('DB_TYPE', 'mysql'),
       'hostname' => env('DB_HOST', '127.0.0.1'),
       'database' => env('DB_NAME', 'campusops_test'),
       'username' => env('DB_USER', 'root'),
       'password' => env('DB_PASS', ''),
       'hostport' => env('DB_PORT', '3306'),
       'charset'  => 'utf8mb4',
       'prefix'   => '',
   ];

   \think\facade\Db::setConfig([
       'default'     => 'mysql',
       'connections' => [
           'mysql' => $dbConfig,
       ],
   ]);
   ```

Also update `repo/phpunit.xml` to pass test DB environment variables:
- Add a `<php>` section inside the `<phpunit>` element (if not present) with:
  ```xml
  <php>
      <env name="DB_TYPE" value="mysql"/>
      <env name="DB_HOST" value="127.0.0.1"/>
      <env name="DB_NAME" value="campusops_test"/>
      <env name="DB_USER" value="root"/>
      <env name="DB_PASS" value=""/>
      <env name="DB_PORT" value="3306"/>
  </php>
  ```

---

## FIX 7: Change-log UX lacks highlighted diff (Medium)

**Problem:** The frontend change log modal in `frontend/src/modules/activities.js` (lines ~392-413) renders version diffs as plain `key: old → new` text. The requirement calls for visually highlighted diffs showing added/removed/changed fields.

**Files to modify:**
- `repo/frontend/src/modules/activities.js` — `showChangeLog` function
- `repo/frontend/src/assets/css/app.css` — add diff highlight styles

**Required changes:**

1. In `app.css`, add these styles:
   ```css
   .changelog-field { margin-bottom: 8px; padding: 6px 10px; border-radius: 4px; }
   .changelog-field .field-name { font-weight: bold; margin-right: 8px; }
   .changelog-old { background: #fde8e8; color: #9b1c1c; text-decoration: line-through; padding: 2px 4px; border-radius: 2px; }
   .changelog-new { background: #def7ec; color: #03543f; padding: 2px 4px; border-radius: 2px; }
   .changelog-arrow { margin: 0 6px; color: #6b7280; }
   ```

2. In the `showChangeLog` function, replace the plain rendering of changes (lines ~401-403) with:
   ```javascript
   for (var key in changes) {
       var oldVal = typeof changes[key].old === 'object' ? JSON.stringify(changes[key].old) : changes[key].old;
       var newVal = typeof changes[key].new === 'object' ? JSON.stringify(changes[key].new) : changes[key].new;
       html += '<div class="changelog-field">';
       html += '<span class="field-name">' + key + ':</span>';
       html += '<span class="changelog-old">' + oldVal + '</span>';
       html += '<span class="changelog-arrow">&rarr;</span>';
       html += '<span class="changelog-new">' + newVal + '</span>';
       html += '</div>';
   }
   ```

---

## FIX 8: Controller error code mappings collapse auth/resource distinctions (Medium)

**Problem:** Several controller catch blocks return a fixed 404 status regardless of the actual exception code (which might be 403 for authorization errors).

**Files to modify:**
- `repo/backend/app/controller/OrderController.php` — `history()` method (lines ~84-90)
- `repo/backend/app/controller/ShipmentController.php` — `show()` (lines ~102-108), `index()` (lines ~56-61), `scanHistory()` (lines ~149-154), `exceptions()` (lines ~191-197)

**Required changes:**

In each of the listed catch blocks, change the hardcoded `404` to use the exception's code with a safe fallback:
```php
} catch (\Exception $e) {
    $code = $e->getCode() ?: 500;
    if ($code < 100 || $code >= 600) {
        $code = 500;
    }
    return json([
        'success' => false,
        'code' => $code,
        'error' => $e->getMessage(),
    ], $code);
}
```

This preserves 403 for authorization errors, 404 for not-found, and prevents invalid HTTP status codes.

---

## After completing all fixes

Run `git diff --stat` to verify you touched only the files listed above. Do not modify any other files. Provide a brief summary of what was changed for each fix.
