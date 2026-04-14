<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateViolationsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('violations', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $table
            ->addColumn('user_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('rule_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('points', 'integer', ['null' => false])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('status', 'string', ['limit' => 30, 'null' => false, 'default' => 'pending', 'comment' => 'pending, approved, rejected, appealed'])
            ->addColumn('created_by', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id'])
            ->addIndex(['rule_id'])
            ->addIndex(['status'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('rule_id', 'violation_rules', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('created_by', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();
    }
}
