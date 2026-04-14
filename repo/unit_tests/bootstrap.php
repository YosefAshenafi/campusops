<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__) . '/backend/');

require ROOT_PATH . 'vendor/autoload.php';

$app = new \think\App(ROOT_PATH);
$app->initialize();

// Use SQLite in-memory for tests — no external database required.
$app->config->set([
    'default'     => 'sqlite',
    'connections' => [
        'sqlite' => [
            'type'           => 'sqlite',
            'database'       => ':memory:',
            'prefix'         => '',
            'fields_cache'   => false,
            'trigger_sql'    => false,
        ],
    ],
    'auto_timestamp'   => true,
    'datetime_format'  => 'Y-m-d H:i:s',
], 'database');

// Tell the ORM that timestamp columns are named created_at / updated_at.
\think\Model::maker(function (\think\Model $model) {
    $model->setTimeField('created_at', 'updated_at');
});

// Override APP_KEY for tests so EncryptionService doesn't reject the default key.
$app->env->set([
    'APP_KEY' => 'test-encryption-key-for-unit-tests-32ch',
    'ENCRYPTION_KEY' => 'test-encryption-key-for-unit-tests-32ch',
]);

// Create tables in the in-memory database via the ORM.
$tables = [
    "CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL DEFAULT '',
        password_hash TEXT NOT NULL DEFAULT '',
        salt TEXT NOT NULL DEFAULT '',
        role TEXT NOT NULL DEFAULT 'regular_user',
        status TEXT NOT NULL DEFAULT 'active',
        failed_attempts INTEGER NOT NULL DEFAULT 0,
        locked_until TEXT DEFAULT NULL,
        violation_points INTEGER NOT NULL DEFAULT 0,
        created_at TEXT DEFAULT NULL,
        updated_at TEXT DEFAULT NULL
    )",
    "CREATE TABLE IF NOT EXISTS roles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL DEFAULT '',
        permissions TEXT DEFAULT '[]',
        created_at TEXT DEFAULT NULL,
        updated_at TEXT DEFAULT NULL
    )",
    "CREATE TABLE IF NOT EXISTS sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL DEFAULT 0,
        token TEXT NOT NULL DEFAULT '',
        expires_at TEXT DEFAULT NULL,
        created_at TEXT DEFAULT NULL,
        updated_at TEXT DEFAULT NULL
    )",
    "CREATE TABLE IF NOT EXISTS activity_groups (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        created_by INTEGER NOT NULL DEFAULT 0,
        created_at TEXT DEFAULT NULL,
        updated_at TEXT DEFAULT NULL
    )",
    "CREATE TABLE IF NOT EXISTS activity_versions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        group_id INTEGER NOT NULL DEFAULT 0,
        version_number INTEGER NOT NULL DEFAULT 1,
        title TEXT NOT NULL DEFAULT '',
        body TEXT NOT NULL DEFAULT '',
        tags TEXT NOT NULL DEFAULT '[]',
        state TEXT NOT NULL DEFAULT 'draft',
        max_headcount INTEGER NOT NULL DEFAULT 0,
        current_signups INTEGER NOT NULL DEFAULT 0,
        signup_start TEXT DEFAULT NULL,
        signup_end TEXT DEFAULT NULL,
        eligibility_tags TEXT NOT NULL DEFAULT '[]',
        required_supplies TEXT NOT NULL DEFAULT '[]',
        published_at TEXT DEFAULT NULL,
        created_at TEXT DEFAULT NULL,
        updated_at TEXT DEFAULT NULL
    )",
    "CREATE TABLE IF NOT EXISTS activity_signups (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        group_id INTEGER NOT NULL DEFAULT 0,
        user_id INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL DEFAULT 'confirmed',
        acknowledged_at TEXT DEFAULT NULL,
        created_at TEXT DEFAULT NULL,
        updated_at TEXT DEFAULT NULL
    )",
    "CREATE TABLE IF NOT EXISTS activity_change_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        group_id INTEGER NOT NULL DEFAULT 0,
        from_version INTEGER NOT NULL DEFAULT 0,
        to_version INTEGER NOT NULL DEFAULT 0,
        changes TEXT DEFAULT '{}',
        created_at TEXT DEFAULT NULL,
        updated_at TEXT DEFAULT NULL
    )",
    "CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        activity_id INTEGER NOT NULL DEFAULT 0,
        created_by INTEGER NOT NULL DEFAULT 0,
        team_lead_id INTEGER NOT NULL DEFAULT 0,
        state TEXT NOT NULL DEFAULT 'placed',
        items TEXT NOT NULL DEFAULT '[]',
        notes TEXT DEFAULT '',
        payment_method TEXT DEFAULT '',
        amount REAL NOT NULL DEFAULT 0,
        ticket_number TEXT DEFAULT NULL,
        auto_cancel_at TEXT DEFAULT NULL,
        closed_at TEXT DEFAULT NULL,
        invoice_address TEXT DEFAULT NULL,
        pending_address_correction TEXT DEFAULT NULL,
        created_at TEXT DEFAULT NULL,
        updated_at TEXT DEFAULT NULL
    )",
    "CREATE TABLE IF NOT EXISTS order_state_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL DEFAULT 0,
        from_state TEXT NOT NULL DEFAULT '',
        to_state TEXT NOT NULL DEFAULT '',
        changed_by INTEGER NOT NULL DEFAULT 0,
        notes TEXT DEFAULT '',
        created_at TEXT DEFAULT NULL,
        updated_at TEXT DEFAULT NULL
    )",
    "CREATE TABLE IF NOT EXISTS search_index (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        entity_type TEXT NOT NULL DEFAULT '',
        entity_id INTEGER NOT NULL DEFAULT 0,
        title TEXT NOT NULL DEFAULT '',
        body TEXT NOT NULL DEFAULT '',
        tags TEXT NOT NULL DEFAULT '[]',
        normalized_text TEXT NOT NULL DEFAULT '',
        pinyin_text TEXT NOT NULL DEFAULT '',
        author TEXT DEFAULT '',
        view_count INTEGER NOT NULL DEFAULT 0,
        created_at TEXT DEFAULT NULL,
        updated_at TEXT DEFAULT NULL
    )",
    "CREATE TABLE IF NOT EXISTS violation_rules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL DEFAULT '',
        description TEXT DEFAULT '',
        points INTEGER NOT NULL DEFAULT 0,
        category TEXT NOT NULL DEFAULT 'general',
        created_by INTEGER NOT NULL DEFAULT 0,
        created_at TEXT DEFAULT NULL,
        updated_at TEXT DEFAULT NULL
    )",
    "CREATE TABLE IF NOT EXISTS violations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL DEFAULT 0,
        rule_id INTEGER NOT NULL DEFAULT 0,
        points INTEGER NOT NULL DEFAULT 0,
        notes TEXT DEFAULT '',
        status TEXT NOT NULL DEFAULT 'pending',
        created_by INTEGER NOT NULL DEFAULT 0,
        created_at TEXT DEFAULT NULL,
        updated_at TEXT DEFAULT NULL
    )",
    "CREATE TABLE IF NOT EXISTS audit_trail (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL DEFAULT 0,
        entity_type TEXT NOT NULL DEFAULT '',
        entity_id INTEGER NOT NULL DEFAULT 0,
        action TEXT NOT NULL DEFAULT '',
        old_state TEXT DEFAULT '',
        new_state TEXT DEFAULT '',
        metadata TEXT DEFAULT '{}',
        created_at TEXT DEFAULT NULL,
        updated_at TEXT DEFAULT NULL
    )",
    "CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL DEFAULT 0,
        type TEXT NOT NULL DEFAULT '',
        title TEXT NOT NULL DEFAULT '',
        body TEXT DEFAULT '',
        is_read INTEGER NOT NULL DEFAULT 0,
        created_at TEXT DEFAULT NULL,
        updated_at TEXT DEFAULT NULL
    )",
    "CREATE TABLE IF NOT EXISTS user_groups (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL DEFAULT '',
        created_at TEXT DEFAULT NULL,
        updated_at TEXT DEFAULT NULL
    )",
    "CREATE TABLE IF NOT EXISTS user_group_members (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        group_id INTEGER NOT NULL DEFAULT 0,
        user_id INTEGER NOT NULL DEFAULT 0,
        created_at TEXT DEFAULT NULL,
        updated_at TEXT DEFAULT NULL
    )",
    "CREATE TABLE IF NOT EXISTS violation_appeals (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        violation_id INTEGER NOT NULL DEFAULT 0,
        appellant_notes TEXT DEFAULT '',
        reviewer_id INTEGER DEFAULT NULL,
        decision TEXT DEFAULT '',
        reviewer_notes TEXT DEFAULT '',
        final_notes TEXT DEFAULT '',
        created_at TEXT DEFAULT NULL,
        updated_at TEXT DEFAULT NULL
    )",
    "CREATE TABLE IF NOT EXISTS violation_evidence (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        violation_id INTEGER NOT NULL DEFAULT 0,
        filename TEXT DEFAULT '',
        sha256 TEXT DEFAULT '',
        file_path TEXT DEFAULT '',
        created_at TEXT DEFAULT NULL,
        updated_at TEXT DEFAULT NULL
    )",
    "CREATE TABLE IF NOT EXISTS file_uploads (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        filename TEXT DEFAULT '',
        sha256 TEXT DEFAULT '',
        file_path TEXT DEFAULT '',
        created_at TEXT DEFAULT NULL,
        updated_at TEXT DEFAULT NULL
    )",
    "CREATE TABLE IF NOT EXISTS shipments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL DEFAULT 0,
        tracking_number TEXT DEFAULT '',
        status TEXT DEFAULT 'pending',
        created_at TEXT DEFAULT NULL,
        updated_at TEXT DEFAULT NULL
    )",
];

foreach ($tables as $sql) {
    \think\facade\Db::execute($sql);
}

// Seed RBAC roles for permission checks.
\think\facade\Db::execute("INSERT INTO roles (name, permissions) VALUES ('administrator', ?)", [json_encode(['users.*', 'orders.*', 'activities.*', 'violations.*', 'reports.*'])]);
\think\facade\Db::execute("INSERT INTO roles (name, permissions) VALUES ('operations_staff', ?)", [json_encode(['orders.read', 'orders.update', 'activities.read'])]);
\think\facade\Db::execute("INSERT INTO roles (name, permissions) VALUES ('reviewer', ?)", [json_encode(['orders.read', 'activities.read', 'violations.read'])]);
\think\facade\Db::execute("INSERT INTO roles (name, permissions) VALUES ('team_lead', ?)", [json_encode(['orders.read', 'orders.create', 'activities.read'])]);
\think\facade\Db::execute("INSERT INTO roles (name, permissions) VALUES ('regular_user', ?)", [json_encode(['orders.read', 'orders.create'])]);
\think\facade\Db::execute("INSERT INTO roles (name, permissions) VALUES ('faculty', ?)", [json_encode(['activities.read'])]);
