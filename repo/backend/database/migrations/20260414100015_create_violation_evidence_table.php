<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateViolationEvidenceTable extends Migrator
{
    public function change()
    {
        $table = $this->table('violation_evidence', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $table
            ->addColumn('violation_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('filename', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('sha256', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('file_path', 'string', ['limit' => 500, 'null' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['violation_id'])
            ->addForeignKey('violation_id', 'violations', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
