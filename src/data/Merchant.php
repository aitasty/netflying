<?php
/*
 * @Author: He bin 
 * @Date: 2022-01-26 15:15:22 
 * @Last Modified by: He.Bin
 * @Last Modified time: 2022-05-31 11:25:23
 */

namespace Netflying\Payment\data;

/**
 * 支付通道基础数据结构
 */
class Merchant extends Model
{
    protected $fields = [
        //支付通道类型，标识，名称等
        'type' => 'string',
        //收款帐号
        'merchant' => 'string',
        //是否测试环境
        'is_test'  => 'bool',
        //api帐号信息
        'api_account' => 'array',
        //api接口信息
        'api_data' => 'array',
    ];
    protected $fieldsNull = [
        'is_test' => null,
        'type' => null,
        'merchant' => null,
        'api_account' => [],
        'api_data' => [],
    ];

    protected $apiAccount = [];

    protected $apiData = [];

}
