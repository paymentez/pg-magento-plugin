<?php

namespace Paymentez\Module\Model;

use Magento\Framework\Api\{AttributeValueFactory, ExtensionAttributesFactory};
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Manager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Validator\Exception as MagentoValidatorException;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\{Cc, Logger};
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Paymentez\Exceptions\PaymentezErrorException;
use Paymentez\Paymentez;

class Payment extends Cc
{
    const METHOD_CODE = 'paymentez_module';

    protected $_code = self::METHOD_CODE;
    protected $_isGateway = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = true;
    protected $_minOrderTotal = 0;
    protected $_supportedCurrencyCodes = ['BRL', 'COP', 'MXN', 'USD', 'CLP', 'ARS', 'VEF', 'PEN'];
    protected $_typesCards;
    protected $eventManager;
    protected $_service;
    protected $_currenciesThatSupportAuthorize = ['BRL', 'MXN', 'PEN'];

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
    )
    {
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

        $_logging = boolval((integer)$this->getConfigData('dev_use_logs'));
        if (!$_logging) {
            $this->_logger = new FooLogger();
        }
        $this->_logger->info("Init Paymentez Constructor");

        $this->_minOrderTotal = $this->getConfigData('min_order_total');
        $this->_typesCards = $this->getConfigData('cctypes');
        $this->eventManager = $eventManager;
    }

    public function canUseForCurrency($currencyCode)
    {
        $this->_logger->info("canUseForCurrency => $currencyCode");
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            $this->_logger->error("Currency $currencyCode not allowed");
            return false;
        }
        $this->_logger->info("Currency $currencyCode allowed");
        return true;
    }

    public function capture(InfoInterface $payment, $amount)
    {
        $this->_logger->info("Starting capture");
        $this->initPaymentezSdk();

        //check if payment has been authorized
        $transactionId = $payment->getParentTransactionId();

        if (is_null($transactionId)) {
            $this->_logger->info("Capture require authorize");
            $this->authorize($payment, floatval($amount));
            $transactionId = $payment->getParentTransactionId();
        }


        // For currencies that not allow authorization, was used a debit, and the capture is not required
        if (!$this->currencyAllowAuthorization($payment)) {
            $this->_logger->info("Transactions does not apply for capture");
            $order = $payment->getOrder();
            $original_amount = $order->getGrandTotal();
            $this->_logger->info("original_amount $$original_amount => amount $$amount");
            if ($amount != $original_amount) {
                $this->_logger->info("Different amounts");
                throw new MagentoValidatorException(__("Transaction does not allow different amount for capture"));
            }
            $payment->setIsTransactionClosed(1);
            return $this;
        }

        try {
            $this->_logger->info("Executing capture: $transactionId : $$amount");
            $capture = $this->_service->capture((string)$transactionId, (float)$amount);
            $this->_logger->info("Capture Response => " . json_encode($capture));
        } catch (PaymentezErrorException $paymentezError) {
            $this->_logger->error("Error in capture => " . $paymentezError->getMessage());
            throw new MagentoValidatorException(__('Payment capturing error. Detail: ' . $paymentezError->getMessage()));
        }

        if ($capture->transaction->status !== "success") {
            $error_code = isset($capture->transaction->status_detail) && !empty($capture->transaction->status_detail) ? $capture->transaction->status_detail : "ERR-CP";
            $msg = "Lo sentimos, tu pago no pudo ser procesado. (code: $error_code)";

            $this->debug($capture, $msg);

            throw new MagentoValidatorException(__($msg));
        }

        $payment->setIsTransactionClosed(1);

        $this->_logger->info("Finalize Capture");
        return $this;
    }

    public function authorize(InfoInterface $payment, $amount)
    {
        $this->_logger->info("Starting authorize");
        $AUTHORIZE = 'authorize';
        $DEBIT = 'debit';
        $service = $this->currencyAllowAuthorization($payment) ? $AUTHORIZE : $DEBIT;

        $this->_logger->info("Service to use: $service");
        $this->initPaymentezSdk();
        $order = $payment->getOrder();
        $card_token = (string)$this->getCardToken();
        $user_id = !empty($order->getCustomerId()) ? $order->getCustomerId() : $order->getCustomerEmail();

        $userDetails = [
            'id' => (string)$user_id,
            'email' => (string)$order->getCustomerEmail()
        ];

        $orderDetails = [
            'amount' => floatval($amount),
            'description' => $this->sliceText(sprintf('Payment of order #%s, Customer email: %s Shipping method: %s',
                $order->getIncrementId(),
                $order->getCustomerEmail(),
                $order->getShippingMethod()), 247),
            'dev_reference' => (string)$order->getIncrementId(),
            'vat' => 0.00
        ];

        $this->_logger->info("Executing $service => token: $card_token, order: " . json_encode($orderDetails) .
            ", user: " . json_encode($userDetails));

        try {
            if ($service == $AUTHORIZE) {
                $this->_logger->info("Executing authorize");
                $response = $this->_service->authorize($card_token, $orderDetails, $userDetails);
            } else {
                $this->_logger->info("Executing debit (create)");
                $response = $this->_service->create($card_token, $orderDetails, $userDetails);
            }
            $this->_logger->info("$service response => " . json_encode($response));
        } catch (PaymentezErrorException $paymentezError) {
            $this->_logger->error("Error in $service => " . $paymentezError->getMessage());
            throw new MagentoValidatorException(__("Payment $service error. Detail: " . $paymentezError->getMessage()));
        }

        $status = $response->transaction->status;
        $transactionId = $response->transaction->id;

        if ($status !== "success") {
            $error_code = isset($response->transaction->status_detail) && !empty($response->transaction->status_detail) ? $response->transaction->status_detail : "ERR-CP";
            $msg = "Lo sentimos, tu pago no pudo ser procesado. (code: $error_code)";

            $this->debug($response, $msg);

            throw new MagentoValidatorException(__($msg));
        }

        $payment->setParentTransactionId($transactionId);
        $payment->setTransactionId($transactionId);
        $payment->setIsTransactionClosed(0);

        $card_type = $response->card->type;
        $card_number = $response->card->bin . "-xxxx-" . $response->card->number;
        $auth_code = $response->transaction->authorization_code;
        $comment = "Transaction ID: $transactionId|| Brand Code: $card_type || Number Card: $card_number|| Authorization Code: $auth_code";
        $order->addStatusHistoryComment($comment, $order->getStatus());

        $this->_logger->info("Finalize $service");
        return $this;
    }

    public function refund(InfoInterface $payment, $amount)
    {
        $this->_logger->info("Starting refund");
        $this->initPaymentezSdk();

        $order = $payment->getOrder();
        $grandTotal = $order->getGrandTotal();

        if ($amount != $grandTotal) {
            return $this;
        }

        $transactionId = $payment->getParentTransactionId();

        // This apply when parent transaction has a suffix, i.e.: VN-1234-capture
        $transactionIdExploded = explode('-', $transactionId);
        if (count($transactionIdExploded) > 2) {
            $transactionId = "$transactionIdExploded[0]-$transactionIdExploded[1]";
        }

        try {
            $this->_logger->info("Executing refund: $transactionId, amount: $$amount");
            $refund = $this->_service->refund((string)$transactionId, (float)$amount);
            $this->_logger->info("Refund response => " . json_encode($refund));
        } catch (PaymentezErrorException $paymentezError) {
            $this->_logger->error("Error in refund => " . $paymentezError->getMessage());
            throw new MagentoValidatorException(__('Payment refund error. Detail: ' . $paymentezError->getMessage()));
        }

        $payment
            ->setTransactionId($transactionId . '-' . Transaction::TYPE_REFUND)
            ->setParentTransactionId($transactionId)
            ->setIsTransactionClosed(1)
            ->setShouldCloseParentTransaction(1);

        $this->_logger->info("Finalize Refund");
        return $this;
    }


    public function getConfigPaymentAction()
    {
        $payment_action = $this->getConfigData('payment_action');
        $this->_logger->info("Payment Action => $payment_action");
        return $payment_action;
    }

    public function isAvailable(CartInterface $quote = null)
    {
        $this->_logger->info("Starting isAvailable");
        $this->_minOrderTotal = $this->getConfigData('min_order_total');

        if ($quote && $quote->getBaseGrandTotal() < $this->_minOrderTotal) {
            $this->_logger->warning("Not isAvailable by invalid amount");
            return false;
        }

        $credentials = $this->getServerCredentials();

        if (empty($credentials)) {
            $this->_logger->warning("Not isAvailable by empty credentials");
            return false;
        }

        $validation = parent::isAvailable($quote);
        $this->_logger->info("isAvailable => $validation");
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
        $isStaging = boolval((integer)$this->getConfigData('staging_mode'));

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
        return boolval((integer)$this->getConfigData('staging_mode')) ? 'stg' : 'prod';
    }

    public function getActiveTypeCards()
    {
        $this->_logger->info("Starting getActiveTypeCards");
        $activeTypes = explode(",", $this->_typesCards);
        $supportType = [
            "AE" => "American Express",
            "VI" => "Visa",
            "MC" => "MasterCard",
            "DI" => "Discover",
            "DN" => "Diners",
            "ELO" => "Elo",
            "AU" => "Aura",
            "CS" => "Credisensa",
            "SO" => "Solidario",
            "EX" => "Exito",
            "AK" => "Alkosto",
            "CD" => "Codensa",
            "SX" => "Sodexo",
            "JC" => "JCB"
        ];

        $out = [];

        foreach ($activeTypes AS $value) {
            $out[$value] = $supportType[$value];
        }
        $this->_logger->info("activeTypes =>", $activeTypes);
        $this->_logger->info("supportType =>", $supportType);
        $this->_logger->info("out =>", $out);

        return $out;
    }

    public function validate()
    {
        $info = $this->getInfoInstance();
        $errorMsg = false;
        $availableTypes = explode(',', $this->getConfigData('cctypes'));
        $binNumber = $info->getAdditionalInformation('cc_bin');
        $last4 = $info->getCcLast4();
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
                . $info->getCcType()
            );
        }

        if (!empty($errorMsg)) {
            throw new LocalizedException($errorMsg);
        }

        return $this;
    }

    private function getServerCredentials(): array
    {
        $isStaging = boolval((integer)$this->getConfigData('staging_mode'));

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
        $isProduction = boolval((integer)$this->getConfigData('staging_mode')) ? false : true;

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

    private function currencyAllowAuthorization($payment)
    {
        $this->_logger->info('Validate if currency allow auth');
        $order = $payment->getOrder();
        $currency_code = $order->getOrderCurrencyCode();
        $this->_logger->info("currency => $currency_code");
        $allow = in_array($currency_code, $this->_currenciesThatSupportAuthorize);
        $this->_logger->info("_currenciesThatSupportAuthorize => ", $this->_currenciesThatSupportAuthorize);
        $this->_logger->info("allow => $allow");
        return $allow;
    }
}
