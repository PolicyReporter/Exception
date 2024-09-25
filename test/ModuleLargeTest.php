<?php

namespace Exception\ExceptionTest;

use Laminas\Http\Headers;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Mvc\Application;
use Laminas\Mvc\MvcEvent;

require_once('module/Application/test/PR_TestCase.php');

/**
 * @covers \Exception\Module
 * @small
 */
class ModuleTest extends \PR_TestCase
{
    public function test_onError_NoHeaderException()
    {
        $module = new \Exception\Module();

        $event = new MvcEvent();
        $event->setParam('error', Application::ERROR_EXCEPTION);
        $event->setParam('exception', new \Exception\User("A test exception!"));
        $event->setApplication(Application::init(include 'config/application.config.php'));

        $request = $this->prophesize(Request::class);
        $request->getRequestUri()->willReturn('my_request');
        $request->getHeaders()->willReturn(new Headers());
        $event->setRequest($request->reveal());

        $splunkLog = [];
        $this->mockSplunkLogCapture($splunkLog);
        $actual = $module->onError($event);

        //nothing is returned on html errors
        $this->assertNull($actual);

        //Assert our request page is logged
        $this->assertIsNumeric($splunkLog[0][1]['durationMilliseconds']);
        unset($splunkLog[0][1]['durationMilliseconds']);
        $this->assertEquals(
            [
                [
                    0 => 'visited',
                    1 => [
                        'route'                => 'my_request',
                        'page'                 => 'my_request',
                    ],
                ],
            ],
            $splunkLog
        );
    }

    public function test_onError_HtmlException()
    {
        $module = new \Exception\Module();

        $event = new MvcEvent();
        $event->setParam('error', Application::ERROR_EXCEPTION);
        $event->setParam('exception', new \Exception\User("A test exception!"));
        $event->setApplication(Application::init(include 'config/application.config.php'));

        $request = $this->prophesize(Request::class);
        $request->getRequestUri()->willReturn('my_request');
        $request->getHeaders()->willReturn(Headers::fromString('Accept: */*'));
        $event->setRequest($request->reveal());

        $splunkLog = [];
        $this->mockSplunkLogCapture($splunkLog);
        $actual = $module->onError($event);

        //nothing is returned on html errors
        $this->assertNull($actual);

        //Assert our request page is logged
        $this->assertIsNumeric($splunkLog[0][1]['durationMilliseconds']);
        unset($splunkLog[0][1]['durationMilliseconds']);
        $this->assertEquals(
            [
                [
                    0 => 'visited',
                    1 => [
                        'route'                => 'my_request',
                        'page'                 => 'my_request',
                        'statusCode'           => 422,
                    ],
                ],
            ],
            $splunkLog
        );
    }

    public function test_onError_JsonException()
    {
        $module = new \Exception\Module();

        $event = new MvcEvent();
        $event->setParam('error', Application::ERROR_EXCEPTION);
        $event->setParam('exception', new \Exception\User("A test exception!"));
        $event->setApplication(Application::init(include 'config/application.config.php'));

        $request = $this->prophesize(Request::class);
        $request->getRequestUri()->willReturn('my_request');
        $request->getHeaders()->willReturn(Headers::fromString('Accept: application/json'));
        $event->setRequest($request->reveal());

        $splunkLog = [];
        $this->mockSplunkLogCapture($splunkLog);
        $actual = $module->onError($event);

        //We get our full error object on json errors
        $errorMessage = json_encode(
            [
                'error' => [
                    [
                        'name'   => 'global',
                        'fields' => [
                            [
                                'name'     => 'global',
                                'messages' => [['text' => 'A test exception!']],
                            ],
                        ],
                    ],
                ],
            ]
        );

        $this->assertEquals($errorMessage, $actual->serialize());

        //Assert our request page is logged
        $this->assertIsNumeric($splunkLog[0][1]['durationMilliseconds']);
        unset($splunkLog[0][1]['durationMilliseconds']);
        $this->assertEquals(
            [
                [
                    0 => 'visited',
                    1 => [
                        'route'                => 'my_request',
                        'page'                 => 'my_request',
                        'statusCode'           => 422,
                    ],
                ],
            ],
            $splunkLog
        );
    }
}
