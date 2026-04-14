<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateTasksTable extends Migrator
{
    public function change()
    {
        $table = $this->table('tasks', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $table
            ->addColumn('activity_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('title', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('assigned_to', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('status', 'string', ['limit' => 30, 'null' => false, 'default' => 'pending', 'comment' => 'pending, in_progress, completed'])
            ->addColumn('due_date', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['activity_id'])
            ->addIndex(['assigned_to'])
            ->addIndex(['status'])
            ->addForeignKey('activity_id', 'activity_groups', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
