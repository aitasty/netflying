<?php

namespace Netflying\Pay\Airwallex;

use Exception;
use Netflying\Pay\PayAbstract;
use Netflying\Common\Utils;
use Netflying\Common\Curl;

class Awx extends PayAbstract
{
    //必要初始化配置
    protected $config = [
        'test' => '',
        //客户ID号
        'clientId' => '',
        //API key
        'apiKey' => '',
        //Publishable key
        'publishableKey' => '',
        //webhook 密钥
        'webhookSecret' => '',
        //请求回调地址
        'tokenUrl' => '',
        //网关地址[测试或生产]
        'airWallexDomain' => '',
        //提交订单接口地址
        'createPayUrl' => '',
        //必须,指纹设备标识
        'org_id' => '',
        //必须,统一的完成返回地址,[提交订单后,验证3ds后,3ds短信码验证后]
        'return_url' => '',
        //可选,是否需要订单前缀,[订单前缀是用来区分不同来源,如多站的情况]
        'order_prefix' => '',
        //支持卡类型
        'card_type' => [],
    ];
    protected $cardType = [1, 3]; //默认只支持visa,master 对照self::checkCardType()
    protected $excludeConfKey = ['order_prefix', 'card_type'];
    protected $descriptor = "awx"; //显现在用户帐单描述
    //会话token
    protected $tokenKey = "airwallexToken";
    protected $token = "";
    //token 20分钟失效
    protected $tokenExpire = 1000;

    /** 
     * 缓存Closure函数(文件缓存,redis缓存等),一定要设置,否则接口会每次都请求token
     * 标准化格式 function ($key,$value,$expire) { return cache($key,$value,$expire) }
     * @param $key 缓存key
     * @param $value 设置缓存值. 为空:表示获取$key缓存; is_null:表示删除缓存
     * @param $expire 设置缓存值过期时间,单位秒
     */
    protected $cacheFunc = null;

    public function __construct(array $config, $descriptor = '', $cacheFunc = null)
    {
        $this->config($config);
        if (!empty($this->config['cart_type'])) {
            $this->cardType = $this->config['cart_type'];
        }
        if (!empty($descriptor)) {
            $this->descriptor = $descriptor;
        }
        if (!empty($cacheFunc)) {
            $this->cacheFunc = $cacheFunc;
        }
        $this->getToken();
    }
    public function descriptor($msg = '')
    {
        if (!empty($msg)) {
            $this->descriptor = $msg;
        }
        return $this->descriptor;
    }
    /**
     * 用户端设备指纹标识
     * @return [array]
     * [
     *      'device_id' => '随机字符串，需保证每次支付页面刷新加载执行该脚本的时候都不一样，保证唯一性',
     *      'jscript' => '设备指纹的获取需要2-4秒钟，只要消费者在页面上停留超过这个时间就能抓取,装载进<head></head>,如需要还可加装iframe(<noscript><iframe></iframe></noscript>)',  
     * ]
     */
    public function deviceToken()
    {
        $device_id = join("-", str_split(substr(md5(uniqid(mt_rand(), 1)),   8,   16), 4));
        //因固定的,可直接写在代码里
        $jscript = "https://h.online-metrix.net/fp/tags.js?org_id=" . $this->config['org_id'] . "&session_id=" . $device_id;
        return [
            'device_id' => $device_id,
            'jscript' => $jscript
        ];
    }

    /**
     * 判断卡类型
     * @param string $cardNumber
     * @return void
     */
    public function checkCardType($cardNumber, $verify = true)
    {
        if (empty($cardNumber) || !is_string($cardNumber)) {
            return '';
        }
        $cardCheckre = [
            122 => '/^(4026|417500|4405|4508|4844|4913|4917)\d+$/u', //electron?Visa Electron?
            117 => '/^(5018|5020|5038|5612|5893|6304|6759|6761|6762|6763|0604|6390)\d+$/u', //maestro
            123 => '/^(5019)\d+$/u', //Dankort
            430 => '/^(62|88)\d+$/u', //UnionPay International (redirect)
            1 => '/^4[0-9]{12}(?:[0-9]{3})?$/u', //visa
            2 => '/^3[47][0-9]{13}$/u', //AMEX
            3 => '/^5[1-5][0-9]{14}$/u', //mastercard
            132 => '/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/u', //diners
            128 => '/^6(?:011|5[0-9]{2})[0-9]{12}$/u', //discover
            125 => '/^(?:2131|1800|35\d{3})\d{11}$/u', //JCB
        ];
        $cardId = "";
        foreach ($cardCheckre as $id => $reg) {
            if (preg_match($reg, $cardNumber)) {
                $cardId = $id;
                break;
            }
        }
        if (!empty($cardId) && $verify) {
            if (in_array($cardId, $this->cardType)) {
                return true;
            } else {
                return false;
            }
        }
        return '';
    }
    /**
     * 创建支付
     *
     * @param array $order  参照$this->orderData();
     * @param array $credit  参照$this->orderCredit()
     * @param [string] $device_id  来自deviceToken的device_id
     * @return void
     */
    public function purchase($data)
    {
        $data = Utils::modeData([
            'order' => [],
            'credit'  => [],
            'device_id' => ''
        ], $data);
        $order = $data['order'];
        $credit = $data['credit'];
        $device_id = $data['device_id'];
        try {
            $orderData = $this->orderData($order);
            $creditData = $this->orderCredit($credit);
        } catch (Exception $e) {
            $this->error($e->getMessage());
            return [];
        }
        $createPayUrl = $this->config['airWallexDomain'] . $this->config['createPayUrl'];
        $createResponse = $this->request($createPayUrl, is_array($orderData) ? json_encode($orderData) : []);
        if (!empty($createResponse['id']) && $createResponse['status'] == 'REQUIRES_PAYMENT_METHOD') {
            return $this->confirmPayment($createResponse, $creditData, $orderData['merchant_order_id'], $device_id);
        } else {
            $this->error('payment create failed');
            return [];
            // throw new Exception('payment create failed!');
        }
    }

    /**
     * 返回后处理业务流转(含3ds form自动中转)[因3ds必须通过浏览器form提交来流转客户端环境]
     */
    public function returnConfim($data = [])
    {
        if (empty($data)) {
            $data = $_REQUEST;
        }
        $threeds  = isset($data['3ds']) ? $data['3ds'] : 0;
        $status   = isset($data['status']) ? $data['status'] : 0;
        $msg      = isset($data['msg']) ? $data['msg'] : '';
        $response = isset($data['Response']) ? $data['Response'] : '';
        $md       = isset($data['MD']) ? $data['MD'] : '';
        $paymentIntentId = isset($data['paymentIntentId']) ? $data['paymentIntentId'] : '';
        $orderId  = isset($data['orderId']) ? $data['orderId'] : '';
        $type = isset($data['type']) ? $data['type'] : '';
        $error = [];
        //3ds form
        if (!empty($md)) { //3ds有感认证()
            $ret = $this->confirmContinue3ds($data);
            if (!empty($ret)) {
                echo '<script>location.href="' . $ret['url'] . '";</script>';
                die;
            } else {
                $status = -1;
                $error = $this->error();
            }
        } elseif ($threeds == 1 && !empty($response)) {
            $ret = $this->confirmContinue($data);
            if (!empty($ret)) {
                if ($ret['method'] == 'post') {
                    $ret['data']['md'] = $paymentIntentId;
                    echo $this->confirmContinueForm($ret);
                    die;
                } else {
                    echo '<script>location.href="' . $ret['url'] . '";</script>';
                    die;
                }
            } else {
                $error = $this->error();
            }
        }
        if ($type == 'confirmContinue3ds') {
            $status = -1;
        }
        //payment end
        $msg = !empty($error['msg']) ? $error['msg'] : (!empty($msg) ? base64_decode($msg) : '');
        return [
            'status'  => $status, //1:成功, 0:失败或异常, -1:取消或被取消
            'msg'     => $msg,    //成功或失败提示信息
            'type'    => $type,
            'orderId' => $orderId, //原始订单编号
            'id' => str_replace($this->config['order_prefix'], '', $orderId), //无前缀订单编号
        ];
    }

    /**
     * 处理回调
     *
     * @return false|array
     * [
     *      'order_id' => '有前缀订单号',
     *      'order_sn' => '无前缀订单号',
     *      'type' => '支付类型',
     *      'pay_merchant' => '收款帐号',
     *      'pay_sn' => '通知流水号',
     *      'pay_id' => '付款编号',
     *      'pay_amount' => '支付金额',
     *      'pay_fee' => '手续费',
     *      'currency' => '货币',
     *      'pay_status' => '支付状态', //0默认无,1支付成功,-1退款
     *      'pay_status_str' => '状态文本描述',
     *      'pay_time' => '通道支付时间',
     *      'billing' => [
     *              'first_name' => '姓',
     *              'last_name'  => '名',
     *              'phone'      => '手机号',
     *              'country'    => '国家',
     *              'province'   => '省/洲',
     *              'city'       => '城市',
     *              'address'    => '街道/详细地址',
     *              'district'   => '',
     *              'zipcode'    => '',
     *       ],      
     * ]
     */
    public function notify()
    {
        $data = Utils::request($rawData);
        $json = $rawData['input'];
        //验证有效信息
        $header = Utils::modeData([
            'HTTP_X_TIMESTAMP' => time(),
            'HTTP_X_SIGNATURE' => '',
        ], $_SERVER);
        $timestamp = $header['HTTP_X_TIMESTAMP'];
        $signature = $header['HTTP_X_SIGNATURE'];
        if (empty($signature)) {
            $this->error('signature error', 1);
            return false;
        }
        if (hash_hmac('sha256', $timestamp . $json, $this->config['webhookSecret']) != $signature) {
            $this->error('failed to verify the signature', 2);
            return false;
        }
        if (empty($data) || !is_array($data)) {
            $this->error('notify data error', 3);
            return false;
        }
        $name = $data['name'];
        $response = $data['data']['object'];
        //id
        $order_sn = $response['merchant_order_id'];
        $pay_sn  = $response['request_id']; //交易流水订单号
        $pay_id  = $response['id']; //payment intent id
        //交易金额
        $type = 'airwallex';
        $pay_merchant = $this->config['clientId'];
        $pay_amount = 0;
        $pay_fee = 0;
        $currency = isset($response['currency']) ? $response['currency'] : '';
        $pay_status = 0;
        $pay_status_str = $response['status'];
        $pay_time = !empty($data['createAt']) ? strtotime($data['createAt']) : 0;
        switch ($name) {
            case 'payment_intent.created': //创建订单
                break;
            case 'payment_intent.cancelled':
                break;
            case 'payment_intent.succeeded': //支付处理成功
                $pay_amount = $response['amount'];
                $pay_status = 1;
                break;
            case 'refund.received':
                break;
            case 'refund.processing':   //退款中
                break;
            case 'refund.succeeded':   //退款完成
                $pay_amount = -abs($response['amount']);
                $pay_status = -1;
                break;
        }
        $pb = isset($response['latest_payment_attempt']['payment_method']['billing']) ? $response['latest_payment_attempt']['payment_method']['billing'] : [];
        $billing = [];
        if (!empty($pb)) {
            $address = $pb['address'];
            $billing = [
                'first_name' => $pb['first_name'],
                'last_name'  => $pb['last_name'],
                'phone'      => isset($pb['phone_number']) ? $pb['phone_number'] : '', //可为空
                'country'    => $address['country_code'],
                'province'   => $address['state'],
                'city'       => $address['city'],
                'address'    => $address['street'],
                'district'   => '',
                'zipcode'    => '',
            ];
        }
        return [
            'order_id'      => $order_sn,
            'order_sn'      => str_replace($this->config['order_prefix'], '', $order_sn), //无前缀订单编号
            'type'          => $type,
            'pay_merchant'  => $pay_merchant,
            'pay_sn'        => $pay_sn,
            'pay_id'        => $pay_id,
            'pay_amount'    => $pay_amount,
            'pay_fee'       => $pay_fee,
            'currency'      => $currency,
            'pay_status'    => $pay_status,
            'pay_status_str' => $pay_status_str,
            'pay_time'      => $pay_time,
            'billing' => $billing
        ];
    }

    /**
     * 创建支付订单，form提交到3ds认证[表单结构]
     *
     * @param array $data
     * @return void
     */
    public function createPaymentForm($data = [])
    {
        if (empty($data['url'])) {
            return '';
        }
        $formHtml = '<form id="collectionForm" action="' . $data['url'] . '" name="devicedata" method="POST" >';
        $formHtml .= '<input type="hidden" name="Bin" value="' . $data['data']['Bin'] . '">';
        $formHtml .= '<input type="hidden"  name="JWT"  value="' . $data['data']['jwt'] . '">';
        $formHtml .= '<input type="hidden"  name="continue"  value="Continue">';
        $formHtml .= '</form>';
        $formHtml .= '<script>document.getElementById("collectionForm").submit()</script>';
        return $formHtml;
    }
    /**
     * 跳转到3DS有感认证[表单结构]
     *
     * @param array $data
     * @return void
     */
    public function confirmContinueForm($data = [])
    {
        if (empty($data['url'])) {
            return '';
        }
        $formHtml = '<form id="stepUpForm" action="' . $data['url'] . '" name="stepup" method="POST" >';
        $formHtml .= '<input type="hidden"  name="JWT"  value="' . $data['data']['jwt'] . '">';
        if (!empty($data['data']['md'])) { //自定义标识字段[使用paymentIntentId],提交到会原样返回(实际并不会返回)
            $formHtml .= '<input type="hidden"  name="MD"  value="' . $data['data']['md'] . '">';
        }
        $formHtml .= '<input type="hidden"  name="continue"  value="Continue">';
        $formHtml .= '</form>';
        $formHtml .= '<script>document.getElementById("stepUpForm").submit()</script>';
        return $formHtml;
    }


    /**
     * 开启3DS验证
     *
     * @param [type] $data $data['response'] [confirmPayment->REQUIRES_CUSTOMER_ACTION->form的3ds隐式提交后返回的Response]
     * @return [array] $ret
     */
    protected function confirmContinue($data)
    {
        $requireKey = ['response', 'paymentIntentId', 'orderId'];
        //key首字母小写
        if (!empty($data)) {
            foreach ($data as $k => $v) {
                $k = lcfirst($k);
                $data[$k] = $v;
            }
        }
        //verify
        foreach ($requireKey as $k) {
            if (empty($data[$k])) {
                $msg = '[confirmContinue]' . $k . ' error';
                $this->error($msg, 1001);
                // throw new Exception($msg);
                return [];
            }
        }
        $confirmUrl = $this->config['airWallexDomain'] . '/api/v1/pa/payment_intents/' . $data['paymentIntentId'] . '/confirm_continue';
        $post = [
            'request_id' => Utils::dayOrderSn($this->config['order_prefix']) . mt_rand(100, 999),
            'type' => '3dsCheckEnrollment', // 3ds_check_enrollment 官方样例使用该参数类型
            'three_ds' => [
                'device_data_collection_res' => $data['response']
            ],
        ];
        $return_url = Utils::domain() . $this->config['return_url'];
        $return_url = Utils::buildUri($return_url, ['status' => 'success', 'type' => 'confirmContinue', 'paymentIntentId' => $data['paymentIntentId']]);
        return $this->requestApi($confirmUrl, $post, [
            'type' => 'confirmContinue',
            'orderId' => $data['orderId'],
            'return_url' => $return_url,
            'next_data' => []
        ]);
    }
    /**
     * 3DS验证(有感验证,会跳转到短信通知页面)
     * https://www.airwallex.com/docs/online-payments__api-integration__native-api__payment-with-3d-secure
     * @param [type] $data
     * @return void
     */
    protected function confirmContinue3ds($data)
    {
        $requireKey = ['response', 'paymentIntentId', 'transactionId', 'orderId'];
        //key首字母小写
        if (!empty($data)) {
            foreach ($data as $k => $v) {
                $k = lcfirst($k);
                $data[$k] = $v;
            }
        }
        $type = 'confirmContinue3ds';
        //verify
        foreach ($requireKey as $k) {
            if (empty($data[$k])) {
                $msg = "[{$type}] " . $k . ' error';
                $this->error($msg, 1001);
                // throw new Exception($msg);
                return [];
            }
        }
        $confirmUrl = $this->config['airWallexDomain'] . '/api/v1/pa/payment_intents/' . $data['paymentIntentId'] . '/confirm_continue';
        $post = [
            'request_id' => Utils::dayOrderSn($this->config['order_prefix']) . mt_rand(100, 999),
            'type' => '3dsValidate',
            'three_ds' => [
                'ds_transaction_id' => $data['transactionId']
            ]
        ];
        return $this->requestApi($confirmUrl, $post, [
            'type' => $type,
            'orderId' => $data['orderId'],
            'return_url' => '',
            'next_data' => []
        ]);
    }


    /**
     * 确认提交支传递
     *
     * @param [type] $createResponse
     * @param [type] $credit
     * @param [string] $orderId
     * @param [type] $device_id
     * @return void
     */
    protected function confirmPayment($createResponse, $credit, $orderId, $deviceId)
    {
        $paymentIntentId = $createResponse['id'];
        $confirmUrl = $this->config['airWallexDomain'] . '/api/v1/pa/payment_intents/' . $paymentIntentId . '/confirm';
        $requestId = Utils::dayOrderSn($this->config['order_prefix']) . mt_rand(100, 999);
        $billing = $createResponse['order']['shipping'];
        $payment_method = [
            'type' => 'card',
            'billing' => $billing,
            'card' => $credit
        ];
        $payment_method_options = [
            'card' => [
                'auto_capture' => true,
            ]
        ];
        $device = [
            'device_id' => $deviceId
        ];
        //return_url: The URL to redirect your customer back to after they authenticate or cancel their payment on the PaymentMethod’s app or site;
        // If you’d prefer to redirect to a mobile application, you can alternatively supply an application URI scheme.
        // the return_url that is required to provide the response upon the completion of the flow.
        $return_url = Utils::domain() . $this->config['return_url'];
        //default: 3ds
        $return_url = Utils::buildUri($return_url, ['3ds' => 1, 'paymentIntentId' => $paymentIntentId, 'orderId' => $orderId]);
        $post = [
            'request_id' => $requestId,
            'payment_method' => $payment_method,
            'payment_method_options' => $payment_method_options,
            'device' => $device,
            'return_url' => $return_url, //注意:第一次需要把orderId带上,否则后续返回将无orderId
        ];
        return $this->requestApi($confirmUrl, $post, [
            'type' => 'confirmPayment',
            'return_url' => $return_url,
            'orderId' => $orderId,
            'next_data' => [
                'Bin' => $credit['number']
            ]
        ]);
    }
    /**
     * 统一api支付请求交互
     *
     * @param [string] $url
     * @param [array] $post
     * @param array $param
     * [
     *      'orderId'   => '订单编号', //必须
     *      'type'       => '调用接口类型识标', //可选
     *      'return_url' => '成功后的返回地址',  //可选
     *      'next_data'  => '下一步form提交所需额外参数', //可选[array]
     * ] 
     * @return [array]
     */
    protected function requestApi($url, $post, $param = [])
    {
        $response = $this->request($url, json_encode($post, JSON_UNESCAPED_UNICODE));
        $status = isset($response['status']) ? $response['status'] : ''; //todo: if empty status
        $orderId = !empty($param['orderId']) ? $param['orderId'] : ''; //订单编号
        $returnUrl = !empty($param['return_url']) ? $param['return_url'] : '';
        $type = isset($param['type']) ? $param['type'] : '';
        if (empty($returnUrl)) {
            $returnUrl = Utils::domain() . $this->config['return_url'];
        }
        $returnUrl = Utils::buildUri($returnUrl, ['orderId' => $orderId]);
        //exception
        $code = isset($response['code']) ? $response['code'] : '';
        $message = isset($response['message']) ? $response['message'] : '';
        if (empty($status) && !empty($code)) {
            $status = $code;
        }
        //有可能已经是成功状态,重复操作了.
        $status = strpos($message, 'SUCCEEDED') !== false ? 'SUCCEEDED' : $status;
        if ($status == 'SUCCEEDED') {
            $returnUrl = Utils::buildUri($returnUrl, ['status' => 1, 'msg' => base64_encode('succeeded')]); //成功
            $ret = [
                'type' => 'redirect',
                'method' => 'get',  //直接成功跳转到return_url
                'url'   => $returnUrl,
                'data' => [],
            ];
            return $ret;
        } elseif ($status == 'CANCELLED') { //The PaymentIntent has been cancelled. Uncaptured funds will be returned.
            $returnUrl = Utils::buildUri($returnUrl, ['status' => -1, 'msg' => base64_encode('cancelled')]); //被取消
            $ret = [
                'type' => 'redirect',
                'method' => 'get',  //直接成功跳转到return_url
                'url'   => $returnUrl,
                'data' => [],
            ];
            return $ret;
        } elseif ($status == 'REQUIRES_CUSTOMER_ACTION') {
            //has next action
            $next_action = $response['next_action'];
            $jwt         = $next_action['data']['jwt'];
            if (empty($jwt)) {
                $msg = "[{$type}] jwt no result";
                $this->error($msg);
                return [];
            } else {
                //前端模拟form post提交跳转到next_url,跳转到自动定位到->$return_url ['3ds'=>1,'Response'=>'设备收集码']
                //The 3D Secure 2 flow will provide a response in the return_url you earlier provided.
                //The encrypted content you have received contains the device details that the issuer requires.
                $next_action['method'] = strtolower($next_action['method']);
                if (!empty($param['next_data']) && is_array($param['next_data'])) {
                    $next_action['data'] = array_merge($next_action['data'], $param['next_data']);
                }
                /**
                 * $next_action 数据结构
                 * [
                 *      'type' => 'redirect',
                 *      'method' => 'POST',
                 *      'url' => '',
                 *      'data' => [
                 *          'jwt' => '',
                 *          'stage' => 'WAITING_DEVICE_DATA_COLLECTION',
                 *          //3ds 
                 *          "acs": "https://0merchantacsstag.cardinalcommerce.com/MerchantACSWeb/creq.jsp",
                 *          "xid": "hiIGJAOdHalwwc31RjD0",
                 *          "req":   "eyJtZXNzYWdlVHlwZSI6IkNSZXEiLCJtZXNzYWdlVmVyc2lvbiI6IjIuMS4wIiwidGhyZWVEU1NlcnZlclRyYW5zSUQiOiJlNTkzNGExOC04OWE5LTRjMDQtYjcxYi01MDE4Y2E2MDg3ZmIiLCJhY3NUcmFuc0lEIjoiZTE1YzdmOGMtMjc0Ni00NGU1LTgzNzQtZWUxNzNmODcyNDMyIiwiY2hhbGxlbmdlV2luZG93U2l6ZSI6IjAyIn0",
                 *          "stage": "WAITING_USER_INFO_INPUT"
                 *      ],
                 * ]
                 */
                if (!empty($next_action['data']['jwt'])) {
                    $next_action['data']['JWT'] = $next_action['data']['jwt'];
                }
                return $next_action;
            }
        } else {
            // $status == 'CANCELLED' 
            // The PaymentIntent has been cancelled. Uncaptured funds will be returned.
            // $status == 'REQUIRES_PAYMENT_METHOD'
            //1. Populate payment_method when calling confirm
            //2. This value is returned if payment_method is either null, or the payment_method has failed during confirm,
            //   and a different payment_method should be provide
            // $status == 'REQUIRES_CAPTURE'            
            //See next_action for the details. For example next_action=capture indicates that capture is outstanding.
            $this->error(['status' => $status, 'message' => $message]);
        }
        $returnUrl = Utils::buildUri($returnUrl, ['status' => 0, 'msg' => base64_encode('[' . $status . ']' . $message), 'type' => $type]); //未支付,或异常失败
        $ret = [
            'type' => 'redirect',
            'method' => 'get',  //直接成功跳转到return_url
            'url'   => $returnUrl,
            'data' => [],
        ];
        return $ret;
    }
    /**
     * 从时效缓存中获取token
     *
     * @return string $token
     */
    protected function getToken()
    {
        $token = "";
        if (is_callable($this->cacheFunc)) {
            $token = call_user_func_array($this->cacheFunc, [$this->tokenKey]);
        }
        if (empty($token)) {
            $token = $this->setToken();
        }
        $this->token = $token;
        return $token;
    }
    /**
     * 请求并设置缓存时效
     *
     * @return string $token
     */
    protected function setToken()
    {
        $result = $this->request($this->config['tokenUrl']);
        $token  = isset($result['token']) ? $result['token'] : '';
        if (is_callable($this->cacheFunc)) {
            call_user_func_array($this->cacheFunc, [$this->tokenKey, $token, $this->tokenExpire]);
        }
        return $token;
    }
    /**
     * 订单数据结构[标准化]
     * @param $data = [
     *          'amount' => '101.01',
     *          'currency' => 'USD',
     *          'first_name' => 'aaaa',
     *          'last_name'  => 'bbbb',
     *          'city' => 'Reinholds',
     *          'country_code' => 'US',
     *          'street' => 'Pudong District',
     *          'state' => 'AL',
     *          'phone_number' => '11223344',
     *          'order_id' => '订单编号', //可选
     *  ];
     * @return [array]
     */
    protected function orderData(array $data = [])
    {
        $order = [];
        try {
            $address  = [
                'city' => $data['city'],  //required: City of the address,1-50 characters long
                'country_code' => $data['country_code'], //required: country code (2-letter ISO 3166-2 country code)
                'street' => $data['street'], //required: 1-200 characters long, Should not be a Post Office Box address, please enter a valid address
            ];
            //address optional 
            if (!empty($data['state'])) { //State or province of the address,1-50 characters long
                $address['state'] = $data['state'];
            }
            if (!empty($data['postcode'])) { //Postcode of the address, 1-50 characters long
                $address['postcode'] = $data['postcode'];
            }
            $shipping = [
                'first_name'   => $data['first_name'],
                'last_name'    => $data['last_name'],
                'address'      => $address
            ];
            //shipping optional
            if (!empty($data['shipping_method'])) { //Shipping method for the product
                $shipping['shipping_method'] = $data['shipping_method'];
            }
            if (!empty($data['phone_number'])) { //
                $shipping['phone_number'] = $data['phone_number'];
            }
            if (!empty($data['email'])) { //文档无该参数,加入也无效
                $shipping['email'] = $data['email'];
            }
            //自动加前缀
            $pre = $this->config['order_prefix'];
            $orderId = !empty($data['order_id']) ? $pre . trim($data['order_id'], $pre) : Utils::dayOrderSn($pre);
            $order = [
                'merchant_order_id' => $orderId, //The order ID created in merchant's order system that corresponds to this PaymentIntent
                'request_id' => Utils::dayOrderSn($this->config['order_prefix']) . mt_rand(100, 999), //Unique request ID specified by the merchant
                'amount' => $data['amount'],    //Payment amount. This is the order amount you would like to charge your customer.
                'currency' => $data['currency'], //Payment currency
                'descriptor' => $this->descriptor, //Descriptor that will display to the customer. For example, in customer's credit card statement
                'order' => [
                    'shipping' => $shipping
                ]
            ];
        } catch (Exception $e) {
            $error = "[orderData]" . $e->getMessage();
            $this->error($error);
            throw new Exception($error);
        }
        return $order;
    }
    /**
     * 信用卡信息结构[标准化]
     *   $credit = [
     *      'number' => '4000000000001091',
     *      'expiry_month' => '10',
     *      'expiry_year' => '2025',
     *      'cvc' => '123',
     *      'name' => '',
     *  ];
     * @return [array]
     */
    protected function orderCredit(array $data)
    {
        $credit = [];
        try {
            $credit = [
                'number' => $data['number'], //Card number
                'expiry_month' => $data['expiry_month'], //Two digit number representing the card’s expiration month
                'expiry_year' => $data['expiry_year'], //Four digit number representing the card’s expiration year
            ];
            //conditional
            if (!empty($data['cvc'])) {  //CVC code of this card
                $credit['cvc'] = $data['cvc'];
            }
            if (empty($data['name'])) { //Card holder name
                $credit['name'] = $data['name'];
            }
        } catch (Exception $e) {
            $error = "[orderCredit]" . $e->getMessage();
            $this->error($error);
            throw new Exception($error);
        }
        return $credit;
    }
    /**
     * 统一接口交互[自动带入所需的头信息与token]
     *
     * @param string $url
     * @param array  $data
     * @param boolean $isFollow
     * @return array
     */
    protected function request($url, $data = [], $isFollow = false)
    {
        if (empty($this->token)) {
            $header = [
                'Content-Type'  => 'application/json; charset=utf-8',
                'x-api-key'     => $this->config['apiKey'],
                'x-client-id'   => $this->config['clientId'],
            ];
        } else {
            $header = [
                'Content-Type'  => 'application/json; charset=utf-8',
                'region'        => 'string',
                'Authorization' => 'Bearer ' . $this->token,
            ];
        }
        $Curl   = new Curl;
        $result = $Curl->follow(true)->httpheader($header)->returntransfer(true)->verify(false, false)->verbose(0)->conntimeout(30)->timeout(30)->post($url, $data);
        $error  = $Curl->curlError();
        if (!empty($error)) {
            $this->error($error, 1);
        }
        return Utils::jsonArray($result);
    }
}
