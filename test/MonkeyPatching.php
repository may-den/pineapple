<?php
namespace Pineapple\Test;

// construct this object and ditch it; it's here to shim into pineapple
// namespaces below this test ('use' aliases but does not trigger loading)
class MonkeyPatching
{
    // the class - it does nothing! the action is below.
}

namespace Pineapple;

// psr-2 dislikes 'use' after a second namespace declaration
use Pineapple\Test\Exception\MonkeyTriggerErrorException;

// monkey-patch trigger_error
function trigger_error($message, $code)
{
    throw new MonkeyTriggerErrorException($message, $code);
}
