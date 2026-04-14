<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateChecklistItemsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('checklist_items', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $table
            ->addColumn('checklist_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('label', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('completed', 'boolean', ['default' => false])
            ->addColumn('completed_by', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('completed_at', 'timestamp', ['null' => true])
            ->addIndex(['checklist_id'])
            ->addForeignKey('checklist_id', 'checklists', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
