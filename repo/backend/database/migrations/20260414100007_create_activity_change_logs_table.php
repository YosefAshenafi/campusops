<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateActivityChangeLogsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('activity_change_logs', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $table
            ->addColumn('group_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('from_version', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('to_version', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('changes', 'json', ['null' => true, 'comment' => 'JSON diff of changed fields'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['group_id'])
            ->addForeignKey('group_id', 'activity_groups', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
