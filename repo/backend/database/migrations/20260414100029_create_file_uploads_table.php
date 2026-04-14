<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateFileUploadsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('file_uploads', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);
        $table
            ->addColumn('uploaded_by', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('filename', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('original_name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('sha256', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('file_path', 'string', ['limit' => 500, 'null' => false])
            ->addColumn('size', 'integer', ['null' => false, 'comment' => 'File size in bytes'])
            ->addColumn('category', 'string', ['limit' => 50, 'null' => true, 'comment' => 'evidence, supply, etc.'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['uploaded_by'])
            ->addIndex(['sha256'])
            ->addIndex(['category'])
            ->addForeignKey('uploaded_by', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();
    }
}
