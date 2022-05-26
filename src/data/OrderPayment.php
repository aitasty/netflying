<?php
/*
 * @Author: He.Bin 
 * @Date: 2022-05-24 11:05:25 
 * @Last Modified by: He.Bin
 * @Last Modified time: 2022-05-24 17:37:43
 */

namespace Netflying\Payment\data;


/**
 *  订单第三方支付记录数据(第三方同步或异常信息)
 */

class OrderPayment extends Model
{
    protected $fields = [
        //订单编号
        'sn' => 'string',
        //支付方式
        'type' => 'string',
        //收款帐号
        'merchant' => 'string',
        //付款编号
        'pay_id' => 'string',
        //支付(通知)流水号
        'pay_sn' => 'string',
        //货币
        'currency' => 'string',
        //支付金额
        'amount' => 'int',
        //手续费
        'fee' => 'int',
        //支付状态,0未支付,1已支付,-1退款
        'status' => 'int',
        //状态描述, created,approved,Pending,authorised等
        'status_descrip' => '状态描述',
        //状态时间
        'pay_time' => 'int',
    ];
    protected $defaults = [
        'sn' => null,
        'type' => null,
        'merchant' => null,
        'pay_id' => '',
        'pay_sn' => '',
        'currency' => null,
        'amount' => null,
        'fee' => 0,
        'status' => 0,
        'status_descrip' => null,
        'pay_time' => 0
    ];

}
