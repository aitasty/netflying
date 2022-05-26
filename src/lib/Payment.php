<?php

namespace Netflying\Payment\lib;

class Payment
{
    private $sdkjs = 'payment.js';

    protected $maxAge = 0;

    /**
     * 需要获取所有支持接口的sdk初始化参数
     */
    public function sdkInit()
    {
        
    }

    /**
     * payment sdk引用地址内容接口
     *
     * @return string
     */
    public function sdkjs()
    {
        $url= __DIR__.'/../js/'.$this->sdkjs;
        $js = file_get_contents($url);
        $this->cacheControl();
        echo $js;
        die;
    }
    /** 资源缓存头部
     * @return void
     */
    protected function cacheControl($type = 'js')
    {
        if ($type == 'js') {
            header('Content-type: application/x-javascript');
        }
        if ($this->maxAge > 0) {
            header('Cache-Control: max-age=' . $this->maxAge);
        } else {
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Cache-Control: post-check=0, pre-check=0", false);
            header("Pragma: no-cache");
        }
    }


}