<?php
namespace Pineapple\Test;

// construct this object and ditch it; it's here to shim into pineapple
// namespaces below this test ('use' aliases but does not trigger loading)
class MonkeyPatching
{
    public static $dontThrow;
    public static $unthrownException;

    public function __construct($dontThrow = false)
    {
        self::$dontThrow = $dontThrow;
    }
}

namespace Pineapple;

// psr-2 dislikes 'use' after a second namespace declaration
use Pineapple\Test\Exception\MonkeyTriggerErrorException;
use Pineapple\Test\MonkeyPatching;

// monkey-patch trigger_error
function trigger_error($message, $code)
{
    if (MonkeyPatching::$dontThrow === false) {
        throw new MonkeyTriggerErrorException($message, $code);
    }

    MonkeyPatching::$unthrownException = new MonkeyTriggerErrorException($message, $code);
}
