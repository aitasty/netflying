<?php

namespace Netflying\Pay\Paypal;


use DateTime;
use JsonSerializable;
use Exception;
use Netflying\Pay\PayAbstract;
use Netflying\Paypal\IpnListener;
use Netflying\Common\Utils;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;
use PayPal\Api\VerifyWebhookSignature;
use PayPal\Api\Plan;
use PayPal\Api\Patch;
use PayPal\Api\PatchRequest;

use PayPal\Common\PayPalModel;
use PayPal\Exception\PayPalConnectionException;

/**
 * 参考文档
 * paypal api: https://developer.paypal.com/docs/api/overview/
 * paypal example: https://github.com/paypal/PayPal-PHP-SDK/tree/master/sample/billing
 * 三种支付模式：
 *      1. Nvp.php
 *      2. SmartButton.php
 *      3. Braintree.php
 */
abstract class Paypal extends PayAbstract
{

    /**
     * paypal客户端sdk初始化IDclient-id
     *
     * @return string
     */
    public function clientId()
    {
        $data = Utils::modeData(['clientId' => ''], $this->config);
        return $data['clientId'];
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
        $post = Utils::request($rawData);
        $requestBody = $rawData['input'];
        $notify = [];
        if (!empty($post)) { //&& isset($post['ipn_track_id'])
            $verify = $this->paypalIpnVerify($post);
            $notify = $this->paypalIpnData($verify);
        } else {
            $verify = $this->paypalWebhookVerify($requestBody);
            $notify = $this->paypalWebhookData($verify);
        }
        if (empty($notify)) {
            $this->error('[Paypal notify empty]' . $requestBody);
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
            'returnUrl' => Utils::buildUri($this->config['return_url'], [$this->config['createPlan'] => 1]), //使用通用地址并加入特定标识
            'cancelUrl' => Utils::buildUri($this->config['return_url'], [$this->config['createPlan'] => 0]),
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
            $this->error(['code' => $ex->getCode(), 'data' => $ex->getData()]);
            return false;
        } catch (Exception $ex) {
            $this->error(['code' => $ex->getCode(), 'data' => $ex->getMessage()]);
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
        $data = Utils::modeData($mode, $data);
        try {
            $ret = Plan::all($data, $this->apiContext());
            return json_decode($ret->toJson(), true);
        } catch (Exception $e) {
            $this->error(['code' => $e->getCode(), 'data' => $e->getMessage()]);
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
            $this->error(['code' => $e->getCode(), 'data' => $e->getMessage()]);
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
     * 创建指定模型对象
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
            $modeVal = Utils::modeData($modeData, $v);
            $instance = new $class();
            foreach ($modeVal as $mk => $mv) {
                $method = strpos($mk, '_') === false ? 'set' . ucfirst($mk) : Utils::getCamelizeName($mk);
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
                                    $mvMethod = strpos($mvk, '_') === false ? 'set' . ucfirst($mvk) : Utils::getCamelizeName($mvk);
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

    /**
     * api context
     *
     * @return ApiContext
     */
    protected function apiContext()
    {
        return new ApiContext(new OAuthTokenCredential($this->config['clientId'], $this->config['secret']));
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
     * @param array $verify
     * @return false|array
     */
    protected function paypalIpnData($verify)
    {
        $data = [];
        try {
            $post = Utils::modeData([
                'invoice'       => isset($verify['item_number']) ? $verify['item_number'] : '',
                'payment_gross' => !empty($verify['mc_gross']) ? $verify['mc_gross'] : 0,
                'payment_fee'   => !empty($verify['mc_fee']) ? $verify['mc_fee'] : 0,
                'payment_date'  => 0,
                'mc_currency'   => '',
                'payment_status' => '',
                'ipn_track_id'  => '',
                'txn_id'        => '',
                'txn_type'      => '',
            ], $verify, [
                'payment_date' => function ($value) {
                    return is_string($value) ? strtotime($value) : $value;
                },
                'payment_status' => 'strtolower',
                'txn_type' => 'strtolower'
            ]);
            $order_sn = $post['invoice'];
            if (empty($order_sn)) {
                $this->error('paypalIpnData order_sn error');
                return false;
            }
            $resource_type = 'sale';
            $pay_amount = $post['payment_gross'];
            if ($pay_amount < 0) {
                $resource_type = 'refund';
            }
            $data = [
                'order_sn' => $order_sn, //订单号
                'pay_id' => $post['ipn_track_id'],  //状态事务ID(Authorization Unique Transaction ID)
                'parent_payment' => $post['txn_id'], //pp运单号
                'pay_time' => $post['payment_date'], //创建时间
                'pay_amount' => $pay_amount,  //支付金额
                'currency' => $post['mc_currency'], //支付货币
                'pay_fee' => $post['payment_fee'],          //手续费
                'pay_fee_currency' => $post['mc_currency'],     //手续费货币
                'resource_type' =>  $resource_type, //交易类型,如:sale,refund[绝对小写]支付还是退款
                'event_type' => $post['txn_type'],  //事件类型 express_checkout
                'state' => $post['payment_status'], //状态completed,denied,(reversed,refunded)[绝对小写]
                'pay_status_str' => $post['payment_status'],
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
     * @param array $verify
     * @return false|array
     */
    protected function paypalWebhookData($verify)
    {
        $data = [];
        try {
            $post = isset($verify['webhook_event']) ? $verify['webhook_event'] : [];
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
                $this->error('paypalWebhookData invoice_number error');
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
}
