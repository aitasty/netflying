<?php

namespace Netflying\interface;

use Netflying\data\Merchant;
use Netflying\data\Order;
use Netflying\data\OrderProduct;
use Netflying\data\Redirect;
use Netflying\data\OrderPayment;

interface Pay
{
    /**
     * 初始化支付通道
     *
     * @param Merchant $data 支付通道需要的对象数据
     * @return this
     */
    public function init(Merchant $merchant);
    /**
     * 提交支付信息(有的支付需要提交之后payment最后确认并完成)
     * @param Order
     * @return Redirect
     */
    public function purchase(Order $order, OrderProduct $product);
    /**
     *  统一回调通知接口
     *
     * @return OrderPayment
     */
    public function notify();

}