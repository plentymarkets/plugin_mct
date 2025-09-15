<?php

namespace MCT\Helpers;

use Carbon\Carbon;
use MCT\Repositories\HistoryDataRepository;
use Plenty\Modules\Order\Date\Models\OrderDateType;

class MappingHelper
{
    /**
     * @var HistoryDataRepository
     */
    private $historyData;

    public function __construct(
        HistoryDataRepository $historyData
    )
    {
        $this->historyData = $historyData;
    }

    public function getMarketplaceValueMapping()
    {
        return [
            150.00 => '5028142',
            102.01 => '5028259',
            102.03 => '5029078',
            102.05 => '5029772',
            160.10 => '5028143',
            2.08   => '1999971',
            4.00   => '5024143',
            4.01   => '5024143',
            4.02   => '5024143',
            4.03   => '5024143',
            4.04   => '5024143',
            4.05   => '5024143',
            4.06   => '5024143',
            4.07   => '5024143',
            4.08   => '5024143',
            4.09   => '5024143',
            4.11   => '5024143',
            4.12   => '5024143',
            4.13   => '5024143',
            4.14   => '5024143',
            4.15   => '5024143',
            4.16   => '5024143',
            4.21   => '5024143',
            4.22   => '5024143',
            4.24   => '5024143',
            4.25   => '5024143',
            4.26   => '5024143',
            143.00 => '5025855',
            149.00 => '5015255',
            9.00   => '5029170',
            10.00  => '5030019',
            12.00  => '5028223',
            13.00  => '5026997',
            14.00  => '5029208',
            15.00  => '5029209',
            176.00 => '5015185',
            154.00 => '5014263',
            171.00 => '5014263'
        ];
    }

    public function getShippingValueMapping()
    {
        return [
            10 => 'PRI',
            11 => 'FBA',
            12 => 'ASF',
            14 => '005'
        ];
    }

    public function getQualf7ValueMapping()
    {
        return [
            9.00 => '50'
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

    /**
     * @param string $marketplace
     * @param string $deliveryCountry
     * @param string $billingTaxId
     * @return bool
     */
    public function useNetPrice(string $marketplace, string $deliveryCountry, string $billingTaxId)
    {
        if ($this->isB2Bclient($marketplace) && (strtolower($deliveryCountry) != 'de') && ($billingTaxId != '')){
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

    public function addHistoryData($message, $plentyOrderId = -1)
    {
        $data = [
            'plentyOrderId' => $plentyOrderId,
            'message'       => $message,
            'savedAt'       => Carbon::now()->toDateTimeString()
        ];

        $this->historyData->save($data);
    }
}