<?php

namespace Exception;

require_once('module/Application/test/PR_TestCase.php');

/**
 * @large
 */
class RenderLargeTest extends \PR_TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        self::cleanDb();
    }

    public function test_reallyLargeId()
    {
        $this->mockLoadSession();

        $this->dispatch('/policy/9999999999999999999999999999/review');

        $this->assertMatchesRegularExpression('/Error 422: Internal Server Error/', $this->getResponse()->getContent());
        $this->assertResponseStatusCode(500);
    }

    public function test_error()
    {
        $this->mockLoadSession();

        $this->mockMethod(\Application\Controller\HomeController::class, 'viewAction', function () {
            throw new \ParseError('Fail');
        });

        $this->dispatch('/');

        $this->assertMatchesRegularExpression('/Error 500: Internal Server Error/', $this->getResponse()->getContent());
        $this->assertResponseStatusCode(500);
    }
}
