<?php

namespace Netflying\Payment\lib;

use Netflying\Payment\data\RequestCreate;
use Netflying\Payment\data\Response;

/**
 * 接口日志类接口
 */
interface LogInterface
{
    /**
     * 写入日志
     */
    public function save(RequestCreate $Request, Response $Response);
}
