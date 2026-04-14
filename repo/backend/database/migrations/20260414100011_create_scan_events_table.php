<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateScanEventsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('scan_events', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $table
            ->addColumn('shipment_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('scan_code', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('location', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('scanned_by', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('result', 'string', ['limit' => 20, 'null' => false, 'default' => 'success', 'comment' => 'success, failure'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['shipment_id'])
            ->addForeignKey('shipment_id', 'shipments', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('scanned_by', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();
    }
}
