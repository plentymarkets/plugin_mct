<?php

namespace MCT\Migrations;

use Plenty\Modules\Plugin\DataBase\Contracts\Migrate;
use Plenty\Modules\Plugin\Exceptions\MySQLMigrateException;
use MCT\Models\TableRow;

class CreateMCTExportTable
{
    /**
     * @param  Migrate  $migrate
     * @throws MySQLMigrateException
     */
    public function run(Migrate $migrate)
    {
        $migrate->createTable(TableRow::class);
    }

    protected function rollback(Migrate $migrate)
    {
        $migrate->deleteTable(TableRow::class);
    }
}
