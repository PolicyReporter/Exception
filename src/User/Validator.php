<?php

declare(strict_types=1);

namespace Policyreporter\Exception\User;

/**
 * Exceptions of or descended from this type will not be printed to the error_log
 * they represent exceptions entirely caused by a PEBKAC
 */
class Validator extends \Policyreporter\Exception\User
{
    private $nestedObject = [];

    public function __construct(
        array $validationErrors,
        int $code = 422,
        ?\Throwable $previousException = null
    ) {
        $textMessages = [];
        foreach ($validationErrors as $form => $fields) {
            foreach ($fields as $field => $messages) {
                foreach ($messages as $message) {
                    $textMessages[] = $this->compileTemplate($message['text'], \if_n_get_r([], $message, 'meta'));
                }
            }
        }
        $this->nestedObject = $validationErrors;

        $textMessageString = implode(' ', $textMessages);
        $error = 'Validation errors occurred' . (mb_strlen($textMessageString) ? ": $textMessageString" : '.');
        parent::__construct($error, $code, $previousException, $textMessages);
    }

    private function compileTemplate($template, $replacements)
    {
        foreach ($replacements as $token => $replacement) {
            $template = preg_replace('/{{' . $token . '}}/', $replacement, $template);
        }
        return $template;
    }

    public function toNestedObject()
    {
        return $this->nestedObject;
    }
}
