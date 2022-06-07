<?php
namespace Netflying\PaymentTest;

use Netflying\Payment\common\Utils;
use Netflying\Payment\common\Request;
use Netflying\Payment\common\Openssl;

use Netflying\Payment\data\Address;
use Netflying\Payment\data\CreditCard;
use Netflying\Payment\data\CreditCardSsl;
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
            'country_code'    => 'DE',
            'region'          => '',
            'city'            => 'AL',
            'district'        => '',
            'postal_code'     => '1233322',
            'street_address'  => 'aaabbbccc',
            'street_address2' => ''
        ]);
    }
    public function creditCard()
    {
        return new CreditCard([
            'card_number'    => '4000000000000002',
            'expiry_month'   => '12',
            'expiry_year'    => '2025',
            'cvc'            => '123',
            'holder_name'    => 'join jack',
            'reference' => []
        ]);
    }
    public function creditCardSsl()
    {
        $card = $this->creditCard();
        return new CreditCardSsl([
            'encrypt' => Openssl::encrypt($card)
        ]);
    }

    public function order()
    {
        return new Order([
            'sn' => $this->sn,
            'currency' => 'EUR',
            'purchase_amount' => 12000,
            'items_amount' => 10000,
            'freight' => 2000,
            'tax_amount' => 0,
            'coupon_amount' => 0,
            'client_ip' => Request::ip(),
            'session_id' => Request::cookieSession(),
            'user_agent' => Request::ua(),
            'descript' => '',
            'products' => [
                $this->OrderProduct(1),
                $this->OrderProduct(2)
            ],
            'address' => [
                'billing' => $this->address(),
                'shipping' => $this->address(),
            ],
            'credit_card' => $this->CreditCardSsl(),
        ]);
    }
    public function orderProduct($id=1)
    {
        return new OrderProduct([
            'sn' => $this->sn,
            'name' => 'product ring',
            'reference_id' => $id,
            'reference' => 'cat666',
            'quantity' => 1,
            'unit_price' => 5000,
            'total_price' => 5000,
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