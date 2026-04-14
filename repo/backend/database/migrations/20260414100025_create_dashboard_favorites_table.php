<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateDashboardFavoritesTable extends Migrator
{
    public function change()
    {
        $table = $this->table('dashboard_favorites', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $table
            ->addColumn('user_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('dashboard_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id', 'dashboard_id'], ['unique' => true])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('dashboard_id', 'dashboards', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
