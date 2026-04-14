<?php

use think\migration\Migrator;

class AddSearchEngagementColumns extends Migrator
{
    public function up()
    {
        $table = $this->table('search_index');
        if (!$table->hasColumn('view_count')) {
            $table->addColumn('view_count', 'integer', ['default' => 0, 'null' => false, 'comment' => 'View/engagement count for popularity sort']);
        }
        if (!$table->hasColumn('reply_count')) {
            $table->addColumn('reply_count', 'integer', ['default' => 0, 'null' => false, 'comment' => 'Reply/comment count for reply_count sort']);
        }
        $table->save();
    }

    public function down()
    {
        $table = $this->table('search_index');
        if ($table->hasColumn('reply_count')) {
            $table->removeColumn('reply_count');
        }
        if ($table->hasColumn('view_count')) {
            $table->removeColumn('view_count');
        }
        $table->save();
    }
}
