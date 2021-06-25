<?php

namespace Netflying\Pay;

interface PayInterface
{
    /**
     * 提交支付信息(有的支付需要提交之后payment最后确认并完成)
     */
    public function purchase(array $data);
    /**
     *  统一回调通知接口
     *
     * @return void
     */
    public function notify();

}