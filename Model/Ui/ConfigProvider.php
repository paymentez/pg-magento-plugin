<?php

namespace Paymentez\Module\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Paymentez\Module\Model\Payment;
use Magento\Checkout\Model\Cart;
use Magento\Framework\Validator\Exception as MagentoValidatorException;


class ConfigProvider implements ConfigProviderInterface 
{
    /**
     * @var array[]
     */
    protected $methodCodes = [
        'paymentez_module'     
    ];

    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $methods = [];
    
    /**
     * @var \Paymentez\Module\Model\Payment
     */
    protected $payment;

    /**
    * @var Magento\Checkout\Model\Cart
    */
    protected $cart;

    /**
    * @var
    */
    private $config = [];


    /**     
     * @param PaymentHelper $paymentHelper
     * @param Paymentez\Module\\ModelPayment $payment
     * @param Magento\Checkout\Model\Cart $cart
     */
    public function __construct(PaymentHelper $paymentHelper, 
        Payment $payment, 
        Cart $cart)
    {

        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $paymentHelper->getMethodInstance($code);
        }

        $this->cart = $cart;
        $this->payment = $payment;
    }

    /**
    * Magic method
    * @return mixed
    */
    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }

        throw new MagentoValidatorException(__("Undefined property {$name}"));
    }

    /**
    * Magic method
    * @return object \Paymentez\Module\Model\Ui\ConfigProvider
    */
    public function __set($name, $value)
    {
        if (property_exists($this, $name)) {
            $this->$name = $value;
            return $this;
        }

        return false;
    }

    /**
     * Set config template form need
     * @return object \Paymentez\Module\Model\Ui\ConfigProvider
     */
    public function setConfig()
    {                
        $config = [];

        foreach ($this->methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {
                $clientCredentials = $this->payment->getClientCredentials();

                if (empty($clientCredentials)) {
                    throw new MagentoValidatorException(__("Missing Paymentez settings."));
                }

                $config['payment']['paymentez']['app_code'] = $clientCredentials['code'];
                $config['payment']['paymentez']['app_key'] = $clientCredentials['api_key'];
                $config['payment']['paymentez']['env'] = $this->payment->getClientEnvironment();
                $config['payment']['paymentez']['is_active'] = $this->methods[$code]->isAvailable();

                $config['payment']['ccform']["availableTypes"][$code] = $this->payment->getActiveTypeCards();
                $config['payment']['total'] = $this->cart->getQuote()->getGrandTotal();
                $config['payment']['ccform']["hasVerification"][$code] = true;
                $config['payment']['ccform']["hasSsCardType"][$code] = false;
                $config['payment']['ccform']["months"][$code] = $this->getSpanishMonths();
                $config['payment']['ccform']["years"][$code] = $this->getYears();
                $config['payment']['ccform']["cvvImageUrl"][$code] = "https://www.ekwb.com/shop/skin/frontend/base/default/images/cvv.gif";
                $config['payment']['ccform']["ssStartYears"][$code] = $this->getStartYears();
            }
        }
                
        return $this->__set('config', $config);
    }

    public function getConfig()
    {
        return $this->setConfig()->__get('config');
    }

    private function getSpanishMonths()
    {
        return [
            '1' => "01 - Enero",
            '2' => "02 - Febrero",
            '3' => "03 - Marzo",
            '4' => "04 - Abril",
            '5' => "05 - Mayo",
            '6' => "06 - Junio",
            '7' => "07 - Julio",
            '8' => "08 - Augosto",
            '9' => "09 - Septiembre",
            '10' => "10 - Octubre",
            '11' => "11 - Noviembre",
            '12' => "12 - Diciembre"
        ];
    }
    
    private function getYears()
    {
        $years = [];
        $currentYear = (intval(date('Y')) - 1);

        for($i=1; $i <= 7; $i++) {
            $year = (string) ($currentYear + $i);
            $years[$year] = $year;
        }

        return $years;
    }
    
    private function getStartYears()
    {
        $years = [];
        $currentYear = intval(date("Y"));

        for($i=5; $i >= 0; $i--) {
            $year = ($currentYear - $i);
            $years["{$year}"] = "{$year}";
        }

        return $years;
    }
}