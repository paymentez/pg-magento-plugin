<?php

namespace Paymentez\Module\Api;


interface PaymentNotificationInterface
{
	/**
	* Listener for Paymentez webhook
	*
	* @api
	*
	* @return \Magento\Framework\App\ResponseInterface
	*/
	public function notificate();
}
