<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateSearchIndexTable extends Migrator
{
    public function up()
    {
        $table = $this->table('search_index', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $table
            ->addColumn('entity_type', 'string', ['limit' => 50, 'null' => false, 'comment' => 'activity, order'])
            ->addColumn('entity_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('title', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('body', 'text', ['null' => true])
            ->addColumn('tags', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('author', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('normalized_text', 'text', ['null' => true, 'comment' => 'Full-text searchable content'])
            ->addColumn('pinyin_text', 'text', ['null' => true, 'comment' => 'Pinyin expansion for Chinese support'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['entity_type', 'entity_id'], ['unique' => true])
            ->addIndex(['entity_type'])
            ->create();

        // Add FULLTEXT indexes (Phinx doesn't support FULLTEXT directly)
        $this->execute('ALTER TABLE search_index ADD FULLTEXT INDEX idx_fulltext_search (normalized_text)');
        $this->execute('ALTER TABLE search_index ADD FULLTEXT INDEX idx_fulltext_title (title)');
    }

    public function down()
    {
        $this->dropTable('search_index');
    }
}
