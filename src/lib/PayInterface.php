<?php

namespace Netflying\Payment\lib;

use Netflying\Payment\data\Merchant;
use Netflying\Payment\data\Order;
use Netflying\Payment\data\Redirect;
use Netflying\Payment\data\OrderPayment;

/**
 * 标准支付接口类接口
 */
interface PayInterface
{
    /**
     * 初始化商户支付通道
     *
     * @param Merchant $Merchant 支付通道需要的对象数据
     * @return self
     */
    public function merchant(Merchant $Merchant);
    /**
     * 提交支付信息(有的支付需要提交之后payment最后确认并完成)
     * @param Order
     * @return Redirect
     */
    public function purchase(Order $Order): Redirect;
    /**
     *  统一回调通知接口
     *
     * @return OrderPayment
     */
    public function notify(): OrderPayment;

}