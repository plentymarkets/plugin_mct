<?php

namespace MCT\Services;

use Carbon\Carbon;
use MCT\Clients\ClientForSFTP;
use MCT\Configuration\PluginConfiguration;
use MCT\Helpers\MappingHelper;
use MCT\Helpers\OrderHelper;
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
     * @var OrderRepositoryContract
     */
   private $orderRepository;

    /**
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     * @var MappingHelper
     */
    private $mappingHelper;

    /**
     * @param ClientForSFTP $ftpClient
     * @param PluginConfiguration $configRepository
     * @param OrderRepositoryContract $orderRepository
     * @param OrderHelper $orderHelper
     * @param MappingHelper $mappingHelper
     */
    public function __construct(
        ClientForSFTP           $ftpClient,
        PluginConfiguration     $configRepository,
        OrderRepositoryContract $orderRepository,
        OrderHelper             $orderHelper,
        MappingHelper           $mappingHelper
    )
    {
        $this->ftpClient            = $ftpClient;
        $this->configRepository     = $configRepository;
        $this->orderRepository      = $orderRepository;
        $this->orderHelper          = $orderHelper;
        $this->mappingHelper        = $mappingHelper;
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
            'SNDPRN'    => $this->orderHelper->getValueBasedOnMarketplace($order->referrerId),
            'RCVPOR'    => 'SAPPW1'
        ];

        $record['E2EDK01005'] = [
            'CURCY'     => $order->amount->currency,
            'KUNDEUINR' => '',
            'AUGRU'     => $this->orderHelper->getValueBasedOnShippingProfile($order->shippingProfileId),
            'LIFSK'     => 'Y1'
        ];

        $record['E2EDK14'] = [];
        $record['E2EDK14'][] = [
            'QUALF'     => '006',
            'ORGID'     => '00'
        ];
        $record['E2EDK14'][] = [
            'QUALF'     => '007',
            'ORGID'     => $this->orderHelper->getValueForQualf007($order->referrerId)
        ];
        $record['E2EDK14'][] = [
            'QUALF'     => '008',
            'ORGID'     => '1200'
        ];
        $record['E2EDK14'][] = [
            'QUALF'     => '010',
            'ORGID'     => 'AFT'
        ];
        $record['E2EDK14'][] = [
            'QUALF'     => '012',
            'ORGID'     => $this->orderHelper->getValueForQualf012($order->referrerId)
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
            'DATUM'     => $order->dates->filter(
                function ($date) {
                    return $date->typeId == OrderDateType::ORDER_ENTRY_AT;
                }
            )->first()->date->isoFormat("YYYYMMDD")
        ];
        $record['E2EDK03'][] = [
            'IDDAT'     => '106',
            'DATUM'     => $order->dates->filter(
                function ($date) {
                    return $date->typeId == OrderDateType::ORDER_ENTRY_AT;
                }
            )->first()->date->isoFormat("YYYYMMDD")
        ];
        $record['E2EDK03'][] = [
            'IDDAT'     => '012',
            'DATUM'     => $order->dates->filter(
                function ($date) {
                    return $date->typeId == OrderDateType::ORDER_ENTRY_AT;
                }
            )->first()->date->isoFormat("YYYYMMDD")
        ];

        $record['E2EDK05001'] = [
            'KSCHL'     => 'YF10',
            'KOTXT'     => 'Versandkosten',
            'BETRG'     => $this->orderHelper->getShippingCosts($order)
        ];

        $record['E2EDKA1003GRP'] = [];

        $record['E2EDKA1003GRP'][] = [
            'E2EDKA1003'    => [
                'PARVW' => 'AG',
                'PARTN' => $this->orderHelper->getValueBasedOnMarketplace($order->referrerId),
            ]
        ];

        if ((strtoupper($order->deliveryAddress->country->isoCode2) == 'SK') &&
            ((strlen($order->deliveryAddress->postalCode) < 4) || ($order->deliveryAddress->postalCode[3] != ' '))) {
            switch($order->deliveryAddress->postalCode[3]){
                case '-':
                case '_':
                $order->deliveryAddress->postalCode[3] = ' ';
                    break;
                default:
                    $order->deliveryAddress->postalCode = substr($order->deliveryAddress->postalCode, 0, 4)
                        . ' '
                        . substr($order->deliveryAddress->postalCode, 4);
                    break;
            }
        }
        $record['E2EDKA1003GRP'][] = [
            'E2EDKA1003'    => [
                'PARVW' => 'WE',
                'PARTN' => $this->orderHelper->getValueBasedOnMarketplace($order->referrerId),
                'NAME1' => substr($order->deliveryAddress->name2 . ' ' . $order->deliveryAddress->name3, 0, 35),
                'NAME2' => substr($order->deliveryAddress->name1, 0, 35),
                'STRAS' => substr($order->deliveryAddress->address1 . ' ' . $order->deliveryAddress->address2, 0, 35),
                'ORT01' => substr($order->deliveryAddress->town, 0, 35),
                'PSTLZ' => $order->deliveryAddress->postalCode,
                'LAND1' => $order->deliveryAddress->country->isoCode2,
                'TELF1' => $order->deliveryAddress->phone
            ]
        ];

        $record['E2EDKA1003GRP'][] = [
            'E2EDKA1003'    => [
                'PARVW' => 'RG',
                'PARTN' => $this->orderHelper->getValueBasedOnMarketplace($order->referrerId),
            ]
        ];

        if ((strtoupper($order->billingAddress->country->isoCode2) == 'SK') &&
            ((strlen($order->billingAddress->postalCode) < 4) || ($order->billingAddress->postalCode != ' '))) {
            //error - needs to be clarified
        }
        $record['E2EDKA1003GRP'][] = [
            'E2EDKA1003'    => [
                'PARVW' => 'RE',
                'PARTN' => $this->orderHelper->getValueBasedOnMarketplace($order->referrerId),
                'NAME1' => substr($order->billingAddress->name2 . ' ' . $order->billingAddress->name3, 0, 35),
                'NAME2' => substr($order->billingAddress->name1, 0, 35),
                'STRAS' => substr($order->billingAddress->address1 . ' ' . $order->billingAddress->address2, 0, 35),
                'ORT01' => substr($order->billingAddress->town, 0, 35),
                'PSTLZ' => $order->billingAddress->postalCode,
                'LAND1' => $order->billingAddress->country->isoCode2,
                'TELF1' => ''
            ]
        ];

        $record['E2EDK02'] = [];
        $record['E2EDK02'][] = [
            'QUALF'     => '001',
            'BELNR'     => substr($order->getPropertyValue(OrderPropertyType::EXTERNAL_ORDER_ID), 0, 18),
            'DATUM'     => $order->dates->filter(
                function ($date) {
                    return $date->typeId == OrderDateType::ORDER_ENTRY_AT;
                }
            )->first()->date->isoFormat("YYYYMMDD")
        ];
        $record['E2EDK02'][] = [
            'QUALF'     => '017',
            'BELNR'     => substr(substr($order->getPropertyValue(OrderPropertyType::EXTERNAL_ORDER_ID), 4), 0, 18),
            'DATUM'     => $order->dates->filter(
                function ($date) {
                    return $date->typeId == OrderDateType::ORDER_ENTRY_AT;
                }
            )->first()->date->isoFormat("YYYYMMDD")
        ];
        $record['E2EDK02'][] = [
            'QUALF'     => '011',
            'BELNR'     => $order->id,
            'DATUM'     => $order->dates->filter(
                function ($date) {
                    return $date->typeId == OrderDateType::ORDER_ENTRY_AT;
                }
            )->first()->date->isoFormat("YYYYMMDD")
        ];

        $record['E2EDKT1002GRP'][] = [
            'E2EDKT1002'    => [
                'TDID'          => 'FIS1',
                'TSSPRAS'       => 'D',
                'TSSPRAS_ISO'   => $order->billingAddress->country->isoCode2,
            ],
            'E2EDKT2001'    => [
                'TDLINE'        => $this->orderHelper->getTdline($order)
            ]
        ];

        //order records
        $counterTen = 10;
        /** @var OrderItem $orderItem */
        foreach ($order->orderItems as $orderItem) {
            if (
                ($orderItem->typeId === OrderItemType::TYPE_VARIATION) ||
                ($orderItem->typeId === OrderItemType::TYPE_PROMOTIONAL_COUPON)
            )
            {
                $record['E2EDP01011GRP']['E2EDP0101']['POSEX'] = $counterTen;
                $record['E2EDP01011GRP']['E2EDP0101']['MENGE'] = $orderItem->quantity;
                $record['E2EDP01011GRP']['E2EDP0101']['PREIS'] = $orderItem->getAmountAttribute()->priceOriginalGross;
                $record['E2EDP01011GRP']['E2EDP19003']['QUALF'] = '002';
                if ($orderItem->typeId === OrderItemType::TYPE_VARIATION) {
                    $record['E2EDP01011GRP']['E2EDP19003']['IDTNR'] = $orderItem->variation->number;
                }
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
                    $str .= $this->arrayToXml($v) . "";
                    if (next($array) !== false){
                        $str .= "</$parentTag>\n<$parentTag>\n";
                    }
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
     * @param TableRow $order
     * @return string
     */
    public function generateXMLFromOrderData(TableRow $order): string
    {
        $resultedXML = '<?xml version="1.0"?>
<Send>
';

        $orderData = json_decode($order->exportedData, true);
        $resultedXML .= $this->arrayToXml(['idocData' => $orderData]);
        $resultedXML .= "\n</Send>";

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
        $fileName = $filePrefix . '_'.$batchNo.'.xml';
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
     * @param TableRow $order
     * @param $generationTime
     * @return void
     */
    public function markRowsAsSent(TableRow $order, $generationTime): void
    {
        /** @var ExportDataRepository $exportDataRepository */
        $exportDataRepository = pluginApp(ExportDataRepository::class);

        try {
            $exportData = [
                'plentyOrderId'    => $order->plentyOrderId,
                'exportedData'     => $order->exportedData,
                'savedAt'          => $order->savedAt,
                'sentAt'           => $generationTime,
            ];
            $exportDataRepository->save($exportData);
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
            $exportList = $exportDataRepository->listUnsent();
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

        /** @var TableRow $order */
        foreach ($exportList as $order) {
            $thisTime = Carbon::now();
            $generationTime = $thisTime->toDateTimeString();
            $xmlContent = $this->generateXMLFromOrderData($order);
            if (!$this->sendToFTP(
                $xmlContent,
                $thisTime->isoFormat("YYYYMMDD") . '_' . $thisTime->isoFormat("HHmmss"),
                $order->plentyOrderId
            )) {
                return false;
            }
            $this->markRowsAsSent($order, $generationTime);
        }

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