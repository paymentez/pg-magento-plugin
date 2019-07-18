<?php

namespace Paymentez\Module\Model\Adminhtml\Source;

use Magento\Framework\Option\ArrayInterface;


class PaymentAction implements ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'authorize',
                'label' => __('Authorize')
            ],
            [
                'value' => 'capture',
                'label' => __('Capture')
            ],
            [
                'value' => 'authorize_capture',
                'label' => __('Authorize & Capture')
            ]
        ];
    }
}
