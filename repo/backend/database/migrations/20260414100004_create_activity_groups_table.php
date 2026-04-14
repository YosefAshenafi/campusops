<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateActivityGroupsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('activity_groups', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $table
            ->addColumn('created_by', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('created_by', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();
    }
}
