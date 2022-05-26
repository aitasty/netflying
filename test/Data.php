<?php
namespace Netflying\Payment\Test;

use Netflying\Payment\common\Utils;

use Netflying\Payment\data\Address;
use Netflying\Payment\data\CreditCard;
use Netflying\Payment\data\Order;
use Netflying\Payment\data\OrderProduct;

class Data
{
    protected $sn = null;

    public function __construct()
    {
        $this->sn = Utils::dayipSn();
    }
    public function address()
    {
        return new Address([
            'first_name'      => 'john',
            'last_name'       => 'jeck',
            'email'           => 'aaa@qq.com',
            'phone'           => '12122233',
            'country_code'    => 'US',
            'region'          => '',
            'city'            => 'AL',
            'district'        => '',
            'postal_code'     => '1233322',
            'street_address'  => 'aaabbbccc',
            'street_address2' => ''
        ]);
    }
    public function CreditCard()
    {
        return new CreditCard([
            'card_number'    => '4000000000000002',
            'expiry_month'   => '12',
            'expiry_year'    => '25',
            'csv'            => '123',
            'holder_name'    => 'join jack',
        ]);
    }
    public function Order()
    {
        return new Order([
            'sn' => $this->sn,
            'currency' => 'USD',
            'purchase_amount' => 12000,
            'items_amount' => 10000,
            'freight' => 2000,
            'tax_amount' => 0,
            'coupon_amount' => 0,
            'client_ip' => Utils::ip(),
            'session_id' => Utils::cookieSession()
        ]);
    }
    public function OrderProduct($id=1)
    {
        return new OrderProduct([
            'sn' => $this->sn,
            'name' => 'product ring',
            'reference_id' => $id,
            'reference' => 'cat666',
            'quantity' => 1,
            'unit_price' => 10000,
            'total_price' => 10000,
            'quantity_unit' => 'kg',
            'total_tax_price' => 0,
            'total_discount_price' => 0,
            'image_url' => '',
            'product_url' => ''
        ]);
    }

}
if (!count(debug_backtrace()))
{
}