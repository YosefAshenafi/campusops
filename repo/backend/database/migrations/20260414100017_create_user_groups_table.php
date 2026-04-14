<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateUserGroupsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('user_groups', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $table
            ->addColumn('name', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['name'], ['unique' => true])
            ->create();
    }
}
