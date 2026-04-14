<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateUsersTable extends Migrator
{
    public function change()
    {
        $table = $this->table('users', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $table
            ->addColumn('username', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('password_hash', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('salt', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('role', 'string', ['limit' => 50, 'null' => false, 'default' => 'regular_user'])
            ->addColumn('status', 'string', ['limit' => 20, 'null' => false, 'default' => 'active', 'comment' => 'active, locked, disabled'])
            ->addColumn('failed_attempts', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('locked_until', 'timestamp', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['username'], ['unique' => true])
            ->addIndex(['role'])
            ->addIndex(['status'])
            ->create();
    }
}
