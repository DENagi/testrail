<?php

namespace Codeception\TestRail;

use Codeception\Event\FailEvent;
use Codeception\Event\TestEvent;
use Codeception\Events;
use Codeception\Extension;
use Codeception\TestRail\Entities\Milestone;
use Codeception\TestRail\Entities\Plan;
use Codeception\TestRail\Entities\Run;
use Codeception\TestRail\Entities\Suite;
use Codeception\TestRail\Entities\TestCase;
use GuzzleHttp\Client;

/**
 * Class TestRailIntegrationExtension
 * Integration with testRails
 * To work custom parameter "file_path" for test case needed in testRail
 */
class TestRailIntegrationExtension extends Extension
{
    /**
     * @const int
     */
    private const TEST_STATUS_FAILED = 5;

    /**
     * @const int
     */
    private const TEST_STATUS_PASSED = 1;

    /**
     * Codeception events we subscribed to and methods
     * @var string[]
     */
    public static $events = [
        Events::TEST_BEFORE => 'beforeTest',
        Events::TEST_FAIL => 'testFailed',
        Events::TEST_ERROR => 'testFailed',
        Events::TEST_SUCCESS => 'testPassed'
    ];

    /**
     * Is extension needed
     *
     * @var bool
     */
    private $extensionNeeded = true;

    /**
     * Current "version" of tests run - e.g. "Release/2.2"
     * @var string
     */
    private $version;

    /**
     * TestRails api client
     * @var Api
     */
    private $api;

    /**
     * Current suite from testRails API
     * @var Suite
     */
    private $currentSuite;

    /**
     * Current milestone from testRails API
     * @var Milestone
     */
    private $currentMilestone;

    /**
     * Current plan from testRails API
     * @var Plan
     */
    private $currentPlan;

    /**
     * Current run
     * @var Run
     */
    private $currentRun;

    /**
     * Current cases from testRails API
     * @var TestCase
     */
    private $currentCase;

    /**
     * Cached content of test files (to parse them)
     * @var string[]
     */
    private $filesContent = [];

    /**
     * Cached count of each test run count (to detect dataProviders)
     * @var int[]
     */
    private $testRunsCount = [];

    /**
     * @param Api $api
     */
    public function setApi(Api $api): void
    {
        $this->api = $api;
    }

    /**
     * {@inheritdoc}
     */
    public function _initialize(): void
    {
        $this->version = $this->config['version'] ?? '';
        if (!$this->version) {
            $this->output->debug('Version is not specified. TestRails integration is skipped');
            $this->extensionNeeded = false;
        } else {
            $this->output->debug(sprintf('TestRails integration is enabled. Version: %s', $this->version));
        }

        if ($this->isExtensionNeeded()) {
            $api = new Api(
                new Client(),
                $this->config['url'],
                $this->config['username'],
                $this->config['password'],
                $this->config['projectId']
            );
            $this->setApi($api);
        }

        parent::_initialize();
    }

    /**
     * Check if extension must work
     * @return bool
     */
    private function isExtensionNeeded(): bool
    {
        return $this->extensionNeeded;
    }


    /**
     * @param TestEvent $e
     */
    public function beforeTest(TestEvent $e): void
    {
        if (!$this->isExtensionNeeded()) {
            return;
        }
        $this->ensureTestCaseExists($e);
    }


    /**
     * @param FailEvent $e
     */
    public function testFailed(FailEvent $e): void
    {
        if (!$this->isExtensionNeeded()) {
            return;
        }

        $message = $e->getFail()->getMessage();
        $message .= "\n" . $e->getFail()->getFile() . ': ' . $e->getFail()->getLine();
        $message .= "\n" . substr($e->getFail()->getTraceAsString(), 0, 512);

        $this->ensureTestRunExists($e);
        $results = $this->currentRun->getId();
        $caseId = $this->getCaseID($e)->getId();
        $this->setTestResult($results, $caseId, false, $e->getTime(), $message);
        $this->close_run($e);
    }


    /**
     * @param TestEvent $e
     */
    public function testPassed(TestEvent $e): void
    {
        if (!$this->isExtensionNeeded()) {
            return;
        }

        $this->ensureTestRunExists($e);
        $results = $this->currentRun->getId();
        $caseId = $this->getCaseID($e)->getId();
        $this->setTestResult($results, $caseId, true, $e->getTime(), '');
        $this->close_run($e);
    }

    /**
     * @param TestEvent $e
     * @return void
     */
    private function ensureTestCaseExists(TestEvent $e): void
    {
        $testName = $this->getCaseID($e)->getTitle();

        if (!isset($testName)) {
            die("Please add a test case to the " . $this->currentSuite->getName() . " suite in testRail");
        }
    }


    /**
     * @param TestEvent $e
     * @return void
     */
    private function ensureTestRunExists(TestEvent $e): void
    {
        $suiteName = $this->getSuiteID($e)->getName();

        $runs = $this->api->getRuns();

        /*
         * Checks if a run exists, if not it creates for the first time
         */
        if ((in_array($suiteName, array_keys($runs)) == false)){
            $this->currentRun = $this->createRun($e);
        }

        var_dump($this->currentRun);

        /*
         * Check if run exists and if the run is not completed
         */
        if ((in_array($suiteName, array_keys($runs)) == true) && ($runs[$suiteName]->isCompleted() == false)){
            return;
        }
    }

    /**
     * @param TestEvent $e
     */
    private function close_run(TestEvent $e)
    {
        $suiteName = $this->getSuiteID($e)->getName();
        $suiteId = $this->getSuiteID($e)->getId();

        // Fetch all the runs from the project excluding the closed runs
        $runs = $this->api->getRuns();

        // Fetch all the test cases from the provided suite
        $cases = $this->api->getCases($suiteId);

        $total = ($runs[$suiteName]->getPassedCount()) + ($runs[$suiteName]->getFailedCount());

        if ($runs[$suiteName]->getPassedCount() == count($cases) ||
            $runs[$suiteName]->getFailedCount() == count($cases) ||
            $total == count($cases))
        {
            $runID = $this->currentRun->getId();
            $this->api->closeRun($runID);
        }
    }

    /**
     * @param int $runId
     * @param int $caseId
     * @param bool $passed
     * @param float $elapsed
     * @param string $errorMessage
     */
    private function setTestResult(int $runId, int $caseId, bool $passed, float $elapsed, string $errorMessage): void
    {
        $statusId = $passed ? self::TEST_STATUS_PASSED : self::TEST_STATUS_FAILED;

        if (isset( $this->currentRun)){
            $this->api->setTestResult(
                $runId,
                $caseId,
                $statusId,
                $errorMessage,
                $this->version,
                $this->formatElapsed(max(1, ceil($elapsed)))
            );
        }
    }

    /**
     * @param int $seconds
     * @return string
     */
    private function formatElapsed(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds / 60) % 60);
        $seconds %= 60;

        return "{$hours}h {$minutes}m {$seconds}s";
    }

    /**
     * @param TestEvent $e
     * @return Suite
     */
    private function getSuiteID(TestEvent $e): Suite
    {
        $suiteId = $e->getTest()->getMetadata()->getParam();
        $this->currentSuite = $this->api->getSuite((int) $suiteId["testSuiteId"][0]);
        return $this->currentSuite;
    }

    /**
     * @param TestEvent $e
     * @return TestCase
     */
    private function getCaseID(TestEvent $e): TestCase
    {
        $caseID = $e->getTest()->getMetadata()->getParam();
        $this->currentCase = $this->api->getCase((int) $caseID['testCaseId'][0]);
        return $this->currentCase;
    }


    /**
     * Create "run" in testRails for current suit
     * @param TestEvent $e
     * @return Run
     */
    public function createRun(TestEvent $e): Run
    {
        $suiteId = $this->getSuiteID($e)->getId();
        $testName = $this->getSuiteID($e)->getName();
        return $this->api->addRun($suiteId, $testName, '');
    }

}
