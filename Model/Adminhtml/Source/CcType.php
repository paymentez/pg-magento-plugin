<?php

namespace Paymentez\Module\Model\Adminhtml\Source;

use Magento\Payment\Model\Source\Cctype as CreditCardsBrands;


class CcType extends CreditCardsBrands
{
    public function getAllowedTypes()
    {
        return [
            'VI',
            'MC',
            'AE'
        ];
    }
}