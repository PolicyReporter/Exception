<?php

declare(strict_types=1);

namespace Policyreporter\Exception\Service;

class Exception
{
    public static function generateApiError($formName, $fieldName, $message)
    {
        return [
            "error" => [
                [
                    "name" => $formName,
                    "fields" => [
                        [
                            "name" => $fieldName,
                            "messages" => [
                                ["text" => $message]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }
}
