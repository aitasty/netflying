<?php

namespace Netflying\Pay\Paypal;

use Exception;
use Netflying\Common\Utils;

use Braintree\Gateway;
use Braintree\Exception\NotFound;

/**
 * paypal braintree button模式(paypal集成braintree,异步通知ipn模式)
 * 一.初始化Paypal
 * $Paypal = new Paypal([
 *      'sandbox'   => '',          //required
 *      'clientId'  => '',          //required
 *      'secret'    => '',          //required
 *      'accessToken' => '',        //required
 *      'account'   => '',          //required
 *      'currency' => '',           //required
 *      'merchantAccounts' => [],   //optional
 *      'max_age' => 0,             //optional    
 * ]);     
 * 二. 前端引用js内容
 * $Paypal->js();
 * 三. paypal.js交互
 *      1. 初始化sdk: 1. 初始化js:  js -> 'token_url' => {clientId: $Paypal->clientId(),token:$Paypal->token();};
 *      2. 支付:
 *          2.1, 成功approve回调 --提交(所需参数已自动封装,额外业务参数自行提交处理)--> $Paypal->purchase();
 *      3. 周期支付:
 *          3.1  js -> 'create_agreement_url' => $Paypal->createBillingAgreement();
 *          3.2  js -> 'approve_agreement_url' => $Paypal->executeAgreement();
 *      4. 授权添加paypal支付方式(授权之后会非调起静默支付)
 *          4.1  js->approve回调获取payload => $Paypal->boundCustomer([...(必要token已自动封装),customer=>payload['details']]);
 * 
 * braintree paypal button模式(braintree集成paypal模式,异步通知webhook模式)
 * 一.初始化Paypal
 * $Paypal = new Braintree([
 *      'sandbox'   => '',          //required
 *      'clientId'  => '',          //required
 *      'secret'    => '',          //required
 *      'account'   => '',          //required
 *      'braintreeApp' => [],       //required
 *      'currency' => '',           //required
 *      'merchantAccounts' => [],   //required
 *      'max_age' => 0,             //optional    
 * ]); 
 * 二. 前端引用js内容
 * $Paypal->js();
 * 三. paypal.js交互
 *      1. 初始化sdk: 1. 初始化js:  js -> 'token_url' => {clientId: $Paypal->clientId(),token:$Paypal->btToken();};
 *      2. 支付:
 *          2.1, 成功approve回调 --提交(所需参数已自动封装,额外业务参数自行提交处理)--> $Paypal->purchase();
 *      3. 周期支付:
 *          3.1  js -> 'create_agreement_url' => $Paypal->createBillingAgreement();
 *          3.2  js -> 'approve_agreement_url' => $Paypal->executeAgreement();
 *      4. 授权添加paypal支付方式(授权之后会非调起静默支付)
 *          4.1  js->approve回调获取payload => $Paypal->boundCustomer([...(必要token已自动封装),customer=>payload['details']]);
 */

class Braintree extends Paypal
{

    protected $config = [
            'sandbox'    => '',          //required
            'clientId'   => '',          //required
            'secret'     => '',          //required
            'account'    => '',          //required
            'accessToken'=> '',          //required, paypal内braintree替代$this->braintreeApp配置
            'currency'   => '',          //required
            'return_url' => '',          //required
            'max_age'    => 0,           //optional
            //createPlan 返回地址附加参数名
            'createPlan' => 'paypay_plan',
            //order payment 返回地址附加参数名
            'orderPayment' => 'order_payment',
            //braintree->Business->Merchant Accounts->add new   可收货币商号
            'merchantAccounts' => [
                'USD' => 'USD'
            ]
    ];
    protected $excludeConfKey = [
        'max_age'
    ];
    //braintree sdk(独立braintree)
    protected $braintreeApp = [
        //'environment' => 'sandbox',
        'merchantId' => '', //required
        'publicKey'  => '', //required
        'privateKey' => '', //required
        'webhookId'  => '',  //独立braintree通知是webhook模式
    ];
    //braintree 网关
    protected $braintree = null;

    public function __construct(array $config = [])
    {
        $this->config($config);
        $braintreeApp = isset($config['braintreeApp']) ? $config['braintreeApp'] : [];
        if (!empty($braintreeApp)) { //braintree sdk模式
            $this->braintreeApp = Utils::modeData([
                'merchantId'    => '',
                'publicKey'     => '',
                'privateKey'    => '',
                'webhookId'     => '',
                'environment'   => $this->config['sandbox'] == 1 ? 'sandbox' : 'live',
            ], $braintreeApp);
        } else {
            $this->braintreeApp =  ['accessToken' => $this->config['accessToken']];
        }
        $this->braintree = $this->getway();
    }

    protected function getway()
    {
        return new Gateway($this->braintreeApp);
    }
    /**
     * 获取braintree jssdk初始化token
     *
     * @param string $customerId  已授权用户ID [可选]
     * @return string   $clientToken  前端初始化应用ID
     */
    public function token($customer_id = '')
    {
        $optional = [];
        $data = Utils::modeData(['customer_id' => $customer_id], Utils::request());
        if (!empty($data['customer_id'])) {
            $optional = ['customerId'=>$data['customerId']];
        }
        $clientToken = $this->braintree->clientToken()->generate($optional);
        return $clientToken;
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
     *      'feeAmount' => '手续费',
     *      'feeCurrency' => '手续费货币',
     * ]
     */
    public function purchase($data)
    {
        $data = Utils::modeData([
            'nonce'     => '',
            'deviceData' => '',
            'amount'    => '',
            'currency'  => '',
            'orderId'   => Utils::dayOrderSn(),
        ], $data, [
            'currency' => 'strtoupper'
        ]);
        $merchantAccountId = $this->merchantAccountId($data['currency']);
        $options = [
            'orderId' => $data['orderId'],
            'amount'  => $data['amount'],
            'paymentMethodNonce' => $data['nonce'],
            'deviceData' => $data['deviceData'],
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
            $result = Utils::modeData(
                [
                    'currencyIsoCode' => '',
                    'paymentId' => '',
                    'payerEmail' => '',
                    'authorizationId' => '',
                    'transactionFeeAmount' => '',
                    'transactionFeeCurrencyIsoCode' => '',
                    'payerId' => 0,
                ],
                array_merge($transaction, $paypal)
            );
            return [
                'orderId'   => $data['orderId'],
                'amount'    => $data['amount'],
                'currency'  => $result['currencyIsoCode'],
                'paymentId' => $result['paymentId'],
                'payerEmail' => $result['payerEmail'],
                'authorizationId' => $result['authorizationId'],
                'feeAmount'     => $result['transactionFeeAmount'],
                'feeCurrency'   => $result['transactionFeeCurrencyIsoCode'],
                'payerId'       => $result['payerId'],
            ];
        } else {
            $jsonArr = $result->jsonSerialize();
            $message = isset($jsonArr['message']) ? $jsonArr['message'] : 'error';
            $this->error($message);
            return false;
        }
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
    /** 创建用户
     * 注意: email 必须
     * @param array $data
     * @return void
     */
    public function createCustomerId(array $data)
    {
        $customer = Utils::modeData([
            'firstName' => '', //The first name. The first name value must be less than or equal to 255 characters.
            'lastName'  => '', //The last name. The last name value must be less than or equal to 255 characters.
            'company'   => '',  //Company name. Maximum 255 characters.
            'email'   => '',  //Email address composed of ASCII characters.  
            'phone'   => '',  //Phone number. Maximum 255 characters.
            'fax'     => '', //Fax number. Maximum 255 characters.
            'website' => Utils::domain()
        ], $data);
        if (empty($customer['email'])) {
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
    /**
     * 获取货币对应商户号
     * @param string $currency
     * @return void
     */
    protected function merchantAccountId($currency)
    {
        $currency = strtoupper($currency);
        return isset($this->config['merchantAccounts'][$currency]) ? $this->config['merchantAccounts'][$currency] : '';
    }
}
