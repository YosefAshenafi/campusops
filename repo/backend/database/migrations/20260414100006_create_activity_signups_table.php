<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateActivitySignupsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('activity_signups', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $table
            ->addColumn('group_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('user_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('status', 'string', ['limit' => 30, 'null' => false, 'default' => 'active', 'comment' => 'active, pending_acknowledgement, canceled'])
            ->addColumn('acknowledged_at', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['group_id'])
            ->addIndex(['user_id'])
            ->addIndex(['group_id', 'user_id'], ['unique' => true])
            ->addForeignKey('group_id', 'activity_groups', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
