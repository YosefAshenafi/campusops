<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateShipmentsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('shipments', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $table
            ->addColumn('order_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('carrier', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('tracking_number', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('package_contents', 'json', ['null' => true])
            ->addColumn('weight', 'decimal', ['precision' => 8, 'scale' => 2, 'null' => true])
            ->addColumn('status', 'string', ['limit' => 30, 'null' => false, 'default' => 'created', 'comment' => 'created, in_transit, delivered, exception'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['order_id'])
            ->addIndex(['tracking_number'])
            ->addIndex(['status'])
            ->addForeignKey('order_id', 'orders', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
