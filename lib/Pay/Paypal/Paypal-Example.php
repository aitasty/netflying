<?php

namespace Netflying\Paypal;

use Netflying\Paypal\IpnListener;

use Braintree\Gateway;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;
use DateTime;
use JsonSerializable;
use Braintree\Exception\NotFound;
use Exception;

use PayPal\Api\VerifyWebhookSignature;
use PayPal\Api\Agreement;
use PayPal\Api\Payer;
use PayPal\Api\Plan;
use PayPal\Api\ShippingAddress;
use PayPal\Api\ChargeModel;
use PayPal\Api\Currency;
use PayPal\Api\MerchantPreferences;
use PayPal\Api\PaymentDefinition;
use PayPal\Api\Patch;
use PayPal\Api\PatchRequest;

use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payment;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Api\PaymentExecution;
use PayPal\Api\InputFields;
use PayPal\Api\WebProfile;
use PayPal\Common\PayPalModel;
use PayPal\Exception\PayPalConnectionException;

/**
 * 参考文档
 * paypal api: https://developer.paypal.com/docs/api/overview/
 * paypal example: https://github.com/paypal/PayPal-PHP-SDK/tree/master/sample/billing
 * 
 * nvp支付模式
 * 一. 初始化Paypal
 * $Paypal = new Paypal([
 *      'sandbox' => '',        //required
 *      'api_user' => '',       //required
 *      'api_password' => '',   //required
 *      'api_signature' => '',  //required
 *      'notify_url'  => '',    //required
 *      'return_url'  => '',    //required
 *      'cancel_url'  => '',    //required
 *      'api_version' => '',    //optional
 * ]);
 * 二. 支付
 * $Paypal->nvpCheckout(); //获取客户调起(跳转)地址
 * 三. 客户操作完毕(成功:服务端发起支付确认complete)
 * $Paypal->nvpPayStatus();
 * 
 * paypal smart button模式(异步通知ipn模式)
 * 一.初始化Paypal
 * $Paypal = new Paypal(
 *      'sandbox' => '',    //required
 *      'clientId'  => '',  //required
 *      'secret'    => '',  //required
 *      'account'   => '',  //required
 *      'currency' => '',   //required
 *      'max_age' => 0,     //optional    
 * );
 * 二. 前端引用js内容(在外部层输出即可)
 * $Paypal->js();
 * 三. paypal.js交互
 *      1. 初始化sdk:  js -> 'token_url' => {clientId: $Paypal->clientId()};
 *      2. 支付:
 *          2.1, js -> 'create_payment_url' => $Paypal->createPayment();
 *          2.2, js -> 'approve_payment_url' => $Paypal->executePayment();
 *      3. 周期支付:
 *          3.1  js -> 'create_agreement_url' => $Paypal->createBillingAgreement();
 *          3.2  js -> 'approve_agreement_url' => $Paypal->executeAgreement();
 * 
 * paypal braintree button模式(paypal集成braintree,异步通知ipn模式)
 * 一.初始化Paypal
 * $Paypal = new Paypal(
 *      'sandbox'   => '',          //required
 *      'clientId'  => '',          //required
 *      'secret'    => '',          //required
 *      'accessToken' => '',        //required
 *      'account'   => '',          //required
 *      'currency' => '',           //required
 *      'merchantAccounts' => [],   //required
 *      'max_age' => 0,             //optional    
 * );     
 * 二. 前端引用js内容
 * $Paypal->js();
 * 三. paypal.js交互
 *      1. 初始化sdk: 1. 初始化js:  js -> 'token_url' => {clientId: $Paypal->clientId(),token:$Paypal->btToken();};
 *      2. 支付:
 *          2.1, 成功approve回调 --提交(所需参数已自动封装,额外业务参数自行提交处理)--> $Paypal->braintreeCheckout();
 *      3. 周期支付:
 *          3.1  js -> 'create_agreement_url' => $Paypal->createBillingAgreement();
 *          3.2  js -> 'approve_agreement_url' => $Paypal->executeAgreement();
 *      4. 授权添加paypal支付方式(授权之后会非调起静默支付)
 *          4.1  js->approve回调获取payload => $Paypal->boundCustomer([...(必要token已自动封装),customer=>payload['details']]);
 * 
 * braintree paypal button模式(braintree集成paypal模式,异步通知webhook模式)
 * 一.初始化Paypal
 * $Paypal = new Paypal(
 *      'sandbox'   => '',          //required
 *      'clientId'  => '',          //required
 *      'secret'    => '',          //required
 *      'webhookId' => '',          //required
 *      'braintreeApp' => [],       //required
 *      'account'   => '',          //required
 *      'currency' => '',           //required
 *      'merchantAccounts' => [],   //required
 *      'max_age' => 0,             //optional    
 * ); 
 * 二. 前端引用js内容
 * $Paypal->js();
 * 三. paypal.js交互
 *      1. 初始化sdk: 1. 初始化js:  js -> 'token_url' => {clientId: $Paypal->clientId(),token:$Paypal->btToken();};
 *      2. 支付:
 *          2.1, 成功approve回调 --提交(所需参数已自动封装,额外业务参数自行提交处理)--> $Paypal->braintreeCheckout();
 *      3. 周期支付:
 *          3.1  js -> 'create_agreement_url' => $Paypal->createBillingAgreement();
 *          3.2  js -> 'approve_agreement_url' => $Paypal->executeAgreement();
 *      4. 授权添加paypal支付方式(授权之后会非调起静默支付)
 *          4.1  js->approve回调获取payload => $Paypal->boundCustomer([...(必要token已自动封装),customer=>payload['details']]);
 */
abstract class Paypal
{
    //init config
    protected $config = [
        'currency'  => 'USD',  //默认货币
        //js缓存有效时间,单位秒
        'max_age' => 0, //默认无缓存
        //braintree & (webhook or ipn)
        'clientId'  => '',
        'secret'    => '',
        'account'   => '',
        'webhookId' => '', //webhook
        'accessToken' => '', //braintree;paypal内braintree集成
        'sandbox' => 1,
        //createPlan 返回地址附加参数名
        'createPlan' => 'paypay_plan',
        //order payment 返回地址附加参数名
        'orderPayment' => 'order_payment',
        //nvp&ipn
        'api_user'      => '',
        'api_password'  => '',
        'api_signature' => '',
        'api_version'   => '61.0',
        'api_nvp0'      => 'https://api-3t.paypal.com/nvp',
        'api_nvp1'      => 'https://api-3t.sandbox.paypal.com/nvp',
        //nvp 取到TOKEN后需要跳转到的链接
        'nvp_pay_url0'   => 'https://www.paypal.com/webscr?cmd=_express-checkout&useraction=commit&token=',
        'nvp_pay_url1'   => 'https://www.sandbox.paypal.com/webscr?cmd=_express-checkout&useraction=commit&token=',
        //callback url
        'notify_url'  => '', //webhook or nvp
        'return_url'  => '', //webhook or nvp (一般为成功支付返回)
        'cancel_url'  => '', //nvp
    ];
    //braintree sdk
    protected $braintreeApp = [
        //'environment' => 'sandbox',
        'merchantId' => '',
        'publicKey' => '',
        'privateKey' => '',
    ];

    protected $excludeConfKey = [];
    //是否已初始化
    protected $initialized = 1;
    //错误信息
    protected $error = [
        'code' => 0,
        'msg'  => ''
    ];
    //braintree->Business->Merchant Accounts->add new   可收货币商号
    protected $merchantAccounts = [
        'USD' => 'USD',
    ];
    //braintree 网关
    protected $braintree = null;

    public function __construct(array $config = [])
    {
        foreach ($this->config as $k => $v) {
            $conf = isset($config[$k]) ? $config[$k] : $v;
            if ($conf === false && !in_array($k, $this->excludeConfKey)) {
                $this->initialized = 0;
                $this->error("{$k} false");
                break;
            }
            $this->config[$k] = $conf;
        }
        $braintreeApp = isset($config['braintreeApp']) ? $config['braintreeApp'] : [];
        if (!empty($braintreeApp)) {
            foreach ($this->braintreeApp as $k => $v) {
                $this->braintreeApp[$k] = isset($braintreeApp[$k]) ? $braintreeApp[$k] : '';
            }
            $this->braintreeApp['environment'] = $this->config['sandbox'] == 1 ? 'sandbox' : 'live';
        } else {
            $this->braintreeApp =  ['accessToken' => $this->config['accessToken']];
        }
        if (!empty($this->config['accessToken']) || !empty($braintreeApp)) {
            $this->braintree = $this->btGetway();
        }
        if (!empty($config['merchantAccounts'])) {
            $this->merchantAccounts = $config['merchantAccounts'];
        }
        foreach (['notify_url', 'return_url', 'cancel_url'] as $v) {
            $this->config[$v] = $this->domainUrl($this->config[$v]);
        }
    }

    public function purchase()
    {

    }

    /**
     * 获取braintree jssdk初始化token
     *
     * @param string $customerId  已授权用户ID [可选]
     * @return string   $clientToken  前端初始化应用ID
     */
    public function btToken($customer_id = '')
    {
        $optional = [];
        $customer_id = isset($_REQUEST['customer_id']) ? $_REQUEST['customer_id'] : $customer_id;
        if (!empty($customer_id)) {
            $optional = ["customerId" => $customer_id];
        }
        $clientToken = $this->braintree->clientToken()->generate($optional);
        return $clientToken;
    }
    /**
     * paypal客户端sdk初始化IDclient-id
     *
     * @return void
     */
    public function clientId()
    {
        return $this->config['clientId'];
    }
    /**
     * 获取所有支持货币
     *
     * @return void
     */
    public function allMerchantAccount()
    {
        $merchantAccountIterator = $this->braintree->merchantAccount()->all();
        $arr = [];
        foreach ($merchantAccountIterator as $merchantAccount) {
            $arr[] = $merchantAccount->jsonSerialize();
        }
        return $arr;
    }
    /**
     * 添加设置支付货币[也可在braintree或paypal控制台添加]
     *
     * @param [type] $currencyIsoCode
     * @return void
     */
    public function createForCurrency($currencyIsoCode)
    {
        $result = $this->braintree->merchantAccount()->createForCurrency([
            'currency' => $currencyIsoCode
        ]);
        if ($result->success) {
            return $result->merchantAccount->jsonSerialize();
        }
        return false;
    }

    /**
     * 绑定paypal用户
     *
     * @param array $data
     * @param boolean $makeDefault
     * @return false||array 
     * [
     *      'customerId' => '',   
     *      'billingAgreementId' => '',
     *      'email' => '',
     *      'token' => '',
     *      'default' => '',  //true,false
     *      'createdAt' => '',
     *      'updatedAt' => '',
     *      'payerInfo' => [ //paypal帐号信息
     *          'email' => '',
     *          'firstName' => '',
     *          'lastName' => '',
     *          'countryCode' => '',
     *          'payerId' => '', //注意与customerID区别
     *          'shippingAddress' => null
     *      ]
     * ]
     */
    public function boundCustomer(array $data, $makeDefault = false)
    {
        $nonce = $data['nonce'];
        $deviceData = $data['deviceData'];
        $customerId = isset($data['customerId']) ? $data['customerId'] : '';
        $customer = isset($data['customer']) ? $data['customer'] : [];
        if (empty($customerId)) {
            $customerId = $this->createCustomerId($customer);
            if (empty($customerId)) {
                $this->error('customerId empty', -1);
                return false;
            }
        }
        $options = [
            'customerId' => $customerId,
            'paymentMethodNonce' => $nonce,
            'deviceData' => $deviceData
        ];
        if ($makeDefault) {
            $options['options'] = [
                'makeDefault' => true
            ];
        }
        $result = $this->braintree->paymentMethod()->create($options);
        if ($result->success) {
            $data = $this->jsonFormat($result->paymentMethod->jsonSerialize());
            return $data;
        }
        $this->error('paymentMethod create error', -2);
        return false;
    }

    /**
     * 提交结帐信息
     *
     * @param array $data
     * [
     *   'nonce'=>'授权随机数' //必需
     *   'deviceData' => '用户设备号' //必需
     *   'amount'  => '订单金额', //必需 String(小数点两位), 必需大于0,货币跟随merchant account 
     *   'currency' => '提交的货币', //必需
     *   'orderId' => '订单编号', //可选, 为空则自动生成
     * ]
     * @return bool|array [
     *      'orderId' => '订单编号',
     *      'amount'  => '订单金额',
     *      'currency' => '订单货币',
     *      'paymentId' => '支付单号,运单号(paypal专用,其它付款结构不知是否一样)',
     *      'payerEmail' => 'paypal支付帐号(paypal专用)',
     *      'authorizationId' => '授权唯一事务ID(paypal专用)',
     *      'fee_amount' => '手续费',
     *      'fee_currency' => '手续费货币',
     * ]
     */
    public function braintreeCheckout(array $data)
    {
        $nonce = $data['nonce'];
        $deviceData = $data['deviceData'];
        $amount = $data['amount'];
        $currency = isset($data['currency']) ? strtoupper($data['currency']) : '';
        $orderId = isset($data['orderId']) ? $data['orderId'] : $this->dayOrderSn();
        $merchantAccountId = $this->merchantAccountId($currency);
        $options = [
            'orderId' => $orderId,
            'amount'  => $amount,
            'paymentMethodNonce' => $nonce,
            'deviceData' => $deviceData,
            'options' => [
                'submitForSettlement' => True
            ]
        ];
        if (!empty($merchantAccountId)) {
            $options['merchantAccountId'] = $merchantAccountId; //**指定收款货币帐号
        }
        $result = $this->braintree->transaction()->sale($options);
        if ($result->success) {
            $transaction = $this->jsonFormat($result->transaction->jsonSerialize());
            $paypal = isset($transaction['paypal']) ? $transaction['paypal'] : [];
            $transaction['orderId'] = $orderId;
            $transaction['amount']  = $amount;
            $transaction['currency'] = isset($transaction['currencyIsoCode']) ? $transaction['currencyIsoCode'] : '';
            $transaction['paymentId'] = isset($paypal['paymentId']) ? $paypal['paymentId'] : '';
            $transaction['payerEmail'] = isset($paypal['payerEmail']) ? $paypal['payerEmail'] : '';
            $transaction['authorizationId'] = isset($paypal['authorizationId']) ? $paypal['authorizationId'] : ''; //授权唯一事务ID
            $transaction['fee_amount'] = isset($paypal['transactionFeeAmount']) ? $paypal['transactionFeeAmount'] : '';
            $transaction['fee_currency'] = isset($paypal['transactionFeeCurrencyIsoCode']) ? $paypal['transactionFeeCurrencyIsoCode'] : '';
            $transaction['payer_id'] = $paypal['payerId'] ?? 0;
            return $transaction;
        } else {
            $jsonArr = $result->jsonSerialize();
            $message = isset($jsonArr['message']) ? $jsonArr['message'] : 'error';
            $this->error($message);
            return false;
        }
    }

    /**
     * nvp创建快捷支付链接
     * [在paypal创建待支付确认订单]
     * @param array $data [订单信息]
     * [
     *      'order_sn' => '', //require
     *      'amount'   => '', //require
     *      'currency' => '', //require
     *      'description' => '',
     *      'site_name' => '',
     * ]
     * @return false|array 
     * [
     *      'payUrl' =  '快捷支付地址'
     *      'data'   => '提交的订单信息',//含自动生成的订单号
     * ]
     */
    public function nvpCheckout($data = [])
    {
        $post = $this->modeMerge([
            'order_sn' => null,
            'amount'   => null,
            'currency' => null,
            'description' => '',
            'site_name' => '',
        ], $data);
        if (empty($post['order_sn'])) {
            $post['order_sn'] = $this->dayOrderSn();
        }
        //verify require
        $post = $post ? $post : [];
        foreach ($post as $k => $v) {
            if (is_null($v)) {
                $this->error('nvpCheckout.is_null.' . $k);
                return false;
            }
        }
        $token = $this->nvpCheckoutToken($post);
        $payUrl = $this->getNvpPayUrl($token);
        return [
            'pay_url' => $payUrl,
            'data'   => $data,
        ];
    }
    /**
     * 待确认支付token订单详情,
     * 用户支付完成,商家服务端作最后确认支付提交,并完成订单
     * @param [type] $token
     * @return void
     */
    public function nvpTokenDetails($token = NULL)
    {
        if (empty($token)) {
            return false;
        }
        $fields = array(
            'TOKEN' => $token,
        );
        $returnData = $this->nvpRequest('GetExpressCheckoutDetails', $fields);
        return $returnData;
    }
    /**
     * 客户端返回地址处理
     *
     * @return false|array
     * [
     *      'status'=>'同步回调状态'  //1:支付成功,0:未支付
     *      'code' => '' // -1:取消; -2:无效token; 0:未支付; 1:成功; 
     *      'data' => [ //token有效有数据
     *          'order_sn' => '订单编号',
     *          'amount' => '',
     *          'currency' => '',
     *          'transaction_id' => '', //成功支付事务ID
     *          'shipping' => [  //支付成功,有paypal收货地址
     *              'first_name':'',
     *              'last_name':'',
     *              'email': '',
     *              'country': '',
     *              'state': '',
     *              'city': '',
     *              'zipcode':'',
     *              'street':'',
     *              'phone':'',
     *              'district':'',
     *          ]
     *      ]
     * ]
     */
    public function nvpPayStatus()
    {
        $request = $_REQUEST;
        $PayerID   = isset($request['PayerID']) ? $request['PayerID'] : ''; //支付者ID,取消则为空
        $token = isset($request['token']) ? trim($request['token']) : ''; //用于最终支付
        if (empty($token)) {
            return [
                'status' => 0,
                'code'   => -2,
                'data'   => [],
            ];
        }
        if (empty($PayerID)) {
            return [
                'status' => 0, //未支付
                'code' => -1, //取消
                'data' => [],
            ];
        }
        $detail = $this->nvpTokenDetails($token); //请求查询token
        if (empty($detail)) { //token错误
            return [
                'status' => 0, //未支付
                'code' => -2, //无效token
                'data' => [],
            ];
        }
        $status = 0;
        $code = 0; //默认用户未支付确认
        $payerStatus = isset($detail['PAYERSTATUS']) ? $detail['PAYERSTATUS'] : '';
        if (!empty($payerStatus)) { //客户端已确认
            //服务端发起最后确认
            $fields   = array(
                'PAYERID'          => $PayerID,
                'AMT'              => $detail['AMT'],
                'ITEMAMT'          => $detail['AMT'],
                'CURRENCYCODE'     => $detail['CURRENCYCODE'],
                'RETURNFMFDETAILS' => 1,
                'TOKEN'            => $token,
                'PAYMENTACTION'    => 'Sale', //Sale或者...
                'NOTIFYURL'        => $this->config['notify_url'],
                'INVNUM'           => $detail['INVNUM'],
                'CUSTOM'           => '',
                //'SHIPPINGAMT'=>'', //总运费
                //'INSURANCEAMT' =>'', //货物保险费用
            );
            $response = $this->nvpRequest('DoExpressCheckoutPayment', $fields);
            if (!empty($response)) {
                $status = 1;
                $code = 1;
            } else { //服务端提交异常,可查看$this->error()信息
                return false;
            }
        }
        $fullname = isset($detail['SHIPTONAME']) ? $detail['SHIPTONAME'] : '';
        $fullnameArr = !empty($fullname) ? explode(' ', $fullname) : [];
        $first_name = isset($fullnameArr[0]) ? $fullnameArr[0] : '';
        $last_name  = isset($fullnameArr[1]) ? $fullnameArr[1] : '';
        $street = !empty($detail['SHIPTOSTREET']) ? $detail['SHIPTOSTREET'] : '';
        $street2 = !empty($detail['SHIPTOSTREET2']) ? ' ' . $detail['SHIPTOSTREET2'] : '';
        $data = [
            'order_sn'  => isset($detail['INVNUM']) ? $detail['INVNUM'] : '',
            'amount'    => isset($detail['AMT']) ? $detail['AMT'] : '', //SHIPPINGAMT,HANDLINGAMT,TAXAMT,INSURANCEAMT,SHIPDISCAMT
            'currency'  => isset($detail['CURRENCYCODE']) ? $detail['CURRENCYCODE'] : '',
            'transaction_id' => isset($detail['TRANSACTIONID']) ? $detail['TRANSACTIONID'] : '', //成功支付事务ID
            'shipping'  => [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email'     => isset($detail['EMAIL']) ? $detail['EMAIL'] : '',
                'phone'     => '',
                'country'   => isset($detail['SHIPTOCOUNTRYCODE']) ? $detail['SHIPTOCOUNTRYCODE'] : '',
                'state'  => isset($detail['SHIPTOSTATE']) ? $detail['SHIPTOSTATE'] : '',
                'city'      => isset($detail['SHIPTOCITY']) ? $detail['SHIPTOCITY'] : '',
                'district'  => '',
                'zipcode'   => isset($detail['SHIPTOZIP']) ? $detail['SHIPTOZIP'] : '',
                'street'   => $street . $street2,
            ],
        ];
        return [
            'status' => $status,
            'code' => $code,
            'data' => $data,
        ];
    }

    /**
     * 回调通知 [Automatic judgment ipn or webhooks data]
     * 验证回调数据,验证失败,直接报非200错误码,为了可重复通知. 200代表通知成功无需再通知
     * @return false|array
     * [
     *      'type' => '支付类型', //paypal
     *      'pay_id' => 'ipn:状态事务ID;ipn_track_id',  //wk:pp交易号或状态事务ID,第一次交易生成时为PAYID- (Payment ID),之后为状态事务ID(Authorization Unique Transaction ID)
     *      'parent_payment' => 'ipn:pp运单号;txn_id', //wk:第一次交易生成时为空,之后为状态事务ID(Authorization Unique Transaction ID)
     *      'order_sn' => '', //订单号
     *      'pay_time' => '', //创建时间
     *      'pay_amount' => '',  //支付金额, 负号为退款
     *      'currency' => '', //支付货币
     *      'pay_fee' => '',          //手续费
     *      'pay_fee_currency' => '',     //手续费货币
     *      'resource_type' =>  '', //交易类型,如:sale,refund等[绝对小写],支付还是退款.
     *      'event_type' => 'ipn:事件类型 express_checkout',    //事件类型,如:PAYMENT.SALE.COMPLETED
     *      'state' => '', //状态[绝对小写] 事件类型 express_checkout
     *      'pay_status_str' => '支付状态文本', //wk:事件类型,如:PAYMENT.SALE.COMPLETED
     *      'summary' => $summary, //交易描述[max:100]
     *      'pay_merchant' => $this->config['account'],  //收款帐号
     *      'pay_status' => '0||1', //-1:退款, 0:拒绝支付不成功, 1:支付成功
     * ]
     */
    public function notify()
    {
        $requestBody = file_get_contents('php://input');
        if (empty($requestBody)) {
            $this->error('Paypal notify $requestBody empty');
            return false;
        }
        //if ipn
        $post = [];
        try {
            @parse_str($requestBody, $post);
        } catch (\Exception $e) {
        }
        $notify = [];
        if (!empty($post)) { //&& isset($post['ipn_track_id'])
            $verify = $this->paypalIpnVerify($post);
            $notify = $this->paypalIpnData($verify);
        } else {
            $verify = $this->paypalWebhookVerify($requestBody);
            $notify = $this->paypalWebhookData($verify);
        }
        if (empty($notify)) {
            $this->error('[Paypal notify $notify empty]' . $requestBody);
            return false;
        }
        $notify['type'] = 'paypal';
        $notify['pay_status'] = 0;
        if ($notify['resource_type'] == 'refund') { //退款
            if ($notify['state'] == 'completed' || $notify['state'] == 'reversed' || $notify['state'] == 'refunded') { //完成
                $notify['pay_status'] = -1;
            }
        } elseif ($notify['resource_type'] == 'sale' || $notify['resource_type'] == 'payment') { //支付
            if ($notify['state'] == 'completed') { //完成
                $notify['pay_status'] = 1;
            } elseif ($notify['state'] == 'denied') { //失败状态:拒绝
                $notify['pay_status'] = 0;
            }
            if ($notify['pay_amount'] < 0) { //REVERSED,REFUNDED(金额为负) 退款
                $notify['pay_status'] = -1;
            }
        }
        return $notify;
    }

    /**
     * 创建帐单周期计划 create plan
     * 
     * 默认创建无收费模式的计划
     * 
     * @param array $planData 
     * [
     *      'name' => '计划名称',
     *      'description' => '计划描述',
     *      'type' => '', //FIXED:The plan has a fixed number of payment cycles. INFINITE:The plan has infinite, or 0, payment cycles.
     *      
     *       //The URL where the customer can approve the agreement.
     *      'returnUrl' => '',
     *      //The URL where the customer can cancel the agreement.
     *      'cancelUrl' => '',
     *      //Indicates whether PayPal automatically bills the outstanding balance in the next billing cycle. 
     *      //The outstanding balance is the total amount of any previously failed scheduled payments.
     *      //NO: PayPal does not automatically bill the customer the outstanding balance.
     *      //YES: PayPal automatically bills the customer the outstanding balance.
     *      'autoBillAmount' => '',
     *      //The action if the customer's initial payment fails.
     *      //CONTINUE: The agreement remains active and the failed payment amount is added to the outstanding balance. 
     *      //If auto-billing is enabled, PayPal automatically bills the outstanding balance in the next billing cycle.
     *      //CANCEL: PayPal creates the agreement but sets its state to pending until the initial payment clears.
     *      //If the initial payment clears, the pending agreement becomes active. If the initial payment fails, the pending agreement is canceled.
     *      'initialFailAmountAction' => '',
     *      //The maximum number of allowed failed payment attempts. The default value, which is 0, defines infinite failed payment attempts.
     *      'maxFailAttempts' => '',
     *      //The currency and amount of the set-up fee for the agreement. 
     *      //This fee is the initial, non-recurring payment amount that is due immediately when the billing agreement is created. 
     *      //Can be used as the initial amount to trigger the initial_fail_amount_action. The default for the amount is 0.
     *      'setupFee' => [
     *          'value' => '',
     *          'currency' => '',
     *      ]
     * ]
     * @param array $definitionData (计划收费模式,default:无收费只是为了开通援权)
     * [[ //数字二维数组
     *      'name' => '',
     *      //The payment definition type. Each plan must have at least one regular payment definition and, optionally, a trial payment definition.
     *      //Each definition specifies how often and for how long the customer is charged.
     *      //TRIAL,REGULAR.
     *      'type' => '',
     *      //The frequency of the payment in this definition. Possible values: WEEK,DAY,YEAR,MONTH.
     *      'frequency' => '',
     *      //The interval at which the customer is charged. Value cannot be greater than 12 months.
     *      'frequencyInterval' => '',
     *      //The number of payment cycles. For infinite plans with a regular payment definition, set cycles to 0.
     *      'cycles' => '',
     *      'amount' => [
     *          'currency' => '',
     *          'value' => '',
     *      ],
     *      //charge_models An array of shipping fees and taxes.
     *      'chargeModels' => [ //二维数字数组 最多也就2个
     *          0 => [
     *              'type' => '', //TAX,SHIPPING.
     *              'amount' => [
     *                  'value' => 0,
     *                  'currency' => '',
     *              ]
     *          ],
     *          1 => [....]
     *      ]
     * ]]
     * @return string $planId
     */
    public function createPlan($planData = [], $definitionData = [])
    {
        // dataMode
        $planMode = [
            'name' => $this->config['account'],
            'description' => 'paypal',
            'type' => 'INFINITE'
        ];
        $definitionMode = [ //必须存在一个收费计划
            'name' => 'paypal',
            'type' => 'REGULAR', //TRIAL,REGULAR
            'frequency' => 'YEAR',
            'frequencyInterval' => '1',
            'cycles' => 0, //type:INFINITE, cycles must be cycles 0
            'amount' => [
                'value' => 0.01, //必须大于0
                'currency' => $this->config['currency']
            ],
            'chargeModels' => [
                'type' => 'SHIPPING',
                'amount' => [
                    'value' => 0,
                    'currency' => $this->config['currency']
                ]
            ]
        ];
        $merchantMode = [
            'returnUrl' => $this->buildUri($this->config['return_url'], [$this->config['createPlan'] => 1]), //使用通用地址并加入特定标识
            'cancelUrl' => $this->buildUri($this->config['cancel_url'], [$this->config['createPlan'] => 0]),
            'autoBillAmount' => 'yes',
            'initialFailAmountAction' => 'CONTINUE',
            'maxFailAttempts' => 0,
            'setupFee' => [
                'value' => 0,
                'currency' => $this->config['currency']
            ]
        ];
        // 1. Create a new billing plan
        $planIns = $this->buildModeInstance($planMode, $planData, '\PayPal\Api\Plan');
        $Plan = $planIns[0];
        // 2. Set billing plan definitions
        if (isset($definitionData['chargeModels']) && !isset($definitionData['chargeModels'][0])) {
            $definitionData['chargeModels'] = [$definitionData['chargeModels']]; //自动合为二维数字数组
        }
        $definitionIns = $this->buildModeInstance(
            $definitionMode,
            $definitionData,
            '\PayPal\Api\PaymentDefinition',
            [
                'amount' => '\PayPal\Api\Currency',
                'chargeModels' => [
                    'class' => '\PayPal\Api\ChargeModel',
                    'keyClass' => [
                        'amount' => '\PayPal\Api\Currency'
                    ]
                ]
            ]
        );
        //3. Set merchant preferences
        $merchantIns = $this->buildModeInstance($merchantMode, $planData, '\PayPal\Api\MerchantPreferences', ['setupFee' => '\PayPal\Api\Currency']);
        $Plan->setPaymentDefinitions($definitionIns);
        $Plan->setMerchantPreferences($merchantIns[0]);
        //create plan
        $planId = 0;
        try {
            $apiContext = $this->apiContext();
            $createdPlan = $Plan->create($apiContext);
            $patch = new Patch();
            $value = new PayPalModel('{"state":"ACTIVE"}');
            $patch->setOp('replace')
                ->setPath('/')
                ->setValue($value);
            $patchRequest = new PatchRequest();
            $patchRequest->addPatch($patch);
            $createdPlan->update($patchRequest, $apiContext);
            $planId = $createdPlan->getId();
        } catch (PayPalConnectionException $ex) {
            $err = json_encode(['code' => $ex->getCode(), 'data' => $ex->getData()]);
            $this->error($err);
            return false;
        } catch (Exception $ex) {
            $err = json_encode(['code' => $ex->getCode(), 'data' => $ex->getMessage()]);
            $this->error($err);
            return false;
        }
        return $planId;
    }
    /**
     * 获取已创建的plan计划列表
     *
     * @param array $data
     * [
     *      //The number of plans to list on a single page. 
     *      //For example, if page_size is 10, each page shows ten plans. A valid value is a non-negative, non-zero integer.
     *      'page_size' => 20,
     *      //The zero-indexed number of the first page that begins the set of pages that are returned in the response.
     *      'page' => 0,
     *      //Filters the plans in the response by a plan status. Possible values: CREATED,ACTIVE,INACTIVE,ALL.
     *      'status' => 'ALL',
     *      //Indicates whether the response includes the total_items and total_pages fields. Value is yes or no
     *      'total_required' => 'yes', //是否显示总页数
     *      
     * ]
     * @return false|arrya []
     */
    public function planList($data = [])
    {
        $mode = [
            'page' => 0,
            'page_size' => 20,
            'status' => 'ALL',
            'total_required' => 'yes',
        ];
        $data = $this->modeMerge($mode, $data);
        try {
            $ret = Plan::all($data, $this->apiContext());
            return json_decode($ret->toJson(), true);
        } catch (Exception $e) {
            $err = json_encode(['code' => $e->getCode(), 'data' => $e->getMessage()]);
            $this->error($err);
            return false;
        }
    }
    /**
     * planId获取计划详情
     *
     * @param string $planId
     * @return false|array []
     */
    public function getPlan($planId)
    {
        if (empty($planId)) {
            return false;
        }
        try {
            $ret = Plan::get($planId, $this->apiContext());
            return json_decode($ret->toJson(), true);
        } catch (Exception $e) {
            $err = json_encode(['code' => $e->getCode(), 'data' => $e->getMessage()]);
            $this->error($err);
            return false;
        }
    }

    /**
     * Undocumented function
     *
     * @param string $planId  <required>,根据createPlan获取
     * @param array $agreementData //<required>
     * [
     *      //Name of the agreement.[128]
     *      'name' => '',
     *      //The agreement description. [128]
     *      'description' => '',
     *      //the date and time when this agreement begins,
     *      //The start date must be no less than 24 hours after the current date as the agreement can take up to 24 hours to activate.
     *      //example format: 2019-06-17T9:45:04Z
     *      'startDate' => '', //required
     *      
     *      'paymentMethod' => '', //<required>. The payment method. Possible values: bank,paypal.
     * ]
     * @param array $shippingData (The shipping address for a payment. Must be provided if it differs from the default address.)
     * [
     *     //<required> The two-character ISO 3166-1 code that identifies the country or region.
     *     'countryCode' => '',
     *     //<required> The city name.
     *     'city' => '',
     *     //<required> The first line of the address. For example, number or street.
     *     'line1' => '',
     *     //The code for a US state or the equivalent for other countries
     *     'state' => '',
     *     //The second line of the address. For example, suite or apartment number.
     *     'line2' => '',
     *     //The postal code, which is the zip code or equivalent. Typically required for countries with a postal code or an equivalent.
     *     'postalCode' => '',
     *     //The name of the recipient at this address.
     *     'recipientName' => '', 
     *     'phone' => '',
     *     //The default shipping address of the payer.
     *     'defaultAddress' => '',
     *     //Shipping Address marked as preferred by Payer.
     *     'preferredAddress' => '',
     * ]
     * @return false|array 
     * [
     *    'agreement' => '',
     *    'approvealUrl' => '',   
     *    'billingToken' => '',
     * ]
     */
    public function agreement($planId, $agreementData = [], $shippingData = [])
    {
        //mode
        $agreementMode = [
            'name' => $planId,
            'description' => $planId,
            'startDate' => gmdate("Y-m-d\TH:i:s\Z", strtotime("+1 day")), //The start date must be no less than 24 hours
        ];
        $payerMode = [
            'paymentMethod' => 'paypal',
        ];
        $shippingMode = [
            'countryCode' => '',
            'state' => '',
            'city' => '',
            'line1' => '',
            'line2' => '',
            'postalCode' => '',
            'recipientName' => '', //Name of the recipient at this address.
            'phone' => '',
            'defaultAddress' => '',
            'preferredAddress' => '', //首选收货地址
        ];
        //create instance
        $agreementIns = $this->buildModeInstance($agreementMode, $agreementData, '\PayPal\Api\Agreement');
        $Agreement = $agreementIns[0];
        // Add Plan ID
        // Please note that the plan Id should be only set in this case.
        $Plan = new Plan();
        $Plan->setId($planId);
        $Agreement->setPlan($Plan);
        // Add Payer
        $payerIns = $this->buildModeInstance($payerMode, $agreementData, '\PayPal\Api\Payer');
        $Payer = $payerIns[0];
        $Agreement->setPayer($Payer);
        // Add shipping
        if (!empty($shippingData['countryCode'])) {
            $shippingIns = $this->buildModeInstance($shippingMode, $shippingData, '\PayPal\Api\ShippingAddress');
            $Shipping = $shippingIns[0];
            $Agreement->setShipping($Shipping);
        }
        // ### Create Agreement
        try {
            // Please note that as the agreement has not yet activated, we wont be receiving the ID just yet.
            $ret = $Agreement->create($this->apiContext());
            // ### Get redirect url
            // The API response provides the url that you must redirect
            // the buyer to. Retrieve the url from the $agreement->getApprovalLink()
            $approvalUrl = $ret->getApprovalLink();
            $result = json_decode($ret->toJson(), true);
        } catch (PayPalConnectionException $ex) {
            $err = json_encode(['code' => $ex->getCode(), 'data' => $ex->getData()]);
            $this->error($err);
            return false;
        } catch (Exception $ex) {
            $err = json_encode(['code' => $ex->getCode(), 'data' => $ex->getMessage()]);
            $this->error($err);
            return false;
        }
        $token = '';
        if (!empty($approvalUrl)) {
            $arr  = parse_url($approvalUrl);
            if (!empty($arr)) {
                $query    = isset($arr['query']) ? $arr['query'] : '';
                $queryArr = !empty($query) ? $this->parseQuery($query) : [];
                $token = isset($queryArr['token']) ? $queryArr['token'] : '';
            }
        }
        return [
            'agreement' => $result,
            'approvealUrl' => $approvalUrl,
            'billingToken' => $token
        ];
    }

    /**
     * 创建smart button的调起token
     * paypal.buttons({createBillingAgreement:fetch function(){}})
     * @param array $planData [required]
     * [
     *     'plan_id' => '', //可以直接使用已创建过的计划id
     *      //或
     *      'name' => '计划名称',
     *      'description' => '计划描述',
     *      'type' => 'INFINITE', //FIXED:The plan has a fixed number of payment cycles. INFINITE:The plan has infinite, or 0, payment cycles.
     *      //更多高级选项参考 $this->createPlan() 说明
     * ]
     * @param array $definitionData [required]
     * [
     *      0 => [
     *          'name' => '',
     *          'type' => 'REGULAR',  //TRIAL,REGULAR.
     *          'frequency' => 'YEAR', //WEEK,DAY,YEAR,MONTH.
     *          'frequencyInterval' => '1', //Value cannot be greater than 12 months.
     *          'cycles' => '0', //周期次数，如果无限计划值为0
     *          'amount' => [
     *              'currency' => '',
     *              'value' => '',
     *          ],
     *         'chargeModels' => [ //最多2个,默认shipping 0
     *               0 => [
     *                   'type' => '', //TAX,SHIPPING.
     *                   'amount' => [
     *                       'value' => 0,
     *                       'currency' => '',
     *                   ]
     *               ],
     *               1 => [....]
     *      ],
     *      ... 或多个 ...  
     *      //更多高级选项参考 $this->createPlan() 说明
     * ]
     * @param array $agreementData [required,必需但已有默认值]
     * [
     *      'name' => '协议名称', //默认使用planId,最多[128]
     *      'description' => '协议描述', //默认使用planId,最多[128]
     *      'startDate' => '', //默认第二天生效
     * ]
     * @param array $shippingData  [optional]
     * [
     *     'countryCode' => '2位国家ISO码',//[required] 如:US
     *     'city' => '', //[required]
     *     'line1' => '详细地址1', //[required]
     *     'state' => '',
     *     'line2' => '详细地址2',
     *     'postalCode' => '',
     *     'recipientName' => '', 
     *     'phone' => '',
     *     'defaultAddress' => '', //0,1
     *     //Shipping Address marked as preferred by Payer.
     *     'preferredAddress' => '',
     * ]
     * @return void
     */
    public function createBillingAgreement($planData = [], $definitionData = [], $agreementData = [], $shippingData = [])
    {
        if (!empty($planData['plan_id'])) {
            $planId = $planData['plan_id'];
        } else {
            $planId = $this->createPlan($planData, $definitionData);
        }
        if (empty($planId)) {
            return false;
        }
        return $this->agreement($planId, $agreementData, $shippingData);
    }

    /**
     *  Execute a billing agreement after buyer approval by passing the payment token to the request URI.
     *
     * @param string $token [createBillingAgreement -> orderID]
     * @return false|array
     * [
     *      'id' => '',
     *      'state' => 'Active',//状态
     *      'description' => '帐单计划描述',
     *      'start_date' => '开始时间',
     *      'payer' => [
     *              'payment_method' => 'paypal', //固定
     *              'status' => 'verified',  //验证状态
     *              'payer_info' => [
     *                  'email' => '',
     *                  'first_name' => '',
     *                  'last_name' => '',
     *                  'payer_id' => '',
     *                  'shipping_address' => [
     *                      'recipient_name' => '',
     *                      'line1' => '',
     *                      'line2' => '',
     *                      'city' => '',
     *                      'state' => '',
     *                      'postal_code' => '',
     *                      'country_code' => ''
     *                  ]
     *              ]
     *      ],
     *      'shipping_address' => [
     *          //payer -> payer_info -> shipping_address
     *      ],
     *      'plan' => [
     *          'payment_definitions' => [ //周期计划
     *                  0 => [
     *                      'type' => '',
     *                      'frequency' => 'YEAR',
     *                      'frequency_interval' => '1',
     *                      'cycles' => 0, 
     *                      'amount' => [
     *                          'currency' => '',
     *                          'value' => 0.01
     *                      ]
     *                      'charge_models' => [ //TAX OR SHIPPING
     *                          0 => [
     *                              'type' => 'TAX'
     *                              'amount' => [
     *                                  'currency' => '',
     *                                  'value' => 0.00
     *                              ]
     *                          ],
     *                          1 => [], //可选
     *                      ]
     *                  ],
     *                  1 => [],
     *                  .....
     *           ],
     *      ]
     *      'merchant_preferences' => [
     *              'setup_fee' => [
     *                  'currency' => '',
     *                  'value' => '',
     *              ]
     *              'max_fail_attempts' => 0
     *              'auto_bill_amount' => 'YES'
     *      ],
     *      'links' => [
     *          0 => [
     *              'href' => '',
     *              'rel' => '', //suspend, re_activate, cancel, self,  self
     *              'method' => 'POST',
     *          ],
     *          1 => [...]
     *          2 => [...]
     *          3 => [...]
     *      ],
     *      'agreement_details' => [
     *          'outstanding_balance' => [
     *                  'currency' => 'USD',
     *                  'value' => 0.00
     *          ],
     *          'cycles_remaining' => '',
     *          'cycles_completed' => '',
     *          'next_billing_date' => '',
     *          'failed_payment_count' => '',
     *      ]
     * ]
     */
    public function executeAgreement($token = '')
    {
        $requestBody = file_get_contents('php://input');
        $requestArr = !empty($requestBody) ? json_decode($requestBody, true) : '';
        if (empty($token)) {
            $token = isset($requestArr['token']) ? $requestArr['token'] : '';
        }
        $Agreement = new Agreement();
        try {
            $apiContext = $this->apiContext();
            $Agreement->execute($token, $apiContext);
            $agreementId = $Agreement->getId();
            $ret = Agreement::get($Agreement->getId(), $apiContext);
            $paymentRet = json_decode($ret->toJson(), true);
            return $paymentRet;
        } catch (PayPalConnectionException $ex) {
            $err = json_encode(['code' => $ex->getCode(), 'data' => $ex->getData()]);
            $this->error($err);
            return false;
        } catch (Exception $e) {
            $err = json_encode(['code' => $e->getCode(), 'data' => $e->getMessage()]);
            $this->error($err);
            return false;
        }
    }

    public function getAgreement($id)
    {
        try {
            $apiContext = $this->apiContext();
            $ret = Agreement::get($id, $apiContext);
            $result = json_decode($ret->toJson(), true);
            return $result;
        } catch (PayPalConnectionException $ex) {
            $err = json_encode(['code' => $ex->getCode(), 'data' => $ex->getData()]);
            $this->error($err);
            return false;
        } catch (Exception $ex) {
            $err = json_encode(['code' => $ex->getCode(), 'data' => $ex->getMessage()]);
            $this->error($err);
            return false;
        }
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
     * @param string $billingAgreementToken [$this->agreement() -> billingToken]
     * @return false|array
     * [
     *      'result' => '',
     *      'approvealUrl' => '',
     *      'token' => '前端调起所需的token',  
     * ]
     */
    public function createPayment($orderData = [], $itemData = [], $billingAgreementToken = '')
    {
        $requestBody = file_get_contents('php://input');
        $requestArr = !empty($requestBody) ? json_decode($requestBody, true) : '';
        if (empty($orderData)) { //弱赋值
            $orderData = $requestArr;
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
            'returnUrl' => $this->buildUri($this->config['return_url'], [$this->config['orderPayment'] => 1]),
            'cancelUrl' => $this->buildUri($this->config['return_url'], [$this->config['orderPayment'] => 0]),
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
        $orderData['invoiceNumber'] = !empty($orderData['invoiceNumber']) ? $orderData['invoiceNumber'] : $this->dayOrderSn();
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
        $addressData = $this->modeMerge($addressMode, !empty($orderData['shippingAddressOverride']) ? $orderData['shippingAddressOverride'] : []);
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
            $err = json_encode(['code' => $ex->getCode(), 'data' => $ex->getData()]);
            $this->error($err);
            return false;
        } catch (Exception $ex) {
            $err = json_encode(['code' => $ex->getCode(), 'data' => $ex->getMessage()]);
            $this->error($err);
            return false;
        }
        $token = '';
        if (!empty($approvalUrl)) {
            $arr  = parse_url($approvalUrl);
            if (!empty($arr)) {
                $query    = isset($arr['query']) ? $arr['query'] : '';
                $queryArr = !empty($query) ? $this->parseQuery($query) : [];
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
    public function executePayment($paymentId = '', $payerId = '', $changeFunc = null)
    {
        $requestBody = file_get_contents('php://input');
        $requestArr = !empty($requestBody) ? json_decode($requestBody, true) : '';
        if (empty($paymentId)) {
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
            $amountRet = $this->modeMerge($amountMode, $amountData);
            $detailsRet = $this->modeMerge($detailsMode, isset($amountData['details']) ? $amountData['details'] : []);
            $shippingRet = $this->modeMerge($shippingMode, $shippingData);
            $transData = array_merge($amountRet, $detailsRet, $shippingRet);
            if ($changeFunc instanceof \Closure) {
                $transData =  $this->modeMerge($transData, $changeFunc($transData));
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
            $err = json_encode(['code' => $ex->getCode(), 'data' => $ex->getData()]);
            $this->error($err);
            return false;
        } catch (Exception $e) {
            $err = json_encode(['code' => $e->getCode(), 'data' => $e->getMessage()]);
            $this->error($err);
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
            $err = json_encode(['code' => $ex->getCode(), 'data' => $ex->getData()]);
            $this->error($err);
            return false;
        } catch (Exception $ex) {
            $err = json_encode(['code' => $ex->getCode(), 'data' => $ex->getMessage()]);
            $this->error($err);
            return false;
        }
    }

    /**
     * 创建指定模型对象
     *
     * @param  array $modeData 标准结构数据模型(及默认值),会纠正$customData的结构与值
     * @param  array $customData 用户传递数据模型
     * @param  string $class 要生成模型对象的实例类
     * @param  array $keyClass 数据模型字段中需要额外类实例对象
     * [
     *   'key' => 'class', //字段value必不是数据或对象
     *   'key' => [  //当字段value为数组时且需要对象模型封装时,使用嵌套循环处理
     *          'class' => '',  //下一套迭代的$class参数
     *          'keyClass' => [ //下一套迭代的$keyClass参数
     *              'key' => 'class'
     *          ]      
     *    ]
     * ]
     * @return array []  对象数据模型
     */
    protected function buildModeInstance($modeData, $customData, $class, $keyClass = [])
    {
        if (empty($customData)) {
            $customData = $modeData;
        }
        if (!isset($customData[0])) {
            $customData = [$customData];
        }
        $instanceArr = [];
        foreach ($customData as $k => $v) {
            $modeVal = $this->modeMerge($modeData, $v);
            $instance = new $class();
            foreach ($modeVal as $mk => $mv) {
                $method = strpos($mk, '_') === false ? 'set' . ucfirst($mk) : $this->getCamelizeName($mk);
                if (empty($keyClass) || !in_array($mk, array_keys($keyClass))) {
                    $instance->$method($mv);
                } else {
                    $kClass = isset($keyClass[$mk]) ? $keyClass[$mk] : '';
                    if (!empty($kClass)) {
                        if (is_array($mv)) {
                            //判断是否需要嵌套
                            if (!empty($kClass['keyClass'])) {
                                $kIns = $this->buildModeInstance($mv, [], $kClass['class'], $kClass['keyClass']);
                                $instance->$method(($kIns) > 1 ? $kIns : $kIns[0]);
                            } else {
                                $kIns = new $kClass();
                                foreach ($mv as $mvk => $mvv) {
                                    $mvMethod = strpos($mvk, '_') === false ? 'set' . ucfirst($mvk) : $this->getCamelizeName($mvk);
                                    $kIns->$mvMethod($mvv);
                                }
                                $instance->$method($kIns);
                            }
                        } else {
                            $kkClass = is_string($kClass) ? $kClass : (isset($kClass['class']) ? $kClass['class'] : '');
                            if (!empty($kkClass)) {
                                $instance->$method(new $kkClass($mv));
                            }
                        }
                    }
                }
            }
            $instanceArr[] = $instance;
        }
        return $instanceArr;
    }
    /** 创建用户
     * 注意: email 必须
     * @param array $data
     * @return void
     */
    public function createCustomerId(array $data)
    {
        $customer = $this->modeMerge([
            'firstName' => '', //The first name. The first name value must be less than or equal to 255 characters.
            'lastName' => '', //The last name. The last name value must be less than or equal to 255 characters.
            'company' => '',  //Company name. Maximum 255 characters.
            'email'   => '',  //Email address composed of ASCII characters.  
            'phone'   => '',  //Phone number. Maximum 255 characters.
            'fax'     => '', //Fax number. Maximum 255 characters.
            'website' => self::domain()
        ], $data);
        if (empty($data['email'])) {
            return false;
        }
        $result = $this->braintree->customer()->create($customer);
        if ($result->success) {
            return $result->customer->id;
        }
        return false;
    }
    /** 删除用户
     * @param string $customerId 
     * @return bool
     */
    public function deleteCustomer($customerId)
    {
        $result = $this->braintree->customer()->delete($customerId);
        if ($result->success) {
            return true;
        }
        return false;
    }
    /** 查看用户信息
     * 不存在throw Exception not found (customer with id 6137473601 not found)
     * @param string $customerId  用户ID
     * @return array [
     *      'id' => '用户ID',
     *      'firstName' => '',
     *      'lastName' => '',
     *      'company' => '',
     *      'email' => '',
     *      'phone' => '',
     *      'fax' => '',
     *      'website' => '',
     *      'createdAt' => '创建时间',
     *      'updatedAt' => '更新时间',
     *      'paypalAccounts' => [ //'绑定的paypal付款方式(可有多个)'
     *             0 => [
     *                  'email' => 'paypal帐号',
     *                  'token' => '支付token,很重要用于切换默认支付方式等',  
     *                  'default' => 'true|false', //是否默认支付方式
     *                  //以下次要参数
     *                  'customerId' => 'paypal用户ID',
     *                  'createdAt' => '创建时间戳',
     *                  'updatedAt' => '更新时间戳',
     *                  'payerId' => '',
     *                  'billingAgreementId'=>'',
     *              ],
     *              1 => [...]  //如果绑定多个paypal支付
     *              ....
     *       ],
     *      'paymentMethods' => [], //'绑定的所有付款方式(paypal,信用卡,googlepay等)[目前以paypal为主,数据结构同上]
     * ]
     */
    public function findCustomer($customerId)
    {
        try {
            $customer = $this->braintree->customer()->find($customerId);
        } catch (\Exception $e) {
            return [];
        }
        $customerJson = $customer->jsonSerialize();
        return $this->jsonFormat($customerJson);
    }
    /** 设置为默认支付方式
     * @param string $payToken  来自findCustomer()的返回结果中 -> paypalAccounts -> [0] ->token值
     * @return bool
     */
    public function setDefaultPaymethod($payToken)
    {
        $result = $this->braintree->paymentMethod()->update(
            $payToken,
            [
                'options' => [
                    'makeDefault' => true
                ]
            ]
        );
        if ($result->success) {
            return true;
        }
        return false;
    }
    /** 删除支付方式
     * If the payment method can't be found, you'll receive a Braintree\Exception\NotFound exception.
     * @param [string] $payToken 来自findCustomer()的返回结果中 -> paypalAccounts -> [0] ->token值
     * @return bool
     */
    public function deletePaymethod($payToken)
    {
        try {
            $result = $this->braintree->paymentMethod()->delete($payToken);
            if ($result->success) {
                return true;
            }
        } catch (NotFound $e) {
            return true;
        } catch (Exception $e) {
            return false;
        }
        return false;
    }
    /** 接口调用期间错误信息
     * @param string $msg 错误信息 
     * @param integer $code 错误码(不同的接口错误码自定义)
     * @return array 
     */
    public function error($msg = '', $code = 0)
    {
        if (!empty($msg)) {
            $this->error = ['msg' => $msg, 'code' => $code];
        }
        return $this->error;
    }
    /** 用户端核心js
     * @return string 
     */
    public function js()
    {
        $js = file_get_contents(dirname(__FILE__) . "/js/paypal.js");
        $this->cacheControl();
        echo $js;
        die;
    }
    /** 生成唯一的订单编号
     * @param string $prefix
     * @return void
     */
    public function dayOrderSn($prefix = '')
    {
        date_default_timezone_set("PRC");
        list($usec, $sec) = explode(" ", microtime());
        $dayTime = date('ymdHis', $sec);
        $usec = (float)$usec * 1000000;
        $usec = str_pad($usec, 6, '0');
        $sn = $prefix . $dayTime . $usec;
        return $sn;
    }
    public static function domain()
    {
        return self::scheme() . "://" . self::host();
    }
    protected function apiContext()
    {
        return new ApiContext(new OAuthTokenCredential($this->config['clientId'], $this->config['secret']));
    }
    protected function btGetway()
    {
        return new Gateway($this->braintreeApp);
    }
    protected function merchantAccountId($currency)
    {
        $currency = strtoupper($currency);
        return isset($this->merchantAccounts[$currency]) ? $this->merchantAccounts[$currency] : '';
    }

    protected function domainUrl($url)
    {
        $domain = self::domain();
        $pos = strpos($url, 'http');
        if (false === $pos || $pos > 0) {
            return $domain . '/' . ltrim($url, '/');
        }
        return $url;
    }
    protected function buildUri($url, $data = [])
    {
        if (empty($data)) {
            return $url;
        }
        $arr  = parse_url($url);
        if (empty($arr)) {
            return $url;
        }
        $query    = isset($arr['query']) ? $arr['query'] : '';
        $queryArr = !empty($query) ? $this->parseQuery($query) : [];
        $queryArr = array_merge($queryArr, $data);
        $query = $this->buildQuery($queryArr);
        $scheme = isset($arr['scheme']) ? $arr['scheme'] . '://' : '';
        $host   = isset($arr['host']) ? $arr['host'] : '';
        $path   = isset($arr['path']) ? $arr['path'] : '';
        return $scheme . $host . $path . '?' . $query;
    }

    /**
     * Build a query string from an array of key value pairs.
     * This function can use the return value of parse_query() to build a query
     * string. This function does not modify the provided keys when an array is
     * encountered (like http_build_query would).
     *
     * @param array     $params   Query string parameters.
     * @param int|false $encoding Set to false to not encode, PHP_QUERY_RFC3986
     *                            to encode using RFC3986, or PHP_QUERY_RFC1738
     *                            to encode using RFC1738.
     * @return string
     */
    protected function buildQuery(array $params, $encoding = PHP_QUERY_RFC3986)
    {
        if (!$params) {
            return '';
        }
        if ($encoding === false) {
            $encoder = function ($str) {
                return $str;
            };
        } elseif ($encoding === PHP_QUERY_RFC3986) {
            $encoder = 'rawurlencode';
        } elseif ($encoding === PHP_QUERY_RFC1738) {
            $encoder = 'urlencode';
        } else {
            throw new \Exception('Invalid type');
        }
        $qs = '';
        foreach ($params as $k => $v) {
            $k = $encoder($k);
            if (!is_array($v)) {
                $qs .= $k;
                if ($v !== null) {
                    $qs .= '=' . $encoder($v);
                }
                $qs .= '&';
            } else {
                foreach ($v as $vv) {
                    $qs .= $k;
                    if ($vv !== null) {
                        $qs .= '=' . $encoder($vv);
                    }
                    $qs .= '&';
                }
            }
        }
        return $qs ? (string) substr($qs, 0, -1) : '';
    }
    /**
     * Parse a query string into an associative array.
     * If multiple values are found for the same key, the value of that key
     * value pair will become an array. This function does not parse nested
     * PHP style arrays into an associative array (e.g., foo[a]=1&foo[b]=2 will
     * be parsed into ['foo[a]' => '1', 'foo[b]' => '2']).
     * @param string   $str         Query string to parse
     * @param int|bool $urlEncoding How the query string is encoded
     *
     * @return array
     */
    protected function parseQuery($str, $urlEncoding = true)
    {
        $result = [];
        if ($str === '') {
            return $result;
        }
        if ($urlEncoding === true) {
            $decoder = function ($value) {
                return rawurldecode(str_replace('+', ' ', $value));
            };
        } elseif ($urlEncoding === PHP_QUERY_RFC3986) {
            $decoder = 'rawurldecode';
        } elseif ($urlEncoding === PHP_QUERY_RFC1738) {
            $decoder = 'urldecode';
        } else {
            $decoder = function ($str) {
                return $str;
            };
        }
        foreach (explode('&', $str) as $kvp) {
            $parts = explode('=', $kvp, 2);
            $key = $decoder($parts[0]);
            $value = isset($parts[1]) ? $decoder($parts[1]) : null;
            if (!isset($result[$key])) {
                $result[$key] = $value;
            } else {
                if (!is_array($result[$key])) {
                    $result[$key] = [$result[$key]];
                }
                $result[$key][] = $value;
            }
        }
        return $result;
    }
    /** 验证IPN信息
     *  IPN验证只针对checkout交易记录,BILLING_AGREEMENTS_AGREEMENT援权IPN无需验证,可无视
     * @param [type] $post
     * @return false||array $post
     */
    protected function paypalIpnVerify($post)
    {
        $listener = new IpnListener();
        $listener->use_sandbox = $this->config['sandbox'] ?? 0;
        $verified  = 0;
        try {
            $listener->requirePostMethod();
            $verified = $listener->processIpn($post);
        } catch (\Exception $e) {
            $this->error($e->getMessage()); //数据格式有误
            return false;
        }
        if ($verified == 0) {
            $this->error('verifyed error', -1); //验证失败
            return false;
        }
        return $post;
    }

    /** ipn回调数据格式
     * @param [string&json] $verify
     * @return void
     */
    protected function paypalIpnData($verify)
    {
        $post = $verify;
        $data = [];
        try {
            $order_sn = isset($post['invoice']) ? $post['invoice'] : '';
            if (empty($order_sn)) {
                $order_sn = isset($post['item_number']) ? $post['item_number'] : '';
            }
            if (empty($order_sn)) {
                $this->error('order_sn error');
                return false;
            }
            $resource_type = 'sale';
            $pay_amount = !empty($post['payment_gross']) ? $post['payment_gross'] : (!empty($post['mc_gross']) ? $post['mc_gross'] : 0);
            if ($pay_amount < 0) {
                $resource_type = 'refund';
            }
            $pay_fee = !empty($post['payment_fee']) ? $post['payment_fee'] : (!empty($post['mc_fee']) ? $post['mc_fee'] : 0);
            $pay_time = isset($post['payment_date']) ? $post['payment_date'] : 0;
            $pay_time = is_string($pay_time) ? strtotime($pay_time) : $pay_time;
            $currency = isset($post['mc_currency']) ? $post['mc_currency'] : '';
            $payment_status = isset($post['payment_status']) ? strtolower($post['payment_status']) : '';
            $data = [
                'pay_id' => $post['ipn_track_id'],  //状态事务ID(Authorization Unique Transaction ID)
                'parent_payment' => $post['txn_id'], //pp运单号
                'order_sn' => $order_sn, //订单号
                'pay_time' => $pay_time, //创建时间
                'pay_amount' => $pay_amount,  //支付金额
                'currency' => $currency, //支付货币
                'pay_fee' => $pay_fee,          //手续费
                'pay_fee_currency' => $currency,     //手续费货币
                'resource_type' =>  $resource_type, //交易类型,如:sale,refund[绝对小写]支付还是退款
                'event_type' => isset($post['txn_type']) ? strtolower($post['txn_type']) : '',  //事件类型 express_checkout
                'state' => $payment_status, //状态completed,denied,(reversed,refunded)[绝对小写]
                'pay_status_str' => $payment_status,
                'summary' => "", //交易描述[max:100]
                'pay_merchant' => $this->config['account'],
            ];
            // $address = [
            //     'type'      => 1,
            //     'first_name' => $post['first_name'],
            //     'last_name' => $post['last_name'],
            //     'email'     => $post['payer_email'],
            //     'phone'     => '',
            //     'country'   => $post['address_country'],
            //     'province'  => '',
            //     'city'      => $post['address_city'],
            //     'district'  => '',
            //     'zipcode'   => $post['address_zip'],
            //     'address'   => $post['address_street'],
            // ];
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return false;
        }
        return $data;
    }

    /** webhook 回调通知验证
     * @param string $requestBody
     * @return false||array 
     */
    protected function paypalWebhookVerify($requestBody)
    {
        $output = null;
        try {
            $apiContext = $this->apiContext();
            $mode = $this->config['sandbox'] == 0 ? 'live' : 'sandbox';
            $apiContext->setConfig(['mode' => $mode]);
            $headers = apache_request_headers();
            $headers = array_change_key_case($headers, CASE_UPPER);
            $signatureVerification = new VerifyWebhookSignature();
            $signatureVerification->setAuthAlgo($headers['PAYPAL-AUTH-ALGO']);
            $signatureVerification->setTransmissionId($headers['PAYPAL-TRANSMISSION-ID']);
            $signatureVerification->setCertUrl($headers['PAYPAL-CERT-URL']);
            // Note that the Webhook ID must be a currently valid Webhook that you created with your client ID/secret.
            $signatureVerification->setWebhookId($this->config['webhookId']);
            $signatureVerification->setTransmissionSig($headers['PAYPAL-TRANSMISSION-SIG']);
            $signatureVerification->setTransmissionTime($headers['PAYPAL-TRANSMISSION-TIME']);
            $signatureVerification->setRequestBody($requestBody);
            $request = clone $signatureVerification;
            /** @var \PayPal\Api\VerifyWebhookSignatureResponse $output */
            $output = $signatureVerification->post($apiContext);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return false;
        }
        $status = $output->getVerificationStatus();
        if (strtoupper($status) == 'SUCCESS') {
            return $request->toJSON();
        }
        $this->error('paypalWebhookVerify:' . $status);
        return false;
    }
    /** webhook 回调数据格式
     * @param [type] $verify
     * @return void
     */
    protected function paypalWebhookData($verify)
    {
        $data = [];
        try {
            $postJson = json_decode($verify, true);
            $post = isset($postJson['webhook_event']) ? $postJson['webhook_event'] : [];
            $summary = isset($post['summary']) ? substr($post['summary'], 0, 100) : '';
            $create_time = isset($post['create_time']) ? strtotime($post['create_time']) : 0;
            $resource_type = isset($post['resource_type']) ? strtolower($post['resource_type']) : '';
            $event_type = isset($post['event_type']) ? $post['event_type'] : '';
            $resource = isset($post['resource']) ? $post['resource'] : [];
            $transactions = isset($post['resource']['transactions'][0]) ? $post['resource']['transactions'][0] : [];
            if (!empty($transactions)) {
                $amount = isset($transactions['amount']) ? $transactions['amount'] : [];
                $invoice_number = isset($transactions['invoice_number']) ? $transactions['invoice_number'] : '';
            } else {
                $amount = isset($resource['amount']) ? $resource['amount'] : [];
                $invoice_number = isset($resource['invoice_number']) ? $resource['invoice_number'] : 0; //注意:测试模式可能为空
            }
            if (empty($invoice_number)) {
                $this->error('invoice_number error');
                return false;
            }
            // $pay_id = isset($resource['parent_payment']) ? $resource['parent_payment'] : (isset($resource['id']) ? $resource['id'] : '');
            $pay_id = isset($resource['id']) ? $resource['id'] : ''; //if first PAYID- else links ID
            $parent_payment = isset($resource['parent_payment']) ? $resource['parent_payment'] : ''; //if first created not isset else is PAYID

            $state   = isset($resource['state']) ? strtolower($resource['state']) : '';
            $transaction_fee = isset($resource['transaction_fee']) ? $resource['transaction_fee'] : [];
            $amount_total = isset($amount['total']) ? str_replace('_', '.', $amount['total']) : 0;
            $amount_currency = isset($amount['currency']) ? $amount['currency'] : '';
            $fee_total = isset($transaction_fee['value']) ? str_replace('_', '.', $transaction_fee['value']) : 0;
            $fee_currency = isset($transaction_fee['currency']) ? $transaction_fee['currency'] : '';
            $data = [
                'pay_id' => $pay_id,  //pp交易号或状态事务ID,第一次交易生成时为PAYID- (Payment ID),之后为状态事务ID(Authorization Unique Transaction ID)
                'parent_payment' => $parent_payment, //第一次交易生成时为空,之后为状态事务ID(Authorization Unique Transaction ID)
                'order_sn' => $invoice_number, //订单号
                'pay_time' => $create_time, //创建时间
                'pay_amount' => $amount_total,  //支付金额
                'currency' => $amount_currency, //支付货币
                'pay_fee' => $fee_total,          //手续费
                'pay_fee_currency' => $fee_currency,     //手续费货币
                'resource_type' =>  $resource_type, //交易类型,如:sale,payment,refund等[绝对小写]
                'event_type' => $event_type,    //事件类型,如:PAYMENT.SALE.COMPLETED
                'state' => $state, //状态[绝对小写]
                'pay_status_str' => $event_type,
                'summary' => $summary, //交易描述[max:100]
                'pay_merchant' => $this->config['account'],
            ];
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return false;
        }
        return $data;
    }

    /** 资源缓存头部
     * @return void
     */
    protected function cacheControl($type = 'js')
    {
        if ($type == 'js') {
            header('Content-type: application/x-javascript');
        }
        if ($this->config['max_age'] > 0) {
            header('Cache-Control: max-age=' . $this->config['max_age']);
        } else {
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Cache-Control: post-check=0, pre-check=0", false);
            header("Pragma: no-cache");
        }
    }
    /** 根据模型组装标准数据
     * @param array $mode
     * @param array $data
     * @return array []
     */
    protected function modeMerge(array $mode, array $data)
    {
        $merge = [];
        foreach ($mode as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $vk => $vv) {
                    if (isset($data[$k][$vk])) {
                        $merge[$k][$vk] = $data[$k][$vk];
                    } else {
                        //是否为二维数组,二维数组必须为数字数组
                        if (isset($data[$k][0])) {
                            foreach ($data[$k] as $dk => $dv) {
                                $merge[$k][$dk] = $this->modeMerge($v, $dv);
                            }
                            break;
                        } else {
                            $merge[$k][$vk] = $vv;
                        }
                    }
                }
            } else {
                if (isset($data[$k])) {
                    $merge[$k] = $data[$k];
                } else {
                    if (isset($data[0])) {
                        foreach ($data as $dk => $dv) {
                            $merge[$dk] = $this->modeMerge($mode, $dv);
                        }
                        break;
                    } else {
                        $merge[$k] = $v;
                    }
                }
            }
        }
        return $merge;
    }
    protected static function scheme()
    {
        return isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http';
    }
    /**
     *@param bool $strict true 仅仅获取HOST
     */
    protected static function host($strict = false)
    {
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        return true === $strict && strpos($host, ':') ? strstr($host, ':', true) : $host;
    }
    protected function jsonFormat($json)
    {
        $data = [];
        foreach ($json as $k => $v) {
            $item = $v;
            if ($v instanceof DateTime) {
                $timespamp = $v->getTimestamp();
                $item = $timespamp;
            } else if ($v instanceof JsonSerializable) {
                $item = $v->jsonSerialize();
            }
            if (is_array($item)) {
                $item = $this->jsonFormat($item);
            }
            $data[$k] = $item;
        }
        return $data;
    }
    /**
     * 解析浏览器语言，得到localcode
     * @return bool|mixed|string
     */
    protected function getLocalCode()
    {
        $ret = '';
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && $_SERVER['HTTP_ACCEPT_LANGUAGE']) {
            $tmp = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
            if (stripos($tmp, '-') == 2) {
                $ret = substr($tmp, 0, 5);
                $ret = str_replace('-', '_', $ret);
            }
        }
        return $ret;
    }
    /**
     * 执行nvp API请求
     * @param string $method 需要执行的方法
     * @param array  $fields 参数
     * @throws PaypalException 返回PAYPAL错误号，错误信息为PP返回的JSON字串
     * @return array 返回值，如果出错，会抛异常
     */
    protected function nvpRequest($method, array $fields)
    {
        $datas = array(
            'METHOD'    => $method,
            'VERSION'   => $this->config['api_version'],
            'USER'      => $this->config['api_user'],
            'PWD'       => $this->config['api_password'],
            'SIGNATURE' => $this->config['api_signature'],
        );
        $datas = array_merge($datas, $fields);
        $ch = curl_init();
        $url = $this->config['sandbox'] == 1 ? $this->config['api_nvp1'] : $this->config['api_nvp0'];
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        // Turn off the server and peer verification (TrustManager Concept).
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        // Set the API operation, version, and API signature in the request.
        $nvpreq = http_build_query($datas);
        // Set the request as a POST FIELD for curl.
        curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);
        // Get response from the server.
        $httpResponse = curl_exec($ch);
        parse_str($httpResponse, $response);
        $invnum = isset($fields['INVNUM']) ? $fields['INVNUM'] : 0;
        if (is_array($response) && isset($response['ACK']) && $response['ACK'] == 'Success') {
            return $response;
        } else {
            if (isset($response['ACK'])) {
                $responseStr = json_encode($response);
                // 错误号 & 错误码
                $errorCode = isset($response['L_ERRORCODE0']) ? $response['L_ERRORCODE0'] : 10000;
                $errorMsg  = isset($response['L_LONGMESSAGE0']) ? $response['L_LONGMESSAGE0'] : 'unknown';
                $err = json_encode(['responseStr' => $responseStr, 'errorCode' => $errorCode, 'errorMsg' => $errorMsg, 'invnum' => $invnum, 'method' => $method]);
                $this->error($err);
                return false;
            } else {
                $responseStr = !empty($response) ? json_encode($response) : '';
                $err = json_encode(['responseStr' => $responseStr, 'invnum' => $invnum, 'method' => $method]);
                $this->error($err);
                return false;
            }
        }
    }
    /**
     * nvp 订单信息
     * @param [type] $data
     * @return false|string $token
     */
    protected function nvpCheckoutToken($data)
    {
        $orderSn = $data['order_sn'];
        $description = isset($data['description']) ? $data['description'] : '';
        $siteName    = isset($data['site_name']) ? $data['site_name'] : '';
        $fields   = [
            'CANCELURL' => $this->config['cancel_url'],  //支付取消返回
            'RETURNURL' => $this->config['return_url'], //支付成功返回
            'NOTIFYURL' => $this->config['notify_url'],
            'AMT'       => $data['amount'],
            'ITEMAMT'   => $data['amount'],
            'CURRENCYCODE' => $data['currency'],
            'MAXAMT'    =>  round($data['amount'] + 1, 2), //最高可能总额(最大运费,汇率差等)
            'CUSTOM'    => '',
            'INVNUM'    => $orderSn, //唯一标识值，一般可为订单号
            'DESC'      => $description, //描述
            'PAYMENTACTION' => 'Sale', //支付动作
            //other fields
            'SHIPPINGAMT' => 0, //物流费用，如果有物流
            'NOSHIPPING'  => 2, //一定要有物流信息
        ];
        $localCode = $this->getLocalCode();
        if ($localCode) {
            $fields['LOCALECODE'] = $localCode; //由于可能会出现中文界面，我们主动传递下LOCALECODE
        }
        if (!empty($siteName)) {
            $fields['BRANDNAME'] = $siteName; //显示站点名字
        }
        $returnData = $this->nvpRequest('SetExpressCheckout', $fields);
        if (empty($returnData)) {
            return false;
        }
        return isset($returnData['TOKEN']) ? $returnData['TOKEN'] : false;
    }
    protected function getNvpPayUrl($token)
    {
        if (empty($token)) {
            return false;
        }
        $url = $this->config['sandbox'] == 0 ? $this->config['nvp_pay_url0'] : $this->config['nvp_pay_url1'];
        return $url . ($token ? urlencode($token) : '');
    }
    /**
     * 转驼峰命名
     *
     * @param string $uncamelized_words
     * @param string $separator
     * @return void
     */
    protected function getCamelizeName($uncamelized_words, $separator = '_')
    {
        $uncamelized_words = $separator . str_replace($separator, " ", strtolower($uncamelized_words));
        return ltrim(str_replace(" ", "", ucwords($uncamelized_words)), $separator);
    }
}
