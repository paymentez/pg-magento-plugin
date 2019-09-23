<?php

namespace Paymentez\Module\Model;

use Paymentez\Module\Api\PaymentNotificationInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Response\Http;
use Magento\Sales\Model\Order;
use Magento\Framework\App\{
    RequestInterface,
    ResponseInterface
};


class PaymentNotification implements PaymentNotificationInterface
{
    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $response;

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $order;

    /**
     * CustomerAddress constructor.
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\App\ResponseInterface $response
     */
    public function __construct(RequestInterface $request,
                                ResponseInterface $response, Order $order)
    {
        $this->request = $request;
        $this->response = $response;
        $this->order = $order;
    }

    /**
     * Listener for Paymentez webhook
     *
     * @return \Magento\Framework\App\ResponseInterface
     * @api
     *
     */
    public function notificate()
    {
        $rawContent = $this->request->getContent();
        $params = json_decode($rawContent);
        $lastError = json_last_error();
        $response = $this->response;

        $response->setHeader('Content-Type', 'application/json');

        if ($lastError !== JSON_ERROR_NONE) {
            $response->setStatusCode(Http::STATUS_CODE_400);
            $response->setContent(json_encode([
                'error' => 'Invalid json structure.'
            ]));
        } else {
            $transactionRequested = $params->transaction;
            $orderId = $transactionRequested->dev_reference;

            if (empty($orderId)) {
                $response->setStatusCode(Http::STATUS_CODE_400);
                $response->setContent(json_encode([
                    'error' => 'Missing dev_reference.'
                ]));
            } else {
                $transactionStatus = $transactionRequested->status;
                $statusDetail = $transactionRequested->status_detail;
                $successValues = [1, "success"];
                $transactionId = $transactionRequested->id;

                if (!in_array($transactionStatus, $successValues) || $statusDetail != 3) {
                    $message = $transactionRequested->message;
                    $orderComment = "<strong>[PAYMENTEZ]</strong>:Main Status: {$transactionStatus}, Status detail: {$statusDetail}, Additional comment: {$message}.";

                    $order = $this->order->loadByIncrementId($transactionRequested->dev_reference);
                    $payment = $order->getPayment();

                    $order->setSate(Order::STATE_PENDING_PAYMENT);
                    $order->setStatus(Order::STATE_PENDING_PAYMENT);
                    $order->addStatusHistoryComment($orderComment);

                    $payment->setTransactionId($transactionId);
                    $payment->setIsTransactionClosed(0);

                    $payment->setTransactionAdditionalInfo($orderComment, $transactionId);

                    // Save order and payment
                    $payment->save();
                    $order->save();
                }

                $response->setStatusCode(Http::STATUS_CODE_204);
            }
        }

        $response->send();

        die;
    }
}
