<?php

namespace Paymentez\Module\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Framework\Validator\Exception as MagentoValidatorException;


class DataAssignObserver extends AbstractDataAssignObserver
{
    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $data = $this->readDataArgument($observer);

        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);

        if (!is_array($additionalData)) {
            throw new MagentoValidatorException(__("Payment capturing error."));
        }

        $additionalData = new DataObject($additionalData);
        $paymentMethod = $this->readMethodArgument($observer);

        $payment = $observer->getPaymentModel();

        if (!$payment instanceof InfoInterface) {
            $payment = $paymentMethod->getInfoInstance();
        }

        if (!$payment instanceof InfoInterface) {
            throw new LocalizedException(__('Payment model does not provided.'));
        }

        $cardToken = $additionalData->getData('card_token');

        if (empty($cardToken)) {
            throw new MagentoValidatorException(__("[PAYMENTEZ] Missing card token."));
        }

        $payment->setAdditionalInformation(
            'card_token',
            $cardToken
        );

        $payment->setAdditionalInformation(
            'cc_bin',
            $additionalData->getData('cc_bin')
        );

        $payment->setCcLast4($additionalData->getData('cc_last_4'));
        $payment->setCcType($additionalData->getData('cc_type'));
        $payment->setCcExpMonth($additionalData->getData('cc_exp_month'));
        $payment->setCcExpYear($additionalData->getData('cc_exp_year'));
    }
}
