<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateAllTables extends Migrator
{
    public function up()
    {
        // Deprecated monolithic migration kept for history only.
        // The schema is now managed by the granular 20260414* migrations.
        return;

        // Users table
        $this->table('users', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('username', 'string', ['limit' => 32, 'null' => false])
        ->addColumn('password_hash', 'string', ['limit' => 255, 'null' => false])
        ->addColumn('salt', 'string', ['limit' => 32, 'null' => false])
        ->addColumn('role', 'string', ['limit' => 32, 'null' => false, 'default' => 'regular_user'])
        ->addColumn('status', 'string', ['limit' => 16, 'null' => false, 'default' => 'active'])
        ->addColumn('failed_attempts', 'integer', ['limit' => 11, 'null' => false, 'default' => 0])
        ->addColumn('locked_until', 'datetime', ['null' => true])
        ->addColumn('violation_points', 'integer', ['limit' => 11, 'null' => false, 'default' => 0])
        ->addColumn('created_at', 'datetime', ['null' => false])
        ->addColumn('updated_at', 'datetime', ['null' => false])
        ->create();

        // Roles table
        $this->table('roles', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('name', 'string', ['limit' => 32, 'null' => false])
        ->addColumn('description', 'string', ['limit' => 255, 'null' => true])
        ->addColumn('permissions', 'text', ['null' => true])
        ->addColumn('created_at', 'datetime', ['null' => false])
        ->create();

        // Sessions table
        $this->table('sessions', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('user_id', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('token', 'string', ['limit' => 64, 'null' => false])
        ->addColumn('expires_at', 'datetime', ['null' => false])
        ->addColumn('created_at', 'datetime', ['null' => false])
        ->create();

        // Activity Groups
        $this->table('activity_groups', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('created_by', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('created_at', 'datetime', ['null' => false])
        ->create();

        // Activity Versions
        $this->table('activity_versions', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('group_id', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('version_number', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('title', 'string', ['limit' => 200, 'null' => false])
        ->addColumn('body', 'text', ['null' => true])
        ->addColumn('tags', 'text', ['null' => true])
        ->addColumn('state', 'string', ['limit' => 16, 'null' => false, 'default' => 'draft'])
        ->addColumn('max_headcount', 'integer', ['limit' => 11, 'null' => false, 'default' => 0])
        ->addColumn('signup_start', 'datetime', ['null' => true])
        ->addColumn('signup_end', 'datetime', ['null' => true])
        ->addColumn('eligibility_tags', 'text', ['null' => true])
        ->addColumn('required_supplies', 'text', ['null' => true])
        ->addColumn('published_at', 'datetime', ['null' => true])
        ->addColumn('created_at', 'datetime', ['null' => false])
        ->create();

        // Activity Signups
        $this->table('activity_signups', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('group_id', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('user_id', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('status', 'string', ['limit' => 32, 'null' => false, 'default' => 'confirmed'])
        ->addColumn('acknowledged_at', 'datetime', ['null' => true])
        ->addColumn('created_at', 'datetime', ['null' => false])
        ->create();

        // Activity Change Logs
        $this->table('activity_change_logs', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('group_id', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('from_version', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('to_version', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('changes', 'text', ['null' => true])
        ->addColumn('created_at', 'datetime', ['null' => false])
        ->create();

        // Orders
        $this->table('orders', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('activity_id', 'integer', ['limit' => 11, 'null' => true])
        ->addColumn('created_by', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('team_lead_id', 'integer', ['limit' => 11, 'null' => true])
        ->addColumn('state', 'string', ['limit' => 16, 'null' => false, 'default' => 'placed'])
        ->addColumn('items', 'text', ['null' => true])
        ->addColumn('notes', 'text', ['null' => true])
        ->addColumn('payment_method', 'string', ['limit' => 50, 'null' => true])
        ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => false, 'default' => 0])
        ->addColumn('ticket_number', 'string', ['limit' => 50, 'null' => true])
        ->addColumn('auto_cancel_at', 'datetime', ['null' => true])
        ->addColumn('closed_at', 'datetime', ['null' => true])
        ->addColumn('invoice_address', 'text', ['null' => true])
        ->addColumn('created_at', 'datetime', ['null' => false])
        ->addColumn('updated_at', 'datetime', ['null' => false])
        ->create();

        // Order State History
        $this->table('order_state_history', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('order_id', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('from_state', 'string', ['limit' => 16, 'null' => true])
        ->addColumn('to_state', 'string', ['limit' => 16, 'null' => false])
        ->addColumn('changed_by', 'integer', ['limit' => 11, 'null' => true])
        ->addColumn('notes', 'string', ['limit' => 255, 'null' => true])
        ->addColumn('created_at', 'datetime', ['null' => false])
        ->create();

        // Shipments
        $this->table('shipments', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('order_id', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('carrier', 'string', ['limit' => 64, 'null' => true])
        ->addColumn('tracking_number', 'string', ['limit' => 64, 'null' => true])
        ->addColumn('package_contents', 'text', ['null' => true])
        ->addColumn('weight', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
        ->addColumn('status', 'string', ['limit' => 16, 'null' => false, 'default' => 'created'])
        ->addColumn('created_at', 'datetime', ['null' => false])
        ->create();

        // Scan Events
        $this->table('scan_events', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('shipment_id', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('scan_code', 'string', ['limit' => 64, 'null' => false])
        ->addColumn('location', 'string', ['limit' => 64, 'null' => true])
        ->addColumn('scanned_by', 'integer', ['limit' => 11, 'null' => true])
        ->addColumn('result', 'string', ['limit' => 32, 'null' => false])
        ->addColumn('created_at', 'datetime', ['null' => false])
        ->create();

        // Shipment Exceptions
        $this->table('shipment_exceptions', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('shipment_id', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('description', 'text', ['null' => false])
        ->addColumn('reported_by', 'integer', ['limit' => 11, 'null' => true])
        ->addColumn('created_at', 'datetime', ['null' => false])
        ->create();

        // Violation Rules
        $this->table('violation_rules', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('name', 'string', ['limit' => 64, 'null' => false])
        ->addColumn('description', 'text', ['null' => true])
        ->addColumn('points', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('category', 'string', ['limit' => 32, 'null' => true])
        ->addColumn('created_by', 'integer', ['limit' => 11, 'null' => true])
        ->addColumn('created_at', 'datetime', ['null' => false])
        ->addColumn('updated_at', 'datetime', ['null' => false])
        ->create();

        // Violations
        $this->table('violations', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('user_id', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('rule_id', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('points', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('notes', 'text', ['null' => true])
        ->addColumn('status', 'string', ['limit' => 16, 'null' => false, 'default' => 'pending'])
        ->addColumn('created_by', 'integer', ['limit' => 11, 'null' => true])
        ->addColumn('created_at', 'datetime', ['null' => false])
        ->create();

        // Violation Evidence
        $this->table('violation_evidence', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('violation_id', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('filename', 'string', ['limit' => 128, 'null' => false])
        ->addColumn('sha256', 'string', ['limit' => 64, 'null' => true])
        ->addColumn('file_path', 'string', ['limit' => 255, 'null' => true])
        ->addColumn('created_at', 'datetime', ['null' => false])
        ->create();

        // Violation Appeals
        $this->table('violation_appeals', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('violation_id', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('appellant_notes', 'text', ['null' => true])
        ->addColumn('reviewer_id', 'integer', ['limit' => 11, 'null' => true])
        ->addColumn('decision', 'string', ['limit' => 16, 'null' => true])
        ->addColumn('reviewer_notes', 'text', ['null' => true])
        ->addColumn('final_notes', 'text', ['null' => true])
        ->addColumn('created_at', 'datetime', ['null' => false])
        ->addColumn('decided_at', 'datetime', ['null' => true])
        ->create();

        // User Groups
        $this->table('user_groups', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('name', 'string', ['limit' => 64, 'null' => false])
        ->addColumn('created_at', 'datetime', ['null' => false])
        ->create();

        // User Group Members
        $this->table('user_group_members', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('group_id', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('user_id', 'integer', ['limit' => 11, 'null' => false])
        ->create();

        // Tasks
        $this->table('tasks', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('activity_id', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('title', 'string', ['limit' => 200, 'null' => false])
        ->addColumn('description', 'text', ['null' => true])
        ->addColumn('assigned_to', 'integer', ['limit' => 11, 'null' => true])
        ->addColumn('status', 'string', ['limit' => 16, 'null' => false, 'default' => 'pending'])
        ->addColumn('due_date', 'datetime', ['null' => true])
        ->addColumn('created_at', 'datetime', ['null' => false])
        ->addColumn('updated_at', 'datetime', ['null' => false])
        ->create();

        // Checklists
        $this->table('checklists', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('activity_id', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('title', 'string', ['limit' => 200, 'null' => false])
        ->addColumn('created_at', 'datetime', ['null' => false])
        ->create();

        // Checklist Items
        $this->table('checklist_items', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('checklist_id', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('label', 'string', ['limit' => 255, 'null' => false])
        ->addColumn('completed', 'boolean', ['null' => false, 'default' => false])
        ->addColumn('completed_by', 'integer', ['limit' => 11, 'null' => true])
        ->addColumn('completed_at', 'datetime', ['null' => true])
        ->create();

        // Staffing
        $this->table('staffing', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('activity_id', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('role', 'string', ['limit' => 64, 'null' => false])
        ->addColumn('required_count', 'integer', ['limit' => 11, 'null' => false, 'default' => 1])
        ->addColumn('assigned_users', 'text', ['null' => true])
        ->addColumn('notes', 'text', ['null' => true])
        ->addColumn('created_by', 'integer', ['limit' => 11, 'null' => true])
        ->addColumn('created_at', 'datetime', ['null' => false])
        ->addColumn('updated_at', 'datetime', ['null' => false])
        ->create();

        // Search Index
        $this->table('search_index', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('entity_type', 'string', ['limit' => 32, 'null' => false])
        ->addColumn('entity_id', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('title', 'string', ['limit' => 200, 'null' => false])
        ->addColumn('body', 'text', ['null' => true])
        ->addColumn('tags', 'text', ['null' => true])
        ->addColumn('normalized_text', 'text', ['null' => true])
        ->addColumn('pinyin_text', 'text', ['null' => true])
        ->addColumn('created_at', 'datetime', ['null' => false])
        ->addColumn('updated_at', 'datetime', ['null' => false])
        ->create();

        // Dashboards
        $this->table('dashboards', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('user_id', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('name', 'string', ['limit' => 64, 'null' => false])
        ->addColumn('widgets', 'text', ['null' => true])
        ->addColumn('is_default', 'boolean', ['null' => false, 'default' => false])
        ->addColumn('created_at', 'datetime', ['null' => false])
        ->addColumn('updated_at', 'datetime', ['null' => false])
        ->create();

        // Dashboard Favorites
        $this->table('dashboard_favorites', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('user_id', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('dashboard_id', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('created_at', 'datetime', ['null' => false])
        ->create();

        // Notifications
        $this->table('notifications', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('user_id', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('type', 'string', ['limit' => 32, 'null' => false])
        ->addColumn('title', 'string', ['limit' => 128, 'null' => false])
        ->addColumn('body', 'text', ['null' => true])
        ->addColumn('entity_type', 'string', ['limit' => 32, 'null' => true])
        ->addColumn('entity_id', 'integer', ['limit' => 11, 'null' => true])
        ->addColumn('read_at', 'datetime', ['null' => true])
        ->addColumn('created_at', 'datetime', ['null' => false])
        ->create();

        // User Preferences
        $this->table('user_preferences', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('user_id', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('arrival_reminders', 'boolean', ['null' => false, 'default' => true])
        ->addColumn('activity_alerts', 'boolean', ['null' => false, 'default' => true])
        ->addColumn('order_alerts', 'boolean', ['null' => false, 'default' => true])
        ->addColumn('violation_alerts', 'boolean', ['null' => false, 'default' => true])
        ->addColumn('dashboard_layout', 'text', ['null' => true])
        ->create();

        // Audit Trail
        $this->table('audit_trail', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('user_id', 'integer', ['limit' => 11, 'null' => true])
        ->addColumn('entity_type', 'string', ['limit' => 32, 'null' => false])
        ->addColumn('entity_id', 'integer', ['limit' => 11, 'null' => false])
        ->addColumn('action', 'string', ['limit' => 32, 'null' => false])
        ->addColumn('old_state', 'text', ['null' => true])
        ->addColumn('new_state', 'text', ['null' => true])
        ->addColumn('metadata', 'text', ['null' => true])
        ->addColumn('created_at', 'datetime', ['null' => false])
        ->create();

        // File Uploads
        $this->table('file_uploads', [
            'id' => false,
            'primary_key' => 'id',
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
        ])
        ->addColumn('id', 'integer', ['limit' => 11, 'null' => false, 'identity' => true])
        ->addColumn('uploaded_by', 'integer', ['limit' => 11, 'null' => true])
        ->addColumn('filename', 'string', ['limit' => 128, 'null' => false])
        ->addColumn('original_name', 'string', ['limit' => 128, 'null' => false])
        ->addColumn('sha256', 'string', ['limit' => 64, 'null' => true])
        ->addColumn('file_path', 'string', ['limit' => 255, 'null' => true])
        ->addColumn('size', 'integer', ['limit' => 11, 'null' => true])
        ->addColumn('category', 'string', ['limit' => 32, 'null' => true])
        ->addColumn('created_at', 'datetime', ['null' => false])
        ->create();

        // FK indexes
        $this->table('activity_versions')->addIndex(['group_id'])->save();
        $this->table('activity_signups')->addIndex(['group_id'])->addIndex(['user_id'])->save();
        $this->table('activity_change_logs')->addIndex(['group_id'])->save();
        $this->table('orders')->addIndex(['activity_id'])->addIndex(['created_by'])->save();
        $this->table('order_state_history')->addIndex(['order_id'])->save();
        $this->table('shipments')->addIndex(['order_id'])->save();
        $this->table('scan_events')->addIndex(['shipment_id'])->save();
        $this->table('shipment_exceptions')->addIndex(['shipment_id'])->save();
        $this->table('violations')->addIndex(['user_id'])->addIndex(['rule_id'])->save();
        $this->table('violation_evidence')->addIndex(['violation_id'])->save();
        $this->table('violation_appeals')->addIndex(['violation_id'])->save();
        $this->table('user_group_members')->addIndex(['group_id'])->addIndex(['user_id'])->save();
        $this->table('tasks')->addIndex(['activity_id'])->save();
        $this->table('checklists')->addIndex(['activity_id'])->save();
        $this->table('checklist_items')->addIndex(['checklist_id'])->save();
        $this->table('staffing')->addIndex(['activity_id'])->save();
        $this->table('search_index')->addIndex(['entity_type', 'entity_id'])->save();
        $this->table('dashboards')->addIndex(['user_id'])->save();
        $this->table('dashboard_favorites')->addIndex(['user_id'])->addIndex(['dashboard_id'])->save();
        $this->table('notifications')->addIndex(['user_id'])->save();
        $this->table('user_preferences')->addIndex(['user_id'])->save();
        $this->table('audit_trail')->addIndex(['user_id'])->addIndex(['entity_type', 'entity_id'])->save();
        $this->table('file_uploads')->addIndex(['uploaded_by'])->save();
    }

    public function down()
    {
        // Do not rollback via this deprecated migration.
        return;

        $this->dropTable('file_uploads');
        $this->dropTable('audit_trail');
        $this->dropTable('user_preferences');
        $this->dropTable('notifications');
        $this->dropTable('dashboard_favorites');
        $this->dropTable('dashboards');
        $this->dropTable('search_index');
        $this->dropTable('staffing');
        $this->dropTable('checklist_items');
        $this->dropTable('checklists');
        $this->dropTable('tasks');
        $this->dropTable('user_group_members');
        $this->dropTable('user_groups');
        $this->dropTable('violation_appeals');
        $this->dropTable('violation_evidence');
        $this->dropTable('violations');
        $this->dropTable('violation_rules');
        $this->dropTable('shipment_exceptions');
        $this->dropTable('scan_events');
        $this->dropTable('shipments');
        $this->dropTable('order_state_history');
        $this->dropTable('orders');
        $this->dropTable('activity_change_logs');
        $this->dropTable('activity_signups');
        $this->dropTable('activity_versions');
        $this->dropTable('activity_groups');
        $this->dropTable('sessions');
        $this->dropTable('roles');
        $this->dropTable('users');
    }
}