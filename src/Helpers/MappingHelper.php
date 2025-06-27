<?php

namespace MCT\Helpers;

use Carbon\Carbon;
use Plenty\Modules\Order\Date\Models\OrderDateType;

class MappingHelper
{
    public function getMarketplaceValueMapping()
    {
        return [
            '102.01' => '5028259',
            '160.10' => '5028143',
            '2.08'   => '1999971',
            '4.01'   => '5024143',
            '4.06'   => '5024143',
            '9.00'   => '5029170',
            '10.00'  => '5030019',
            '154.00' => '5014263',
            '171.00' => '5014263'
        ];
    }

    public function getShippingValueMapping()
    {
        return [
            '10' => 'PRI',
            '11' => 'FBA',
            '12' => 'ASF',
            '14' => '005'
        ];
    }

    public function getQualf7ValueMapping()
    {
        return [
            '9.00' => '50'
        ];
    }

    public function getQualf12ValueMapping()
    {
        return [];
    }

    public function isB2Bclient(string $marketplace){
        switch (ltrim($marketplace, '0')){
            case '5024143':
            case '5028223':
            case '5029208':
            case '5029209':
            case '5030019':
                return true;
        }
        return false;
    }

    public function getShippingToDate($order){
        /** @var Carbon $orderDate */
        $orderDate = $order->dates->filter(
            function ($date) {
                return $date->typeId == OrderDateType::ORDER_ENTRY_AT;
            }
        )->first()->date;
        if ($orderDate->isSaturday()){
            $orderDate->addDays(2);
        } elseif ($orderDate->isSunday()) {
            $orderDate->addDays();
        }
        return $orderDate->isoFormat("YYYYMMDD");
    }
}