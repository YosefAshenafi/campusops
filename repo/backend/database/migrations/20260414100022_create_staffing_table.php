<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateStaffingTable extends Migrator
{
    public function change()
    {
        $table = $this->table('staffing', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $table
            ->addColumn('activity_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('role', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('required_count', 'integer', ['null' => false, 'default' => 1])
            ->addColumn('assigned_users', 'json', ['null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('created_by', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['activity_id'])
            ->addForeignKey('activity_id', 'activity_groups', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('created_by', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();
    }
}
