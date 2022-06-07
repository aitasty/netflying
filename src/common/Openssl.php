<?php

namespace Netflying\Payment\common;

class Openssl
{
    protected static $privateFile = __DIR__ . '/../cert/private.pem';
    protected static $publicFile = __DIR__ . '/../cert/public.pem';

    public static function setPrivateFile($file)
    {
        static::$privateFile = $file;
        return static::class;
    }
    public static function setPublicFile($file)
    {
        static::$publicFile = $file;
        return static::class;
    }

    public static function encrypt($value)
    {
        if (empty($value)) {
            return '';
        }
        if (is_object($value)) {
            $isCallable = false;
            try{
                $value = $value->toArray();
                $isCallable = true;
            } catch (\Exception $e) {}
            if (!$isCallable) {
                return '';
            }
        }
        $value = is_array($value) ? json_encode($value) : $value;
        $publicPem = static::$publicFile;
        if (!is_file($publicPem)) {
            return '';
        }
        $key = file_get_contents($publicPem);
        $encrypted = null;
        openssl_public_encrypt($value, $encrypted, $key);
        return base64_encode($encrypted);
    }
    
    public static function decrypt($encrypted)
    {
        if (empty($encrypted)) {
            return '';
        }
        $priPem = static::$privateFile;
        if (!is_file($priPem)) {
            return '';
        }
        $key = file_get_contents($priPem);
        $decrypted = null;
        openssl_private_decrypt(base64_decode($encrypted), $decrypted, $key);
        $json = json_decode($decrypted, true);
        if (json_last_error() == JSON_ERROR_NONE) {
            return $json;
        }
        return $decrypted;
    }

    public static function publicContent()
    {
        $pubPem = static::$publicFile;
        if (!is_file($pubPem)) {
            return '';
        }
        $preg = ['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\r\n"];
        return str_replace($preg, '', file_get_contents($pubPem));
    }
}
