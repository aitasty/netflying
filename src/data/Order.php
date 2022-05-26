<?php
/*
 * @Author: He.Bin 
 * @Date: 2022-05-24 11:05:25 
 * @Last Modified by: He.Bin
 * @Last Modified time: 2022-05-25 16:08:00
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
    ];
    protected $defaults = [
        'sn' => null,
        'currency' => null,
        'purchase_amount' => null,
        'items_amount' => 0,
        'freight' => 0,
        'tax_amount' => 0,
        'coupon_amount' => 0,
        'client_ip' => '',
        'session_id' => ''
    ];


}
