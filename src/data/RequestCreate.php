<?php
/*
 * @Author: He bin 
 * @Date: 2022-01-26 15:15:22 
 * @Last Modified by: He.Bin
 * @Last Modified time: 2022-06-02 11:45:32
 */

namespace Netflying\Payment\data;

/**
 * Request请求数据结构
 */
class RequestCreate extends Model
{
    protected $fields = [
        'type' => 'string',
        'url'  => 'string',
        'headers' => 'array',
        'data' => 'string',
        'log' => 'object',
    ];
    protected $fieldsNull = [
        'type' => null,
        'url' => null,
        'headers' => [],
        'data' => '',
        'log' => ''
    ];
    protected $headers = [];
    protected $data = [];

    //日志类名(含namespace),且该类名实现 \Netflying\payment\lib\LogInterface
    protected $log = "Netflying\Payment\lib\LogInterface";


}
