<?php
/*
 * @Author: He.Bin 
 * @Date: 2022-05-24 11:05:25 
 * @Last Modified by: He.Bin
 * @Last Modified time: 2022-06-03 22:02:46
 */

namespace Netflying\Payment\data;


/**
 *  信用卡数据
 *
 */

class CreditCard extends Model
{
    protected $fields = [
        'card_number'    => 'string',
        'expiry_month'   => 'string',
        //yesr 4位
        'expiry_year'    => 'string',
        'cvc'            => 'string',
        'holder_name'    => 'string',
        'reference' => 'array',
    ];
    protected $fieldsNull = [
        'card_number'    => null,
        'expiry_month'   => null,
        'expiry_year'    => null,
        'cvc'            => null,
        'holder_name'    => null,
        'reference' => []
    ];

    protected $reference = [];

}
