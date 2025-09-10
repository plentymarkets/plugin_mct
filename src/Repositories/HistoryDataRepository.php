<?php

namespace MCT\Repositories;

use MCT\Contracts\HistoryDataRepositoryContract;
use MCT\Models\HistoryData;
use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;

class HistoryDataRepository implements HistoryDataRepositoryContract
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
        $dataHist = pluginApp(HistoryData::class);
        $dataHist->plentyOrderId    = (int)$data['plentyOrderId'];
        $dataHist->message          = (string)$data['message'];
        $dataHist->savedAt          = (string)$data['savedAt'];

        return $this->database->save($dataHist);
    }

    /**
     * @param string $dateLimit
     * @return void
     */
    public function deleteOldRecords(string $dateLimit) : void
    {
        $this->database->query(HistoryData::class)
            ->where('sentAt', '!=', '')
            ->where('sentAt', '<', $dateLimit)
            ->delete();
    }
}