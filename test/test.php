<?php

//引用命名空间
require __DIR__ . '/../src/data/Model.php';
require __DIR__ . '/../src/data/Merchant.php';

//引用测试脚本
require __DIR__ . '/../test/Pay.php';

$Pay = new \NetflyingTest\Pay;
$Pay->index();
