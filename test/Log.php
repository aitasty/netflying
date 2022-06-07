<?php
namespace Netflying\PaymentTest;


class Log
{
    public function save($request,$response) 
    {
        $data = [
            'request' => $request,
            'response' => $response
        ];
        file_put_contents('./log.txt',json_encode($data));
    }
}