<?php
/*
 * @Author: He.Bin 
 * @Date: 2022-05-27 11:38:20 
 * @Last Modified by: He.Bin
 * @Last Modified time: 2022-06-02 12:05:40
 * 通用请口接口类
 */

namespace Netflying\Payment\common;

use Closure;
use Netflying\Payment\data\RequestCreate;
use Netflying\Payment\data\Response;

class Request
{
    /**
     * 请求接口
     * @param RequestCreateData
     * @param Closure
     * @return Response
     */
    public static function create(RequestCreate $Params, Closure $Fn = null)
    {
        $url = $Params->getUrl();
        $startTime = microtime(true);
        $startMem = memory_get_usage();
        $ret = (new Curl)->request($Params->getType(), $url, [
            'headers' => $Params->getHeaders(),
            'data' => $Params->getData()
        ]);
        $Response = new Response($ret);
        $runTime = round(microtime(true) - $startTime, 10); // s
        //$reqs    = $runTime > 0 ? number_format(1 / $runTime, 2) : '∞'; // req/s
        $memoryUse = number_format((memory_get_usage() - $startMem) / 1024, 2); // kb
        $Response->setRunTime($runTime)->setMemoryUse($memoryUse);
        if ($Fn instanceof Closure) {
            $Fn($Params, $Response);
        }
        return $Response;
    }

    /**
     * 获取接收数据
     * @param array &rawData 原始数据
     * @return array
     */
    public static function receive(&$rawData = [])
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
    public static function ua()
    {
        return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    }
    /**
     * 获取session值以及客户端cookie的PHPSESSID
     * @param $key 获取session索引值
     * @param $value 设置session索引值
     * @param $getSet 当从未设置过索引值时,设置值。
     * @return string 默认返回session_id值
     */
    public static function cookieSession($key = '', $value = false, $getSet = false, $fn = '')
    {
        $sessName = session_name();
        $cookieSessId = isset($_COOKIE[$sessName]) ? $_COOKIE[$sessName] : '';
        $isSession = false;
        if (empty(session_id()) && !headers_sent()) {
            session_start();
            $isSession = true;
        }
        $sessionId = session_id();
        if (!empty($cookieSessId) && $cookieSessId != $sessionId && $fn instanceof \Closure) {
            $fn([
                'cookie' => $_COOKIE,
                'session' => $_SESSION,
                'session_id' => $sessionId
            ]);
        }
        $rs = ['id' => $sessionId];
        if (!empty($key)) {
            $sessValue = isset($_SESSION[$key]) ? $_SESSION[$key] : false;
            if ($value !== false) {
                $_SESSION[$key] = $value;
                $rs[$key] = $value;
            } else if ($getSet !== false) {
                if ($sessValue === false) {
                    $_SESSION[$key] = $getSet;
                    $rs[$key] = $getSet;
                } else {
                    $rs[$key] = $sessValue;
                }
            } else {
                $rs[$key] = $sessValue;
            }
        }
        if ($isSession) {
            session_write_close();
        }
        if (empty($key)) {
            return $sessionId;
        } else {
            return $rs[$key];
        }
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

}
