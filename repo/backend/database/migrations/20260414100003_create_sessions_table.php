<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateSessionsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('sessions', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $table
            ->addColumn('user_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('token', 'string', ['limit' => 128, 'null' => false])
            ->addColumn('expires_at', 'timestamp', ['null' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['token'], ['unique' => true])
            ->addIndex(['user_id'])
            ->addIndex(['expires_at'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
