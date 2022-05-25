<?php
/*
 * @Author: He.Bin 
 * @Date: 2022-05-24 11:05:25 
 * @Last Modified by: He.Bin
 * @Last Modified time: 2022-05-24 17:37:43
 */

namespace Netflying\data;


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
    protected $defaults = [
        'status' => null,
        'url' => null,
        'type' => null,
        'data' => [],
        'exception' => []
    ];

    protected $exception = [
        'code' => '', //异常码
        'msg' => '', //异常信息
    ];

    public function getException()
    {
        return $this->exception;
    }

    public function setException(array $data)
    {
        return $this->setter('exception', $this->setterMode($this->exception, $data));
    }
}
