<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateShipmentExceptionsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('shipment_exceptions', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $table
            ->addColumn('shipment_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('description', 'text', ['null' => false])
            ->addColumn('reported_by', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['shipment_id'])
            ->addForeignKey('shipment_id', 'shipments', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('reported_by', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();
    }
}
