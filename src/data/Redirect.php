<?php
/*
 * @Author: He.Bin 
 * @Date: 2022-05-24 11:05:25 
 * @Last Modified by: He.Bin
 * @Last Modified time: 2022-05-24 17:37:43
 */

namespace Netflying\Payment\data;


/**
 *  重定向数据模型
 */

class Redirect extends Model
{
    protected $fields = [
        'status' => 'int',
        'url' => 'string',
        'type' => 'string',
        'data' => 'array',
        'exception' => 'array'
    ];
    protected $fieldsNull = [
        'status' => null,
        'url' => null,
        'type' => null,
        'data' => [],
        'exception' => []
    ];

    protected $exception = [
        'code' => 'int', //异常码
        'msg' => 'string', //异常信息
    ];

    protected $exceptionNull = [
        'code' => 0,
        'msg' => ''
    ];

}
