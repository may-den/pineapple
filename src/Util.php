<?php
namespace Pineapple;

/**
 * PEAR, the PHP Extension and Application Repository
 *
 * PHP versions 4 and 5
 *
 * @category   pear
 * @package    Pineapple
 * @author     Sterling Hughes <sterling@php.net>
 * @author     Stig Bakken <ssb@php.net>
 * @author     Tomas V.V.Cox <cox@idecnet.com>
 * @author     Greg Beaver <cellog@php.net>
 * @copyright  1997-2010 The Authors
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 * @link       http://pear.php.net/package/PEAR
 * @since      File available since Release 0.1
 */

/**
 * Base class for other PEAR classes.  Provides rudimentary
 * emulation of destructors.
 *
 * If you want a destructor in your class, inherit PEAR and make a
 * destructor method called _yourclassname (same name as the
 * constructor, but with a "_" prefix).  Also, in your constructor you
 * have to call the PEAR constructor: parent::__construct();.
 * The destructor method will be called without parameters.  Note that
 * at in some SAPI implementations (such as Apache), any output during
 * the request shutdown (in which destructors are called) seems to be
 * discarded.  If you need to get any debug information from your
 * destructor, use error_log(), syslog() or something similar.
 *
 * @category   pear
 * @package    Pineapple
 * @author     Stig Bakken <ssb@php.net>
 * @author     Tomas V.V. Cox <cox@idecnet.com>
 * @author     Greg Beaver <cellog@php.net>
 * @copyright  1997-2006 The PHP Group
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 * @version    Release: 1.10.1
 * @link       http://pear.php.net/package/PEAR
 * @see        Pineapple\Error
 * @since      Class available since PHP 4.0.2
 * @link       http://pear.php.net/manual/en/core.pear.php#core.pear.pear
 */
class Util
{
    /**
     * Constants
     */
    const PEAR_ERROR_RETURN = 1;
    const PEAR_ERROR_PRINT = 2;
    const PEAR_ERROR_TRIGGER = 4;
    const PEAR_ERROR_DIE = 8;
    const PEAR_ERROR_CALLBACK = 16;

    /**
     * WARNING: obsolete
     * @deprecated
     */
    const PEAR_ERROR_EXCEPTION = 32;

    /**
     * Which class to use for error objects.
     *
     * @var     string
     */
    protected $errorClass = Error::class;

    /**
     * Constructor.
     *
     * @param string $errorClass  (optional) which class to use for
     *                            error objects, defaults to Pineapple\Error.
     */
    public function __construct($errorClass = null)
    {
        if ($errorClass !== null) {
            $this->errorClass = $errorClass;
        }
    }

    /**
     * Handle calling of isError/raiseError/throwError in static context
     *
     * @param string $method    Name of method being called
     * @param array  $arguments Parameters to the function
     * @return mixed
     */
    public static function __callStatic($method, $arguments = [])
    {
        if (!in_array($method, ['isError', 'raiseError', 'throwError'])) {
            // @codeCoverageIgnoreStart
            // can't expect a forced error
            trigger_error('Static method not found', E_USER_ERROR);
            // @codeCoverageIgnoreEnd
        }

        // isError doesn't need $this, but raise and throw need it faked
        if ($method != 'isError') {
            array_unshift($arguments, null);
        }
        return call_user_func_array([self::class, 'static' . ucfirst($method)], $arguments);
    }

    /**
     * Handle calling of isError/raiseError/throwError in an object context.
     *
     * @param string $method    Name of method being called
     * @param array  $arguments Parameters to the function
     * @return mixed
     */
    public function __call($method, $arguments = [])
    {
        if (!in_array($method, ['isError', 'raiseError', 'throwError'])) {
            // @codeCoverageIgnoreStart
            // can't expect a forced error
            trigger_error('Method not found', E_USER_ERROR);
            // @codeCoverageIgnoreEnd
        }

        // isError doesn't need $this, but raise and throw need it faked
        if ($method != 'isError') {
            array_unshift($arguments, $this);
        }
        return call_user_func_array([self::class, 'static' . ucfirst($method)], $arguments);
    }

    /**
     * Tell whether a value is a Pineapple\Error.
     *
     * @param  mixed $data the value to test
     * @param  int   $code if $data is an error object, return true
     *                     only if $code is a string and
     *                     $obj->getMessage() == $code or
     *                     $code is an integer and $obj->getCode() == $code
     * @return boolean     true if parameter is an error
     */
    public static function staticIsError($data, $code = null)
    {
        if (!($data instanceof Error)) {
            return false;
        }

        if (is_null($code)) {
            return true;
        } elseif (is_string($code)) {
            return $data->getMessage() == $code;
        }

        return $data->getCode() == $code;
    }

    /**
     * This method is a wrapper that returns an instance of the
     * configured error class with this object's default error
     * handling applied.  If the $mode and $options parameters are not
     * specified, the object's defaults are used.
     *
     * @param mixed $message     a text error message or a Pineapple\Error object
     * @param int $code          a numeric error code (it is up to your class
     *                           to define these if you want to use codes)
     * @param int $mode          One of PEAR_ERROR_RETURN, PEAR_ERROR_PRINT,
     *                           PEAR_ERROR_TRIGGER, PEAR_ERROR_DIE,
     *                           PEAR_ERROR_CALLBACK, PEAR_ERROR_EXCEPTION.
     * @param mixed $options     If $mode is PEAR_ERROR_TRIGGER, this parameter
     *                           specifies the PHP-internal error level (one of
     *                           E_USER_NOTICE, E_USER_WARNING or E_USER_ERROR).
     *                           If $mode is PEAR_ERROR_CALLBACK, this
     *                           parameter specifies the callback function or
     *                           method.  In other error modes this parameter
     *                           is ignored.
     * @param string $userInfo   If you need to pass along for example debug
     *                           information, this parameter is meant for that.
     * @param string $errorClass The returned error object will be
     *                           instantiated from this class, if specified.
     * @param bool $skipMessage  If true, raiseError will only pass error codes,
     *                           the error message parameter will be dropped.
     * @return object            a Pineapple\Error object
     */
    protected static function staticRaiseError(
        $object,
        $message = null,
        $code = null,
        $mode = null,
        $options = null,
        $userInfo = null,
        $errorClass = null,
        $skipMessage = false
    ) {
        // Check if the object is a Pineapple\Error object
        if (is_object($message)) {
            $code = $message->getCode();
            $userInfo = $message->getUserInfo();
            $errorClass = $message->getType();
            $message->error_message_prefix = '';
            $message = $message->getMessage();
        }

        if ($errorClass !== null) {
            $ec = $errorClass;
        } elseif ($object !== null && isset($object->errorClass)) {
            $ec = $object->errorClass;
        } else {
            $ec = Error::class;
        }

        return $skipMessage ?
            new $ec($code, self::PEAR_ERROR_RETURN, $options, $userInfo) :
            new $ec($message, $code, self::PEAR_ERROR_RETURN, $options, $userInfo);
    }

    /**
     * Simpler form of raiseError with fewer options.  In most cases
     * message, code and userInfo are enough.
     *
     * @param mixed $message   a text error message or a Pineapple\Error object
     * @param int $code        a numeric error code (it is up to your class
     *                         to define these if you want to use codes)
     * @param string $userInfo If you need to pass along for example debug
     *                         information, this parameter is meant for that.
     * @return object          a Pineapple\Error object
     *
     * @see Pineapple\Util::raiseError
     */
    protected static function staticThrowError($object, $message = null, $code = null, $userInfo = null)
    {
        if ($object !== null) {
            return $object->raiseError($message, $code, null, null, $userInfo);
        }

        return self::raiseError($message, $code, null, null, $userInfo);
    }
}
