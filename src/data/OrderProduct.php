<?php
/*
 * @Author: He.Bin 
 * @Date: 2022-05-24 11:05:25 
 * @Last Modified by: He.Bin
 * @Last Modified time: 2022-05-25 16:09:38
 */

namespace Netflying\data;


/**
 *  订单商品数据结构
 */

class OrderProduct extends Model
{
    protected $fields = [
        //订单编号,对于对应订单
        'sn' => 'string',
        //商品名称
        'name' => 'string',
        //商品编号
        'reference_id' => 'string',
        //商品编号,sku等
        'reference' => 'string',
        //商品数量
        'quantity' => 'int',
        //单价
        'unit_price' => 'int',
        //合计
        'total_price' => 'int',
        //计量单位, kg,pcs等
        'quantity_unit' => 'string',
        //税费
        'total_tax_price' => 'int',
        //优惠金额，或优惠券金额
        'total_discount_price' => 'int',
        //商品图片
        'image_url' => 'string',
        //商品链接
        'product_url' => 'string',
    ];
    protected $defaults = [
        'sn' => null,
        'name' => null,
        'reference_id' => null,
        'reference' => null,
        'quantity' => null,
        'unit_price' => null,
        'total_price' => null,
        'quantity_unit' => '',
        'total_tax_price' => 0,
        'total_discount_price' => 0,
        'image_url' => '',
        'product_url' => ''
    ];
}
