<?php
/*
 * @Author: He.Bin 
 * @Date: 2022-05-24 11:05:25 
 * @Last Modified by: He.Bin
 * @Last Modified time: 2022-05-24 17:37:35
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
        'expiry_year'    => 'string',
        'csv'            => 'string',
        'holder_name'    => 'string',
    ];
    protected $fieldsNull = [
        'card_number'    => null,
        'expiry_month'   => null,
        'expiry_year'    => null,
        'csv'            => null,
        'holder_name'    => null,
    ];


}
