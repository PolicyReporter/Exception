<?php

declare(strict_types=1);

namespace Policyreporter\Exception;

/**
 * Exceptions of or descended from this type will not be printed to the error_log
 * they represent exceptions entirely caused by a PEBKAC
 */
class User extends AbstractException
{
    /**
     * Json encodes the message and calls the parent constructor.
     *
     * @param   null           $message             The message to display (default: null)
     * @param   int            $code                The error code (default: 422)
     * @param   Throwable|null $previous            Exceptions to chain (default: null)
     * @param   array          $additionalMessages  Additional messages (default: [])
     */
    public function __construct(
        $message = null,
        $code = 422,
        ?\Throwable $previous = null,
        $additionalMessages = []
    ) {
        parent::__construct($message, $code, $previous, $additionalMessages);
    }
}
