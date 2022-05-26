<?php
/*
 * @Author: He bin 
 * @Date: 2022-01-26 15:15:22 
 * @Last Modified by: He.Bin
 * @Last Modified time: 2022-05-25 17:29:21
 */

namespace Netflying\Payment\data;

/**
 * 支付通道基础数据结构
 */
class Merchant extends Model
{
    protected $fields = [
        //是否测试环境
        'is_test'  => 'bool',
        //收款帐号
        'merchant' => 'string',
        //返回地址
        'return_url' => 'string',
        //通知地址
        'notify_url' => 'string',
        //api帐号信息
        'api_account' => 'array',
        //api接口信息
        'api_data' => 'array',
    ];
    protected $fieldsNull = [
        'is_test' => null,
        'merchant' => null,
        'return_url' => null,
        'notify_url' => null,
        'api_account' => [],
        'api_data' => []
    ];

    protected $apiAccount = [];

    protected $apiData = [];

}
