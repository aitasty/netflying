<?php

namespace Netflying\Payment\common;


class Utils
{

    public static function empty($value)
    {
        if (!is_numeric($value) && empty($value)) {
            return true;
        }
        return false;
    }

    public static function mapData(array $mode, array $data, array $alias = [])
    {
        $put = [];
        foreach ($mode as $field => $val) {
            if (is_array($val)) {
                $put[$field] = self::mapData($val, (isset($data[$field]) ? $data[$field] : []), (isset($alias[$field]) ? $alias[$field] : []));
            } else {
                if (!empty($alias[$field])) {
                    $aliasArr = is_array($alias[$field]) ? $alias[$field] : explode(',', $alias[$field]);
                    foreach ($aliasArr as $f) {
                        $put[$field] = isset($data[$f]) ? $data[$f] : $val;
                    }
                } else {
                    $put[$field] = isset($data[$field]) ? $data[$field] : $val;
                }
            }
        }
        return $put;
    }

    /** 
     * 模型结构数据
     * @param array $mode 模型结构数据,并带有默认值
     * @param array $data 对模型数据的填充，否则不匹配使用默认值,(可支持值二维模型对称)
     * @param array $callback 对相应层级值使用回调处理
     * @return array []
     */
    public static function modeData(array $mode, array $data, $callback = [])
    {
        $merge = [];
        foreach ($mode as $k => $v) {
            $func = isset($callback[$k]) ? $callback[$k] : [];
            if (is_array($v)) {
                foreach ($v as $vk => $vv) {
                    $func1 = isset($callback[$k][$vk]) ? $callback[$k][$vk] : [];
                    if (isset($data[$k][$vk])) {
                        $merge[$k][$vk] = self::isCallable($data[$k][$vk], $func, $func1);
                    } else {
                        //是否为二维数组,二维数组必须为数字数组,二维模型对称
                        if (isset($data[$k][0])) {
                            foreach ($data[$k] as $dk => $dv) {
                                $merge[$k][$dk] = self::modeData($v, $dv, $func);
                            }
                            break;
                        } else {
                            $merge[$k][$vk] = self::isCallable($vv, $func, $func1);
                        }
                    }
                }
            } else {
                if (isset($data[$k])) {
                    $merge[$k] = self::isCallable($data[$k], $func);
                } else {
                    if (isset($data[0])) {
                        foreach ($data as $dk => $dv) {
                            $merge[$dk] = self::modeData($mode, $dv, $func); //二维模型对称
                        }
                        break;
                    } else {
                        $merge[$k] = self::isCallable($v, $func);
                    }
                }
            }
        }
        return $merge;
    }
    /**
     * 从里向外对值执行函数
     * @param [type] $value
     * @return all
     */
    public static function isCallable($value)
    {
        if (self::empty($value)) {
            return $value;
        }
        $args = func_get_args();
        array_shift($args);
        // $args = !empty($args) ? array_reverse($args) : [];
        if (empty($args)) {
            return $value;
        }
        //数组扁平化
        $flat = function ($arr) use (&$flat) {
            $data = [];
            foreach ($arr as $v) {
                if (is_array($v)) {
                    $data = array_merge($data, $flat($v));
                } else {
                    $data[] = $v;
                }
            }
            return $data;
        };
        $data = array_reverse($flat($args));
        foreach ($data as $func) {
            if (!empty($func) && is_callable($func)) {
                $callValue = $func($value);
                $value = !is_null($callValue) ? $callValue : $value;
            }
        }
        return $value;
    }

    /** 
     * 生成唯一的订单编号
     * @param string $prefix
     * @return string
     */
    public static function dayipSn($prefix = '')
    {
        date_default_timezone_set("PRC");
        list($usec, $sec) = explode(" ", microtime());
        $time = date('ymdHis', $sec);
        $usec = (float)$usec * 1000000;
        $usec = substr(str_pad($usec, 6, '0'), 0, 3);
        $ip = Request::ip();
        $ipint = ip2long($ip);
        if (empty($ipint)) {
            $ipint = mt_rand(0, 1000000000);
        }
        $sn = $prefix . $time . $ipint . $usec . mt_rand(0, 99);
        return $sn;
    }

    /**
     * json格式字符串转数据
     *
     * @param string $value
     * @param string $error
     * @return array
     */
    public static function jsonArray($value, &$error = '')
    {
        $arr = [];
        try {
            $arr = @json_decode($value, true);
            $error = json_last_error();
        } catch (\Exception $e) {
        }
        return $arr;
    }
    /**
     * 转驼峰命名
     * @param string $uncamelized_words
     * @param string $separator
     * @return void
     */
    public static function getCamelizeName($uncamelized_words, $separator = '_')
    {
        $uncamelized_words = $separator . str_replace($separator, " ", strtolower($uncamelized_words));
        return ltrim(str_replace(" ", "", ucwords($uncamelized_words)), $separator);
    }
    /**
     * 数字精度算法
     *
     * @param array $deciman
     * @param string $type  default: +  [+ - * /]
     * @param integer $hedge [0-9]
     * @param integer $multiple
     * @return float
     */
    public static function calfloat($decimal, $hedge = 2, $type = "+", $multiple = 100)
    {
        if (empty($decimal) || !is_array($decimal)) {
            return $decimal;
        }
        $first = array_shift($decimal);
        if (empty($decimal) || is_null($decimal)) {
            return $first;
        }
        $multiple = (int)$multiple;
        $rs = intval((string)((float)$first * (int)$multiple));
        $multitotal = $multiple;
        foreach ($decimal as $v) {
            $mv = intval((string)((float)$v * $multiple));
            if ($type == '+') {
                $rs += $mv;
            } elseif ($type == '-') {
                $rs -= $mv;
            } elseif ($type == '*') {
                $multitotal *= $multiple;
                $rs *= $mv;
            } elseif ($type == '/') {
                $multitotal = 1;
                if ($mv == 0) {
                    $rs = 0;
                } else {
                    $rs /= $mv;
                }
            }
        }
        $hedge = (int)abs($hedge);
        if ($hedge == 0) {
            return intval($rs / $multitotal);
        }
        return sprintf('%.' . $hedge . 'f', ($rs / $multitotal));
    }
    public static function caladd()
    {
        $decimal = func_get_args();
        if (empty($decimal)) {
            return 0;
        }
        return self::calfloat($decimal, 2, '+');
    }
    public static function calsub()
    {
        $decimal = func_get_args();
        if (empty($decimal)) {
            return 0;
        }
        return self::calfloat($decimal, 2, '-');
    }
    public static function calmul()
    {
        $decimal = func_get_args();
        if (empty($decimal)) {
            return 0;
        }
        return self::calfloat($decimal, 2, '*');
    }
    public static function caldiv()
    {
        $decimal = func_get_args();
        if (empty($decimal)) {
            return 0;
        }
        return self::calfloat($decimal, 2, '/');
    }

    /**
     * 多级数组合并
     */
    public static function arrayMerge(array $arr, array $arr1)
    {
        $newArr = [];
        foreach ($arr as $k => $v) {
            $val = isset($arr1[$k]) ? $arr1[$k] : null;
            if (is_null($val)) {
                $newArr[$k] = $v;
            } else {
                if (is_array($val)) {
                    $newArr[$k] = self::arrayMerge((is_array($v) ? $v : []), $val);
                } else {
                    $newArr[$k] = $val;
                }
            }
        }
        foreach ($arr1 as $k1 => $v1) {
            $val1 = isset($arr[$k1]) ? $arr[$k1] : null;
            if (!is_null($val1)) {
                continue;
            }
            $newArr[$k1] = $v1;
        }
        return $newArr;
    }
}
