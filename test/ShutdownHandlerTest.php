<?php

namespace Exception;

use Laminas\Console\Request;
use Laminas\Mvc\MvcEvent;
use Exception\ShutdownHandler;
use Laminas\Router\RouteMatch;
use Laminas\Stdlib\Parameters;

require_once('module/Application/test/PR_TestCase.php');

/**
 * @small
 */
class ShutdownHandlerTest extends \PR_TestCase
{
    public function testGetSessionData()
    {
        $_SESSION = [
            "userpermission" => 'Gooten Tuck userpermission',
            "username"       => 'Gooten Tuck username',
            "firstname"      => 'Gooten Tuck firstname',
            "memberid"       => 'Gooten Tuck memberid',
            "usercompany"    => 'Gooten Tuck usercompany',
            "usercompanyid"  => 'Gooten Tuck usercompanyid',
        ];

        $handler = ShutdownHandler::handle(new MvcEvent());
        $actual = $this->invokeMethod($handler, 'getSessionData', [], true);
        $expected = [
            "userpermission=Gooten Tuck userpermission",
            "username=Gooten Tuck username",
            "firstname=Gooten Tuck firstname",
            "memberid=Gooten Tuck memberid",
            "usercompany=Gooten Tuck usercompany",
            "usercompanyid=Gooten Tuck usercompanyid",
        ];

        $this->assertEquals($expected, $actual);

        $_SESSION = [
            "userpermission" => 'Gooten Tuck userpermission',
            "username"       => 'Gooten Tuck username',

            "Can't see me 1" => 123,
            "Can't see me 2" => 456,
        ];

        $expected = [
            "userpermission=Gooten Tuck userpermission",
            "username=Gooten Tuck username",
        ];

        $actual = $this->invokeMethod($handler, 'getSessionData', [], true);
        $this->assertEquals($expected, $actual);

        $_SESSION = [
            "userpermission" => null,
            "username"       => '',
            "firstname"      => 0,
        ];

        $actual = $this->invokeMethod($handler, 'getSessionData', [], true);
        $this->assertEmpty($actual);

        $_SESSION = [
            "userpermission" => 1,
            "username"       => 2,
            "firstname"      => true,
        ];

        $actual = $this->invokeMethod($handler, 'getSessionData', [], true);
        $expected = [
            "userpermission=1",
            "username=2",
            "firstname=1",
        ];

        $this->assertEquals($expected, $actual);
    }

    public function testGetRouteData()
    {
        $event = new MvcEvent();
        $handler = ShutdownHandler::handle($event);

        $params = [
            'a' => 1,
            'b' => 2,
            'c' => null,
            'd' => '',
        ];

        $expected = [
            "a=1",
            "b=2",
        ];
        $actual = $this->invokeMethod($handler, 'getRouteData', [$params], true);
        $this->assertEquals($expected, $actual);
    }

    public function testGetRouteData_ndArray()
    {
        $event = new MvcEvent();
        $handler = ShutdownHandler::handle($event);

        $params = [
            'a' => 1,
            'b' => 2,
            'c' => null,
            'd' => '',
            'e' => [
                'thingy1' => 'stuff',
                'thingy2' => 'stuff2'
            ],
            'f' => [
                'badthingy' => [
                    'badstuff' => 'some bad stuff',
                    'badstuff2' => 'more bad stuff',
                'theotherbadthing' => 'with more stuff',
                ],
            ],
        ];

        $expected = [
            "a=1",
            "b=2",
            'e=[thingy1=stuff, thingy2=stuff2]',
            'f=[badthingy=[badstuff=some bad stuff, badstuff2=more bad stuff, theotherbadthing=with more stuff]]',
        ];
        $actual = $this->invokeMethod($handler, 'getRouteData', [$params], true);
        $this->assertEquals($expected, $actual);
    }

    public function testGetRequestData()
    {
        $request = (new \Laminas\Http\PhpEnvironment\Request());
        $handler = ShutdownHandler::handle(new MvcEvent());
        $expected = [
            'Method=GET',
            'Uri=http:/',
        ];
        $actual = $this->invokeMethod($handler, 'getRequestData', [$request], true);
        $this->assertEquals($expected, $actual);

        $request->setUri('Uri its me');
        $request->setMethod('Post');

        $expected = [
            'Method=POST',
            'Uri=Uri%20its%20me',
        ];
        $actual = $this->invokeMethod($handler, 'getRequestData', [$request], true);
        $this->assertEquals($expected, $actual);

        //Test Console Request

        $_SERVER["argv"] = [];//this is required for testing Console/Request
        $consoleRequest = new Request();
        $handler = ShutdownHandler::handle(new MvcEvent());
        $params = [
            'abba' => 'imma',
            'saba' => 'safta',
        ];

        $expected = [
            'abba=imma',
            'saba=safta',
        ];

        $consoleRequest->setParams(new Parameters($params));
        $actual = $this->invokeMethod($handler, 'getRequestData', [$consoleRequest], true);
        $this->assertEquals($expected, $actual);
    }

    public function testCheckSuppressionFlagNothingSuppressed()
    {
        $request = (new \Laminas\Http\PhpEnvironment\Request());
        $handler = ShutdownHandler::handle(new MvcEvent());

        $message = 'I am an error!';
        $file = '/lib/Diff';
        $output = 'This is my already formed string';
        $line = '334';
        $type = '4';

        // If the error is not suppressed then we should see the error message and $type, $line, $file
        $expected = 'Unhandled error caught in \Exception\ShutdownHandler. ' .
                        $output . ' ' . $message .
                        ' type=' . $type .
                        ' in ' . $file .
                        ' on line ' . $line;

        $actual = $this->invokeMethod($handler, 'checkSuppressionFlag', [$message, $file, $type, $line, $output], true);
        $this->assertEquals($expected, $actual);
    }

    public function testCheckSuppressionFlagAddSuppress()
    {
        $request = (new \Laminas\Http\PhpEnvironment\Request());
        $handler = ShutdownHandler::handle(new MvcEvent());

        $message = 'Maximum execution time of 5 seconds exceeded';
        $file = '/opt/pr/policyr/vendor/phpspec/php-diff/lib/Diff/SequenceMatcher.php';
        $line = '334';
        $type = '4';

        $output = 'This is my already formed string';

        // Since this is a suppressed error, we don't really need $line and $type
        $expected = $output . ' suppress_for_slack=true';

        $actual = $this->invokeMethod($handler, 'checkSuppressionFlag', [$message, $file, $type, $line, $output], true);
        $this->assertEquals($expected, $actual);
    }
}
