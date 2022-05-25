<?php
/*
 * @Author: He.Bin 
 * @Date: 2022-05-24 11:05:25 
 * @Last Modified by: He.Bin
 * @Last Modified time: 2022-05-25 15:55:04
 */

namespace Netflying\data;


/**
 *  用户地址数据
 * 
 * @property string first_name;  string [ 0 .. 99 ] characters Customer’s first name.
 * @property string last_name; string [ 0 .. 99 ] characters Customer’s last name.
 * @property string email; string [ 0 .. 99 ] characters Customer’s email.
 * @property string phone; string [ 5 .. 99 ] characters Customer’s phone.
 * @property string country_code; string [ 0 .. 99 ] characters Customer’s country; Customer’s country. This value overrides the purchase country if they are different. Should follow the standard of ISO 3166 alpha-2. E.g. GB, US, * * DE, SE.
 * @property string region; string [ 0 .. 99 ] characters Customer’s region/state.
 * @property string city; string [ 0 .. 99 ] characters Customer’s city.
 * @property string district; string [ 0 .. 99 ] characters Customer’s district.
 * @property string postal_code; string [ 0 .. 10 ] characters Customer’s address.
 * @property string street_address; string [ 0 .. 99 ] characters Customer’s street.
 * @property string street_address2
 */

class Address extends Model
{
    protected $fields = [
        'first_name'      => 'string',
        'last_name'       => 'string',
        'email'           => 'string',
        'phone'           => 'string',
        'country_code'    => 'string',
        'region'          => 'string',
        'city'            => 'string',
        'district'        => 'string',
        'postal_code'     => 'string',
        'street_address'  => 'string',
        'street_address2' => 'string'
    ];
    protected $defaults = [
        'first_name'      => null,
        'last_name'       => null,
        'email'           => null,
        'phone'           => '',
        'country_code'    => null,
        'region'          => '',
        'city'            => null,
        'district'        => '',
        'postal_code'     => null,
        'street_address'  => null,
        'street_address2' => ''
    ];


}
