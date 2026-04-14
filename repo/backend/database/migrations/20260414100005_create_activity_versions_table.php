<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateActivityVersionsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('activity_versions', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $table
            ->addColumn('group_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('version_number', 'integer', ['null' => false, 'default' => 1])
            ->addColumn('title', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('body', 'text', ['null' => true])
            ->addColumn('tags', 'json', ['null' => true])
            ->addColumn('state', 'string', ['limit' => 30, 'null' => false, 'default' => 'draft', 'comment' => 'draft, published, in_progress, completed, archived'])
            ->addColumn('max_headcount', 'integer', ['null' => true])
            ->addColumn('signup_start', 'timestamp', ['null' => true])
            ->addColumn('signup_end', 'timestamp', ['null' => true])
            ->addColumn('eligibility_tags', 'json', ['null' => true])
            ->addColumn('required_supplies', 'json', ['null' => true])
            ->addColumn('published_at', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['group_id'])
            ->addIndex(['state'])
            ->addIndex(['group_id', 'version_number'], ['unique' => true])
            ->addForeignKey('group_id', 'activity_groups', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
