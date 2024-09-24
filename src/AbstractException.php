<?php

declare(strict_types=1);

namespace Policyreporter\Exception;

/**
 * Common methods to our exceptions to unify our printing format in a stable manner.
 */
abstract class AbstractException extends \Exception
{
    public const DEFAULT_FORM = 'global';
    public const DEFAULT_FIELD = self::DEFAULT_FORM;

    public const DEFAULT_STRING_LIMIT = 15;

    private $additionalFields = [];

    private $form = null;
    private $field = null;

    /** @var string */
    private $requestId = null;

    private static $sensitiveClassMethods = [
        'PDO->__construct' => 1
    ];

    /**
     * Json encodes the message and calls the parent constructor.
     *
     * @param   null           $message             The message to display (default: null)
     * @param   int            $code                The error code (default: 0)
     * @param   Throwable|null $previous            Exceptions to chain (default: null)
     * @param   array          $additionalMessages  Additional messages (default: [])
     */
    public function __construct($message = null, $code = 0, ?\Throwable $previous = null, $additionalMessages = [])
    {
        if ($code === null || $code === 0) {
            $code = 500;
        }
        parent::__construct($message, $code, $previous);

        if (is_array($additionalMessages) && count($additionalMessages)) {
            $this->additionalFields['messages'] = $additionalMessages;
        } elseif (!empty($additionalMessages)) {
            $this->additionalFields['messages'] = [$additionalMessages];
        }

        // Set defaults for $this->_form and $this->_field for generic errors
        foreach (['form', 'field'] as $var) {
            if ($this->{"{$var}"} === null) {
                $this->{"{$var}"} = constant(__CLASS__ . '::DEFAULT_' . mb_strtoupper($var));
            }
        }
    }

    /**
     * Returns an array of messages associated with the Exception (may be empty)
     *
     * @return  array       An array of messages
     */
    public function getMessages()
    {
        $mainMessage = $this->getMessage();
        $additionalMessages = isset($this->additionalFields['messages']) ? $this->additionalFields['messages'] : [];
        if ($mainMessage) {
            return array_merge([$mainMessage], $additionalMessages);
        }
        return $additionalMessages;
    }

    public function getAdditionalMessages()
    {
        return $this->additionalFields['messages'] ?? [];
    }

    public function getAdditionalFields()
    {
        return $this->additionalFields;
    }

    /**
     * Exception toString method
     *
     * Produces matching output to PHP's exception, except for the inclusion of the
     * built in code field (omitted from printing in PHP's base exceptions) and
     * auxiliary data from additionalFields
     *
     * @return string
     */
    public function __toString()
    {
        return self::toString($this);
    }

    /**
     * Exception toString method
     *
     * Produces matching output to PHP's exception, except for the inclusion of the
     * built in code field (omitted from printing in PHP's base exceptions) and
     * auxiliary data from additionalFields
     *
     * @param $e
     * @param array $optionalParams
     * @return string
     */
    public static function toString($e, array $optionalParams = [])
    {
        $string = '';

        $reqState = \LegacyServiceManager::get(\Authentication\Service\RequestState::class);

        foreach ($reqState as $key => $val) {
            if (!array_key_exists($key, $optionalParams) && isset($reqState[$key])) {
                $string .= "$key=$val,";
            }
        }

        if (!empty($optionalParams)) {
            $string .= implode(", ", array_map(function ($v, $k) {
                $value = $v;
                return "$k=$value";
            }, $optionalParams, array_keys($optionalParams))) . " ";
        }

        if (!empty($e->getPrevious())) {
            $string .= $e->getPrevious() . PHP_EOL . PHP_EOL . "Next ";
        }

        $string .= "exception '" . get_class($e) . "'";
        if (!empty($e->getMessage())) {
            $string .= " with message '" . $e->getMessage() . "'";
        }
        if (!empty($e->getCode())) {
            $string .= ' of code ' . $e->getCode();
        }
        $string .= " in " . $e->getFile() . ':' . $e->getLine();
        if (method_exists($e, 'getAdditionalFields')) {
            $additionalFields = $e->getAdditionalFields();
            if (!empty($additionalFields)) {
                // Splunk exports require extra escaping around strings found in S3 hostId which prepends a `=` sign
                // such as s9lzHYrFp76ZVxRcpX9+5cjAnEH2ROuNkd2BHfIa6UkFVdtjf5mKR3/eTPFvsiP/XV/VLi31234=
                $splunkEscapePattern = '/<HostId>(.*?)=<\/HostId>/';
                $splunkReplace = '<HostId>${1}</HostId>';
                $fields = array_map(
                    'buildDbgString',
                    $additionalFields
                );
                array_walk($fields, function (&$v, $k) use ($splunkEscapePattern, $splunkReplace) {
                    $escapedValue = preg_replace($splunkEscapePattern, $splunkReplace, $v);
                    $v = "{$k}={$escapedValue}";
                });
                $string .= PHP_EOL . "Additional Data:" . PHP_EOL . "  " . implode(PHP_EOL . "  ", $fields);
            }
        }
        $string .= PHP_EOL . "Stack trace:" . PHP_EOL . self::formatTrace($e);

        return $string;
    }

    /**
     * Format the trace of an exception for plain-text printing
     *
     * @param \Throwable $e The exception containing the trace to format
     * @return string The formatted trace
     */
    private static function formatTrace($e)
    {
        $trace = [];
        $i = 0;
        foreach ($e->getTrace() as $frame) {
            $frameString = '';
            $frameString .= "#{$i} ";
            if (!empty($frame['file'])) {
                $frameString .= "{$frame['file']}({$frame['line']}): ";
            } else {
                $frameString .= "[internal function]: ";
            }
            if (!empty($frame['type'])) {
                $frameString .= "{$frame['class']}{$frame['type']}";
            }
            $frameString .= $frame['function'] ?? '';
            $formattedArgs = [];
            $args = $frame['args'] ?? [];
            foreach ($args as $arg) {
                if (
                    (isset($frame['class']) && isset($frame['function'])) &&
                    array_key_exists("{$frame['class']}->{$frame['function']}", self::$sensitiveClassMethods)
                ) {
                    $arg = new \Policyreporter\Exception\ProtectedString($arg);
                }
                $formattedArgs[] = self::formatArg($arg, self::DEFAULT_STRING_LIMIT);
            }
            if (count($formattedArgs)) {
                $frameString .= '(' . implode(', ', $formattedArgs) . ')';
            } else {
                $frameString .= '( )';
            }
            $trace[] = $frameString;
            $i++;
        }
        $trace[] = "#{$i} {main}";
        return implode(\PHP_EOL, $trace);
    }

    /**
     * Format an argument in a stack trace for printing
     *
     * @param mixed $argument The argument, of absolutely any type, to format
     * @param int $stringLimit The maximum number of string characters to print
     * @return string The string representing the formatted argument
     */
    private static function formatArg($argument, $stringLimit)
    {
        switch (gettype($argument)) {
            case 'boolean':
                return $argument ? 'true' : 'false';
            case 'integer':
            case 'double':
                return "{$argument}";
            case 'string':
                if (\strlen($argument) > $stringLimit) {
                    return "'" . \substr($argument, 0, $stringLimit) . "...'";
                } else {
                    return "'{$argument}'";
                }
            case 'array':
                return 'Array(' . count($argument) . ')';
            case 'object':
                if (get_class($argument) === \Policyreporter\Exception\ProtectedString::class) {
                    return '<protected-string>';
                } else {
                    return 'Object(' . get_class($argument) . ')';
                }
            case 'resource':
                return "{$argument}(" . \get_resource_type($argument) . ')';
            case 'NULL':
                return 'null';
            default:
                return '<unknown type>';
        }
    }

    /**
     * Transform this exception into a nestedObject
     *
     * These generic exceptions will work off a simple template, validation
     * exceptions may be significantly more complex
     *
     * @return string[][][]
     */
    public function toNestedObject()
    {
        return [
            $this->form => [
                $this->field => array_map(
                    function ($v) {
                        return ['text' => $v];
                    },
                    $this->getMessages()
                )
            ]
        ];
    }

    /**
     * Transform this exception into an array of nestedObjects
     *
     * @return string[][][][]
     */
    public static function nestedObjectsFromChain(\Throwable $exception)
    {
        $nestedObjects = [];
        while ($exception !== null) {
            // General exceptions should not be included
            if ($exception instanceof \Exception\AbstractException) {
                $nestedObjects[] = $exception->toNestedObject();
            } else {
                $nestedObjects[] = [
                    static::DEFAULT_FORM => [static::DEFAULT_FIELD => [['text' => 'An internal error occurred.']]]
                ];
            }
            $exception = $exception->getPrevious();
        }
        return $nestedObjects;
    }

    /**
     * Combine an array of nestedObjects into a single nestedObject
     *
     * @return string[][][]
     */
    public static function mergeNestedObjects(array $nestedObjects)
    {
        $mergedNestedObject = [];
        foreach ($nestedObjects as $nestedObject) {
            foreach ($nestedObject as $form => $fields) {
                // If we've not yet declared the thing an array, do so now
                if (!isset($mergedNestedObject[$form])) {
                    $mergedNestedObject[$form] = [];
                }
                foreach ($fields as $field => $messages) {
                    // If we've not yet declared the thing an array, do so now
                    if (!isset($mergedNestedObject[$form][$field])) {
                        $mergedNestedObject[$form][$field] = [];
                    }
                    $mergedNestedObject[$form][$field] = array_merge(
                        $mergedNestedObject[$form][$field],
                        $messages
                    );
                }
            }
        }
        return $mergedNestedObject;
    }

    /**
     * Extract all the error codes from a given exception chain
     *
     * @param \Throwable $exception
     * @return int[] The codes occurring in the error chain
     */
    public static function codesFromChain(\Throwable $exception)
    {
        $codes = [];
        while ($exception !== null) {
            $codes[] = $exception->getCode();
            $exception = $exception->getPrevious();
        }
        return $codes;
    }

    /**
     * Extract the most severe HTTP code from a list of codes
     *
     * When sending a response to the user that has multiple exceptions
     * attached to it we should determine the most severe error level
     * to use to mark the response with
     *
     * @param int[] $codes The codes to determine the dominant within
     * @return int The dominant code
     */
    public static function dominantCode(array $codes)
    {
        $codes = array_unique($codes);
        sort($codes);
        $groupedCodes = [];
        foreach ($codes as $code) {
            // Determine response level... 2xx 3xx 4xx or 5xx
            $code = is_numeric($code) ? $code : 500;
            $codeLevel = intval($code / 100);
            $groupedCodes[$codeLevel][] = $code;
        }
        $dominantCodeGroup = last($groupedCodes);
        if (count($dominantCodeGroup) > 1) {
            return intval(first($dominantCodeGroup) / 100) * 100;
        } else {
            return first($dominantCodeGroup);
        }
    }
}
