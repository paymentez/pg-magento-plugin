<?php

namespace Paymentez\Module\Model;

use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Payment\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Framework\DataObject;
use Magento\Payment\Model\InfoInterface;
use Magento\Framework\Validator\Exception as MagentoValidatorException;
use Paymentez\Exceptions\PaymentezErrorException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Event\Manager;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Paymentez\Paymentez;

use Magento\Payment\Model\Method\{
	Logger,
	Cc
};

use Magento\Framework\Api\{
	ExtensionAttributesFactory,
	AttributeValueFactory
};

class Payment extends Cc
{
    const METHOD_CODE                       = 'paymentez_module';

    protected $_code                    	= self::METHOD_CODE;
    protected $_isGateway                   = true;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = true;
    protected $_canRefund                   = true;
    protected $_minOrderTotal 				= 0;
    protected $_supportedCurrencyCodes 		= ['MXN', 'USD'];
    protected $_typesCards;
    protected $eventManager;
    protected $_service;

    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        ModuleListInterface $moduleList,
        TimezoneInterface $localeDate,
        Manager $eventManager,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $moduleList,
            $localeDate,
            null,
            null,
            $data
        );

        $this->_code = self::METHOD_CODE;
        $this->_minOrderTotal = $this->getConfigData('min_order_total');
        $this->_typesCards = $this->getConfigData('cctypes');
        $this->eventManager = $eventManager;
    }

    public function canUseForCurrency($currencyCode)
    {
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            return false;
        }

        return true;
    }

    public function capture(InfoInterface $payment, $amount)
	{
		$this->initPaymentezSdk();

		//check if payment has been authorized
		$transactionId = $payment->getParentTransactionId();

        if(is_null($transactionId)) {
            $this->authorize($payment, floatval($amount));
            $transactionId = $payment->getParentTransactionId();
        }

		try {
            $capture = $this->_service->capture($transactionId, floatval($amount));
        } catch (PaymentezErrorException $paymentezError) {
            $this->debug($payment->getData(), $paymentezError->getMessage());

            throw new MagentoValidatorException(__('Payment capturing error.'));
        }

        if ($capture->transaction->status !== "success") {
            $msg = isset($charge->transaction->status_detail)
                    && !empty($charge->transaction->status_detail) ? $charge->transaction->status_detail : "Payment authorize error.'";

            $this->debug($charge, $msg);

            throw new MagentoValidatorException(__($msg));
        }

        $payment->setIsTransactionClosed(1);

        return $this;
    }

    public function authorize(InfoInterface $payment, $amount)
    {
    	$this->initPaymentezSdk();
    	$order = $payment->getOrder();

        $userDetails = [
        	'id' => $order->getCustomerId(),
        	'email' => $order->getCustomerEmail()
        ];

        $orderDetails = [
        	'amount' => floatval($amount),
        	'description' => $this->sliceText(sprintf('Payment of order #%s, Customer email: %s Shipping method: %s',
                $order->getIncrementId(),
                $order->getCustomerEmail(),
                $orden->getShippingMethod()), 247),
        	'dev_reference' => $order->getIncrementId(),
        	'vat' => 0.00
        ];

        try {
        	$charge = $this->_service->authorize($this->getCardToken(),
        		$orderDetails,
        		$userDetails);
        } catch (PaymentezErrorException $paymentezError) {
            $this->debug($payment->getData(), $paymentezError->getMessage());

            throw new MagentoValidatorException(__('Payment authorize error.'));
        }

        $status = $charge->transaction->status;

        if ($status !== "success") {
        	$msg = isset($charge->transaction->status_detail)
        			&& !empty($charge->transaction->status_detail) ? $charge->transaction->status_detail : "Payment authorize error.'";

        	$this->debug($charge, $msg);

        	throw new MagentoValidatorException(__($msg));
        }

        $transactionId = $charge->transaction->id;

        $payment->setParentTransactionId($transactionId);
        $payment->setTransactionId($transactionId);
        $payment->setIsTransactionClosed(0);

        return $this;
    }

    public function refund(InfoInterface $payment, $amount)
    {
    	$this->initPaymentezSdk();

    	$transactionId = $payment->getParentTransactionId();

        try {
        	$this->_service->refund($transactionId, floatval($amount));
        } catch (PaymentezErrorException $e) {
        	$this->debug($payment->getData(), $paymentezError->getMessage());
            throw new MagentoValidatorException(__('Payment refunding error.'));
        }

        $payment
            ->setTransactionId($transactionId . '-' . Transaction::TYPE_REFUND)
            ->setParentTransactionId($transactionId)
            ->setIsTransactionClosed(1)
            ->setShouldCloseParentTransaction(1);

        return $this;
    }

    public function getConfigPaymentAction()
    {
        return self::ACTION_AUTHORIZE_CAPTURE;
    }

    public function isAvailable(CartInterface $quote = null){
        $this->_minOrderTotal = $this->getConfigData('min_order_total');

        if($quote && $quote->getBaseGrandTotal() < $this->_minOrderTotal) {
            return false;
        }

        $credentials = $this->getServerCredentials();

        if (empty($credentials)) {
        	return false;
        }

        return parent::isAvailable($quote);
    }

	public function assignData(DataObject $data)
	{
		parent::assignData($data);

	    $this->eventManager->dispatch(
	        'payment_method_assign_data_' . $this->getCode(),
	        [
	            AbstractDataAssignObserver::METHOD_CODE => $this,
	            AbstractDataAssignObserver::MODEL_CODE => $this->getInfoInstance(),
	            AbstractDataAssignObserver::DATA_CODE => $data
	        ]
	    );

	    return $this;
	}

    public function getClientCredentials(): array
    {
    	$isStaging = boolval((integer) $this->getConfigData('staging_mode'));

    	return $isStaging ? [
    		'code' => $this->getConfigData('staging_client_code'),
    		'api_key' => $this->getConfigData('staging_client_key')
    	] : [
    		'code' => $this->getConfigData('production_client_code'),
    		'api_key' => $this->getConfigData('production_client_key')
    	];
    }

    public function getClientEnvironment(): string
    {
    	return boolval((integer) $this->getConfigData('staging_mode')) ? 'stg' : 'prod';
    }

    public function getActiveTypeCards()
    {
        $activeTypes = explode(",", $this->_typesCards);
        $supportType = [
            "AE" => "American Express",
            "VI" => "Visa",
            "MC" => "MasterCard"
        ];

        $out = [];

        foreach ($activeTypes AS $value) {
            $out[$value] = $supportType[$value];
        }

        return $out;
    }

    public function validate()
    {
        $info = $this->getInfoInstance();
        $errorMsg = false;
        $availableTypes = explode(',', $this->getConfigData('cctypes'));
        $binNumber = $info->getAdditionalInformation('cc_bin');
        $last4 =  $info->getCcLast4();
        $ccNumber = $binNumber . $last4;
        $ccNumber = preg_replace('/[\-\s]+/', '', $ccNumber);

        $info->setCcNumber(implode('', [
        	$ccNumber,
        	"******",
        	$last4
        ]));

        $ccType = '';

        if (in_array($info->getCcType(), $availableTypes)) {
            if ($this->validateCcNumOther($binNumber)) {
                $ccTypeRegExpList = [
                    // Visa
                    'VI' => '/^4[0-9]{12}([0-9]{3})?$/',
                    // MasterCard
                    'MC' => '/^(?:5[1-5][0-9]{2}|222[1-9]|22[3-9][0-9]|2[3-6][0-9]{2}|27[01][0-9]|2720)[0-9]{12}$/',
                    // American Express
                    'AE' => '/^3[47][0-9]{13}$/',
                ];

                // Validate only main brands.
                $ccNumAndTypeMatches = isset(
                        $ccTypeRegExpList[$info->getCcType()]
                    ) && preg_match(
                        $ccTypeRegExpList[$info->getCcType()],
                        $ccNumber
                    ) || !isset(
                        $ccTypeRegExpList[$info->getCcType()]
                    );

                $ccType = $ccNumAndTypeMatches ? $info->getCcType() : 'OT';
            } else {
                $errorMsg = __('Custom Invalid Credit Card Number');
            }
        } else {
            $errorMsg = __(
                'Custom This credit card type is not allowed for this payment method.'
            );
        }
        if ($ccType != 'SS' && !$this
                ->_validateExpDate(
                    $info->getCcExpYear(),
                    $info->getCcExpMonth()
                )) {
            $errorMsg = __(
                'Custom Please enter a valid credit card expiration date.'
                .$info->getCcType()
            );
        }

        if (!empty($errorMsg)) {
            throw new LocalizedException($errorMsg);
        }

        return $this;
    }

    private function getServerCredentials(): array
    {
    	$isStaging = boolval((integer) $this->getConfigData('staging_mode'));

    	return $isStaging ? [
    		'code' => $this->getConfigData('staging_server_code'),
    		'api_key' => $this->getConfigData('staging_server_key')
    	] : [
    		'code' => $this->getConfigData('production_server_code'),
    		'api_key' => $this->getConfigData('production_server_key')
    	];
    }

    private function initPaymentezSdk()
    {
    	$isProduction = boolval((integer) $this->getConfigData('staging_mode')) ? false : true;

    	// Initalize Paymentez SDK
        $credentials = $this->getServerCredentials();

        if (empty($credentials)) {
        	throw new MagentoValidatorException(__('[Paymentez]: Missing client credentials.'));
        }

        Paymentez::init($credentials['code'], $credentials['api_key'], $isProduction);

        $this->_service = Paymentez::charge();
    }

    private function getCardToken(): string
    {
    	$info = $this->getInfoInstance();
    	$cardToken = $info->getAdditionalInformation('card_token');

        if (!$cardToken) {
            throw new MagentoValidatorException(__('Missing card token.'));
        }

        return $cardToken;
    }

    private function sliceText(string $text, int $maxchar, string $end = '...'): string
    {
        if (strlen($text) > $maxchar) {
            $text = substr($text, 0, $maxchar) . '...';
        }

        return $text;
    }
}
