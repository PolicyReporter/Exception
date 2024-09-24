<?php

declare(strict_types=1);

namespace Policyreporter\Exception\Internal;

class NoTransaction extends \Policyreporter\Exception\Internal
{
    public function __construct(?\Throwable $previous = null, $additionalMessages = [])
    {
        $message = 'An active transaction is required for this operation.';
        $code = 500;

        parent::__construct($message, $code, $previous, $additionalMessages);
    }
}
