<?php

namespace Netflying\Payment\exception;


class BaseException extends \Exception
{
    public function __construct($message, int $code = 0, $previous = null)
    {
        if (is_object($message)) {
            $isCallable = false;
            try {
                $message = $message->toArray();
                $isCallable = true;
            } catch (\Exception $e) {
            }
            if (!$isCallable) {
                $message = "";
            }
        }
        $message = is_array($message) ? json_encode($message) : $message;
        return parent::__construct($message, $code, $previous);
    }
}
