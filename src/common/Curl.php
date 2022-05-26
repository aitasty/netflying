<?php

namespace Netflying\Payment\common;

/**
 *  CURL通用配置类【单线程&多线程】
 *  @author hebin
 *  @version v2.0
 */

if (!function_exists('curl_setopt_array')) {
	function curl_setopt_array(&$ch, $curl_options)
	{
		foreach ($curl_options as $option => $value) {
			$option = strtoupper($option);
			if (!curl_setopt($ch, $option, $value)) {
				return false;
			}
		}
		return true;
	}
}
if (!function_exists('nowflush')) {
	function nowflush($str, $timeout = 0)
	{
		@ob_end_flush();
		echo str_repeat(" ", 2048);
		echo $str;
		flush();
		if (!empty($timeout)) {
			sleep($timeout);
		}
	}
}
if (!function_exists('msectime')) {
	function msectime()
	{
		list($msec, $sec) = explode(' ', microtime());
		$msectime =  sprintf('%.0f', (floatval($msec) + floatval($sec)) * 10000);
		$msectime =  substr($msectime, 0, -4) . '.' . substr($msectime, -4);
		return $msectime;
	}
}
class Curl
{
	private 	$_stdfp  	= 	false;
	private 	$_stdtrack 	= 	false;
	private 	$_options 	= 	array();
	private     $cookiejarFile = '';
	private     $cookieFile    = '';
	private  	$_cookie     = 	[];
	private     $_cookieout  =  [];
	private  	$_jarfile    = 	false;
	private  	$_cookiejar  = 	false;
	private     $_url = false;
	private 	$_id = false;
	private 	$_ch		=	false;
	private  	$_jarhandle = 	false;
	private 	$_data		=	[];
	private 	$_response	= 	false;
	private 	$_header	= 	false;
	private     $_headerout =   false;
	private     $_location  =   false;
	private 	$_body		= 	false;
	private 	$_errno  	= 	false;
	private 	$_errmsg 	= 	false;
	private 	$_info  	= 	false;
	private     $_httpcode  =   false;
	private 	$_postjson  =   false;
	public function __construct($url = false, $id = false)
	{
		$this->_id = $id;
		if ($this->isUrl($url)) {
			$this->_init($url);
		}
	}
	public function __clone()
	{
		trigger_error('Clone is not allow!', E_USER_ERROR);
	}
	private function _init($url = '')
	{
		if (!function_exists("curl_init")) {
			return false;
		}
		$this->free();
		$this->_ch = curl_init();
		if (!isset($this->_options[CURLOPT_COOKIE])) {
			$this->cookiejar();
		} else {
			$this->cookie();
		}
        // init config if not set curlopt
		if (!empty($url)) $this->url($url);
		if (!isset($this->_options[CURLOPT_RETURNTRANSFER])) $this->returntransfer();
		if (!isset($this->_options[CURLOPT_HTTPHEADER])) 	$this->httpheader();
		if (!isset($this->_options[CURLOPT_HEADER])) 		$this->header();
		if (!isset($this->_options[CURLINFO_HEADER_OUT])) 	$this->headerout();
		if (!isset($this->_options[CURLOPT_USERAGENT])) 		$this->useragent();
		if (!isset($this->_options[CURLOPT_FOLLOWLOCATION])) $this->follow();
		if (!isset($this->_options[CURLOPT_AUTOREFERER])) 	$this->autoreferer();
		if (!isset($this->_options[CURLOPT_REFERER])) 		$this->referer();
		if (!isset($this->_options[CURLOPT_CONNECTTIMEOUT])) $this->conntimeout();
		if (!isset($this->_options[CURLOPT_TIMEOUT])) 		$this->timeout();
		if (!isset($this->_options[CURLOPT_SSL_VERIFYPEER])) $this->verify();
		if (!isset($this->_options[CURLOPT_COOKIESESSION])) 	$this->cookiesession();
		if (!isset($this->_options[CURLOPT_VERBOSE])) 		$this->verbose();
		if (!isset($this->_options[CURLOPT_NOBODY])) 		$this->nobody();
		$this->_options[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
		return $this;
	}
	public function url($url)
	{
		if (!empty($url)) {
			$this->_options[CURLOPT_URL] = $url;
			$this->_url = $url;
		}
		return $this;
	}
	public function cookiejarFile($file = '')
	{
		if (!file_exists($file))
			return false;
		$this->cookiejarFile = $file;
	}
	public function cookieFile($file = '')
	{
		if (!file_exists($file))
			return false;
		$this->cookieFile = $file;
	}
	public function cookiejar($cookie = true)
	{
		if (!empty($cookie)) {
			$cookie = is_string($cookie) ? $cookie : $this->_cookiejar;
			$this->_jarhandle 	= tmpfile();
			$metaDatas 			= stream_get_meta_data($this->_jarhandle);
			$this->_jarfile 	= $metaDatas['uri'];
			if (!empty($cookie)) {
				$this->_cookiejar = $cookie;
				fwrite($this->_jarhandle, $cookie);
				if (!empty($this->cookiejarFile)) {
					@file_put_contents($this->cookiejarFile, $this->_cookiejar);
				}
			}
			$this->_options[CURLOPT_COOKIEJAR]  = $this->_jarfile;
			$this->_options[CURLOPT_COOKIEFILE] = $this->_jarfile;
			if (isset($this->_options[CURLOPT_COOKIE])) {
				unset($this->_options[CURLOPT_COOKIE]);
			}
		} else {
			$this->cookie();
		}
		return $this;
	}
	public function cookie($cookie = true)
	{
		if (!empty($cookie)) {
			$cookie = is_array($cookie) ? $cookie : $this->_cookie;
			$cookieParse = true;
			if (is_array($cookie)) {
				$cookie = array_merge($this->_cookie, $cookie);
				$this->_cookie 	=  $cookie;
				$cookieParse 	=  http_build_query($cookie);
				$cookieParse 	=  str_ireplace('&', '; ', $cookieParse);
				if (!empty($this->cookieFile)) {
					@file_put_contents($this->cookieFile, json_encode($this->_cookie));
				}
			}
			$this->_options[CURLOPT_COOKIE] = $cookieParse;
			if (isset($this->_options[CURLOPT_COOKIEJAR])) {
				unset($this->_options[CURLOPT_COOKIEJAR]);
			}
			if (isset($this->_options[CURLOPT_COOKIEFILE])) {
				unset($this->_options[CURLOPT_COOKIEFILE]);
			}
		} else {
			$this->cookiejar();
		}
		return $this;
	}
	public function returntransfer($isReturn = true)
	{
		$this->_options[CURLOPT_RETURNTRANSFER] = $isReturn;
		return $this;
	}
	public function httpheader($options = [])
	{
		$headers = [];
		$headers 	= array_merge($headers, $options);
		$headerArr 	= array();
		foreach ($headers as $n => $v) {
			$headerArr[] = $n . ': ' . trim($v);
		}
		$this->_options[CURLOPT_HTTPHEADER] = $headerArr;
		return  $this;
	}
	public function header($openhead = true)
	{
		$this->_options[CURLOPT_HEADER] = $openhead;
		return $this;
	}
	public function headerout($openhead = true)
	{
		$this->_options[CURLINFO_HEADER_OUT] = $openhead;
		return $this;
	}
	public function useragent($type = 'pc')
	{
		$userAgent['pc'] 		= 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.169 Safari/537.36';
		$userAgent['iphone'] 	= 'Mozilla/5.0 (iPad; U; CPU OS 3_2_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B500 Safari/531.21.10';
		$userAgent['android'] 	= 'Mozilla/5.0 (Linux; U; Android 2.2; en-us; Nexus One Build/FRF91) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1';
		if (array_key_exists($type, $userAgent)) {
			$this->_options[CURLOPT_USERAGENT] = $userAgent[$type];
		} else {
			$this->_options[CURLOPT_USERAGENT] = $type;
		}
		return $this;
	}
	public function follow($followlocation = true, $maxredirs = 0)
	{
		$this->_options[CURLOPT_FOLLOWLOCATION] = $followlocation;
		if ($followlocation == true && $maxredirs > 0) {
			$this->_options[CURLOPT_MAXREDIRS] = $maxredirs;
		}
		return $this;
	}
	public function autoreferer($autoreferer = true)
	{
		$this->_options[CURLOPT_AUTOREFERER] = $autoreferer;
		return $this;
	}
	public function referer($referer = false)
	{
		if (!empty($referer)) {
			$this->_options[CURLOPT_REFERER] = $referer;
		}
		return $this;
	}
	public function conntimeout($timeout = 30)
	{
		$this->_options[CURLOPT_CONNECTTIMEOUT] = $timeout;
		return $this;
	}
	public function timeout($timeout = 60)
	{
		$this->_options[CURLOPT_TIMEOUT] = $timeout;
		return $this;
	}
	public function verify($verifypeer = false, $verifyhost = 0, $cadir = '', $cainfo = '')
	{
		$this->_options[CURLOPT_SSL_VERIFYPEER] = $verifypeer;
		$this->_options[CURLOPT_SSL_VERIFYHOST] = $verifyhost;
		if ($verifypeer == true) {
			if (!empty($cadir)) {
				$this->_options[CURLOPT_CAPATH] = $cadir;
			}
			if (!empty($cainfo)) {
				$this->_options[CURLOPT_CAINFO] = $cainfo;
			}
		}
		return $this;
	}
	public function cookiesession($isopen = false)
	{
		$this->_options[CURLOPT_COOKIESESSION] = $isopen;
		return $this;
	}
	public function verbose($isopen = false)
	{
		if (!empty($isopen)) {
			$this->_options[CURLOPT_VERBOSE] = true;
			$this->_stdfp = fopen('php://temp', 'w+'); //
			$this->_options[CURLOPT_STDERR]  = $this->_stdfp;
		} else {
			if (isset($this->_options[CURLOPT_VERBOSE])) unset($this->_options[CURLOPT_VERBOSE]);
			if (isset($this->_options[CURLOPT_STDERR])) unset($this->_options[CURLOPT_STDERR]);
		}
		return $this;
	}
	public function nobody($nobody = false)
	{
		$this->_options[CURLOPT_NOBODY] = $nobody;
		return $this;
	}
	public function interWayout($ip)
	{
		$this->_options[CURLOPT_INTERFACE] = $ip;
		return $this;
	}
	public function proxy($type = CURLPROXY_HTTP, $ip)
	{
		if (empty($ip)) {
			return $this;
		}
		if ($type === CURLPROXY_HTTP) {
			$this->_options[CURLOPT_HTTPPROXYTUNNEL] = true;
			$this->_options[CURLOPT_PROXYTYPE] = $type;
			$this->_options[CURLOPT_PROXY] = $ip;
		} else {
			$this->_options[CURLOPT_PROXYTYPE] = $type;
			$this->_options[CURLOPT_PROXY] = $ip;
		}
		return $this;
	}
	public function id()
	{
		return $this->_id;
	}
	public function ch()
	{
		return $this->_ch;
	}
	public function getUrl()
	{
		return $this->_url;
	}
	private function parseSetCookie($header)
	{
		if (empty($header))
			return false;
		preg_match_all("/set\-cookie:([^\r\n]*)/i", $header, $cookiematches);
		$cookiePreg	= isset($cookiematches[1]) ? $cookiematches[1] : false;
		if (empty($cookiePreg) || !is_array($cookiePreg)) {
			return false;
		}
		foreach ($cookiePreg as $key => $val) {
			$cookieItem = trim(str_ireplace('; ', '&', $val));
			parse_str($cookieItem, $itemArr);
			if (empty($itemArr) || !is_array($itemArr)) continue;
			$this->_cookie = array_merge($this->_cookie, $itemArr);
		}
		if (!empty($this->cookieFile)) {
			@file_put_contents($this->cookieFile, json_encode($this->_cookie));
		}
		return $this->_cookie;
	}
	private function parseCookie($headerout)
	{
		if (empty($headerout))
			return false;
		preg_match_all("/Cookie:([^\r\n]*)/i", $headerout, $cookiematches);
		$cookiePreg	= isset($cookiematches[1]) ? $cookiematches[1] : false;
		if (empty($cookiePreg) || !is_array($cookiePreg)) {
			return false;
		}
		foreach ($cookiePreg as $key => $val) {
			$cookieItem = trim(str_ireplace('; ', '&', $val));
			parse_str($cookieItem, $itemArr);
			if (empty($itemArr) || !is_array($itemArr)) continue;
			$this->_cookieout = array_merge($this->_cookieout, $itemArr);
		}
		return $this->_cookieout;
	}
	private function parseSetLocation($header)
	{
		if (empty($header))
			return false;
		preg_match_all("/Location:([^\r\n]*)/i", $header, $locationmatches);
		$locationmatches	= isset($locationmatches[1]) ? $locationmatches[1] : false;
		if (empty($locationmatches) || !is_array($locationmatches)) {
			return false;
		}
		foreach ($locationmatches as $key => $val) {
			$this->_location = trim($val);
		}
		return $this->_location;
	}
	public function setOption($optArray = [])
	{
		if (empty($optArray) || !is_array($optArray)) {
			return false;
		}
		foreach ($optArray as $opt_key => $opt_value) {
			$opt_key = strtoupper($opt_key);
			$this->_options[$opt_key] = $opt_value;
		}
		return $this;
	}
	public function curlInfo()
	{
		return $this->_info;
	}
	public function curlError()
	{
		// $result = "errno:{$this->_errno} & errmsg:{$this->_errmsg}";
		return $this->_errmsg;
	}
	public function curlTrack()
	{
		return $this->_stdtrack;
	}
	public function curlCookie()
	{
		return $this->_cookie;
	}
	public function curlCookieOut()
	{
		return $this->_cookieout;
	}
	public function curlCookiejar()
	{
		return $this->_cookiejar;
	}
	public function responseHeader()
	{
		return $this->_header;
	}
	public function curlHeaderOut()
	{
		return $this->_headerout;
	}
	public function getResponse()
	{
		return $this->_response;
	}
	public function curlLocation()
	{
		return $this->_location;
	}
	public function parseData($data = [], $encode = true)
	{
		if (empty($data) || !is_array($data)) {
			return false;
		}
		foreach ($data as $key => $value) {
			if (is_array($value)) {
				$this->parseData($value, $encode);
			} else {
				$encode_key   = $encode ? urlencode($key) : $key;
				$encode_value = $encode ? urlencode($value) : $value;
				if ($encode_key != $key) unset($data[$key]);
				$data[$encode_key] = $encode_value;
			}
		}
		return $data;
	}
	public function data($data = [], $encode = true)
	{
		$this->_data = $this->parseData($data, $encode);
		return $this;
	}
	public function postData($data = [])
	{
		$this->resetpost();
		$postData = !empty($data) ? $data : $this->_data;
		if ($this->_postjson && !empty($postData)) {
			if (is_array($postData)) {
				$postData = json_encode($postData);
			}
			$options[CURLOPT_POST] 		 = true;
			$options[CURLOPT_POSTFIELDS] = $postData;
			$this->setOption($options);
			return $this;
		}

		$buildData = http_build_query($postData);
		if (isset($postData[0])) {
			$newData = [];
			foreach ($postData as $key => $val) {
				$arr = explode('=', $val);
				if (!isset($arr[1]))
					continue;
				$newData[] = $arr[0] . '=' . urlencode($arr[1]);
			}
			$buildData = implode('&', $newData);
		}
		if (!empty($postData)) {
			$options[CURLOPT_POST] 		 = true;
			$options[CURLOPT_POSTFIELDS] = $buildData;
			$this->setOption($options);
		}
		return $this;
	}
	public function getData($data = [])
	{
		$this->resetpost();
		$getData = !empty($data) ? $data : $this->_data;
		if (!empty($getData)) {
			$url  = isset($this->_options[CURLOPT_URL]) ? $this->_options[CURLOPT_URL] : '';
			$url .= (stripos($url, '?') === false) ? '?' : '&';
			$url .= http_build_query($getData);
			$this->url($url);
		}
		return $this;
	}

	public function resetpost()
	{
		if (isset($this->_options[CURLOPT_POST])) unset($this->_options[CURLOPT_POST]);
		if (isset($this->_options[CURLOPT_POSTFIELDS])) unset($this->_options[CURLOPT_POSTFIELDS]);
		return $this;
	}
	public function get($url = false, $data = array(), $host = false)
	{
		$this->url($url);
		if (!empty($host)) {
			$this->httpheader(array('Host' => $host));
		}
		$result = $this->getData($data)->execute();
		$this->close();
		return $result;
	}
	public function postJson($isJson = false)
	{
		$this->_postjson = $isJson ? true : false;
		return $this;
	}
	public function post($url = false, $data = array(), $host = false)
	{
		$this->url($url);
		if (!empty($host)) {
			$this->httpheader(array('Host' => $host));
		}
		$result = $this->postData($data)->execute();
		$this->close();
		return $result;
	}
	public function curlFile($filepath)
	{
		$filepath = realpath($filepath);
		return class_exists('\CURLFile') ? new \CURLFile($filepath) : '@' . $filepath;
	}
	public function setoptDone()
	{
		curl_setopt_array($this->_ch, $this->_options);
		return $this;
	}
    /**
     * 请求curl
     *
     * @param [string] $type [get,post]
     * @param [string] $url
     * @param array $params
     * [
     *  'headers' => ['key'=>'value','key1'=>'value1']
     *  'data' => ['k'=>'v','k1'=>'v1',[]]
     *  'host' => 'headers host domain or ip'
     * ]
     * @return void
     */
	public function request($type, $url, array $params = [])
	{
		$host = isset($params['host']) ? $params['host'] : '';
		$data = isset($params['data']) ? $params['data'] : [];
		$headers = isset($params['headers']) ? $params['headers'] : [];
		if (!empty($headers)) {
			$this->httpheader($headers);
		}
		$this->header(true);
		$type = strtolower($type);
		if ($type == 'get') {
			$this->get($url, $data, $host);
		} elseif ($type == 'post') {
			$this->post($url, $data, $host);
		}
		return $this->response();
	}
	public function response()
	{
		return [
			'code' => $this->_httpcode,
			'errno' => $this->_errno,
			'errmsg' => $this->_errmsg,
			'info' => $this->_info,
			'header' => $this->_header,
			'body' => $this->_body,
		];
	}
	private function execute()
	{
		$this->_init();
		if (!isset($this->_options[CURLOPT_URL])) {
			return false;
		}
		curl_setopt_array($this->_ch, $this->_options);
		$this->_response 	=    curl_exec($this->_ch);
		$this->_errno 	  	=   curl_errno($this->_ch);
		$this->_errmsg   	=   curl_error($this->_ch);
		$this->_info        = curl_getinfo($this->_ch);
		$this->_httpcode 	= curl_getinfo($this->_ch, CURLINFO_HTTP_CODE);
		$result = $this->_response;
		if (isset($this->_info['http_code']) && $this->_info['http_code'] == '200') {
			if (!empty($this->_options[CURLOPT_HEADER])) {
				$headsize 		= isset($this->_info['header_size']) ? $this->_info['header_size'] : 0;
				$this->_header  = substr($this->_response, 0, $headsize);
				$this->_body 	= substr($this->_response, $headsize);
				$result = $this->_body;
			}
		}
		if (!empty($this->_options[CURLINFO_HEADER_OUT])) {
			$this->_headerout = curl_getinfo($this->_ch, CURLINFO_HEADER_OUT);
			$this->parseCookie($this->_headerout);
		}
		$this->parseSetCookie($this->_header);
		$this->parseSetLocation($this->_header);

		if (is_resource($this->_stdfp) && isset($this->_options[CURLOPT_VERBOSE])) {
			rewind($this->_stdfp);
			$this->_stdtrack .= stream_get_contents($this->_stdfp);
		}
		return $result;
	}
	private function close()
	{
		if ($this->_ch) {
			curl_close($this->_ch);
		}
		if (is_resource($this->_jarhandle)) {
			rewind($this->_jarhandle);
			$this->_cookiejar = stream_get_contents($this->_jarhandle);
			if (!empty($this->cookiejarFile)) { //写入指定文件
				@file_put_contents($this->cookiejarFile, $this->_cookiejar);
			}
			fclose($this->_jarhandle);
		}
	}
	private function free()
	{
		$this->_data    = false;
		$this->_response = false;
		$this->_header  = false;
		$this->_body    = false;
		$this->_errno 	= false;
		$this->_errmsg	= false;
		$this->_info    = false;
		$this->_httpcode = false;
	}
	private function toXml($datas)
	{
		if (
			!is_array($datas)
			|| count($datas) <= 0
		) {
			return false;
		}

		$xml = "<xml>";
		foreach ($datas as $key => $val) {
			if (is_numeric($val)) {
				$xml .= "<" . $key . ">" . $val . "</" . $key . ">";
			} else {
				$xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
			}
		}
		$xml .= "</xml>";
		return $xml;
	}
	private function fromXml($xml)
	{
		if (!$xml) {
			return false;
		}
		$xml_parser = xml_parser_create();
		if (!xml_parse($xml_parser, $xml, true)) {
			xml_parser_free($xml_parser);
			return $xml;
		}
		libxml_disable_entity_loader(true);
		$datas = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
		return $datas;
	}
	private function isUrl($url)
	{
		if (!empty($url) && preg_match("/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i", $url)) {
			return true;
		}
		return false;
	}
	public function queueRun($curls, $thread = 2, $optset = '', $callback = '')
	{
		if (empty($curls))
			return false;
		if (empty($optset)) {
			$optset = function () {
			};
		} elseif (!empty($optset) && !($optset instanceof \Closure)) {
			throw new \Exception("param 3 must be a Closure");
			return false;
		}
		if (empty($callback)) {
			$callback = function () {
			};
		} elseif (!($callback instanceof \Closure)) {
			throw new \Exception("param 4 is not a Closure");
			return false;
		}
		reset($curls);
		$rolling_window = $thread;
		$rolling_window = (sizeof($curls) < $rolling_window) ? sizeof($curls) : $rolling_window;
		$master  = curl_multi_init();
		for ($i = 0; $i < $rolling_window; $i++) {
			$url = current($curls);
			next($curls);
			$isUrl = $this->isUrl($url);
			if (empty($isUrl)) {
				$info = ['url' => $url, 'status' => -1, 'msg' => 'url error'];
				$callback($info, '', '', '');
				continue;
			}
			$v = new self($url);
			$optset($v, $url);
			$ch = $v->ch();
			if (!is_resource($ch)) {
				$info = ['url' => $url, 'status' => -2, 'msg' => 'curl resource error'];
				$callback($info, '', '', '');
				continue;
			}
			$v->setoptDone();
			curl_multi_add_handle($master, $ch);
			$v = null;
			unset($v);
		}
		$running = NULL;
		do {
			while (($execrun = curl_multi_exec($master, $running)) == CURLM_CALL_MULTI_PERFORM);
			if ($execrun != CURLM_OK)
				break;
			while ($done = curl_multi_info_read($master)) {
				$handle = $done['handle'];
				$errno  = curl_errno($handle);
				$errmsg = curl_error($handle);
				$info   = curl_getinfo($handle);
				$content = curl_multi_getcontent($handle);
				if (isset($info['http_code']) && $info['http_code'] == 200) {
					$info['status'] = 1;
					$info['msg'] = 'success';
				} else {
					$info['status'] = 0;
					$info['msg'] = 'error';
				}
				$callback($info, $content, $errno, $errmsg);
				$url  = current($curls);
				if (!empty($url)) {
					next($curls);
					$isUrl = $this->isUrl($url);
					if (empty($isUrl)) {
						$info = ['url' => $url, 'status' => -1, 'msg' => 'url error'];
						$callback($info, '', '', '');
						continue;
					}
					$v = new self($url);
					$optset($v, $url);
					$ch = $v->ch();
					if (!is_resource($ch)) {
						$info = ['url' => $url, 'status' => -2, 'msg' => 'curl resource error'];
						$callback($info, '', '', '');
						continue;
					}
					$v->setoptDone();
					curl_multi_add_handle($master, $ch);
					$v = null;
					unset($v);
				}
				curl_multi_remove_handle($master, $handle);
				curl_close($done['handle']);
			}
			if ($master > 0) {
				curl_multi_select($master, 0.5);
			}
		} while ($running);
		curl_multi_close($master);
		return true;
	}
	public function batchRun($curls, $optset = '')
	{
		if (empty($curls))
			return false;
		if (empty($optset)) {
			$optset = function () {
			};
		} elseif (!empty($optset) && !($optset instanceof \Closure)) {
			throw new \Exception("param 3 must be a Closure");
			return false;
		}
		$mh = curl_multi_init();
		$chArr = [];
		foreach ($curls as $id => $url) {
			$isUrl = $this->isUrl($url);
			if (empty($isUrl)) {
				continue;
			}
			$v = new self($url);
			$optset($v, $url);
			$ch = $v->ch();
			if (!is_resource($ch)) {
				continue;
			}
			$v->setoptDone();
			curl_multi_add_handle($mh, $ch);
			$chArr[$id] = $ch;
			$v = null;
			unset($v);
		}
		$active = null;
		do {
			$mrc = curl_multi_exec($mh, $active);
		} while ($mrc == CURLM_CALL_MULTI_PERFORM);
		while ($active && $mrc == CURLM_OK) {
			do {
				$mrc = curl_multi_exec($mh, $active);
			} while ($mrc == CURLM_CALL_MULTI_PERFORM);
		}
		$result = [];
		foreach ($chArr as $id => $ch) {
			$errno  = curl_errno($ch);
			$errmsg = curl_error($ch);
			$info   = curl_getinfo($ch);
			$content = curl_multi_getcontent($ch);
			curl_multi_remove_handle($mh, $ch);
			curl_close($ch);
			$result[$id] = [
				'id'     => $id,
				'errno'  => $errno,
				'errmsg' => $errmsg,
				'info'   => $info,
				'content' => $content,
			];
		}
		curl_multi_close($mh);
		return $result;
	}
}


/*
$error_no=array(
		[1] => 'CURLE_UNSUPPORTED_PROTOCOL', 
		[2] => 'CURLE_FAILED_INIT', 
		[3] => 'CURLE_URL_MALFORMAT', 
		[4] => 'CURLE_URL_MALFORMAT_USER', 
		[5] => 'CURLE_COULDNT_RESOLVE_PROXY', 
		[6] => 'CURLE_COULDNT_RESOLVE_HOST', 
		[7] => 'CURLE_COULDNT_CONNECT', 
		[8] => 'CURLE_FTP_WEIRD_SERVER_REPLY',
		[9] => 'CURLE_REMOTE_ACCESS_DENIED',
		[11] => 'CURLE_FTP_WEIRD_PASS_REPLY',
		[13] => 'CURLE_FTP_WEIRD_PASV_REPLY',
		[14] =>'CURLE_FTP_WEIRD_227_FORMAT',
		[15] => 'CURLE_FTP_CANT_GET_HOST',
		[17] => 'CURLE_FTP_COULDNT_SET_TYPE',
		[18] => 'CURLE_PARTIAL_FILE',
		[19] => 'CURLE_FTP_COULDNT_RETR_FILE',
		[21] => 'CURLE_QUOTE_ERROR',
		[22] => 'CURLE_HTTP_RETURNED_ERROR',
		[23] => 'CURLE_WRITE_ERROR',
		[25] => 'CURLE_UPLOAD_FAILED',
		[26] => 'CURLE_READ_ERROR',
		[27] => 'CURLE_OUT_OF_MEMORY',
		[28] => 'CURLE_OPERATION_TIMEDOUT',
		[30] => 'CURLE_FTP_PORT_FAILED',
		[31] => 'CURLE_FTP_COULDNT_USE_REST',
		[33] => 'CURLE_RANGE_ERROR',
		[34] => 'CURLE_HTTP_POST_ERROR',
		[35] => 'CURLE_SSL_CONNECT_ERROR',
		[36] => 'CURLE_BAD_DOWNLOAD_RESUME',
		[37] => 'CURLE_FILE_COULDNT_READ_FILE',
		[38] => 'CURLE_LDAP_CANNOT_BIND',
		[39] => 'CURLE_LDAP_SEARCH_FAILED',
		[41] => 'CURLE_FUNCTION_NOT_FOUND',
		[42] => 'CURLE_ABORTED_BY_CALLBACK',
		[43] => 'CURLE_BAD_FUNCTION_ARGUMENT',
		[45] => 'CURLE_INTERFACE_FAILED',
		[47] => 'CURLE_TOO_MANY_REDIRECTS',
		[48] => 'CURLE_UNKNOWN_TELNET_OPTION',
		[49] => 'CURLE_TELNET_OPTION_SYNTAX',
		[51] => 'CURLE_PEER_FAILED_VERIFICATION',
		[52] => 'CURLE_GOT_NOTHING',
		[53] => 'CURLE_SSL_ENGINE_NOTFOUND',
		[54] => 'CURLE_SSL_ENGINE_SETFAILED',
		[55] => 'CURLE_SEND_ERROR',
		[56] => 'CURLE_RECV_ERROR',
		[58] => 'CURLE_SSL_CERTPROBLEM',
		[59] => 'CURLE_SSL_CIPHER',
		[60] => 'CURLE_SSL_CACERT',
		[61] => 'CURLE_BAD_CONTENT_ENCODING',
		[62] => 'CURLE_LDAP_INVALID_URL',
		[63] => 'CURLE_FILESIZE_EXCEEDED',
		[64] => 'CURLE_USE_SSL_FAILED',
		[65] => 'CURLE_SEND_FAIL_REWIND',
		[66] => 'CURLE_SSL_ENGINE_INITFAILED',
		[67] => 'CURLE_LOGIN_DENIED',
		[68] => 'CURLE_TFTP_NOTFOUND',
		[69] => 'CURLE_TFTP_PERM',
		[70] => 'CURLE_REMOTE_DISK_FULL',
		[71] => 'CURLE_TFTP_ILLEGAL',
		[72] => 'CURLE_TFTP_UNKNOWNID',
		[73] => 'CURLE_REMOTE_FILE_EXISTS',
		[74] => 'CURLE_TFTP_NOSUCHUSER',
		[75] => 'CURLE_CONV_FAILED',
		[76] => 'CURLE_CONV_REQD',
		[77] => 'CURLE_SSL_CACERT_BADFILE',
		[78] => 'CURLE_REMOTE_FILE_NOT_FOUND',
		[79] => 'CURLE_SSH',
		[80] => 'CURLE_SSL_SHUTDOWN_FAILED',
		[81] => 'CURLE_AGAIN',
		[82] => 'CURLE_SSL_CRL_BADFILE',
		[83] => 'CURLE_SSL_ISSUER_ERROR',
		[84] => 'CURLE_FTP_PRET_FAILED',
		[84] => 'CURLE_FTP_PRET_FAILED',
		[85] => 'CURLE_RTSP_CSEQ_ERROR',
		[86] => 'CURLE_RTSP_SESSION_ERROR',
		[87] => 'CURLE_FTP_BAD_FILE_LIST',
		[88] => 'CURLE_CHUNK_FAILED',
);
*/