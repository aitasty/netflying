<?php
/*
 * @Author: He bin 
 * @Date: 2022-01-26 15:15:22 
 * @Last Modified by: He.Bin
 * @Last Modified time: 2022-06-02 12:13:20
 */

namespace Netflying\Payment\data;

/**
 * Request请求响应数据
 */
class Response extends Model
{
    protected $fields = [
        'code' => 'int', //http响应码
        'errno' => 'string', //错误码
        'errmsg' => 'string', //错误信息
        'info' => 'array', //请求详情
        'header' => 'string', //响应头
        'body' => 'string', //响应体
        'run_time' => 'string', //请求执行时间
        'memory_use' => 'string', //消耗内存数
        'reference' => 'array', //额外附属后置处理信息
    ];
    protected $fieldsNull = [
        'code' => 0,
        'errno' => '',
        'errmsg' => '',
        'info' => [],
        'header' => '',
        'body' => '',
        'run_time' => '',
        'memory_use' => '',
        'reference' => [],
    ];

    protected $info = [];

}
