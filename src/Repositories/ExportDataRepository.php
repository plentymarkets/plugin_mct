<?php

namespace MCT\Repositories;

use MCT\Contracts\ExportDataRepositoryContract;
use MCT\Models\HistoryData;
use MCT\Models\TableRow;
use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;

class ExportDataRepository implements ExportDataRepositoryContract
{
    /**
     * @var DataBase
     */
    private $database;

    /**
     * @param DataBase $database
     */
    public function __construct(DataBase $database)
    {
        $this->database = $database;
    }

    /**
     * @param array $data
     * @return \Plenty\Modules\Plugin\DataBase\Contracts\Model
     */
    public function save(array $data)
    {
        $tableRow = pluginApp(TableRow::class);
        $tableRow->plentyOrderId    = (int)$data['plentyOrderId'];
        $tableRow->exportedData     = (string)$data['exportedData'];
        $tableRow->savedAt          = (string)$data['savedAt'];
        $tableRow->sentAt           = (string)$data['sentAt'];

        return $this->database->save($tableRow);
    }

    /**
     * @param $plentyOrderId
     * @return array|\Plenty\Modules\Plugin\DataBase\Contracts\Model[]|null
     */
    public function get($plentyOrderId)
    {
        $tableRow = $this->database->query(TableRow::class)
            ->where('plentyOrderId', '=', $plentyOrderId)
            ->get();

        return is_array($tableRow) ? $tableRow : null;
    }

    /**
     * @return TableRow[]
     */
    public function listUnsent()
    {
        return $this->database->query(TableRow::class)
            ->where('sentAt', '=', '')
            ->get();
    }

    /**
     * @param int $plentyOrderId
     * @return bool
     */
    public function orderExists(int $plentyOrderId) : bool
    {
        $results = $this->database->query(TableRow::class)
            ->where('plentyOrderId', '=', $plentyOrderId)
            ->limit(1)
            ->get();
        if (count($results) > 0){
            return true;
        }
        return false;
    }

    /**
     * @param string $dateLimit
     * @return void
     */
    public function deleteOldRecords(string $dateLimit) : void
    {
        $this->database->query(TableRow::class)
            ->where('sentAt', '!=', '')
            ->where('sentAt', '<', $dateLimit)
            ->delete();
        $this->database->query(HistoryData::class)
            ->where('sentAt', '!=', '')
            ->where('sentAt', '<', $dateLimit)
            ->delete();
    }

    /**
     * @param string $plentyOrderId
     * @return void
     */
    public function deleteOneRecord(string $plentyOrderId) : void
    {
        $this->database->query(TableRow::class)
            ->where('plentyOrderId', '=', $plentyOrderId)
            ->delete();
    }

    /**
     * @param string $dateLimit
     * @return void
     */
    public function deleteAllRecords() : void
    {
        $this->database->query(TableRow::class)
            ->delete();
    }
}