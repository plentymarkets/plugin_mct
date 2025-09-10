<?php

namespace MCT\Contracts;

use MCT\Models\HistoryData;
use Plenty\Modules\Plugin\Database\Contracts\Model;

interface HistoryDataRepositoryContract
{
    public function save(array $data);


    public function deleteOldRecords(string $dateLimit) : void;
}
