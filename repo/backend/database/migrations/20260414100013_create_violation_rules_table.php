<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateViolationRulesTable extends Migrator
{
    public function change()
    {
        $table = $this->table('violation_rules', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $table
            ->addColumn('name', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('points', 'integer', ['null' => false, 'comment' => 'Positive for rewards, negative for demerits'])
            ->addColumn('category', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('created_by', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['category'])
            ->addForeignKey('created_by', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();
    }
}
