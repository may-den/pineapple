<?php
namespace Pineapple;

/**
 * PEAR_Exception
 *
 * PHP versions 4 and 5
 *
 * @category   pear
 * @package    PEAR
 * @author     Tomas V. V. Cox <cox@idecnet.com>
 * @author     Hans Lellelid <hans@velum.net>
 * @author     Bertrand Mansion <bmansion@mamasam.com>
 * @author     Greg Beaver <cellog@php.net>
 * @copyright  1997-2009 The Authors
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 * @link       http://pear.php.net/package/PEAR
 * @since      File available since Release 1.3.3
 */

/**
 * Base PEAR_Exception Class
 *
 * 1) Features:
 *
 * - Nestable exceptions (throw new PEAR_Exception($msg, $prev_exception))
 * - Definable triggers, shot when exceptions occur
 * - Pretty and informative error messages
 * - Added more context info available (like class, method or cause)
 * - cause can be a PEAR_Exception or an array of mixed
 *   PEAR_Exceptions/ErrorStack warnings
 * - callbacks for specific exception classes and their children
 *
 * 2) Ideas:
 *
 * - Maybe a way to define a 'template' for the output
 *
 * 3) Inherited properties from PHP Exception Class:
 *
 * protected $message
 * protected $code
 * protected $line
 * protected $file
 * private   $trace
 *
 * 4) Inherited methods from PHP Exception Class:
 *
 * __clone
 * __construct
 * getMessage
 * getCode
 * getFile
 * getLine
 * getTraceSafe
 * getTraceSafeAsString
 * __toString
 *
 * 5) Usage example
 *
 * ```php
 *  require_once 'PEAR/Exception.php';
 *
 *  class Test {
 *     function foo() {
 *         throw new PEAR_Exception('Error Message', ERROR_CODE);
 *     }
 *  }
 *
 *  function myLogger($pear_exception) {
 *     echo $pear_exception->getMessage();
 *  }
 *  // each time a exception is thrown the 'myLogger' will be called
 *  // (its use is completely optional)
 *  PEAR_Exception::addObserver('myLogger');
 *  $test = new Test;
 *  try {
 *     $test->foo();
 *  } catch (PEAR_Exception $e) {
 *     print $e;
 *  }
 * ```
 *
 * @category   pear
 * @package    PEAR
 * @author     Tomas V.V.Cox <cox@idecnet.com>
 * @author     Hans Lellelid <hans@velum.net>
 * @author     Bertrand Mansion <bmansion@mamasam.com>
 * @author     Greg Beaver <cellog@php.net>
 * @copyright  1997-2009 The Authors
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 * @version    Release: 1.10.1
 * @link       http://pear.php.net/package/PEAR
 * @since      Class available since Release 1.3.3
 */
class Exception extends \Exception
{
    const OBSERVER_PRINT = -2;
    const OBSERVER_TRIGGER = -4;
    const OBSERVER_DIE = -8;
    protected $cause;
    private static $observers = [];
    private static $uniqueid = 0;
    private $trace;

    /**
     * Supported signatures:
     *  - Pineapple\Exception(string $message);
     *  - Pineapple\Exception(string $message, int $code);
     *  - Pineapple\Exception(string $message, \Exception $cause);
     *  - Pineapple\Exception(string $message, \Exception $cause, int $code);
     *  - Pineapple\Exception(string $message, Error $cause);
     *  - Pineapple\Exception(string $message, Error $cause, int $code);
     *  - Pineapple\Exception(string $message, array $causes);
     *  - Pineapple\Exception(string $message, array $causes, int $code);
     *
     * @param string exception message
     * @param int|\Exception|Error|array|null exception cause
     * @param int|null exception code or null
     */
    public function __construct($message, $p2 = null, $p3 = null)
    {
        if (is_int($p2)) {
            $code = $p2;
            $this->cause = null;
        } elseif (is_object($p2) || is_array($p2)) {
            // using is_object allows both Exception and Error
            if (is_object($p2) && !($p2 instanceof \Exception)) {
                if (!class_exists(Error::class) || !($p2 instanceof Error)) {
                    throw new self('exception cause must be \Exception, ' .
                        'array, or Error');
                }
            }
            $code = $p3;
            if (is_array($p2) && isset($p2['message'])) {
                // fix potential problem of passing in a single warning
                $p2 = [$p2];
            }
            $this->cause = $p2;
        } else {
            $code = null;
            $this->cause = null;
        }

        parent::__construct($message, $code);
        $this->signal();
    }

    /**
     * @param mixed $callback  - A valid php callback, see php func is_callable()
     *                         - A PEAR_Exception::OBSERVER_* constant
     *                         - An array(const PEAR_Exception::OBSERVER_*,
     *                           mixed $options)
     * @param string $label    The name of the observer. Use this if you want
     *                         to remove it later with removeObserver()
     */
    public static function addObserver($callback, $label = 'default')
    {
        self::$observers[$label] = $callback;
    }

    /**
     * @param string $label The name of the observer to remove.
     */
    public static function removeObserver($label = 'default')
    {
        unset(self::$observers[$label]);
    }

    /**
     * @return int unique identifier for an observer
     */
    public static function getUniqueId()
    {
        return self::$uniqueid++;
    }

    private function signal()
    {
        foreach (self::$observers as $func) {
            if (is_callable($func)) {
                call_user_func($func, $this);
                continue;
            }
            settype($func, 'array');
            switch ($func[0]) {
                case self::OBSERVER_PRINT:
                    $f = isset($func[1]) ? $func[1] : '%s';
                    printf($f, $this->getMessage());
                    break;

                case self::OBSERVER_TRIGGER:
                    $f = isset($func[1]) ? $func[1] : E_USER_NOTICE;
                    trigger_error($this->getMessage(), $f);
                    // @codeCoverageIgnoreStart
                    // this cannot be reached during unit test
                    break;
                    // @codeCoverageIgnoreEnd

                // @codeCoverageIgnoreStart
                // can't cover this, die is kind of a finality
                case self::OBSERVER_DIE:
                    $f = isset($func[1]) ? $func[1] : '%s';
                    die(sprintf($f, $this->getMessage()));
                    break;
                // @codeCoverageIgnoreEnd

                default:
                    trigger_error('invalid observer type', E_USER_WARNING);
            }
        }
    }

    /**
     * Return specific error information that can be used for more detailed
     * error messages or translation.
     *
     * This method may be overridden in child exception classes in order
     * to add functionality not present in PEAR_Exception and is a placeholder
     * to define API
     *
     * The returned array must be an associative array of parameter => value like so:
     * ```php
     * array('name' => $name, 'context' => array(...))
     * ```
     *
     * @return array
     */
    public function getErrorData()
    {
        return [];
    }

    /**
     * Returns the exception that caused this exception to be thrown
     *
     * @return Exception|array The context of the exception
     */
    public function getCause()
    {
        return $this->cause;
    }

    /**
     * Function must be public to call on caused exceptions
     * @param array
     */
    public function getCauseMessage(&$causes)
    {
        $causes[] = [
            'class' => get_class($this),
            'message' => $this->message,
            'file' => 'unknown',
            'line' => 'unknown'
        ];

        if ($this->cause instanceof self) {
            $this->cause->getCauseMessage($causes);
        } elseif ($this->cause instanceof \Exception) {
            $causes[] = [
                'class' => get_class($this->cause),
                'message' => $this->cause->getMessage(),
                'file' => $this->cause->getFile(),
                'line' => $this->cause->getLine()];
        } elseif (class_exists(Error::class) && $this->cause instanceof Error) {
            $causes[] = [
                'class' => get_class($this->cause),
                'message' => $this->cause->getMessage(),
                'file' => 'unknown',
                'line' => 'unknown'
            ];
        } elseif (is_array($this->cause)) {
            foreach ($this->cause as $cause) {
                if ($cause instanceof self) {
                    $cause->getCauseMessage($causes);
                } elseif (true && ($cause instanceof \Exception)) {
                    // apologies for true above, it's a temporary hack as phpunit omits it from code coverage.
                    $causes[] = [
                        'class' => get_class($cause),
                        'message' => $cause->getMessage(),
                        'file' => $cause->getFile(),
                        'line' => $cause->getLine()
                    ];
                } elseif (class_exists(Error::class) && $cause instanceof Error) {
                    $causes[] = [
                        'class' => get_class($cause),
                        'message' => $cause->getMessage(),
                        'file' => 'unknown',
                        'line' => 'unknown'
                    ];
                } elseif (is_array($cause) && isset($cause['message'])) {
                    // ErrorStack warning
                    $causes[] = [
                        'class' => $cause['package'],
                        'message' => $cause['message'],
                        'file' => isset($cause['context']['file']) ? $cause['context']['file'] : 'unknown',
                        'line' => isset($cause['context']['line']) ? $cause['context']['line'] : 'unknown',
                    ];
                }
            }
        }
    }

    /**
     * @return array The stack trace of the exception
     */
    public function getTraceSafe()
    {
        if (!isset($this->trace)) {
            $this->trace = $this->getTrace();
        }
        return $this->trace;
    }

    /**
     * @return string The name of the class where the error has occurred
     */
    public function getErrorClass()
    {
        $trace = $this->getTraceSafe();
        return $trace[0]['class'];
    }

    /**
     * @return string The name of the method where the error occurred
     */
    public function getErrorMethod()
    {
        $trace = $this->getTraceSafe();
        return $trace[0]['function'];
    }

    /**
     * @return string The description view of the error
     */
    public function __toString()
    {
        if (isset($_SERVER['REQUEST_URI'])) {
            return $this->toHtml();
        }
        return $this->toText();
    }

    /**
     * @return string An HTML view of the error that has occurred
     */
    public function toHtml()
    {
        $trace = $this->getTraceSafe();
        $causes = [];
        $this->getCauseMessage($causes);
        $html =  "<table style=\"border: 1px\" cellspacing=\"0\">\n";
        foreach ($causes as $i => $cause) {
            $html .= sprintf(
                "<tr>
                    <td colspan=\"3\" style=\"background: #ff9999\">
                        %s <b>%s</b>: %s in <b>%s</b> on line <b>%s</b>
                    </td>
                </tr>",
                str_repeat('-', $i),
                $cause['class'],
                htmlentities($cause['message']),
                $cause['file'],
                $cause['line']
            );
        }
        $html .= '
            <tr>
                <td colspan="3" style="background-color: #aaaaaa; text-align: center; font-weight: bold;">
                    Exception trace
                </td>
            </tr>
            <tr>
                <td style="text-align: center; background: #cccccc; width:20px; font-weight: bold;">#</td>
                <td style="text-align: center; background: #cccccc; font-weight: bold;">Function</td>
                <td style="text-align: center; background: #cccccc; font-weight: bold;">Location</td>
            </tr>
        ';

        foreach ($trace as $k => $v) {
            $html .= '<tr><td style="text-align: center;">' . $k . '</td><td>';
            if (!empty($v['class'])) {
                $html .= $v['class'] . $v['type'];
            }
            $html .= $v['function'];
            $args = [];
            if (!empty($v['args'])) {
                foreach ($v['args'] as $arg) {
                    switch (gettype($arg)) {
                        case 'NULL':
                            $args[] = 'null';
                            break;

                        case 'array':
                            $args[] = 'Array';
                            break;

                        case 'object':
                            $args[] = 'Object(' . get_class($arg) . ')';
                            break;

                        case 'boolean':
                            $args[] = $arg ? 'true' : 'false';
                            break;

                        case 'integer':
                        case 'double':
                            $args[] = $arg;
                            break;

                        default:
                            $arg = (string) $arg;
                            $str = htmlspecialchars(substr($arg, 0, 16));
                            if (strlen($arg) > 16) {
                                $str .= '&hellip;';
                            } else {
                                $args[] = "'" . $str . "'";
                            }
                            break;
                    }
                }
            }

            $html .= '(' . implode(', ', $args) . ')'
                   . '</td>'
                   . '<td>' . (isset($v['file']) ? $v['file'] : 'unknown')
                   . ':' . (isset($v['line']) ? $v['line'] : 'unknown')
                   . '</td></tr>' . "\n";
        }
        $html .= '<tr><td style="text-align: center;">' . ($k + 1) . '</td>'
               . '<td>{main}</td>'
               . '<td>&nbsp;</td></tr>' . "\n"
               . '</table>';
        return $html;
    }

    /**
     * @return string A text view of the Error that has occurred
     */
    public function toText()
    {
        $causes = [];
        $this->getCauseMessage($causes);
        $causeMsg = '';
        foreach ($causes as $i => $cause) {
            $causeMsg .= str_repeat(' ', $i) . $cause['class'] . ': '
                . $cause['message'] . ' in ' . $cause['file']
                . ' on line ' . $cause['line'] . "\n";
        }
        return $causeMsg . $this->getTraceAsString();
    }
}
