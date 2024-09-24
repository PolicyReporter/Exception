<?php

declare(strict_types=1);

namespace Exception;

require_once('module/Application/test/PR_ModuleAuthTest.php');

/**
 * @small
 */
class ExceptionAuthTest extends \PR_ModuleAuthTest
{
    protected $controller;

    public function setUp(): void
    {
        parent::setUp();
        $this->moduleConfig = include 'module/Exception/config/module.config.php';
        $this->routes = $this->moduleConfig['router']['routes'];

        $this->endpointsRoleShouldGrantAccessTo = [
        'ANY_LEVEL' => [],
        'MAINTAINER' => [],
        'ADMIN' => [
        'errors',
        ],
        'NULL' => [],
        ];

        $this->endpointsSingleFlagShouldGrantAccessTo = [
        'policyupdatesexportaccess' => [],
        'coveredaccess' => [],
        'coveredzipaccess' => [],
        'coveredtrialaccess' => [],
        'rxclstandardaccess' => [],
        'rxclzipaccess' => [],
        'rxcltrialaccess' => [],
        'contact1access' => [],
        'contact2access' => [],
        'calaccess' => [],
        'searchaccess' => [],
        'formularyaccess' => [],
        'pbmaccess' => [],
        'feeschedaccess' => [],
        'rawurlaccess' => [],
        'mbpbaccess' => [],
        'documentcoveredaccess' => [],
        'payercoverageaccess' => [],
        'hdateamaccess' => [],
        'policyupdatesaccess' => [],
        'policydbaccess' => [],
        'productaccess' => [],
        'NULL' => [],
        'searchexportaccess' => [],
        'rxpayercoverageaccess' => [],
        'payerweburlexportaccess' => [],
        ];
    }

  // public function test_generateSnapshot()
  // {
  //   $this->generateSnapshot();
  // }
}
