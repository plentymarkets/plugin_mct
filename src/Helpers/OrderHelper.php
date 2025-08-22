<?php

namespace MCT\Helpers;

use Plenty\Modules\Item\ItemShippingProfiles\Contracts\ItemShippingProfilesRepositoryContract;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Models\OrderItem;
use Plenty\Modules\Order\Models\OrderItemType;
use Plenty\Modules\Order\Referrer\Contracts\OrderReferrerRepositoryContract;
use Plenty\Modules\Order\Shipping\Contracts\ParcelServicePresetRepositoryContract;
use Plenty\Modules\Order\Shipping\ParcelService\Models\ParcelServicePreset;

class OrderHelper
{
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
     * @var int
     */
    private $speditionProfileId;


    public function __construct(
        MappingHelper $mappingHelper,
        ParcelServicePresetRepositoryContract $shippingProfileRepository
    )
    {
        $this->marketplaceValueMapping  = $mappingHelper->getMarketplaceValueMapping();
        $this->shippingValueMapping     = $mappingHelper->getShippingValueMapping();
        $this->qualf7ValueMapping       = $mappingHelper->getQualf7ValueMapping();
        $this->qualf12ValueMapping      = $mappingHelper->getQualf12ValueMapping();
        $this->speditionProfileId       = $this->getSpeditionShippingProfileId($shippingProfileRepository);
    }

    private function getSpeditionShippingProfileId(ParcelServicePresetRepositoryContract $shippingProfileRepository){
        /** @var ParcelServicePreset[] $shippingProfiles */
        $shippingProfiles = $shippingProfileRepository->getPresetList();

        /** @var ParcelServicePreset $shippingProfile */
        foreach ($shippingProfiles as $shippingProfile) {
            if ($shippingProfile->backendName === 'Spedition'){
                return $shippingProfile->id;
            }
        }
        return -1;
    }

    /**
     * @param float $referrerId
     * @return bool
     */
    public function isAmazonOrder(float $referrerId)
    {
        /** @var OrderReferrerRepositoryContract $orderReferrerRepo */
        $orderReferrerRepo = pluginApp(OrderReferrerRepositoryContract::class);

        $referrers = $orderReferrerRepo->getList();
        if(!empty($referrers))
        {
            foreach($referrers as $referrer)
            {
                if(($referrerId === (float)$referrer->referrer_id) && (substr($referrer->name, 0, 7) === 'Amazon '))
                {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param Order $order
     * @return string|void
     */
    public function getTaxId(Order $order)
    {
        switch ($this->getValueBasedOnMarketplace($order->referrerId)){
            case '5024143':
            case '5028223':
            case '5029208':
            case '5029209':
            case '5030019':
                if (
                    (strtolower($order->deliveryAddress->country->isoCode2) != 'de') &&
                    ($order->billingAddress->name1 !== '')  &&
                    ($order->billingAddress->taxIdNumber !== '')
                ) {
                    return $order->billingAddress->taxIdNumber;
                }
                break;
        }
        return '';
    }

    /**
     * @param Order $order
     * @return string|void
     */
    public function getTdline(Order $order)
    {
        return 'Ihre UST-ID. Nr.: ' . $order->billingAddress->taxIdNumber;
    }

    /**
     * @param float $shippingProfileId
     * @return mixed|string
     */
    public function getValueBasedOnShippingProfile(float $shippingProfileId)
    {
        if (isset($this->shippingValueMapping[$shippingProfileId])) {
            return $this->shippingValueMapping[$shippingProfileId];
        }
        return 'AMP';
    }

    /**
     * @param float $referrerId
     * @return mixed|string
     */
    public function getValueForQualf007(float $referrerId)
    {
        if (isset($this->qualf7ValueMapping[$referrerId])) {
            return $this->qualf7ValueMapping[$referrerId];
        }
        return '21';
    }

    /**
     * @param float $referrerId
     * @return mixed|string
     */
    public function getValueForQualf012(float $referrerId)
    {
        if (isset($this->qualf12ValueMapping[$referrerId])) {
            return $this->qualf12ValueMapping[$referrerId];
        }
        return 'TA';
    }

    public function orderHasSpeditionShippingProfile(Order $order)
    {
        //the client requested to consider an order as 'Spedition' if at least one item or the order itself has 'Spedition' shipping profile
        if ($order->shippingProfileId == $this->speditionProfileId){
            return true;
        }

        /** @var OrderItem $orderItem */
        foreach ($order->orderItems as $orderItem) {
            if ($orderItem->shippingProfileId == $this->speditionProfileId){
                return true;
            }
        }
        return false;
    }

    /**
     * @param float $referrerId
     * @param bool $leadingZeroes
     * @return string
     */
    public function getValueBasedOnMarketplace(float $referrerId, bool $leadingZeros=false)
    {
        if (isset($this->marketplaceValueMapping[$referrerId])) {
            $value = $this->marketplaceValueMapping[$referrerId];
        } else {
            $value = '1234567';
        }
        if ($leadingZeros) {
            return str_pad($value, 10, "0", STR_PAD_LEFT);
        }
        return $value;
    }

    /**
     * @param Order $order
     * @return float|int
     */
    public function getShippingCosts(Order $order)
    {
        $shippingCosts = 0.0;
        /** @var OrderItem $orderItem */
        foreach ($order->orderItems as $orderItem) {
            if ($orderItem->typeId === OrderItemType::TYPE_SHIPPING_COSTS) {
                $amountAttribute = $orderItem->getAmountAttribute();
                $shippingCosts += $amountAttribute->priceOriginalGross * $orderItem->quantity;
            }
        }
        return $shippingCosts;
    }
}