<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateOrderStateHistoryTable extends Migrator
{
    public function change()
    {
        $table = $this->table('order_state_history', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $table
            ->addColumn('order_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('from_state', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('to_state', 'string', ['limit' => 30, 'null' => false])
            ->addColumn('changed_by', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['order_id'])
            ->addForeignKey('order_id', 'orders', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('changed_by', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();
    }
}
