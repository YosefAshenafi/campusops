<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateOrdersTable extends Migrator
{
    public function change()
    {
        $table = $this->table('orders', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $table
            ->addColumn('activity_id', 'integer', ['null' => true, 'signed' => false, 'comment' => 'References activity_groups.id'])
            ->addColumn('created_by', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('team_lead_id', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('state', 'string', ['limit' => 30, 'null' => false, 'default' => 'placed', 'comment' => 'placed, pending_payment, paid, ticketing, ticketed, canceled, closed'])
            ->addColumn('items', 'json', ['null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('payment_method', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
            ->addColumn('ticket_number', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('auto_cancel_at', 'timestamp', ['null' => true, 'comment' => 'Set when entering pending_payment state'])
            ->addColumn('closed_at', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['state'])
            ->addIndex(['activity_id'])
            ->addIndex(['created_by'])
            ->addIndex(['team_lead_id'])
            ->addIndex(['auto_cancel_at'])
            ->addForeignKey('created_by', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();
    }
}
