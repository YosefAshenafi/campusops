<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateAuditTrailTable extends Migrator
{
    public function change()
    {
        $table = $this->table('audit_trail', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $table
            ->addColumn('user_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('entity_type', 'string', ['limit' => 50, 'null' => false])
            ->addColumn('entity_id', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('action', 'string', ['limit' => 50, 'null' => false])
            ->addColumn('old_state', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('new_state', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('metadata', 'json', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['entity_type', 'entity_id'])
            ->addIndex(['user_id'])
            ->addIndex(['action'])
            ->addIndex(['created_at'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();
    }
}
