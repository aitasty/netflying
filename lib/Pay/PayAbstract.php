<?php

namespace Netflying\Pay;

abstract class PayAbstract implements PayInterface
{
    //init config
    protected $config = [];
    //排除校验
    protected $excludeConfKey = [];
    //是否可初始化
    protected $initialized = 1;
    //错误信息
    protected $error = [
        'code' => 0,
        'msg'  => ''
    ];
    /**
     * 初始化配置参数
     * @param array $config 构造必要配置参数
     * @return void|Exception
     */
    protected function config(array $config = [])
    {
        foreach ($this->config as $k => $v) {
            $conf = isset($config[$k]) ? $config[$k] : $v;
            if (is_numeric($conf)) {
                continue;
            }
            if (empty($conf) && !in_array($k, $this->excludeConfKey)) {
                $this->initialized = 0;
                $this->error("{$k} false");
                break;
            }
            $this->config[$k] = $conf;
        }
        if ($this->initialized != 1) {
            throw new \Exception($this->error()['msg']);
        }
    }

    /** 接口调用期间错误信息
     * @param string $msg 错误信息 
     * @param integer $code 错误码(不同的接口错误码自定义)
     * @return array 
     */
    public function error($msg = '', $code = 0)
    {
        if (!empty($msg)) {
            $msg = is_array($msg) ? json_encode($msg, JSON_UNESCAPED_UNICODE) : $msg;
            $this->error = ['msg' => $msg, 'code' => $code];
        }
        return $this->error;
    }
}
