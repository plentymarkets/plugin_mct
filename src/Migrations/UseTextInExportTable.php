<?php

namespace MCT\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Plenty\DataBase\Migrations\Migration;

class UseTextInExportTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @param \Illuminate\Database\Schema\Builder $schema
     */
    public function run(Builder $schema)
    {
        $schema->table('plugin_nespresso_f_t_p_order_export__export_stack', function (Blueprint $table) {
            $table->dropColumn('exportedData');
        });
        $schema->table('plugin_nespresso_f_t_p_order_export__export_stack', function (Blueprint $table) {
            $table->text('exportedData')->default('');
        });
    }

    protected function rollback(Builder $schema)
    {
    }
}
