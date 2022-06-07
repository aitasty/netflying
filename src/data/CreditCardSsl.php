<?php
/*
 * @Author: He.Bin 
 * @Date: 2022-05-24 11:05:25 
 * @Last Modified by: He.Bin
 * @Last Modified time: 2022-06-02 00:09:42
 */

namespace Netflying\Payment\data;

use Netflying\Payment\common\Openssl;
/**
 *  信用卡数据
 *
 */

class CreditCardSsl extends Model
{
    protected $fields = [
        'encrypt' => 'string'
    ];
    protected $fieldsNull = [
        'encrypt'    => null,
    ];

    /**
     * CreditCardSsl 转 CreditCard
     * @return CreditCard
     */
    public function creditCard()
    {
        $encrypt = $this->getEncrypt();
        return new CreditCard(
            Openssl::decrypt($encrypt)
        );
    }

}
