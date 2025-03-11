<?php

namespace MCT\Helpers;

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
            '14' => '5'
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
}