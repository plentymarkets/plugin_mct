<?php

namespace MCT\Services;

use Carbon\Carbon;
use MCT\Clients\ClientForSFTP;
use MCT\Configuration\PluginConfiguration;
use MCT\Models\TableRow;
use MCT\Repositories\ExportDataRepository;
use MCT\Repositories\SettingRepository;
use Plenty\Modules\Account\Address\Models\AddressOption;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Date\Models\OrderDateType;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Models\OrderItem;
use Plenty\Modules\Order\Models\OrderItemType;
use Plenty\Modules\Order\Property\Models\OrderPropertyType;
use Plenty\Modules\Order\Referrer\Contracts\OrderReferrerRepositoryContract;
use Plenty\Modules\Order\RelationReference\Models\OrderRelationReference;
use Plenty\Plugin\Log\Loggable;

class OrderExportService
{
    use Loggable;

    /**
     * @var ClientForSFTP
     */
    private $ftpClient;

    /**
     * @var PluginConfiguration
     */
    private $configRepository;

    /**
     * @var int
     */
    private $totalOrdersPerBatch;

    /**
     * @var OrderRepositoryContract
     */
   private $orderRepository;

    /**
     * @var array
     */
   private $marketplaceValueMapping;

    /**
     * @var array
     */
    private $shippingValueMapping;

    /**
     * @var array
     */
    private $qualf7ValueMapping;

    /**
     * @var array
     */
    private $qualf12ValueMapping;

    /**
     * @param ClientForSFTP $ftpClient
     */
    public function __construct(
        ClientForSFTP           $ftpClient,
        PluginConfiguration     $configRepository,
        OrderRepositoryContract $orderRepository
    )
    {
        $this->ftpClient            = $ftpClient;
        $this->configRepository     = $configRepository;
        $this->totalOrdersPerBatch  = $this->configRepository->getTotalOrdersPerBatch();
        $this->orderRepository      = $orderRepository;
        $this->marketplaceValueMapping = $this->getMarketplaceValueMapping();
        $this->shippingValueMapping = $this->getShippingValueMapping();
        $this->qualf7ValueMapping   = $this->getQualf7ValueMapping();
        $this->qualf12ValueMapping  = $this->getQualf12ValueMapping();
    }

    private function getMarketplaceValueMapping()
    {
        return [
            '150.00' => '5028142',
            '102.01' => '5028259',
            '143.00' => '5025855'
        ];
    }

    private function getShippingValueMapping()
    {
        return [
            '10' => 'PRI',
            '11' => 'FBA',
            '12' => 'ASF',
            '14' => '5'
        ];
    }

    private function getQualf7ValueMapping()
    {
        return [
            '9.00' => '50',

        ];
    }

    private function getQualf12ValueMapping()
    {
        return [

        ];
    }

    private function getValueBasedOnShippingProfile(float $shippingProfileId)
    {
        if (isset($this->shippingValueMapping[$shippingProfileId])) {
            return $this->shippingValueMapping[$shippingProfileId];
        }
        return 'AMP';
    }

    private function getValueForQualf007(float $referrerId)
    {
        if (isset($this->qualf7ValueMapping[$referrerId])) {
            return $this->qualf7ValueMapping[$referrerId];
        }
        return '21';
    }

    private function getValueForQualf012(float $referrerId)
    {
        if (isset($this->qualf12ValueMapping[$referrerId])) {
            return $this->qualf12ValueMapping[$referrerId];
        }
        return 'TA';
    }

    private function getValueBasedOnMarketplace(float $referrerId)
    {
        if (isset($this->marketplaceValueMapping[$referrerId])) {
            return '000' . $this->marketplaceValueMapping[$referrerId];
        }
        return '';
        /*
        /** @var OrderReferrerRepositoryContract $orderReferrerRepo */
        /*$orderReferrerRepo = pluginApp(OrderReferrerRepositoryContract::class);

        $referrers = $orderReferrerRepo->getList();
        if(!empty($referrers))
        {
            foreach($referrers as $referrer)
            {
                if($referrerId === $referrer->referrer_id)
                {
                }
            }
        }
        */
    }

    /**
     * @param Order $order
     * @return void
     */
    public function processOrder(Order $order)
    {
        $record = [];

        $record['EDI_DC40'] = [
            'IDOCTYP'   => 'ORDERS05',
            'MESTYP'    => 'ORDERS',
            'MESCOD'    => 'AFT',
            'SNDPOR'    => 'BIZP_TRFC',
            'SNDPRT'    => 'KU',
            'SNDPRN'    => $this->getValueBasedOnMarketplace($order->referrerId),
            'RCVPOR'    => 'SAPPW1'
        ];

        $record['E2EDK01005'] = [
            'CURCY'     => $order->amount->currency,
            'AUGRU'     => $this->getValueBasedOnShippingProfile($order->shippingProfileId),
            'LIFSK'     => 'Y1'
        ];

        $record['E2EDK14'] = [];
        $record['E2EDK14'][] = [
            'QUALF'     => '008',
            'ORGID'     => '1200'
        ];
        $record['E2EDK14'][] = [
            'QUALF'     => '007',
            'ORGID'     => $this->getValueForQualf007($order->referrerId)
        ];
        $record['E2EDK14'][] = [
            'QUALF'     => '006',
            'ORGID'     => '00'
        ];
        $record['E2EDK14'][] = [
            'QUALF'     => '012',
            'ORGID'     => $this->getValueForQualf012($order->referrerId)
        ];
        $record['E2EDK14'][] = [
            'QUALF'     => '013',
            'ORGID'     => 'EDI'
        ];
        $record['E2EDK14'][] = [
            'QUALF'     => '016',
            'ORGID'     => '1200'
        ];

        $record['E2EDK03'] = [];
        $record['E2EDK03'][] = [
            'IDDAT'     => '105',
            'DATUM'     => ''
        ];
        $record['E2EDK03'][] = [
            'IDDAT'     => '106',
            'DATUM'     => ''
        ];
        $record['E2EDK03'][] = [
            'IDDAT'     => '012',
            'DATUM'     => ''
        ];

        $record['E2EDK05001'] = [
            'KSCHL'     => 'YF10',
            'KOTXT'     => 'Versandkosten',
            'BETRG'     => $order->shippingInformation->shippingCosts
        ];

        $record['E2EDKA1003GRP'] = [];

        $record['E2EDKA1003GRP'][] = [
            'E2EDKA1003'    => [
                'PARVW' => 'AG',
                'PARTN' => $this->getValueBasedOnMarketplace($order->referrerId),
            ]
        ];

        $record['E2EDKA1003GRP'][] = [
            'E2EDKA1003'    => [
                'PARVW' => 'WE',
                'PARTN' => $this->getValueBasedOnMarketplace($order->referrerId),
                'NAME1' => $order->deliveryAddress->name2 . ' ' . $order->deliveryAddress->name3,
                'NAME2' => $order->deliveryAddress->name1,
                'STRAS' => $order->deliveryAddress->address1 . ' ' . $order->deliveryAddress->address2,
                'ORT01' => $order->deliveryAddress->town,
                'PSTLZ' => $order->deliveryAddress->postalCode,
                'LAND1' => $order->deliveryAddress->country->isoCode2,
                'TELF1' => $order->deliveryAddress->phone
            ]
        ];

        $record['E2EDKA1003GRP'][] = [
            'E2EDKA1003'    => [
                'PARVW' => 'RG',
                'PARTN' => $this->getValueBasedOnMarketplace($order->referrerId),
            ]
        ];

        $record['E2EDKA1003GRP'][] = [
            'E2EDKA1003'    => [
                'PARVW' => 'RE',
                'PARTN' => $this->getValueBasedOnMarketplace($order->referrerId),
                'NAME1' => $order->billingAddress->name2 . ' ' . $order->billingAddress->name3,
                'NAME2' => $order->billingAddress->name1,
                'STRAS' => $order->billingAddress->address1 . ' ' . $order->billingAddress->address2,
                'ORT01' => $order->billingAddress->town,
                'PSTLZ' => $order->billingAddress->postalCode,
                'LAND1' => $order->billingAddress->country->isoCode2,
                'TELF1' => ''
            ]
        ];

        $record['E2EDK02'] = [
            'QUALF'     => '017',
            'BELNR'     => $order->getPropertyValue(OrderPropertyType::EXTERNAL_ORDER_ID),
            'DATUM'     => $order->dates->filter(
                                function ($date) {
                                    return $date->typeId == OrderDateType::ORDER_ENTRY_AT;
                                }
                            )->first()->date->isoFormat("DD/MM/YYYY")
        ];

        $record['E2EDKT1002GRP'][] = [
            'E2EDKT100'    => [
                'TDID'          => 'FIS1',
                'TSSPRAS'       => 'D',
                'TSSPRAS_ISO'   => $order->billingAddress->country->isoCode2,
                'TDLINE'        => '' //is only populated for Amazon Business buyers - when buyer has a VAT ID
            ]
        ];

        //order records
        $counterTen = 10;
        /** @var OrderItem $orderItem */
        foreach ($order->orderItems as $orderItem) {
            if ($orderItem->typeId === OrderItemType::TYPE_VARIATION) {
                $record['E2EDP01011GRP']['E2EDP0101']['POSEX'] = $counterTen;
                $record['E2EDP01011GRP']['E2EDP0101']['MENGE'] = $orderItem->quantity;
                $record['E2EDP01011GRP']['E2EDP0101']['PREIS'] = $orderItem->getAmountAttribute()->priceOriginalGross; //Order item amounts: Price gross
                $record['E2EDP01011GRP']['E2EDP19003']['QUALF'] = '002';
                $record['E2EDP01011GRP']['E2EDP19003']['IDTNR'] = $orderItem->variation->number;
                $counterTen += 10;
            }
        }

        $this->saveRecord($order->id, $record);
    }

    /**
     * @param Order $order
     * @return null
     */
    public function getCustomerId(Order $order)
    {
        $relation = $order->relations
            ->where('referenceType', OrderRelationReference::REFERENCE_TYPE_CONTACT)
            ->where('relation', OrderRelationReference::RELATION_TYPE_RECEIVER)
            ->first();

        if ($relation !== null) {
            return $relation->referenceId;
        }

        return -1;
    }

    /**
     * @param int $plentyOrderId
     * @param array $record
     * @return bool
     */
    public function saveRecord(int $plentyOrderId, array $record){

        $exportData = [
            'plentyOrderId'    => $plentyOrderId,
            'exportedData'     => json_encode($record),
            'savedAt'          => Carbon::now()->toDateTimeString(),
            'sentdAt'          => '',
        ];

        /** @var ExportDataRepository $exportDataRepository */
        $exportDataRepository = pluginApp(ExportDataRepository::class);
        try {
            if (!$exportDataRepository->orderExists($plentyOrderId)) {
                /** @var TableRow $savedObject */
                $exportDataRepository->save($exportData);

                $statusOfProcessedOrder = $this->configRepository->getProcessedOrderStatus();
                if ($statusOfProcessedOrder != ''){
                    $this->orderRepository->updateOrder(['statusId' => $statusOfProcessedOrder], $plentyOrderId);
                }
                return true;
            }
            $this->getLogger(__METHOD__)
                ->addReference('orderId', $plentyOrderId)
                ->report(PluginConfiguration::PLUGIN_NAME . '::error.orderExists', $exportData);
            return false;
        } catch (\Throwable $e) {
            $this->getLogger(__METHOD__)
                ->addReference('orderId', $plentyOrderId)
                ->error(PluginConfiguration::PLUGIN_NAME . '::error.saveExportError',
                [
                    'message'     => $e->getMessage(),
                    'exportData'  => $exportData
                ]);
        }
        return false;
    }

    /**
     * @return string
     */
    public function getBatchNumber(): string
    {
        $settingsRepository = pluginApp(SettingRepository::class);
        return $settingsRepository->getBatchNumber();
    }

    public function escapeValue($value)
    {
        $escaped = str_replace('&', '&amp;', $value);
        $escaped = str_replace('<', '&lt;', $escaped);
        $escaped = str_replace('>', '&gt;', $escaped);
        $escaped = str_replace('"', '&quot;', $escaped);
        $escaped = str_replace("'", '&apos;', $escaped);

        return $escaped;
    }

    /**
     * @param $array
     * @return string
     */
    public function arrayToXml(array $array, string $parentTag = ''): string
    {
        if (($array === null) || (count($array) == 0)){
            return '';
        }

        $str = '';

        foreach ($array as $k => $v) {
            if (is_array($v)) {
                if (count($v) == 0){
                    $str .= "<$k />\n";
                    continue;
                }
                if (is_int($k)){
                    $str .= "<$parentTag>\n" . $this->arrayToXml($v) . "</$parentTag>\n";
                } else {
                    $str .= "<$k>\n" . $this->arrayToXml($v, $k) . "</$k>\n";
                }
            }
            else {
                if ((string)$v === ''){
                    $str .= "<$k />\n";
                } else {
                    $str .= "<$k>" . $this->escapeValue($v) . "</$k>\n";
                }
            }
        }
        return $str;
    }

    /**
     * @param TableRow[] $exportList
     * @param string $generationTime
     * @param string $batchNo
     * @return string
     */
    public function generateXMLFromOrderData($exportList, $generationTime, $batchNo): string
    {
        $resultedXML = '<?xml version="1.0"?>
<Send 
    xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
    xmlns="http://Microsoft.LobServices.Sap/2007/03/Idoc/3/ORDERS05//750/Send"
>
';

        /** @var TableRow $order */
        foreach ($exportList as $order){
            $orderData = json_decode($order->exportedData, true);
            $resultedXML .= $this->arrayToXml(['idocData' => $orderData]);
        }
        $resultedXML .= "\n</import_batch>";

        return $resultedXML;
    }

    /**
     * @param string $xmlContent
     * @param string $filePrefix
     * @param string $batchNo
     * @return bool
     */
    public function sendToFTP(string $xmlContent, string $filePrefix, string $batchNo)
    {
        $fileName = $filePrefix . '-32-'.$batchNo.'.xml';
        try {
            $this->getLogger(__METHOD__)->info(
                PluginConfiguration::PLUGIN_NAME . '::general.logMessage',
                [
                    'xmlContent' => $xmlContent,
                    'fileName'=> $fileName
                ]
            );
            $result = $this->ftpClient->uploadXML($fileName, $xmlContent);
            if (is_array($result) && array_key_exists('error', $result) && $result['error'] === true) {
                $this->getLogger(__METHOD__)
                    ->error(PluginConfiguration::PLUGIN_NAME . '::globals.ftpFileUploadError',
                        [
                            'errorMsg'          => $result['error_msg'],
                            'fileName'          => $fileName,
                        ]
                    );
                return false;
            }
        } catch (\Throwable $exception) {
            $this->getLogger(__METHOD__)->error(
                PluginConfiguration::PLUGIN_NAME . '::error.writeFtpError',
                [
                    'message' => $exception->getMessage(),
                    'fileName'=> $fileName
                ]
            );
            return false;
        }
        return true;
    }

    /**
     * @param TableRow[]$exportList
     * @param string $generationTime
     * @return void
     */
    public function markRowsAsSent($exportList, $generationTime): void
    {
        /** @var ExportDataRepository $exportDataRepository */
        $exportDataRepository = pluginApp(ExportDataRepository::class);

        try {
            /** @var TableRow $order */
            foreach ($exportList as $order){
                $exportData = [
                    'plentyOrderId'    => $order->plentyOrderId,
                    'exportedData'     => $order->exportedData,
                    'savedAt'          => $order->savedAt,
                    'sentAt'           => $generationTime,
                ];
                $exportDataRepository->save($exportData);
            }
        } catch (\Throwable $e) {
            $this->getLogger(__METHOD__)->error(
                PluginConfiguration::PLUGIN_NAME . '::error.updateMarkError',
                [
                    'message' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * @return bool
     */
    public function sendDataToClient(): bool
    {
        /** @var ExportDataRepository $exportDataRepository */
        $exportDataRepository = pluginApp(ExportDataRepository::class);
        try {
            $exportList = $exportDataRepository->listUnsent($this->totalOrdersPerBatch);
        } catch (\Throwable $e) {
            $this->getLogger(__METHOD__)->error(PluginConfiguration::PLUGIN_NAME . '::error.readExportError',
                [
                    'message'     => $e->getMessage(),
                ]);
            return false;
        }

        if (count($exportList) == 0){
            return false;
        }

        $settingsRepository = pluginApp(SettingRepository::class);

        $thisTime = Carbon::now();
        $generationTime = $thisTime->toDateTimeString();
        $batchNo = $this->getBatchNumber();
        $xmlContent = $this->generateXMLFromOrderData($exportList, $generationTime, $batchNo);
        if (!$this->sendToFTP(
            $xmlContent,
            $thisTime->isoFormat("DDMMYY") . '-' . $thisTime->isoFormat("HHmm"),
            $batchNo
        )){
            return false;
        }

        $settingsRepository->incrementBatchNumber();

        $this->markRowsAsSent($exportList, $generationTime);

        return true;
    }

    /**
     * @return void
     */
    public function clearExportTable(): void
    {
        /** @var ExportDataRepository $exportDataRepository */
        $exportDataRepository = pluginApp(ExportDataRepository::class);
        try {
            $exportDataRepository->deleteOldRecords(Carbon::now()->subDays(30)->toDateTimeString());
        } catch (\Throwable $e) {
            $this->getLogger(__METHOD__)->error(PluginConfiguration::PLUGIN_NAME . '::error.clearExportTableError',
                [
                    'message'     => $e->getMessage(),
                ]);
        }
    }

    /**
     * @param Order $order
     * @return string
     */
    public static function getOrderLanguage(Order $order)
    {
        $documentLanguage = $order->properties->where('typeId', OrderPropertyType::DOCUMENT_LANGUAGE)->first()->value;
        if(!empty($documentLanguage))
        {
            return strtolower($documentLanguage);
        }

        if ($order->contactReceiver->lang !== ''){
            return $order->contactReceiver->lang;
        }

        return 'de';
    }

}