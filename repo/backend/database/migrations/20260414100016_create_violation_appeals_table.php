<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateViolationAppealsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('violation_appeals', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $table
            ->addColumn('violation_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('appellant_notes', 'text', ['null' => true])
            ->addColumn('reviewer_id', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('decision', 'string', ['limit' => 20, 'null' => true, 'comment' => 'approve, reject'])
            ->addColumn('reviewer_notes', 'text', ['null' => true])
            ->addColumn('final_notes', 'text', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('decided_at', 'timestamp', ['null' => true])
            ->addIndex(['violation_id'])
            ->addForeignKey('violation_id', 'violations', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
