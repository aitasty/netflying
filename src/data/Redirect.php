<?php
/*
 * @Author: He.Bin 
 * @Date: 2022-05-24 11:05:25 
 * @Last Modified by: He.Bin
 * @Last Modified time: 2022-06-02 16:01:59
 */

namespace Netflying\Payment\data;


/**
 *  重定向数据模型
 */

class Redirect extends Model
{
    protected $fields = [
        //1正常,0失败,-1异常
        'status' => 'int',
        'url' => 'string',
        'type' => 'string',
        'params' => 'array',
        'exception' => 'array'
    ];
    protected $fieldsNull = [
        'status' => null,
        'url' => null,
        'type' => null,
        'params' => [],
        'exception' => []
    ];

    protected $params = [];

    protected $exception = [
        'code' => 'int', //异常码
        'msg' => 'string', //异常信息
    ];

    protected $exceptionNull = [
        'code' => 0,
        'msg' => ''
    ];

}
