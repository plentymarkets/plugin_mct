<?php

namespace MCT\Contracts;

use MCT\Models\TableRow;
use Plenty\Modules\Plugin\Database\Contracts\Model;

interface ExportDataRepositoryContract
{
    public function save(array $data);

    public function get($plentyOrderId);

    public function listUnsent();

    public function orderExists(int $plentyOrderId) : bool;

    public function deleteOldRecords(string $dateLimit) : void;

    public function deleteOneRecord(string $plentyOrderId) : void;
}
