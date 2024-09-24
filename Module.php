<?php

namespace Exception;

use Authentication\Service\RequestState;
use Laminas\Http\Response;
use Laminas\Mvc\Application;
use Laminas\Mvc\MvcEvent;

class Module
{
    private $requestTimeStart;

    private const ROUTE_VIEW_PAGE_MAP = [
        'formulary-viewer'         => 'drug_search',
        'policies'                 => 'policy',
        'email-queue/resource'     => 'email-queue',
        'auth/logout'              => 'logout',
        'auth/login/submit'        => 'login',
        'fee-schedule-lookup'      => 'feeschedule',
        'payer-contact-directory'  => 'payerdata',
        'policy-update'            => 'updates',
        'covered-lives/search'     => 'coveredlivessearch',
    ];

    public function onBootstrap(MvcEvent $event)
    {
        $this->requestTimeStart = \microtime(true);
        $eventManager = $event->getApplication()->getEventManager();
        $eventManager->attach(MvcEvent::EVENT_DISPATCH_ERROR, [$this, 'onError'], 1);
        $eventManager->attach(MvcEvent::EVENT_RENDER_ERROR, [$this, 'onError'], 1);

        // Note: this binds the shutdown handler to the onBootstrap event, not the shutdown event
        // However
        // 1. we don't pull very much info off the event anyway
        // 2. most of the info we do pull won't change between bootstrapping and shutdown (e.g., what route we're on)
        // 3. the framework has the ability to edit the initial event to add more data as it runs; that information
        // will be available when we shutdown
        // 4. most importantly: doing this binding now means we always call our shutdown handler, even if we
        // shut down more abruptly than expected, e.g., due to an OOM
        ShutdownHandler::handle($event);
    }

    public function onError(MvcEvent $event)
    {
        if ($event->getError() === Application::ERROR_EXCEPTION) {
            $exception = $event->getParam('exception');
        } else {
            // Fallback to result.exception (does not exist for console routes)
            $exception = $event->getResult()->exception;
        }
        if ($exception === null) {
            return;
        }

        /** @var \Authentication\Service\RequestState $requestState */
        $requestState = $event->getApplication()->getServiceManager()->get(\Authentication\Service\RequestState::class);

        if ($event->getRequest() instanceof \Laminas\Http\Request) {
            $router = $event->getRouteMatch();
            $route = $event->getRequest()->getRequestUri();
            $zendAction = null;
            $controller = null;

            if ($router) {
                $route = $router->getMatchedRouteName();
                $zendAction = $router->getParam('action');
                $controller = $router->getParam('controller');
            }

            $extraLogParams = is_null($zendAction) ? [] : compact('zendAction');

            if ($route == 'legacy') {
                $route = $event->getRequest()->getQuery('view');
            }

            if (array_key_exists($route, self::ROUTE_VIEW_PAGE_MAP)) {
                $page = is_array(self::ROUTE_VIEW_PAGE_MAP[$route]) ?
                    self::ROUTE_VIEW_PAGE_MAP[$route][$zendAction] : self::ROUTE_VIEW_PAGE_MAP[$route];
            } else {
                $page = $route;
            }

            // If our target controller has a special function to log any extra parameters from the request
            $splunkCallable = "\\{$controller}Controller::splunkVisitParamFormat";
            if (is_callable($splunkCallable)) {
                $extraLogParams = array_merge(
                    $extraLogParams,
                    call_user_func_array($splunkCallable, [$event->getRequest(), $route])
                );
            }
            $e = $exception;
            while ($e !== null) {
                if (\is_a($e, \Exception\User::class)) {
                    $e = $e->getPrevious();
                } else {
                    $this->dispatchErrorPrinting($exception, $requestState);

                    if (\extension_loaded('newrelic')) {
                        \newrelic_notice_error($exception);
                    }
                    break;
                }
            }
            $acceptHeader = $event->getRequest()->getHeaders()->get('Accept');
            if (!$acceptHeader) {
                // The client didn't send up any sort of accept header, not even */*
                // this is extremely weird, let's just bail
                // Note that exceptions when doing PHPunit dispatch() could end up here as well
                $this->logToSplunk($route, $page, $extraLogParams);

                return;
            }
            // An HTTP request justifies a single HTTP response code, we need to find
            // the most significant one
            $codes = \Exception\AbstractException::codesFromChain($exception);
            $dominantCode = \Exception\AbstractException::dominantCode($codes);
            if (!defined(Response::class . '::STATUS_CODE_' . $dominantCode)) {
                $dominantCode = Response::STATUS_CODE_500;
            }
            if (
                $event->getResponse() instanceof Response &&
                $event->getResponse()->getStatusCode() !== 401
            ) {
                $event->getResponse()->setStatusCode($dominantCode);
            }
            if ($acceptHeader->match('text/html') || $acceptHeader->match('application/xhtml+xml')) {
                // Currently no handling for html errors
                $extraLogParams['statusCode'] = $dominantCode;
                $this->logToSplunk($route, $page, $extraLogParams);
                return;
            }
            if ($acceptHeader->match('application/json')) {
                if ($event->getResult() instanceof \Laminas\View\Model\JsonModel) {
                    $model = $event->getResult();
                } else {
                    $model = new \Laminas\View\Model\JsonModel();
                }
                // Gather up the nested objects from various exceptions and merge them into
                // a single nested object.
                $nestedObjects = \Exception\AbstractException::mergeNestedObjects(
                    \Exception\AbstractException::nestedObjectsFromChain(
                        $exception
                    )
                );
                // Transform the nested object from [key => [values]] to [[name => key, values => [values]]]
                // Position the nested object on the client response
                $model->setVariable('error', $this->nestedObjectToClientFormat($nestedObjects));

                $event->setViewModel($model);
                $event->stopPropagation();
                $extraLogParams['statusCode'] = $dominantCode;
                $this->logToSplunk($route, $page, $extraLogParams);
                return $model;
            }
        } elseif ($event->getRequest() instanceof \Laminas\Console\Request) {
            $this->dispatchErrorPrinting($exception);

            $code = intval($exception->getCode());

            // If the exception was raised with an error code of zero
            // we want to force it to be 1 so that unix knows something
            // went wrong
            $code = $code ?: 1;

            return $event->setResult(
                (new \Laminas\Console\Response())->setErrorLevel($code)
            );
        }
    }

    /**
     * Utility to log to splunk given a route, page and extra log parameters
     *
     * @param string   $route The route
     * @param string   $page The page
     * @param string[] $additionalFields Any other elements that should be written to
     *                                   the error log as an array of 'name' => 'value'
    */
    private function logToSplunk($route, $page, $extraLogParams)
    {
        $requestTimeEnd = microtime(true);
        $requestDuration = (int) (($requestTimeEnd - $this->requestTimeStart) * 1000);
        $extraLogParams['durationMilliseconds'] = $requestDuration;

        splunkLog('visited', array_merge(compact('route', 'page'), $extraLogParams));
    }

    /**
     * Translate a nested error to client format
     *
     * The client devs prefer an "Array of Objects" type response
     * for easier handling in client code, this format is harder to
     * merge and manipulate server-side so we internally store the
     * error in a different format
     *
     * @param string[][][] $nestedObject The server-preferred format
     * @return string[][][][][] The client-preferred format
     */
    private function nestedObjectToClientFormat($nestedObject)
    {
        $formMessages = [];
        foreach ($nestedObject as $form => $fields) {
            $fieldMessages = [];
            foreach ($fields as $field => $messages) {
                // Clear any empty meta-bodies, as per client-server contract
                $messages = array_map(function ($v) {
                    if (empty($v['meta'])) {
                        unset($v['meta']);
                    }
                    return $v;
                }, $messages);
                $fieldMessages[] = ['name' => $field, 'messages' => $messages];
            }
            $formMessages[] = ['name' => $form, 'fields' => $fieldMessages];
        }
        return $formMessages;
    }

    private function dispatchErrorPrinting($exception, ?\Authentication\Service\RequestState $requestState = null)
    {
        $additionalArguments = [];
        if ($requestState !== null) {
            foreach ($requestState as $key => $val) {
                $additionalArguments[$key] = $val;
            }
        }

        \error(\Exception\AbstractException::toString($exception, $additionalArguments));
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return [
            'Zend\Loader\StandardAutoloader' => [
                'namespaces' => [
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ],
            ],
        ];
    }
}
