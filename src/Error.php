<?php
namespace Pineapple;

/**
 * Standard PEAR error class for PHP 4
 *
 * This class is supserseded by {@link Pineapple\Exception} in PHP 5
 *
 * @category   pear
 * @package    Pineapple
 * @author     Stig Bakken <ssb@php.net>
 * @author     Tomas V.V. Cox <cox@idecnet.com>
 * @author     Gregory Beaver <cellog@php.net>
 * @copyright  1997-2006 The PHP Group
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 * @version    Release: 1.10.1
 * @link       http://pear.php.net/manual/en/core.pear.pear-error.php
 * @see        Pineapple\Util::raiseError(), Pineapple\Util::throwError()
 * @since      Class available since PHP 4.0.2
 */
class Error
{
    private $errorMessagePrefix = '';
    private $mode = Util::PEAR_ERROR_RETURN;
    private $level = E_USER_NOTICE;
    private $code = -1;
    private $message = '';
    private $userInfo = '';
    private $backtrace = null;
    private $callback;

    /**
     * Pineapple\Error constructor
     *
     * @param string $message message
     * @param int    $code    (optional) error code
     * @param int    $mode    (optional) error mode, one of: PEAR_ERROR_RETURN,
     *                        PEAR_ERROR_PRINT, PEAR_ERROR_DIE, PEAR_ERROR_TRIGGER,
     *                        PEAR_ERROR_CALLBACK or PEAR_ERROR_EXCEPTION
     * @param mixed  $options (optional) error level, _OR_ in the case of
     *                        PEAR_ERROR_CALLBACK, the callback function or object/method
     *                        tuple.
     *
     * @param string $userInfo (optional) additional user/debug info
     *
     * @access public
     */
    public function __construct($message = null, $code = null, $mode = null, $options = null, $userInfo = null)
    {
        if ($mode === null) {
            $mode = Util::PEAR_ERROR_RETURN;
        }
        $this->message = isset($message) ? $message : 'unknown error';
        $this->code = $code;
        $this->mode = $mode;
        $this->userInfo = $userInfo;

        $this->backtrace = debug_backtrace();
        if (isset($this->backtrace[0]) && isset($this->backtrace[0]['object'])) {
            unset($this->backtrace[0]['object']);
        }

        if ($mode & Util::PEAR_ERROR_CALLBACK) {
            $this->level = E_USER_NOTICE;
            $this->callback = $options;
        } else {
            if ($options === null) {
                $options = E_USER_NOTICE;
            }

            $this->level = $options;
            $this->callback = null;
        }

        if ($this->mode & Util::PEAR_ERROR_PRINT) {
            if (is_null($options) || is_int($options)) {
                $format = "%s";
            } else {
                $format = $options;
            }

            printf($format, $this->getMessage());
        }

        if ($this->mode & Util::PEAR_ERROR_TRIGGER) {
            trigger_error($this->getMessage(), $this->level);
        }

        // we cannot test something which dies
        // @codeCoverageIgnoreStart
        if ($this->mode & Util::PEAR_ERROR_DIE) {
            $msg = $this->getMessage();
            if (is_null($options) || is_int($options)) {
                $format = "%s";
                if (substr($msg, -1) != "\n") {
                    $msg .= "\n";
                }
            } else {
                $format = $options;
            }
            die(sprintf($format, $msg));
        }
        // @codeCoverageIgnoreEnd

        if ($this->mode & Util::PEAR_ERROR_CALLBACK && is_callable($this->callback)) {
            call_user_func($this->callback, $this);
        }

        if ($this->mode & Util::PEAR_ERROR_EXCEPTION) {
            trigger_error(
                'PEAR_ERROR_EXCEPTION is obsolete, use class Pineapple\Exception for exceptions',
                E_USER_WARNING
            );
            $e = new Exception($this->message, $this->code);
            throw($e);
        }
    }

    /**
     * Get the error mode from an error object.
     *
     * @return int error mode
     * @access public
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * Get the callback function/method from an error object.
     *
     * @return mixed callback function or object/method array
     * @access public
     */
    public function getCallback()
    {
        return $this->callback;
    }

    /**
     * Get the error message from an error object.
     *
     * @return  string  full error message
     * @access public
     */
    public function getMessage()
    {
        return $this->errorMessagePrefix . $this->message;
    }

    /**
     * Get error code from an error object
     *
     * @return int error code
     * @access public
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Get the name of this error/exception.
     *
     * @return string error/exception name (type)
     * @access public
     */
    public function getType()
    {
        return get_class($this);
    }

    /**
     * Get additional user-supplied information.
     *
     * @return string user-supplied information
     * @access public
     */
    public function getUserInfo()
    {
        return $this->userInfo;
    }

    /**
     * Get additional debug information supplied by the application.
     *
     * @return string debug information
     * @access public
     */
    public function getDebugInfo()
    {
        return $this->getUserInfo();
    }

    /**
     * Get the call backtrace from where the error was generated.
     * Supported with PHP 4.3.0 or newer.
     *
     * @param int $frame (optional) what frame to fetch
     * @return array Backtrace, or NULL if not available.
     * @access public
     */
    public function getBacktrace($frame = null)
    {
        if ($frame === null) {
            return $this->backtrace;
        }
        return $this->backtrace[$frame];
    }

    public function addUserInfo($info)
    {
        if (empty($this->userInfo)) {
            $this->userInfo = $info;
        } else {
            $this->userInfo .= " ** $info";
        }
    }

    public function __toString()
    {
        return $this->getMessage();
    }

    /**
     * Make a string representation of this object.
     *
     * @return string a string with an object summary
     * @access public
     */
    public function toString()
    {
        $modes = [];
        $levels = [
            E_USER_NOTICE => 'notice',
            E_USER_WARNING => 'warning',
            E_USER_ERROR => 'error'
        ];

        if ($this->mode & Util::PEAR_ERROR_CALLBACK) {
            if (is_array($this->callback)) {
                $callback = (is_object($this->callback[0]) ?
                    strtolower(get_class($this->callback[0])) :
                    $this->callback[0]) . '::' .
                    $this->callback[1];
            } else {
                $callback = $this->callback;
            }
            return sprintf(
                '[%s: message="%s" code=%d mode=callback callback=%s prefix="%s" info="%s"]',
                strtolower(get_class($this)),
                $this->message,
                $this->code,
                $callback,
                $this->errorMessagePrefix,
                $this->userInfo
            );
        }
        if ($this->mode & Util::PEAR_ERROR_PRINT) {
            $modes[] = 'print';
        }
        if ($this->mode & Util::PEAR_ERROR_TRIGGER) {
            $modes[] = 'trigger';
        }
        // untestable because we can't trigger a die
        // @codeCoverageIgnoreStart
        if ($this->mode & Util::PEAR_ERROR_DIE) {
            $modes[] = 'die';
        }
        // @codeCoverageIgnoreEnd
        if ($this->mode & Util::PEAR_ERROR_RETURN) {
            $modes[] = 'return';
        }
        return sprintf(
            '[%s: message="%s" code=%d mode=%s level=%s prefix="%s" info="%s"]',
            strtolower(get_class($this)),
            $this->message,
            $this->code,
            implode('|', $modes),
            $levels[$this->level],
            $this->errorMessagePrefix,
            $this->userInfo
        );
    }
}
