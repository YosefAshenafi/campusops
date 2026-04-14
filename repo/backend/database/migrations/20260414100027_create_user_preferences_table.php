<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateUserPreferencesTable extends Migrator
{
    public function change()
    {
        $table = $this->table('user_preferences', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $table
            ->addColumn('user_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('arrival_reminders', 'boolean', ['default' => true])
            ->addColumn('activity_alerts', 'boolean', ['default' => true])
            ->addColumn('order_alerts', 'boolean', ['default' => true])
            ->addColumn('dashboard_layout', 'json', ['null' => true])
            ->addIndex(['user_id'], ['unique' => true])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
