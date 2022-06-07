<?php
namespace Netflying\PaymentTest;

use Netflying\Payment\data\Merchant;

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
        return $Merchant;
    }

    

}
if (!count(debug_backtrace()))
{
    $Pay = new Pay;
    $Pay->index();
}