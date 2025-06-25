<?php

namespace MCT\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class AddB2BColumnToExportTable
{
    /**
     * Run the migrations.
     *
     * @param \Illuminate\Database\Schema\Builder $schema
     */
    public function run(Builder $schema)
    {
        $schema->table('plugin_mct__export_stack', function (Blueprint $table) {
            $table->boolean('isB2B')->default(false);
        });
    }

    protected function rollback(Builder $schema)
    {
    }
}
