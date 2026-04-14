<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateNotificationsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('notifications', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $table
            ->addColumn('user_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('type', 'string', ['limit' => 50, 'null' => false, 'comment' => 'order_status, activity_update, violation_alert, arrival_reminder'])
            ->addColumn('title', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('body', 'text', ['null' => true])
            ->addColumn('entity_type', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('entity_id', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('read_at', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id'])
            ->addIndex(['user_id', 'read_at'])
            ->addIndex(['type'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
