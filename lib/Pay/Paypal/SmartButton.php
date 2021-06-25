<?php

namespace Netflying\Pay\Paypal;

use Exception;
use Netflying\Common\Utils;

use PayPal\Api\ItemList;
use PayPal\Api\Payment;
use PayPal\Api\InputFields;
use PayPal\Api\WebProfile;
use PayPal\Exception\PayPalConnectionException;

/**
 * 一.初始化Paypal
 * $Paypal = new SmartButton($config);
 * 二. 前端引用js内容(在外部层输出即可)
 * $Paypal->js();
 * 三. paypal.js交互
 *      1. 初始化sdk:  js -> 'token_url' => {clientId: $Paypal->clientId()};
 *      2. 支付:
 *          2.1, js -> 'create_payment_url' => $Paypal->purchase();
 *          2.2, js -> 'approve_payment_url' => $Paypal->payment();
 *      3. 周期支付:
 *          3.1  js -> 'create_agreement_url' => $Paypal->createBillingAgreement();
 *          3.2  js -> 'approve_agreement_url' => $Paypal->executeAgreement();
 */

class SmartButton extends Paypal
{

    protected $config = [
        'sandbox'    => '',    //required
        'clientId'   => '',    //required
        'secret'     => '',    //required
        'account'    => '',    //required
        'currency'   => '',    //required
        'return_url' => '',    //required
        'max_age'    => 0,     //optional
        //createPlan 返回地址附加参数名
        'createPlan' => 'paypay_plan',
        //order payment 返回地址附加参数名
        'orderPayment' => 'order_payment',

    ];
    protected $excludeConfKey = [
        'max_age'
    ];

    public function __construct(array $config = [])
    {
        $this->config($config);
    }

    /**
     * 创建订单事务,返回smart button调起token
     * paypal.buttons({createOrder: function(){
     *      fetch().then().then();
     * }})
     * 
     * @param array $orderData
     * [
     *      'invoiceNumber' => '订单号', //默认自动生成
     *      'description' => '订单描述',
     *      'currency' => '货币',
     *      'total' => '订单总价', [required]
     *      'shipping' => '运费价格',
     *      'tax' => '税价',
     *      'subtotal' => '商品总价', [required]
     *      'returnUrl' => '成功返回地址',//默认根据配置拼装
     *      'cancelUrl' => '取消返回地址',
     *      //默认货运地址
     *      'shippingAddressOverride' => [
     *          'recipientName' => '',
     *          'line1' => '详细地址1', //optional
     *          'line2' => '详细地址2',//optional
     *          'city' => '城市',
     *          'countryCode' => '国家',
     *          'postalCode'=> '邮编',
     *          'state'=> '省/州',
     *          'phone'=> '手机号'  //optional
     * ]
     * ]
     * @param array $itemData
     * [
     *     0 => [
     *          'name' => '',
     *          'currency' => '',
     *          'quantity' => 1,
     *          'price' => 0
     *     ],
     *     1 => []
     * ]
     * @param string $billingAgreementToken [$this->agreement() -> billingToken] //暂无用
     * @return false|array
     * [
     *      'result' => '',
     *      'approvealUrl' => '',
     *      'token' => '前端调起所需的token',  
     * ]
     */
    public function purchase($data)
    {
        $post = Utils::modeData([
            'orderData' => [],
            'itemData'  => [],
            'billingAgreementToken' => ''
        ], $data);
        $orderData = $post['orderData'];
        $itemData = $post['itemData'];
        //$billingAgreementToken = $post['billingAgreementToken'];
        if (empty($orderData)) { //弱赋值
            $orderData = Utils::request();
        }
        //orderData = detailsMode + amountMode + orderMode + payerMode + RedirectMode
        $detailsMode = [
            'shipping' => 0,
            'tax' => 0,
            'subtotal' => 0,
        ];
        $amountMode = [
            'currency' => $this->config['currency'],
            'total' => 0,
        ];
        $orderMode = [
            'invoiceNumber' => '',
            'description' => '',
        ];
        $payerMode = [
            'paymentMethod' => 'paypal',
        ];
        $RedirectMode = [
            'returnUrl' => Utils::buildUri($this->config['return_url'], [$this->config['orderPayment'] => 1]),
            'cancelUrl' => Utils::buildUri($this->config['return_url'], [$this->config['orderPayment'] => 0]),
        ];
        $itemMode = [
            'name' => '',
            'currency' => $this->config['currency'],
            'quantity' => 1,
            'price' => 0
        ];
        $addressMode = [
            'recipientName' => '',
            'line1' => '', //optional
            'line2' => '', //optional
            'city' => '',
            'countryCode' => '',
            'postalCode' => '',
            'state' => '',
            'phone' => ''  //optional
        ];
        $orderData['invoiceNumber'] = !empty($orderData['invoiceNumber']) ? $orderData['invoiceNumber'] : Utils::dayOrderSn();
        // ### Payer
        // A resource representing a Payer that funds a payment
        // For paypal account payments, set payment method
        // to 'paypal'.
        $payerIns = $this->buildModeInstance($payerMode, $orderData, '\PayPal\Api\Payer');
        $Payer = $payerIns[0];
        // ### Itemized information
        // (Optional) Lets you specify item wise
        // information
        $ItemList = null;
        if (!empty($itemData)) {
            $itemIns = $this->buildModeInstance($itemMode, $itemData, '\PayPal\Api\Item');
            $ItemList = new ItemList();
            $ItemList->setItems($itemIns);
        }
        $addressData = Utils::modeData($addressMode, !empty($orderData['shippingAddressOverride']) ? $orderData['shippingAddressOverride'] : []);
        if (!empty($addressData['recipientName'])) {
            $shippingAddressIns = $this->buildModeInstance($addressData, [], '\PayPal\Api\ShippingAddress');
            $ItemList = !empty($ItemList) ? $ItemList : new ItemList();
            $ItemList->setShippingAddress($shippingAddressIns[0]);
        }
        // ### Additional payment details
        // Use this optional field to set additional
        // payment information such as tax, shipping
        // charges etc.
        $detailsIns = $this->buildModeInstance($detailsMode, $orderData, '\PayPal\Api\Details');
        // ### Amount
        // Lets you specify a payment amount.
        // You can also specify additional details
        // such as shipping, tax.
        $amountIns = $this->buildModeInstance($amountMode, $orderData, '\PayPal\Api\Amount');
        $Amount = $amountIns[0]->setDetails($detailsIns[0]);
        // ### Transaction
        // A transaction defines the contract of a
        // payment - what is the payment for and who
        // is fulfilling it.
        $transactionIns = $this->buildModeInstance($orderMode, $orderData, '\PayPal\Api\Transaction');
        $Transaction = $transactionIns[0]->setAmount($Amount);
        if (!empty($ItemList)) {
            $Transaction->setItemList($ItemList);
        }
        // ### Redirect urls
        // Set the urls that the buyer must be redirected to after 
        // payment approval/ cancellation.
        $redirectIns = $this->buildModeInstance($RedirectMode, $orderData, '\PayPal\Api\RedirectUrls');
        $Redirect = $redirectIns[0];
        $Payment = new Payment;
        $Payment->setIntent("sale")
            ->setPayer($Payer)
            ->setRedirectUrls($Redirect)
            ->setTransactions(array($Transaction));
        $apiContext = $this->apiContext();
        // if (!empty($billingAgreementToken)) {
        //     $Payment->addBillingAgreementToken($billingAgreementToken);
        // }
        try {
            if (empty($addressData['recipientName'])) {
                $inputFields = new InputFields();
                $inputFields->setAllowNote(true)
                    ->setNoShipping(1) // Important step
                    ->setAddressOverride(0);
                $webProfile = new WebProfile();
                $webProfile->setName($orderData['invoiceNumber'])
                    ->setInputFields($inputFields)
                    ->setTemporary(true);
                $createProfile = $webProfile->create($apiContext);
                $Payment->setExperienceProfileId($createProfile->getId()); // Important step
            }
            $ret = $Payment->create($apiContext);
            $approvalUrl = $ret->getApprovalLink();
            $result = json_decode($ret->toJson(), true);
        } catch (PayPalConnectionException $ex) {
            $this->error(['code' => $ex->getCode(), 'data' => $ex->getData()]);
            return false;
        } catch (Exception $ex) {
            $this->error(['code' => $ex->getCode(), 'data' => $ex->getMessage()]);
            return false;
        }
        $token = '';
        if (!empty($approvalUrl)) {
            $arr  = parse_url($approvalUrl);
            if (!empty($arr)) {
                $query    = isset($arr['query']) ? $arr['query'] : '';
                $queryArr = !empty($query) ? Utils::parseQuery($query) : [];
                $token = isset($queryArr['token']) ? $queryArr['token'] : '';
            }
        }
        return [
            'result' => $result,
            'approvealUrl' => $approvalUrl,
            'token' => $token
        ];
    }

    /**
     * 最后确认执行交易事务
     *
     * @param string $paymentId
     * @param string $payerId
     * @param Closure $changeFunc 根据国家地址更新金额(运费税费等) 
     * [
     *      'total' => 0,
     *      'currency' => '',
     *      'subtotal' => 0,
     *      'tax' => 0,
     *      'shipping' => 0,
     *      'country_code' => '',
     *      'postal_code'  => '',
     *      'state' => '',
     *      'city' => '',
     *      'line1' => '',
     * ]
     * @return false|array
     * [
     *      'id' => 'payid-',
     *      'invoice_number' => '订单号',
     *      'status' => 'approved',
     * ]
     */
    public function payment($paymentId = '', $payerId = '', $changeFunc = null)
    {
        if (empty($paymentId)) {
            $requestArr = Utils::request();
            $paymentId = isset($requestArr['paymentID']) ? $requestArr['paymentID'] : '';
            $payerId = isset($requestArr['payerID']) ? $requestArr['payerID'] : '';
        }
        try {
            $apiContext = $this->apiContext();
            $payment = Payment::get($paymentId, $apiContext);
            $paymentRet = json_decode($payment->toJson(), true);
            //最后执行确认，可以更改金额,比如运费等
            $transaction = isset($paymentRet['transactions'][0]) ? $paymentRet['transactions'][0] : [];
            $invoiceNumber = isset($transaction['invoice_number']) ? $transaction['invoice_number'] : '';
            $amountData = isset($transaction['amount']) ? $transaction['amount'] : [];
            $shippingData = isset($transaction['item_list']['shipping_address']) ? $transaction['item_list']['shipping_address'] : [];
            $amountMode = [
                'total' => 0,
                'currency' => '',
            ];
            $detailsMode = [
                'subtotal' => 0,
                'tax' => 0,
                'shipping' => 0,
            ];
            $shippingMode = [
                'country_code' => '',
                'postal_code'  => '',
                'state' => '',
                'city' => '',
                'line1' => '',
            ];
            $amountRet = Utils::modeData($amountMode, $amountData);
            $detailsRet = Utils::modeData($detailsMode, isset($amountData['details']) ? $amountData['details'] : []);
            $shippingRet = Utils::modeData($shippingMode, $shippingData);
            $transData = array_merge($amountRet, $detailsRet, $shippingRet);
            if ($changeFunc instanceof \Closure) {
                $transData =  Utils::modeData($transData, $changeFunc($transData));
            }
            $detailsIns = $this->buildModeInstance([
                'shipping' => 0,
                'tax' => 0,
                'subtotal' => 0,
            ], $transData, '\PayPal\Api\Details');
            $details = $detailsIns[0];
            $amountIns = $this->buildModeInstance([
                'currency' => 0,
                'total' => 0,
                'details' => $details,
            ], $transData, '\PayPal\Api\Amount');
            $amount = $amountIns[0];
            $transactionIns = $this->buildModeInstance([
                'amount' => $amount,
            ], [], '\PayPal\Api\Transaction');
            $transaction = $transactionIns[0];
            $executionIns = $this->buildModeInstance([
                'payerId' => $payerId,
                'add_transaction' => $transaction,
            ], [], '\PayPal\Api\PaymentExecution');
            $execution = $executionIns[0];
            $ret =  $payment->execute($execution, $apiContext);
            $paymentRet = json_decode($ret->toJson(), true);
            return array_merge([
                'id' => isset($paymentRet['id']) ? $paymentRet['id'] : '',
                'invoice_number' => $invoiceNumber,
                'status' => isset($paymentRet['state']) ? $paymentRet['state'] : '',
            ], $transData);
        } catch (PayPalConnectionException $ex) {
            $this->error(['code' => $ex->getCode(), 'data' => $ex->getData()]);
            return false;
        } catch (Exception $e) {
            $this->error(['code' => $e->getCode(), 'data' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 获取payment事务信息
     *
     * @param string $payment_id
     * @return void
     */
    public function getPayment($payment_id = '')
    {
        try {
            $ret = (new Payment)->get($payment_id, $this->apiContext());
            return json_decode($ret->toJson(), true);
        } catch (PayPalConnectionException $ex) {
            $this->error(['code' => $ex->getCode(), 'data' => $ex->getData()]);
            return false;
        } catch (Exception $ex) {
            $this->error(['code' => $ex->getCode(), 'data' => $ex->getMessage()]);
            return false;
        }
    }
}
