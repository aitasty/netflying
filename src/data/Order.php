<?php
/*
 * @Author: He.Bin 
 * @Date: 2022-05-24 11:05:25 
 * @Last Modified by: He.Bin
 * @Last Modified time: 2022-06-04 23:42:45
 */

namespace Netflying\Payment\data;


/**
 *  订单数据模型
 *
 */

class Order extends Model
{
    protected $fields = [
        //订单编号
        'sn'             => 'string',
        //货币
        'currency'       => 'string',
        //应付款
        'purchase_amount'=> 'int', //分为单位
        //订单商品金额
        'items_amount' => 'init',
        //计量单位, kg,pcs等
        'quantity_unit' => 'string',
        //运费
        'freight'    => 'int',
        //税费
        'tax_amount' => 'int',
        //优惠券金额
        'coupon_amount' => 'int',
        //ip
        'client_ip' => 'string',
        //cookie session
        'session_id' => 'string',
        //ua
        'user_agent' => 'string',
        //订单简单描述
        'descript' => 'string',
        //订单商品
        'products' => 'array',
        //地址
        'address' => 'array', //多个，快递及帐单地址
        //credit
        'credit_card' => 'object',
        //decrypt credit card 从CreditCardSsl还原信息
        'credit_card_data' => 'object',
        //订单设备信息,js客户端收集
        'device_data' => 'object',
        //订单扩展信息
        'sn_data' => 'array',
    ];
    protected $fieldsNull = [
        'sn' => null,
        'currency' => null,
        'purchase_amount' => null,
        'items_amount' => 0,
        'quantity_unit' => '',
        'freight' => 0,
        'tax_amount' => 0,
        'coupon_amount' => 0,
        'client_ip' => '',
        'session_id' => '',
        'user_agent' => '',
        'descript' => '',
        'products' => [],
        'address' => [],
        'credit_card' => '',
        'credit_card_data' => '',
        'device_data' => '',
        'sn_data' => []
    ];

    protected $products = [
        '0' => 'object'
    ];
    protected $productsNull = [
        '0' => null
    ];

    protected $address = [
        'shipping' => 'object',
        'billing' => 'object'
    ];
    protected $addressNull = [
        'shipping' => null,
        'billing' => null 
    ];
    protected $snData = [];
    protected $products0 = "Netflying\Payment\data\OrderProduct";
    protected $shipping = "Netflying\Payment\data\Address";
    protected $billing = "Netflying\Payment\data\Address";
    protected $creditCard = "Netflying\Payment\data\CreditCardSsl";
    protected $creditCardData = "Netflying\Payment\data\CreditCard";
    protected $deviceData = "Netflying\Payment\data\OrderDevice";
}
