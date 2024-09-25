<?php

namespace Exception\Controller;

use Authentication\Service\RequestState;

require_once('module/Application/test/PR_TestCase.php');
require_once('lib/basicfunctions.php');

/**
 * @small
 */
class ExceptionControllerTest extends \PR_TestCase
{
    public function tearDown(): void
    {
        $this->cleanMocks();
    }

    public function testExceptionRoute()
    {
        $this->mockLoadSession();
        $this->dispatch("/errors/500");
        $code = $this->getResponseStatusCode();
        $this->assertEquals(500, $code);
    }

    public function testRequestIdEventLogging()
    {
        $requestId = 'THIS_IS_A_TEST';

        $this->mockLoadSession();
        $eventLogPath = configVar('paths', 'eventLog');
        file_put_contents($eventLogPath, '');
        $this->dispatch("/errors/500?REQUEST_ID={$requestId}");

        $logContent = file_get_contents($eventLogPath);
        //assert event log
        $this->assertStringContainsString("REQUEST_ID=\"{$requestId}\"", $logContent);
    }

    public function testRequestIdErrorLogging()
    {
        $requestId = 'THIS_IS_A_TEST';

        $dispatchErrorPrintingArguments = [];
        $this->mockErrorDispatchPrinting($dispatchErrorPrintingArguments);

        $this->dispatch("/errors/500?REQUEST_ID={$requestId}");

        $this->assertNotNull(
            $dispatchErrorPrintingArguments['requestState']
        );

        $this->assertTrue(
            isset($dispatchErrorPrintingArguments['requestState'][RequestState::REQUEST_ID_KEY])
        );

        $this->assertEquals(
            $requestId,
            $dispatchErrorPrintingArguments['requestState'][RequestState::REQUEST_ID_KEY]
        );
    }
}
