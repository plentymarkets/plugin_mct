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
        $this->mappingHelper->addHistoryData('Start processing order ' . $order->id, $order->id);

        $record = [];

        $record['EDI_DC40'] = [
            'IDOCTYP'   => 'ORDERS05',
            'MESTYP'    => 'ORDERS',
            'MESCOD'    => 'AFT',
            'SNDPOR'    => 'BIZP_TRFC',
            'SNDPRT'    => 'KU',
            'SNDPRN'    => $this->orderHelper->getValueBasedOnMarketplace($order->referrerId, true),
            'RCVPOR'    => 'SAPPW1'
        ];

        $record['E2EDK01005'] = [
            'CURCY'     => $order->amount->currency,
            'KUNDEUINR' => $this->orderHelper->getTaxId($order),
            'AUGRU'     => $this->orderHelper->getValueBasedOnShippingProfile($order->shippingProfileId),
            'LIFSK'     => 'Y1'
        ];

        /*
        if ($order->id == 1230) {
            $record['TEST'] = [
                'referrerId' => $this->orderHelper->getValueBasedOnMarketplace($order->referrerId),
                'isoCode' => strtolower($order->deliveryAddress->country->isoCode2),
                'billingName1' => $order->billingAddress->name1,
                'taxNumber' => $order->billingAddress->taxIdNumber
            ];
        }
        */

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
            'DATUM'     => $this->mappingHelper->getShippingToDate($order)
        ];
        $record['E2EDK03'][] = [
            'IDDAT'     => '012',
            'DATUM'     => $order->dates->filter(
                function ($date) {
                    return $date->typeId == OrderDateType::ORDER_ENTRY_AT;
                }
            )->first()->date->isoFormat("YYYYMMDD")
        ];

        $shippingCosts = $this->orderHelper->getShippingCosts($order);
        if ($shippingCosts != 0.0) {
            $record['E2EDK05001'] = [
                'KSCHL' => 'YF10',
                'KOTXT' => 'Versandkosten',
                'BETRG' => $shippingCosts
            ];
        }

        $record['E2EDKA1003GRP'] = [];

        $record['E2EDKA1003GRP'][] = [
            'E2EDKA1003'    => [
                'PARVW' => 'AG',
                'PARTN' => $this->orderHelper->getValueBasedOnMarketplace($order->referrerId),
            ]
        ];

        $deliveryPostalCode = $order->deliveryAddress->postalCode;
        //check for wrong SK postal code
        if ((strtoupper($order->deliveryAddress->country->isoCode2) == 'SK') &&
            (!preg_match("/^\d{3} \d{2}$/", $order->deliveryAddress->postalCode))){

            //try to fix if missing SPACE between parts or using '-' or '_' instead of SPACE
            if (preg_match('/^\d{5}$/', $order->deliveryAddress->postalCode) ||
                preg_match("/^\d{3}[-_]\d{2}$/", $order->deliveryAddress->postalCode)) {
                $deliveryPostalCode = substr($order->deliveryAddress->postalCode, 0, 3)
                    . ' '
                    . substr($order->deliveryAddress->postalCode, -2);
            } else {
                $this->getLogger(__METHOD__)
                    ->addReference('orderId', $order->id)
                    ->error(PluginConfiguration::PLUGIN_NAME . '::error.skPostalCode', [
                        'message'       => 'The SK postal code (delivery) has a wrong format',
                        'orderId'       => $order->id
                    ]);
                $statusOfFaultyOrder = $this->configRepository->getFaultyOrderStatus();
                if ($statusOfFaultyOrder != ''){
                    $this->orderRepository->updateOrder(['statusId' => $statusOfFaultyOrder], $order->id);
                    return;
                }
                return;
            }
        }

        $mctDeliveryName1 = '';
        $mctDeliveryName2 = '';

        //special cases for name1 and name2, based on later specs
        if ($order->deliveryAddress->name1 !== '') {
            if (($order->deliveryAddress->name2 === '') && ($order->deliveryAddress->name3 === '')) {
                $mctDeliveryName1 = $order->deliveryAddress->name1;
                if (strlen($mctDeliveryName1) > 35) {
                    $mctDeliveryName2 = substr($mctDeliveryName1, 36);
                    $mctDeliveryName1 = substr($mctDeliveryName1, 0, 35);
                }
            } else {
                $mctDeliveryName1 = $order->deliveryAddress->name2 . ' ' . $order->deliveryAddress->name3;
                $mctDeliveryName2 = $order->deliveryAddress->name1;
                if (strlen($mctDeliveryName1) > 35) {
                    $mctDeliveryName2 = substr($mctDeliveryName1, 36) . ' ' . $mctDeliveryName2;
                    $mctDeliveryName1 = substr($mctDeliveryName1, 0, 35);
                }
            }
        } else {
            if (($order->deliveryAddress->name2 === '') && ($order->deliveryAddress->name3 === '')) {
                $this->getLogger(__METHOD__)
                    ->addReference('orderId', $order->id)
                    ->error(PluginConfiguration::PLUGIN_NAME . '::error.emptyNames', [
                        'message'           => 'Delivery name fields are empty',
                        'orderId'           => $order->id,
                        'mctDeliveryName1'  => $mctDeliveryName1,
                        'mctDeliveryName2'  => $mctDeliveryName2,
                        'order-name1'       => $order->deliveryAddress->name1,
                        'order-name2'       => $order->deliveryAddress->name2,
                        'order-name3'       => $order->deliveryAddress->name3,
                        'order-name4'       => $order->deliveryAddress->name4
                    ]);
                $statusOfFaultyOrder = $this->configRepository->getFaultyOrderStatus();
                if ($statusOfFaultyOrder != ''){
                    $this->orderRepository->updateOrder(['statusId' => $statusOfFaultyOrder], $order->id);
                    return;
                }
            } else {
                $mctDeliveryName1 = $order->deliveryAddress->name2 . ' ' . $order->deliveryAddress->name3;
                if (strlen($mctDeliveryName1) > 35) {
                    $mctDeliveryName2 = substr($mctDeliveryName1, 36);
                    $mctDeliveryName1 = substr($mctDeliveryName1, 0, 35);
                }
            }
        }

        if ($this->orderHelper->orderHasSpeditionShippingProfile($order)) {
            $mctDeliveryName2 .= ' ' . $order->deliveryAddress->phone;
        }

        $record['E2EDKA1003GRP'][] = [
            'E2EDKA1003'    => [
                'PARVW' => 'WE',
                'PARTN' => $this->orderHelper->getValueBasedOnMarketplace($order->referrerId),
                'NAME1' => substr($mctDeliveryName1, 0, 35),
                'NAME2' => substr($mctDeliveryName2, 0, 35),
                'STRAS' => substr($order->deliveryAddress->address1 . ' ' . $order->deliveryAddress->address2, 0, 35),
                'ORT01' => substr($order->deliveryAddress->town, 0, 35),
                'PSTLZ' => $deliveryPostalCode,
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

        $invoicePostalCode = $order->billingAddress->postalCode;
        //check for wrong SK postal code
        if ((strtoupper($order->billingAddress->country->isoCode2) == 'SK') &&
            (!preg_match("/^\d{3} \d{2}$/", $order->billingAddress->postalCode))){

            //try to fix if missing SPACE between parts or using '-' or '_' instead of SPACE
            if (preg_match('/^\d{5}$/', $order->billingAddress->postalCode) ||
                preg_match("/^\d{3}[-_]\d{2}$/", $order->billingAddress->postalCode)) {
                $invoicePostalCode = substr($order->billingAddress->postalCode, 0, 3)
                    . ' '
                    . substr($order->billingAddress->postalCode, -2);
            } else {
                $this->getLogger(__METHOD__)
                    ->addReference('orderId', $order->id)
                    ->error(PluginConfiguration::PLUGIN_NAME . '::error.skPostalCode', [
                        'message'       => 'The SK postal code (invoice) has a wrong format',
                        'orderId'       => $order->id
                    ]);
                $statusOfFaultyOrder = $this->configRepository->getFaultyOrderStatus();
                if ($statusOfFaultyOrder != ''){
                    $this->orderRepository->updateOrder(['statusId' => $statusOfFaultyOrder], $order->id);
                    return;
                }
                return;
            }
        }

        $mctBillingName1 = '';
        $mctBillingName2 = '';

        //special cases for name1 and name2, based on later specs
        if ($order->billingAddress->name1 !== ''){
            if (($order->billingAddress->name2 === '') && ($order->billingAddress->name3 === '')) {
                $mctBillingName1 = $order->billingAddress->name1;
                if (strlen($mctBillingName1) > 35){
                    $mctBillingName2 = substr($mctBillingName1, 36);
                    $mctBillingName1 = substr($mctBillingName1, 0, 35);
                }
            } else {
                $mctBillingName1 = $order->billingAddress->name2 . ' ' . $order->billingAddress->name3;
                $mctBillingName2 = $order->billingAddress->name1;
                if (strlen($mctBillingName1) > 35){
                    $mctBillingName2 = substr($mctBillingName1, 36) . ' ' . $mctBillingName2;
                    $mctBillingName1 = substr($mctBillingName1, 0, 35);
                }
            }
        } else {
            if (($order->billingAddress->name2 === '') && ($order->billingAddress->name3 === '')) {
                $this->getLogger(__METHOD__)
                    ->addReference('orderId', $order->id)
                    ->error(PluginConfiguration::PLUGIN_NAME . '::error.emptyNames', [
                        'message'           => 'Billing name fields are empty',
                        'orderId'           => $order->id,
                        'mctBillingName1'   => $mctBillingName1,
                        'mctBillingName2'   => $mctBillingName2,
                        'order-name1'       => $order->billingAddress->name1,
                        'order-name2'       => $order->billingAddress->name2,
                        'order-name3'       => $order->billingAddress->name3,
                        'order-name4'       => $order->billingAddress->name4
                    ]);
                $statusOfFaultyOrder = $this->configRepository->getFaultyOrderStatus();
                if ($statusOfFaultyOrder != ''){
                    $this->orderRepository->updateOrder(['statusId' => $statusOfFaultyOrder], $order->id);
                    return;
                }
            } else {
                $mctBillingName1 = $order->billingAddress->name2 . ' ' . $order->billingAddress->name3;
                if (strlen($mctBillingName1) > 35){
                    $mctBillingName2 = substr($mctBillingName1, 36);
                    $mctBillingName1 = substr($mctBillingName1, 0, 35);
                }
            }
        }

        $record['E2EDKA1003GRP'][] = [
            'E2EDKA1003'    => [
                'PARVW' => 'RE',
                'PARTN' => $this->orderHelper->getValueBasedOnMarketplace($order->referrerId),
                'NAME1' => substr($mctBillingName1, 0, 35),
                'NAME2' => substr($mctBillingName2, 0, 35),
                'STRAS' => substr($order->billingAddress->address1 . ' ' . $order->billingAddress->address2, 0, 35),
                'ORT01' => substr($order->billingAddress->town, 0, 35),
                'PSTLZ' => $invoicePostalCode,
                'LAND1' => $order->billingAddress->country->isoCode2,
                'TELF1' => ''
            ]
        ];

        $record['E2EDK02'] = [];
        $record['E2EDK02'][] = [
            'QUALF'     => '001',
            'BELNR'     => $order->getPropertyValue(OrderPropertyType::EXTERNAL_ORDER_ID),
            'DATUM'     => $order->dates->filter(
                function ($date) {
                    return $date->typeId == OrderDateType::ORDER_ENTRY_AT;
                }
            )->first()->date->isoFormat("YYYYMMDD")
        ];

        $externalOrderId = $order->getPropertyValue(OrderPropertyType::EXTERNAL_ORDER_ID);
        if ($this->orderHelper->isAmazonOrder($order->referrerId)){
            $dashPosition = strpos($externalOrderId, '-');
            if ($dashPosition !== false) {
                $externalOrderId = substr($externalOrderId, $dashPosition + 1);
            }
        }
        $record['E2EDK02'][] = [
            'QUALF'     => '017',
            'BELNR'     => substr($externalOrderId, 0, 18),
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

        if ($this->orderHelper->getTaxId($order) != '') {
            $record['E2EDKT1002GRP'][] = [
                'E2EDKT1002' => [
                    'TDID' => 'FIS1',
                    'TSSPRAS' => 'D',
                    'TSSPRAS_ISO' => 'DE' //$order->billingAddress->country->isoCode2,
                    //the use of 'DE' in all cases was specified by the client in an email
                ],
                'E2EDKT2001' => [
                    'TDLINE' => $this->orderHelper->getTdline($order)
                ]
            ];
        } else {
            $record['E2EDKT1002GRP'][] = [
                'E2EDKT1002' => [
                    'TSSPRAS' => '',
                    'TSSPRAS_ISO' => '',
                ],
                'E2EDKT2001' => [
                    'TDLINE' => ''
                ]
            ];
        }

        //order records
        $counterTen = 10;
        /** @var OrderItem $orderItem */
        foreach ($order->orderItems as $orderItem) {
            if (
                ($orderItem->typeId === OrderItemType::TYPE_VARIATION) ||
                ($orderItem->typeId === OrderItemType::TYPE_PROMOTIONAL_COUPON)
            )
            {
                $itemData = [];
                $itemData['E2EDP01011']['POSEX'] = $counterTen;
                $itemData['E2EDP01011']['MENGE'] = $orderItem->quantity;
                if ($this->mappingHelper->useNetPrice(
                    $this->orderHelper->getValueBasedOnMarketplace($order->referrerId),
                    $order->deliveryAddress->country->isoCode2,
                    $order->billingAddress->taxIdNumber
                )){
                    $itemData['E2EDP01011']['VPREI'] = $orderItem->getAmountAttribute()->priceOriginalNet;
                } else {
                    $itemData['E2EDP01011']['PREIS'] = $orderItem->getAmountAttribute()->priceOriginalGross;
                }
                $itemData['E2EDP19003']['QUALF'] = '002';
                if ($orderItem->typeId === OrderItemType::TYPE_VARIATION) {
                    $itemData['E2EDP19003']['IDTNR'] = $orderItem->variation->number;
                }

                $record['E2EDP01011GRP'][] = $itemData;

                $counterTen += 10;
            }
        }

        $this->saveRecord($order->id, $record);

        $this->mappingHelper->addHistoryData('End processing order ' . $order->id, $order->id);
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
            'exportedData'     => json_encode($record, JSON_UNESCAPED_UNICODE),
            'savedAt'          => Carbon::now()->toDateTimeString(),
            'sentdAt'          => '',
        ];

        /** @var ExportDataRepository $exportDataRepository */
        $exportDataRepository = pluginApp(ExportDataRepository::class);
        try {
            if (!$exportDataRepository->orderExists($plentyOrderId)) {
                /** @var TableRow $savedObject */
                $exportDataRepository->save($exportData);
                $this->mappingHelper->addHistoryData('Saved to export stack', $plentyOrderId);
                /*
                $statusOfProcessedOrder = $this->configRepository->getProcessedOrderStatus();
                if ($statusOfProcessedOrder != ''){
                    $this->orderRepository->updateOrder(['statusId' => $statusOfProcessedOrder], $plentyOrderId);
                }
                */
                return true;
            }
            $this->getLogger(__METHOD__)
                ->addReference('orderId', $plentyOrderId)
                ->report(PluginConfiguration::PLUGIN_NAME . '::error.orderExists', $exportData);
            $this->mappingHelper->addHistoryData('Order already exists in the export stack', $plentyOrderId);
            return false;
        } catch (\Throwable $e) {
            $this->getLogger(__METHOD__)
                ->addReference('orderId', $plentyOrderId)
                ->error(PluginConfiguration::PLUGIN_NAME . '::error.saveExportError',
                    [
                        'message'     => $e->getMessage(),
                        'exportData'  => $exportData
                    ]);
            $this->mappingHelper->addHistoryData('Exception when writing to the export table: ' . $e->getMessage(), $plentyOrderId);
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

    private function GetFieldWithNamespace(string $field){
        $namesp = '';
        switch ($field){
            case 'Send':
                $namesp = 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://Microsoft.LobServices.Sap/2007/03/Idoc/3/ORDERS05//750/Send"';
                break;
            case 'EDI_DC40':
            case 'E2EDK01005':
            case 'E2EDK14':
            case 'E2EDK02':
            case 'E2EDK03':
            case 'E2EDK05001':
            case 'E2EDKT1002GRP':
            case 'E2EDKA1003GRP':
            case 'E2EDP01011GRP':
                $namesp = 'xmlns="http://Microsoft.LobServices.Sap/2007/03/Types/Idoc/3/ORDERS05//750"';
                break;
            case 'IDOCTYP':
            case 'MESTYP':
            case 'MESCOD':
            case 'SNDPOR':
            case 'SNDPRT':
            case 'SNDPRN':
            case 'RCVPOR':
                $namesp = 'xmlns="http://Microsoft.LobServices.Sap/2007/03/Types/Idoc/Common/"';
                break;
            default:
                return "<$field>";
        }

        return "<$field $namesp>";
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
                    $str .= $this->arrayToXml($v);
                    if (next($array) !== false){
                        $str .= "</$parentTag>\n" . $this->GetFieldWithNamespace($parentTag) . "\n";
                    }
                } else {
                    $str .= $this->GetFieldWithNamespace($k) . "\n" . $this->arrayToXml($v, $k) . "</$k>\n";
                }
            }
            else {
                if ((string)$v === ''){
                    $str .= "<$k />\n";
                } else {
                    $str .= $this->GetFieldWithNamespace($k) . $this->escapeValue($v) . "</$k>\n";
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
        $resultedXML = '<?xml version="1.0" encoding="UTF-8" ?>
';

        $orderData = json_decode($order->exportedData, true);
        $resultedXML .= $this->arrayToXml(
            [
                'Send' => [
                    'idocData' => $orderData
                ]
            ]
        );

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
                $this->mappingHelper->addHistoryData('Export to ' . $fileName . ' failed!');
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
            $this->mappingHelper->addHistoryData('Exception when sending to ' . $fileName . ': ' . $e->getMessage());
            return false;
        }
        $this->mappingHelper->addHistoryData('Export to ' . $fileName . ' succeeded!');
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
        $this->mappingHelper->addHistoryData('Start sending data to client...');
        /** @var ExportDataRepository $exportDataRepository */
        $exportDataRepository = pluginApp(ExportDataRepository::class);
        try {
            $exportList = $exportDataRepository->listUnsent();
        } catch (\Throwable $e) {
            $this->getLogger(__METHOD__)->error(PluginConfiguration::PLUGIN_NAME . '::error.readExportError',
                [
                    'message'     => $e->getMessage(),
                ]);
            $this->mappingHelper->addHistoryData('Getting unsent list failed: ' . $e->getMessage());
            return false;
        }

        if (count($exportList) == 0){
            $this->mappingHelper->addHistoryData('Nothing to export...');
            return false;
        }

        /** @var TableRow $order */
        foreach ($exportList as $order) {
            $this->mappingHelper->addHistoryData('Exporting order ' . $order->plentyOrderId . ' to XML...');
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