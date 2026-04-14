<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateUserGroupMembersTable extends Migrator
{
    public function change()
    {
        $table = $this->table('user_group_members', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $table
            ->addColumn('group_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('user_id', 'integer', ['null' => false, 'signed' => false])
            ->addIndex(['group_id', 'user_id'], ['unique' => true])
            ->addForeignKey('group_id', 'user_groups', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
