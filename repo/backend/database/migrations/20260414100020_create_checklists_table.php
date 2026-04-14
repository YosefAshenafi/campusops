<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateChecklistsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('checklists', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $table
            ->addColumn('activity_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('title', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['activity_id'])
            ->addForeignKey('activity_id', 'activity_groups', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
