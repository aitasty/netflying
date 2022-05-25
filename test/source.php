<?php

//引用命名空间
require __DIR__ . '/../src/data/Model.php';
require __DIR__ . '/../src/data/Merchant.php';

require __DIR__ . '/../src/lib/Payment.php';


$Payment = new Netflying\lib\Payment;

$Payment->sdkjs();
