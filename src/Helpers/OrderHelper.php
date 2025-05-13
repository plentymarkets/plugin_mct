<?php

namespace MCT\Helpers;

use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Models\OrderItem;
use Plenty\Modules\Order\Models\OrderItemType;
use Plenty\Modules\Order\Referrer\Contracts\OrderReferrerRepositoryContract;

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

    public function __construct(MappingHelper $mappingHelper)
    {
        $this->marketplaceValueMapping = $mappingHelper->getMarketplaceValueMapping();
        $this->shippingValueMapping = $mappingHelper->getShippingValueMapping();
        $this->qualf7ValueMapping   = $mappingHelper->getQualf7ValueMapping();
        $this->qualf12ValueMapping  = $mappingHelper->getQualf12ValueMapping();
    }

    /**
     * @param float $referrerId
     * @return bool
     */
    private function isAmazonOrder(float $referrerId)
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
        if (
            $this->isAmazonOrder($order->referrerId) &&
            ($order->billingAddress->name1 !== '')  &&
            ($order->billingAddress->taxIdNumber !== '')
        ) {
            return $order->billingAddress->taxIdNumber;
        }
        return '';
    }

    /**
     * @param Order $order
     * @return string|void
     */
    public function getTdline(Order $order)
    {
        $taxId = $this->getTaxId($order);
        if ($taxId != '') {
            return 'Ihre UST-ID. Nr.: ' . $order->billingAddress->taxIdNumber;
        }
        return '';
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

    /**
     * @param float $referrerId
     * @return string
     */
    public function getValueBasedOnMarketplace(float $referrerId)
    {
        if (isset($this->marketplaceValueMapping[$referrerId])) {
            return '000' . $this->marketplaceValueMapping[$referrerId];
        }
        return '5024143';
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