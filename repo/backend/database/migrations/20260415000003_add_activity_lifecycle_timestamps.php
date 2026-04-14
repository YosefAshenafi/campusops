<?php

use think\migration\Migrator;

class AddActivityLifecycleTimestamps extends Migrator
{
    public function change()
    {
        $table = $this->table('activity_versions');
        $table
            ->addColumn('started_at', 'timestamp', ['null' => true, 'after' => 'published_at'])
            ->addColumn('completed_at', 'timestamp', ['null' => true, 'after' => 'started_at'])
            ->addColumn('archived_at', 'timestamp', ['null' => true, 'after' => 'completed_at'])
            ->update();
    }
}
