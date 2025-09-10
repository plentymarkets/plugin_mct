<?php

namespace MCT\Migrations;

use Plenty\Modules\Plugin\DataBase\Contracts\Migrate;
use Plenty\Modules\Plugin\Exceptions\MySQLMigrateException;
use MCT\Models\HistoryData;

class CreateHistoryTable
{
    /**
     * @param  Migrate  $migrate
     * @throws MySQLMigrateException
     */
    public function run(Migrate $migrate)
    {
        $migrate->createTable(HistoryData::class);
    }

    protected function rollback(Migrate $migrate)
    {
        $migrate->deleteTable(HistoryData::class);
    }
}
