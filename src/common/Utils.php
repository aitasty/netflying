<?php

namespace Netflying\common;

class Utils
{

    public static function empty($value)
    {
        if (!is_numeric($value) && empty($value)) {
            return true;
        }
        return false;
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
     * 获取请求数据
     * @param array &rawData 原始数据
     * @return array
     */
    public static function request(&$rawData = [])
    {
        $get   = $_GET;
        $post  = $_POST;
        $data  = $_REQUEST;
        $input = @file_get_contents('php://input');
        $put   = [];
        if (!empty($input)) {
            try {
                $put = @json_decode($input, true);
                // if (false !== strpos(self::contentType(), 'application/json')) {
                //     $put = (array) json_decode($input, true);
                // } else {
                //     @parse_str($input, $put);
                // }
            } catch (\Exception $e) {
            }
            if (empty($put)) {
                try {
                    @parse_str($input, $put);
                } catch (\Exception $e) {
                }
            }
        }
        try {
            $rawData['get']   = !empty($get) ? $get : [];
            $rawData['post']  = !empty($post) ? $post : [];
            $rawData['input'] = !empty($input) ? $input : '';
        } catch (\Exception $e) {
        }
        return array_merge($data, $put);
    }

    /**
     * 当前请求 HTTP_CONTENT_TYPE
     * @access public
     * @return string
     */
    public static function contentType()
    {
        $contentType = isset($_SERVER['HTTP_CONTENT_TYPE']) ? $_SERVER['HTTP_CONTENT_TYPE'] : '';
        if ($contentType) {
            if (strpos($contentType, ';')) {
                list($type) = explode(';', $contentType);
            } else {
                $type = $contentType;
            }
            return trim($type);
        }
        return '';
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
        $usec = substr(str_pad($usec, 6, '0'),0,3);
        $ip = self::ip();
        $ipint = ip2long($ip);
        if (empty($ipint)) {
            $ipint = mt_rand(0,1000000000);
        }
        $sn = $prefix . $time . $ipint. $usec;
        return $sn;
    }
    /**
     * 获取客户端IP地址
     * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
     * @param boolean $adv  是否进行高级模式获取（有可能被伪装）
     * @return mixed
     */
    public static function ip($type = 0, $adv = true)
    {
        $type      = $type ? 1 : 0;
        static $ip = null;
        if (null !== $ip) {
            return $ip[$type];
        }
        if ($adv) {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $pos = array_search('unknown', $arr);
                if (false !== $pos) {
                    unset($arr[$pos]);
                }
                $ip = trim(current($arr));
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (isset($_SERVER['REMOTE_ADDR'])) {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        // IP地址合法验证
        $long = sprintf("%u", ip2long($ip));
        $ip   = $long ? [$ip, $long] : ['0.0.0.0', 0];
        return $ip[$type];
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
    public static function isSsl()
    {
        $server = $_SERVER;
        if (isset($server['HTTPS']) && ('1' == $server['HTTPS'] || 'on' == strtolower($server['HTTPS']))) {
            return true;
        } elseif (isset($server['REQUEST_SCHEME']) && 'https' == $server['REQUEST_SCHEME']) {
            return true;
        } elseif (isset($server['SERVER_PORT']) && ('443' == $server['SERVER_PORT'])) {
            return true;
        } elseif (isset($server['HTTP_X_FORWARDED_PROTO']) && 'https' == $server['HTTP_X_FORWARDED_PROTO']) {
            return true;
        }
        return false;
    }
    public static function scheme()
    {
        return self::isSsl() ? 'https' : 'http';
    }
    /**
     *@param bool $strict true 仅仅获取HOST
     */
    public static function host($strict = false)
    {
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        return true === $strict && strpos($host, ':') ? strstr($host, ':', true) : $host;
    }

    public static function domain()
    {
        return self::scheme() . "://" . self::host();
    }
    public static function domainUrl($url)
    {
        $domain = self::domain();
        $pos = strpos($url, 'http');
        if (false === $pos || $pos > 0) {
            return $domain . '/' . ltrim($url, '/');
        }
        return $url;
    }
    public static function buildUri($url, $data = [])
    {
        if (empty($data)) {
            return $url;
        }
        $arr  = parse_url($url);
        if (empty($arr)) {
            return $url;
        }
        $query    = isset($arr['query']) ? $arr['query'] : '';
        $queryArr = !empty($query) ? self::parseQuery($query) : [];
        $queryArr = array_merge($queryArr, $data);
        $query = self::buildQuery($queryArr);
        $scheme = isset($arr['scheme']) ? $arr['scheme'] . '://' : '';
        $host   = isset($arr['host']) ? $arr['host'] : '';
        $path   = isset($arr['path']) ? $arr['path'] : '';
        return $scheme . $host . $path . '?' . $query;
    }

    /**
     * Build a query string from an array of key value pairs.
     * This function can use the return value of parse_query() to build a query
     * string. This function does not modify the provided keys when an array is
     * encountered (like http_build_query would).
     *
     * @param array     $params   Query string parameters.
     * @param int|false $encoding Set to false to not encode, PHP_QUERY_RFC3986
     *                            to encode using RFC3986, or PHP_QUERY_RFC1738
     *                            to encode using RFC1738.
     * @return string
     */
    public static function buildQuery(array $params, $encoding = PHP_QUERY_RFC3986)
    {
        if (!$params) {
            return '';
        }
        if ($encoding === false) {
            $encoder = function ($str) {
                return $str;
            };
        } elseif ($encoding === PHP_QUERY_RFC3986) {
            $encoder = 'rawurlencode';
        } elseif ($encoding === PHP_QUERY_RFC1738) {
            $encoder = 'urlencode';
        } else {
            throw new \Exception('Invalid type');
        }
        $qs = '';
        foreach ($params as $k => $v) {
            $k = $encoder($k);
            if (!is_array($v)) {
                $qs .= $k;
                if ($v !== null) {
                    $qs .= '=' . $encoder($v);
                }
                $qs .= '&';
            } else {
                foreach ($v as $vv) {
                    $qs .= $k;
                    if ($vv !== null) {
                        $qs .= '=' . $encoder($vv);
                    }
                    $qs .= '&';
                }
            }
        }
        return $qs ? (string) substr($qs, 0, -1) : '';
    }
    /**
     * Parse a query string into an associative array.
     * If multiple values are found for the same key, the value of that key
     * value pair will become an array. This function does not parse nested
     * PHP style arrays into an associative array (e.g., foo[a]=1&foo[b]=2 will
     * be parsed into ['foo[a]' => '1', 'foo[b]' => '2']).
     * @param string   $str         Query string to parse
     * @param int|bool $urlEncoding How the query string is encoded
     *
     * @return array
     */
    public static function parseQuery($str, $urlEncoding = true)
    {
        $result = [];
        if ($str === '') {
            return $result;
        }
        if ($urlEncoding === true) {
            $decoder = function ($value) {
                return rawurldecode(str_replace('+', ' ', $value));
            };
        } elseif ($urlEncoding === PHP_QUERY_RFC3986) {
            $decoder = 'rawurldecode';
        } elseif ($urlEncoding === PHP_QUERY_RFC1738) {
            $decoder = 'urldecode';
        } else {
            $decoder = function ($str) {
                return $str;
            };
        }
        foreach (explode('&', $str) as $kvp) {
            $parts = explode('=', $kvp, 2);
            $key = $decoder($parts[0]);
            $value = isset($parts[1]) ? $decoder($parts[1]) : null;
            if (!isset($result[$key])) {
                $result[$key] = $value;
            } else {
                if (!is_array($result[$key])) {
                    $result[$key] = [$result[$key]];
                }
                $result[$key][] = $value;
            }
        }
        return $result;
    }
    /**
     * 解析浏览器语言，得到localcode
     * @return bool|mixed|string
     */
    public static function getLocalCode()
    {
        $ret = '';
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && $_SERVER['HTTP_ACCEPT_LANGUAGE']) {
            $tmp = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
            if (stripos($tmp, '-') == 2) {
                $ret = substr($tmp, 0, 5);
                $ret = str_replace('-', '_', $ret);
            }
        }
        return $ret;
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
}