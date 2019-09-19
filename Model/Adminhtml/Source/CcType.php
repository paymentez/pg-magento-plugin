<?php

namespace Paymentez\Module\Model\Adminhtml\Source;

use Magento\Payment\Model\Source\Cctype as CreditCardsBrands;


class CcType extends CreditCardsBrands
{
    public function getAllowedTypes()
    {
        return [
            'AE',
            'VI',
            'MC',
            'DI',
            'DN',
            'ELO',
            'AU',
            'CS',
            'SO',
            'EX',
            'AK',
            'CD',
            'SX',
            'JC'
        ];
    }
}