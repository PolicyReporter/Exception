<?php

declare(strict_types=1);

namespace Policyreporter\Exception\Controller;

// Forces the dependency on laminas/laminas-mvc - a good candidate for deprecation
class ExceptionController extends \Laminas\Mvc\Controller\AbstractActionController
{
    /**
     * This was written to trigger errors.
     * If you want to trigger a 400 error from client,
     * this controller would do it for you
     *
     * Not actually used in the codebase proper
     *
     * @deprecated
     * @testing GET /errors/500
     */
    public function throwErrorAction()
    {
        $errorCode = $this->params()->fromRoute('code', false);

        if (!$errorCode) {
            return;
        }

        switch ($errorCode) {
            case 500:
                throw new \Exception("Oops something wrong happened");
            break;
        }
    }
}
