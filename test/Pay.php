<?php
namespace NetflyingTest;

use Netflying;
use Netflying\data\Merchant;

class Pay
{
    function index()
    {
        $Merchant = new Merchant([
            'is_test' => 1,
            'merchant' => 'aaa',
            'return_url' => 'return url',
            'notify_url' => 'notify url',
        ]);
        $arr = $Merchant->toJson();
        var_dump($arr);die;
        echo $Merchant;
    }

    

}
if (!count(debug_backtrace()))
{
    $Pay = new Pay;
    $Pay->index();
}