<?php

use think\migration\Migrator;

class AddMissingColumns extends Migrator
{
    public function up()
    {
        $table = $this->table('users');
        if (!$this->hasColumn('users', 'violation_points')) {
            $table->addColumn('integer', 'violation_points', ['default' => 0]);
        }
        $table->save();

        $table = $this->table('user_preferences');
        if (!$this->hasColumn('user_preferences', 'violation_alerts')) {
            $table->addColumn('boolean', 'violation_alerts', ['default' => true]);
        }
        $table->save();

        $table = $this->table('orders');
        if (!$this->hasColumn('orders', 'invoice_address')) {
            $table->addColumn('string', 'invoice_address', ['limit' => 500, 'null' => true]);
        }
        if (!$this->hasColumn('orders', 'pending_address_correction')) {
            $table->addColumn('text', 'pending_address_correction', ['null' => true]);
        }
        $table->save();
    }
}
