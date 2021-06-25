<?php

namespace Netflying\Pay\Paypal;

use Exception;
use Netflying\Common\Utils;
use Netflying\Common\Curl;

/**
 * 一. 初始化Paypal
 * $Paypal = new Nvp($config);
 * 二. 支付
 * $Paypal->purchase(); //获取客户调起(跳转)地址
 * 三. 客户操作完毕(成功:服务端发起支付确认complete)
 * $Paypal->payStatus();
 */
class Nvp extends Paypal
{

    protected $config = [
        'sandbox'       => '',    //required
        'clientId'      => '',    //required
        'secret'        => '',    //required
        'account'       => '',    //required
        'api_user'      => '',    //required
        'api_password'  => '',    //required
        'api_signature' => '',    //required
        'notify_url'    => '',    //required
        'return_url'    => '',    //required
        'cancel_url'    => '',    //required
        'api_version'   => '61.0',    //optional
        'api_nvp0'      => 'https://api-3t.paypal.com/nvp',
        'api_nvp1'      => 'https://api-3t.sandbox.paypal.com/nvp',
        //nvp 取到TOKEN后需要跳转到的链接
        'nvp_pay_url0'   => 'https://www.paypal.com/webscr?cmd=_express-checkout&useraction=commit&token=',
        'nvp_pay_url1'   => 'https://www.sandbox.paypal.com/webscr?cmd=_express-checkout&useraction=commit&token=',
    ];

    public function __construct(array $config = [])
    {
        $this->config($config);
        foreach (['notify_url', 'return_url', 'cancel_url'] as $v) {
            $this->config[$v] = Utils::domainUrl($this->config[$v]);
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
    public function purchase($data)
    {
        $post = Utils::modeData([
            'order_sn' => null,
            'amount'   => null,
            'currency' => null,
            'description' => '',
            'site_name' => '',
        ], $data);
        if (empty($post['order_sn'])) {
            $post['order_sn'] = Utils::dayOrderSn();
        }
        //verify require
        $post = $post ? $post : [];
        foreach ($post as $k => $v) {
            if (is_null($v)) {
                $this->error('nvpCheckout.is_null.' . $k);
                return false;
            }
        }
        $token = $this->checkoutToken($post);
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
        $returnData = $this->request('GetExpressCheckoutDetails', $fields);
        return $returnData;
    }

    /**
     * 客户端支付完成返回地址处理
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
    public function payStatus()
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
            $response = $this->request('DoExpressCheckoutPayment', $fields);
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
            'status'=> $status,
            'code'  => $code,
            'data'  => $data,
        ];
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
     * nvp 订单信息
     * @param [type] $data
     * @return false|string $token
     */
    protected function checkoutToken($data)
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
        $localCode = Utils::getLocalCode();
        if ($localCode) {
            $fields['LOCALECODE'] = $localCode; //由于可能会出现中文界面，我们主动传递下LOCALECODE
        }
        if (!empty($siteName)) {
            $fields['BRANDNAME'] = $siteName; //显示站点名字
        }
        $returnData = $this->request('SetExpressCheckout', $fields);
        if (empty($returnData)) {
            return false;
        }
        return isset($returnData['TOKEN']) ? $returnData['TOKEN'] : false;
    }
    /**
     * 执行nvp API请求
     * @param string $method 需要执行的方法
     * @param array  $fields 参数
     * @throws PaypalException 返回PAYPAL错误号，错误信息为PP返回的JSON字串
     * @return array 返回值，如果出错，会抛异常
     */
    protected function request($method, array $fields)
    {
        $datas = array(
            'METHOD'    => $method,
            'VERSION'   => $this->config['api_version'],
            'USER'      => $this->config['api_user'],
            'PWD'       => $this->config['api_password'],
            'SIGNATURE' => $this->config['api_signature'],
        );
        $url = $this->config['sandbox'] == 1 ? $this->config['api_nvp1'] : $this->config['api_nvp0'];
        $datas = array_merge($datas, $fields);
        $httpResponse = (new Curl)->returntransfer()->verify(false,false)->verbose(0)->timeout(30)->post($url,$datas);
        $response = [];
        try{
            parse_str($httpResponse, $response);
        } catch (Exception $e) {}
        $invnum = isset($fields['INVNUM']) ? $fields['INVNUM'] : 0;
        if (is_array($response) && isset($response['ACK']) && $response['ACK'] == 'Success') {
            return $response;
        } else {
            if (isset($response['ACK'])) {
                $responseStr = json_encode($response);
                // 错误号 & 错误码
                $errorCode = isset($response['L_ERRORCODE0']) ? $response['L_ERRORCODE0'] : 10000;
                $errorMsg  = isset($response['L_LONGMESSAGE0']) ? $response['L_LONGMESSAGE0'] : 'unknown';
                $this->error(['responseStr' => $responseStr, 'errorCode' => $errorCode, 'errorMsg' => $errorMsg, 'invnum' => $invnum, 'method' => $method]);
                return false;
            } else {
                $responseStr = !empty($response) ? json_encode($response) : '';
                $this->error(['responseStr' => $responseStr, 'invnum' => $invnum, 'method' => $method]);
                return false;
            }
        }
    }



}
