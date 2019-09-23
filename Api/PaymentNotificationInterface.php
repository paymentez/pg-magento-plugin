<?php

namespace Paymentez\Module\Api;


interface PaymentNotificationInterface
{
    /**
     * Listener for Paymentez webhook
     *
     * @return \Magento\Framework\App\ResponseInterface
     * @api
     *
     */
    public function notificate();
}
