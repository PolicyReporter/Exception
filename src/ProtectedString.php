<?php

namespace Policyreporter\Exception;

class ProtectedString
{
    private $value = null;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function __toString()
    {
        return $this->value;
    }
}
