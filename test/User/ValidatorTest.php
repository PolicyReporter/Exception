<?php

namespace Exception\User;

require_once('module/Application/test/PR_TestCase.php');

/**
 * @small
 */
class ValidatorTest extends \PR_TestCase
{
    public function test_validatorGetMessages()
    {
        $this->assertSame(
            (new \Exception\User\Validator(
                ['user' => ['name' => [
                    [
                        'text' => "I'm templated and the {{adjective}} server doesn't know any different.",
                        'meta' => ['adjective' => 'silly'],
                    ],
                    [
                        'text' => 'This error is more boring.'
                    ]
                ]]]
            ))->getMessages(),
            [
                "Validation errors occurred: I'm templated and the silly server doesn't know any different. This error is more boring.",
                "I'm templated and the silly server doesn't know any different.",
                'This error is more boring.',
            ]
        );
        $this->assertSame(
            (new \Exception\User\Validator(
                ['user' => ['name' => [
                    [
                        'text' => "I'm templated and the {{adjective}} server doesn't know any different.",
                        'meta' => ['adjective' => 'silly'],
                    ],
                    [
                        'text' => "I'm templated with multiple {{noun}} and the {{adjective}} server is so {{adjective}} that it doesn't know any different.",
                        'meta' => ['adjective' => 'silly', 'noun' => 'replacements'],
                    ],
                    [
                        'text' => 'This error is more boring.'
                    ]
                ]]]
            ))->getMessages(),
            [
                "Validation errors occurred: I'm templated and the silly server doesn't know any different. I'm templated with multiple replacements and the silly server is so silly that it doesn't know any different. This error is more boring.",
                "I'm templated and the silly server doesn't know any different.",
                "I'm templated with multiple replacements and the silly server is so silly that it doesn't know any different.",
                'This error is more boring.',
            ]
        );
        $this->assertSame(
            (new \Exception\User\Validator([]))->getMessages(),
            [
                'Validation errors occurred.',
            ]
        );
    }
}
