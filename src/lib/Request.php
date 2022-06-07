<?php
/*
 * @Author: He.Bin 
 * @Date: 2022-05-27 11:37:19 
 * @Last Modified by: He.Bin
 * @Last Modified time: 2022-05-30 19:31:15
 * 
 * 日志支持,支付请求接口类
 */

namespace Netflying\Payment\lib;

use Netflying\Payment\common\Request as Rq;
use Netflying\Payment\data\Merchant;
use Netflying\Payment\data\RequestCreate;
use Netflying\Payment\data\Response;

class Request
{
    /**
     * 请求接口
     *
     * @param RequestCreateData $params
     * @return Response
     */
    public static function create(RequestCreate $Params)
    {
        return Rq::create($Params, function (RequestCreate $request, Response $response) use ($Params) {
            $logClass = $Params->getLog();
            if (is_object($logClass)) {
                call_user_func_array([$logClass, 'save'], [$request, $response]);
            }
        });
    }
}
