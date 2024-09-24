<?php

namespace Exception;

use Authentication\Service\RequestState;

require_once('module/Application/test/PR_TestCase.php');

/**
 * @small
 */
class AbstractExceptionTest extends \PR_TestCase
{
    /** @var RequestState */
    protected $requestState;

    public function setUp(): void
    {
        parent::setUp();

        $this->requestState = $this->container()->get(RequestState::class);
    }

    public function test_dominantCode()
    {
        foreach ([200, 400, 401, 402, 402, 500, 501, 502, 503] as $code) {
            $this->assertSame($code, \Exception\AbstractException::dominantCode([$code]));
        }
        /** @noinspection PhpDuplicateArrayKeysInspection */
        $dominantCodes = [
            200 => [200, 201],
            200 => [201, 202],
            303 => [201, 202, 303],
            300 => [303, 304],
            300 => [201, 202, 303, 304],
            300 => [201, 303, 304],
            404 => [201, 301, 404],
            501 => [100, 200, 300, 301, 401, 501, 100, 102],
        ];
        foreach ($dominantCodes as $result => $codes) {
            $this->assertSame($result, \Exception\AbstractException::dominantCode($codes));
        }

        //Handle non http code (like SQL error code)
        $this->assertSame(500, \Exception\AbstractException::dominantCode(['P0001']));
    }

    public function test_formatTrace()
    {
        $abstractException = new \ReflectionClass('\Exception\AbstractException');
        $formatTrace = $abstractException->getMethod('formatTrace');
        $formatTrace->setAccessible(true);

        $resultTrace = $this->invokeStaticMethod('\Exception\AbstractException', 'formatTrace', [
            new class {
                public function getTrace()
                {
                    return [
                        ['file'     => '/opt/pr/policyr/vendor/zendframework/zend-loader/src/StandardAutoloader.php',
                         'line'     => 305,
                         'function' => 'include'
                        ],
                        ['file'     => 'ServiceManager.php',
                         'line'     => 200,
                         'function' => 'doCreate',
                         'class'    => 'ServiceManager',
                         'type'     => '->',
                         'args'     => ['Search']
                        ],
                        ['file'     => '/opt/pr/policyr/vendor/zendframework/zend-mvc/src/Application.php',
                         'line'     => 332,
                         'function' => 'triggerEventUntil',
                         'class'    => 'Zend\EventManager\EventManager',
                         'type'     => '->',
                         'args'     => [[], []]
                        ],
                    ];
                }
            }
        ]);

        $this->assertEquals(
            '#0 /opt/pr/policyr/vendor/zendframework/zend-loader/src/StandardAutoloader.php(305): include( )
#1 ServiceManager.php(200): ServiceManager->doCreate(\'Search\')
#2 /opt/pr/policyr/vendor/zendframework/zend-mvc/src/Application.php(332): Zend\EventManager\EventManager->triggerEventUntil(Array(0), Array(0))
#3 {main}',
            $resultTrace
        );

        $resultTrace = $this->invokeStaticMethod('\Exception\AbstractException', 'formatTrace', [
            new class {
                public function getTrace()
                {
                    return [];
                }
            }
        ]);

        $this->assertEquals('#0 {main}', $resultTrace);
    }

    public function test_mergeNestedObjectsFromChain()
    {
        $this->assertSame(
            \Exception\AbstractException::mergeNestedObjects(
                \Exception\AbstractException::nestedObjectsFromChain(
                    new \Exception\User\Validator([])
                )
            ),
            []
        );
        $this->assertSame(
            \Exception\AbstractException::mergeNestedObjects(
                \Exception\AbstractException::nestedObjectsFromChain(
                    new \Exception\User\Validator(['user' => ['name' => [['text' => 'Error.']]]])
                )
            ),
            ['user' => ['name' => [['text' => 'Error.']]]]
        );
        $this->assertSame(
            \Exception\AbstractException::mergeNestedObjects(
                \Exception\AbstractException::nestedObjectsFromChain(
                    new \Exception\User('Error.')
                )
            ),
            [
                \Exception\AbstractException::DEFAULT_FORM =>
                    [
                        \Exception\AbstractException::DEFAULT_FIELD =>
                            [['text' => 'Error.']]
                    ]
            ]
        );

        $nestedObjects = \Exception\AbstractException::nestedObjectsFromChain(
            new \Exception\User\Validator(
                [
                    'user' => [
                        'name' => [
                            [
                                'text' => "I'm an error."
                            ],
                            [
                                'text' => "I'm templated and the {{adjective}} server doesn't know any different.",
                                'meta' => ['adjective' => 'silly'],
                            ],
                        ],
                        'password' => [
                            [
                                'text' => 'Passwords must contain at least 12 parenthesis of differing types.',
                            ],
                        ],
                    ],
                ],
                HTTP_STATUS_UNPROCESSABLE_ENTITY,
                new \Exception\System(
                    'Ah generic errors, so much fun.',
                    0,
                    new \Exception\User\Validator(
                        [
                            'user' => [
                                'name' => [
                                    [
                                        'text' => "I'm templated with multiple {{noun}} and the {{adjective}} server is so {{adjective}} that it doesn't know any different.",
                                        'meta' => ['adjective' => 'silly', 'noun' => 'replacements'],
                                    ],
                                ],
                                'password' => [
                                    [
                                        'text' => 'Password cannot be a SQL keyword because our system does something really, really silly.',
                                    ],
                                ],
                                'id' => [
                                    [
                                        'text' => 'Whoops, we ran out of numbers, the id sequence is empty.',
                                    ],
                                ],
                            ],
                        ]
                    )
                )
            )
        );
        $this->assertSame(
            $nestedObjects,
            [
                [
                    'user' => [
                        'name' => [
                            [
                                'text' => "I'm an error."
                            ],
                            [
                                'text' => "I'm templated and the {{adjective}} server doesn't know any different.",
                                'meta' => ['adjective' => 'silly'],
                            ],
                        ],
                        'password' => [
                            [
                                'text' => 'Passwords must contain at least 12 parenthesis of differing types.',
                            ],
                        ],
                    ],
                ],
                [
                    \Exception\AbstractException::DEFAULT_FORM => [
                        \Exception\AbstractException::DEFAULT_FIELD => [
                            [
                                'text' => 'Ah generic errors, so much fun.',
                            ],
                        ],
                    ],
                ],
                [
                    'user' => [
                        'name' => [
                            [
                                'text' => "I'm templated with multiple {{noun}} and the {{adjective}} server is so {{adjective}} that it doesn't know any different.",
                                'meta' => ['adjective' => 'silly', 'noun' => 'replacements'],
                            ],
                        ],
                        'password' => [
                            [
                                'text' => 'Password cannot be a SQL keyword because our system does something really, really silly.',
                            ],
                        ],
                        'id' => [
                            [
                                'text' => 'Whoops, we ran out of numbers, the id sequence is empty.',
                            ],
                        ],
                    ],
                ]
            ]
        );
        $this->assertSame(
            \Exception\AbstractException::mergeNestedObjects($nestedObjects),
            [
                'user' => [
                    'name' => [
                        [
                            'text' => "I'm an error.",
                        ],
                        [
                            'text' => "I'm templated and the {{adjective}} server doesn't know any different.",
                            'meta' => ['adjective' => 'silly'],
                        ],
                        [
                            'text' => "I'm templated with multiple {{noun}} and the {{adjective}} server is so {{adjective}} that it doesn't know any different.",
                            'meta' => ['adjective' => 'silly', 'noun' => 'replacements'],
                        ],
                    ],
                    'password' => [
                        [
                            'text' => 'Passwords must contain at least 12 parenthesis of differing types.',
                        ],
                        [
                            'text' => 'Password cannot be a SQL keyword because our system does something really, really silly.',
                        ],
                    ],
                    'id' => [
                        [
                            'text' => 'Whoops, we ran out of numbers, the id sequence is empty.',
                        ],
                    ],
                ],
                \Exception\AbstractException::DEFAULT_FORM => [
                    \Exception\AbstractException::DEFAULT_FIELD => [
                        [
                            'text' => "Ah generic errors, so much fun.",
                        ],
                    ],
                ],
            ]
        );
    }

    public function testRequestIdExceptionThrow()
    {
        try {
            $this->requestState[RequestState::REQUEST_ID_KEY] = "ShouldNotBeSeen";
            throw new \Exception("random msg");
        } catch (\Exception $e) {
            $params = ["REQUEST_ID" => "ShouldBeSeen"];
            $errorMsg = \Exception\AbstractException::toString($e, $params);

            //checks for the existence of REQUEST_ID and makes sure it only appears once
            $this->assertTrue(1 === substr_count($errorMsg, "REQUEST_ID=ShouldBeSeen"));
        }
    }

    public function testToStringEscapesSplunk()
    {
        $actual = \Exception\AbstractException::toString(new \Exception\System(
            'Test AWS Exception',
            0,
            null,
            'exception \'Aws\\S3\\Exception\\S3Exception\' with message \'Error executing \"GetObject\" on \"https://biopolicy-stg.s3.amazonaws.com/daniil/fea00e97-88f6-4104-bb97-4e0e1c2cb3cb\"; AWS HTTP error: Client error: `GET https://biopolicy-stg.s3.amazonaws.com/daniil/fea00e97-88f6-4104-bb97-4e0e1c2cb3cb` resulted in a `404 Not Found` response:\n<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Error><Code>NoSuchKey</Code><Message>The specified key does not exist.</Message> (truncated...)\n NoSuchKey (client): The specified key does not exist. - <?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Error><Code>NoSuchKey</Code><Message>The specified key does not exist.</Message><Key>daniil/fea00e97-88f6-4104-bb97-4e0e1c2cb3cb</Key><RequestId>C13E9146C0F92105</RequestId><HostId>Y6skkTdbuXnEN61pEQ7b5lOANnaqqxpzU3xmZ+aKFpN+xg0JuVZkDBlE66y9LKRzk3psEAE3/8E=</HostId></Error>\'\n\nGuzzleHttp\\Exception\\ClientException: Client error: `GET https://biopolicy-stg.s3.amazonaws.com/daniil/fea00e97-88f6-4104-bb97-4e0e1c2cb3cb` resulted in a `404 Not Found` response:\n<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Error><Code>NoSuchKey</Code><Message>The specified key does not exist.</Message> (truncated...)\n in /opt/pr/policyr/vendor/guzzlehttp/guzzle/src/Exception/RequestException.php:113\nStack trace:\n#0 /opt/pr/policyr/vendor/guzzlehttp/guzzle/src/Middleware.php(65): GuzzleHttp\\Exception\\RequestException::create(Object(GuzzleHttp\\Psr7\\Request), Object(GuzzleHttp\\Psr7\\Response))\n#1 /opt/pr/policyr/vendor/guzzlehttp/promises/src/Promise.php(203): GuzzleHttp\\Middleware::GuzzleHttp\\{closure}(Object(GuzzleHttp\\Psr7\\Response))\n#2 /opt/pr/policyr/vendor/guzzlehttp/promises/src/Promise.php(156): GuzzleHttp\\Promise\\Promise::callHandler(1, Object(GuzzleHttp\\Psr7\\Response), Array)\n#3 /opt/pr/policyr/vendor/guzzlehttp/promises/src/TaskQueue.php(47): GuzzleHttp\\Promise\\Promise::GuzzleHttp\\Promise\\{closure}()\n#4 /opt/pr/policyr/vendor/guzzlehttp/guzzle/src/Handler/CurlMultiHandler.php(96): GuzzleHttp\\Promise\\TaskQueue->run()\n#5 /opt/pr/policyr/vendor/guzzlehttp/guzzle/src/Handler/CurlMultiHandler.php(123): GuzzleHttp\\Handler\\CurlMultiHandler->tick()\n#6 /opt/pr/policyr/vendor/guzzlehttp/promises/src/Promise.php(246): GuzzleHttp\\Handler\\CurlMultiHandler->execute(true)\n#7 /opt/pr/policyr/vendor/guzzlehttp/promises/src/Promise.php(223): GuzzleHttp\\Promise\\Promise->invokeWaitFn()\n#8 /opt/pr/policyr/vendor/guzzlehttp/promises/src/Promise.php(267): GuzzleHttp\\Promise\\Promise->waitIfPending()\n#9 /opt/pr/policyr/vendor/guzzlehttp/promises/src/Promise.php(225): GuzzleHttp\\Promise\\Promise->invokeWaitList()\n#10 /opt/pr/policyr/vendor/guzzlehttp/promises/src/Promise.php(267): GuzzleHttp\\Promise\\Promise->waitIfPending()\n#11 /opt/pr/policyr/vendor/guzzlehttp/promises/src/Promise.php(225): GuzzleHttp\\Promise\\Promise->invokeWaitList()\n#12 /opt/pr/policyr/vendor/guzzlehttp/promises/src/Promise.php(62): GuzzleHttp\\Promise\\Promise->waitIfPending()\n#13 /opt/pr/policyr/vendor/aws/aws-sdk-php/src/AwsClient.php(205): GuzzleHttp\\Promise\\Promise->wait()\n#14 /opt/pr/policyr/vendor/aws/aws-sdk-php/src/AwsClient.php(170): Aws\\AwsClient->execute(Object(Aws\\Command))\n#15 /opt/pr/policyr/module/Application/src/Application/Service/S3.php(136): Aws\\AwsClient->__call(\'getObject\', Array)\n#16 /opt/pr/policyr/module/Application/src/Application/Service/FileBucket.php(395): Application\\Service\\S3->getObject(\'fea00e97-88f6-4...\', Array)\n#17 /opt/pr/policyr/module/Application/src/Application/Service/FileBucket.php(383): Application\\Service\\FileBucket->getObject(\'fea00e97-88f6-4...\', \'\')\n#18 /opt/pr/policyr/module/Application/src/Application/Service/Search0.php(208): Application\\Service\\FileBucket->getArtifact(188886, 1487396, \'parsedplaintext\')\n#19 [internal function]: Application\\Service\\Search->Application\\Service\\{closure}(Array)\n#20 /opt/pr/policyr/module/Application/src/Application/Service/Search0.php(228): array_map(Object(Closure), Array)\n#21 /opt/pr/policyr/module/Application/src/Application/Controller/Search0.php(339): Application\\Service\\Search0->generateSlicedResults(Array, Array, 1, Array)\n#22 /opt/pr/policyr/vendor/zendframework/zend-mvc/src/Controller/AbstractRestfulController.php(383): Application\\Controller\\Search0->getList()\n#23 /opt/pr/policyr/vendor/zendframework/zend-eventmanager/src/EventManager.php(322): Zend\\Mvc\\Controller\\AbstractRestfulController->onDispatch(Object(Zend\\Mvc\\MvcEvent))\n#24 /opt/pr/policyr/vendor/zendframework/zend-eventmanager/src/EventManager.php(179): Zend\\EventManager\\EventManager->triggerListeners(Object(Zend\\Mvc\\MvcEvent), Object(Closure))\n#25 /opt/pr/policyr/vendor/zendframework/zend-mvc/src/Controller/AbstractController.php(106): Zend\\EventManager\\EventManager->triggerEventUntil(Object(Closure), Object(Zend\\Mvc\\MvcEvent))\n#26 /opt/pr/policyr/vendor/zendframework/zend-mvc/src/Controller/AbstractRestfulController.php(313): Zend\\Mvc\\Controller\\AbstractController->dispatch(Object(Zend\\Http\\PhpEnvironment\\Request), Object(Zend\\Http\\PhpEnvironment\\Response))\n#27 /opt/pr/policyr/vendor/zendframework/zend-mvc/src/DispatchListener.php(138): Zend\\Mvc\\Controller\\AbstractRestfulController->dispatch(Object(Zend\\Http\\PhpEnvironment\\Request), Object(Zend\\Http\\PhpEnvironment\\Response))\n#28 /opt/pr/policyr/vendor/zendframework/zend-eventmanager/src/EventManager.php(322): Zend\\Mvc\\DispatchListener->onDispatch(Object(Zend\\Mvc\\MvcEvent))\n#29 /opt/pr/policyr/vendor/zendframework/zend-eventmanager/src/EventManager.php(179): Zend\\EventManager\\EventManager->triggerListeners(Object(Zend\\Mvc\\MvcEvent), Object(Closure))\n#30 /opt/pr/policyr/vendor/zendframework/zend-mvc/src/Application.php(332): Zend\\EventManager\\EventManager->triggerEventUntil(Object(Closure), Object(Zend\\Mvc\\MvcEvent))\n#31 /opt/pr/policyr/reporter/zend.php(26): Zend\\Mvc\\Application->run()\n#32 {main}\n\nNext Aws\\S3\\Exception\\S3Exception: Error executing \"GetObject\" on \"https://biopolicy-stg.s3.amazonaws.com/daniil/fea00e97-88f6-4104-bb97-4e0e1c2cb3cb\"; AWS HTTP error: Client error: `GET https://biopolicy-stg.s3.amazonaws.com/daniil/fea00e97-88f6-4104-bb97-4e0e1c2cb3cb` resulted in a `404 Not Found` response:\n<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Error><Code>NoSuchKey</Code><Message>The specified key does not exist.</Message> (truncated...)\n NoSuchKey (client): The specified key does not exist. - <?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Error><Code>NoSuchKey</Code><Message>The specified key does not exist.</Message><Key>daniil/fea00e97-88f6-4104-bb97-4e0e1c2cb3cb</Key><RequestId>C13E9146C0F92105</RequestId><HostId>Y6skkTdbuXnEN61pEQ7b5lOANnaqqxpzU3xmZ+aKFpN+xg0JuVZkDBlE66y9LKRzk3psEAE3/8E=</HostId></Error> in /opt/pr/policyr/vendor/aws/aws-sdk-php/src/WrappedHttpHandler.php:159\nStack trace:\n#0 /opt/pr/policyr/vendor/aws/aws-sdk-php/src/WrappedHttpHandler.php(77): Aws\\WrappedHttpHandler->parseError(Array, Object(GuzzleHttp\\Psr7\\Request), Object(Aws\\Command))\n#1 /opt/pr/policyr/vendor/guzzlehttp/promises/src/Promise.php(203): Aws\\WrappedHttpHandler->Aws\\{closure}(Array)\n#2 /opt/pr/policyr/vendor/guzzlehttp/promises/src/Promise.php(174): GuzzleHttp\\Promise\\Promise::callHandler(2, Array, Array)\n#3 /opt/pr/policyr/vendor/guzzlehttp/promises/src/RejectedPromise.php(40): GuzzleHttp\\Promise\\Promise::GuzzleHttp\\Promise\\{closure}(Array)\n#4 /opt/pr/policyr/vendor/guzzlehttp/promises/src/TaskQueue.php(47): GuzzleHttp\\Promise\\RejectedPromise::GuzzleHttp\\Promise\\{closure}()\n#5 /opt/pr/policyr/vendor/guzzlehttp/guzzle/src/Handler/CurlMultiHandler.php(96): GuzzleHttp\\Promise\\TaskQueue->run()\n#6 /opt/pr/policyr/vendor/guzzlehttp/guzzle/src/Handler/CurlMultiHandler.php(123): GuzzleHttp\\Handler\\CurlMultiHandler->tick()\n#7 /opt/pr/policyr/vendor/guzzlehttp/promises/src/Promise.php(246): GuzzleHttp\\Handler\\CurlMultiHandler->execute(true)\n#8 /opt/pr/policyr/vendor/guzzlehttp/promises/src/Promise.php(223): GuzzleHttp\\Promise\\Promise->invokeWaitFn()\n#9 /opt/pr/policyr/vendor/guzzlehttp/promises/src/Promise.php(267): GuzzleHttp\\Promise\\Promise->waitIfPending()\n#10 /opt/pr/policyr/vendor/guzzlehttp/promises/src/Promise.php(225): GuzzleHttp\\Promise\\Promise->invokeWaitList()\n#11 /opt/pr/policyr/vendor/guzzlehttp/promises/src/Promise.php(267): GuzzleHttp\\Promise\\Promise->waitIfPending()\n'
        ));

        $this->assertStringNotMatchesFormat('%A<HostId>Y6skkTdbuXnEN61pEQ7b5lOANnaqqxpzU3xmZ+aKFpN+xg0JuVZkDBlE66y9LKRzk3psEAE3/8E=</HostId>%A', $actual);
        $this->assertStringMatchesFormat('%A<HostId>Y6skkTdbuXnEN61pEQ7b5lOANnaqqxpzU3xmZ+aKFpN+xg0JuVZkDBlE66y9LKRzk3psEAE3/8E</HostId>%A', $actual);
    }
}
