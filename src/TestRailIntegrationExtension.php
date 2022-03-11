<?php

namespace Codeception\TestRail;

use Codeception\Event\FailEvent;
use Codeception\Event\SuiteEvent;
use Codeception\Event\TestEvent;
use Codeception\Events;
use Codeception\Extension;
use Codeception\TestRail\Entities\Milestone;
use Codeception\TestRail\Entities\Plan;
use Codeception\TestRail\Entities\Run;
use Codeception\TestRail\Entities\Section;
use Codeception\TestRail\Entities\Suite;
use Codeception\TestRail\Entities\TestCase;
use GuzzleHttp\Client;
use function PHPUnit\Framework\assertInstanceOf;
use function PHPUnit\Framework\assertTrue;

/**
 * Class TestRailIntegrationExtension
 * Integration with testRails
 * To work custom parameter "file_path" for test case needed in testRail
 */
class TestRailIntegrationExtension extends Extension
{
    /**
     * @const string
     */
    private const MILESTONE_PREFIX = '[Auto] ';

    /**
     * @const string
     */
    private const PLAN_PREFIX = '[Auto] Regression Plan ';

    /**
     * @const string
     */
    private const RUN_PREFIX = 'Run suite ';

    /**
     * @const string
     */
    private const MILESTONE_DESCRIPTION = 'Created automatically by extension';

    /**
     * @const string
     */
    private const PLAN_DESCRIPTION_PREFIX = 'Automatically created ';

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
        Events::SUITE_BEFORE => 'beforeSuite',
        Events::TEST_BEFORE => 'beforeTest',
        Events::TEST_FAIL => 'testFailed',
        Events::TEST_ERROR => 'testFailed',
        Events::TEST_SUCCESS => 'testPassed',
        Events::TEST_END => 'afterTest'
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
     * Before suit event - prepare all needed entities
     *
     * @param SuiteEvent $e
     */
    public function beforeSuite(SuiteEvent $e): void
    {
        if (!$this->isExtensionNeeded()) {
            return;
        }
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
     * @param TestEvent $e
     */
    public function afterTest(TestEvent $e): void
    {
//        if (!$this->isExtensionNeeded()) {
//            return;
//        }
//
//        $runID = $this->createRun($e)->getId();
//        $this->api->closeRun($runID);
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

//        $results = $this->currentRun->getId();
//        $caseId = $this->currentCase->getId();

//        $this->ensureTestCaseExists($e);
//        $this->setTestResult($results, $caseId, false, $e->getTime(), $message);
    }


    /**
     * @param TestEvent $e
     */
    public function testPassed(TestEvent $e): void
    {
        if (!$this->isExtensionNeeded()) {
            return;
        }


        if (in_array($this->getSuiteID($e)->getName(), $this->ensureTestRunExists($e)) == false){
            var_dump($this->ensureTestRunExists($e));
//            $this->currentRun = $this->createRun($e);
        }

//        $results = $this->currentRun->getId();
//        $caseId = $this->getCaseID($e)->getId();
//        $this->setTestResult($results, $caseId, true, $e->getTime(), '');
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
     * @return array
     */
    private function ensureTestRunExists(TestEvent $e): array
    {
        $runs = $this->api->getRuns();

        if (in_array($this->getSuiteID($e)->getName(), array_keys($runs)) == false){
            $this->currentRun = $this->createRun($e);
        }

        foreach ($runs as $run){
            $name = $run->getName();
            $is_completed = $run->isCompleted();

            if($name == $this->getSuiteID($e)->getName() && $is_completed == false){
                continue;
            }
            elseif($name == $this->getSuiteID($e)->getName() && $is_completed == true){
                unset($runs[$name]);
            }
        }
        return array_keys($runs);
    }

//    /**
//     * @param string $fileName
//     * @param TestEvent $e
//     * @return string
//     */
//    private function getRelativeFileName(string $fileName, TestEvent $e): string
//    {
//        $folders = explode('/', $fileName);
//
//        $resultPath = [];
//
//        foreach ($folders as $folder) {
//            $resultPath[] = $folder;
//
//            if (ucfirst($folder) === $this->getSuiteID($e)->getName()) {
//                $resultPath = [];
//            }
//        }
//
//        return DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $resultPath);
//    }

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

//    /**
//     * @param TestEvent $e
//     * @return string
//     */
//    private function getFullTestName(TestEvent $e): string
//    {
//        $testName = $e->getTest()->getMetadata()->getName();
//
//        $fileName = $e->getTest()->getMetadata()->getFilename();
//
//        $this->testRunsCount[$fileName][$testName] = $this->testRunsCount[$fileName][$testName] ?? 0;
//        $this->testRunsCount[$fileName][$testName]++;
//
//        if (!isset($this->filesContent[$fileName])) {
//            if (is_readable($e->getTest()->getMetadata()->getFilename())) {
//                $file = file($e->getTest()->getMetadata()->getFilename());
//                $this->filesContent[$fileName] = $file;
//            } else {
//                $this->filesContent[$fileName] = [];
//            }
//        }
//
//        $bracketsCount = 0;
//        $functionFound = false;
//        $functionStarted = false;
//        $testDescription = '';
//
//        // trying to find $->wantTo or $I->wantToTest to get test description
//        foreach ($this->filesContent[$fileName] as $item) {
//            if (strpos($item, 'function ' . $testName) !== false) {
//                $functionFound = true;
//            }
//
//            if ($functionStarted && strpos($item, '{') !== false) {
//                $bracketsCount++;
//            }
//
//            if ($functionFound && strpos($item, '{') !== false) {
//                $functionStarted = true;
//            }
//
//            if ($functionStarted && strpos($item, '}') !== false) {
//                $bracketsCount--;
//            }
//
//            if ($functionStarted && $bracketsCount === -1) {
//                // function ends
//                break;
//            }
//
//            if ($functionStarted && strpos($item, '->wantTo') !== false) {
//                preg_match('/->wantTo(Test|)\((\"|\')(.*)(\"|\')\)/isU', $item, $matches);
//                $testDescription = $matches[3] ?? '';
//            }
//        }
//        $title = $testDescription ? ($testName . ': ' . $testDescription) : $testName;
//        $title .= ' #' . $this->testRunsCount[$fileName][$testName];
//
//        return $title;
//    }

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

    private function getRun(TestEvent $e): array
    {
        return $this->api->getRun(84971);
    }

    /**
     * @param TestEvent $e
     * @return Run
     */
    public function createRun(TestEvent $e): Run
    {
        $suiteId = $this->getSuiteID($e)->getId();
        $testName = $this->getSuiteID($e)->getName();
        return $this->api->addRun($suiteId, $testName, '');
    }

    /**
     * Create new case
     * @param int $suitId
     * @param string $testName
     * @param string $filename
     * @return TestCase
     */
    private function createCase(int $suitId, string $testName, string $filename): TestCase
    {

//        $folders = explode('/', $filename);
//        $resultPath = [];
//
//        foreach ($folders as $folder) {
//            $resultPath[] = $folder;
//
//            if (ucfirst($folder) === $this->currentSuite->getName()) {
//                $resultPath = [];
//            }
//        }
//
//        $sections = $this->api->getSections($suitId);
//
//        /** @var Section[] $sectionsByDescription */
//        $sectionsByDescription = [];
//
//        foreach ($sections as $section) {
//            $sectionsByDescription[$section->getDescription()] = $section;
//        }
//
//        $pathsMapped = [];
//
//        $pathToFolder = '';
//
//        foreach ($resultPath as $index => $folder) {
//            $folderFound = false;
//            $pathToFolder = '';
//
//            foreach ($resultPath as $folderName) {
//                if (!$folderFound) {
//                    $pathToFolder .= '/' . $folderName;
//                }
//
//                if ($folder === $folderName) {
//                    $folderFound = true;
//                }
//            }
//
//            $pathsMapped[$folder] = $pathToFolder;
//
//            $previousSectionId = 0;
//
//            if (!isset($sectionsByDescription[$pathToFolder])) {
//                if ($index) {
//                    $previousPath = $pathsMapped[$resultPath[$index - 1]];
//                    $previousSectionId = $sectionsByDescription[$previousPath]->getId() ?? 0;
//                }
//                $sectionsByDescription[$pathToFolder] = $this->api->createSection(
//                    $suitId,
//                    $folder,
//                    $pathToFolder,
//                    $index ? $previousSectionId : 0
//                );
//            }
//        }
//
//        $sectionId = $sectionsByDescription[$pathToFolder]->getId();
//
//        return $this->api->addCase($sectionId, $testName, $pathToFolder);
    }

//    /**
//     * Check if plan created and create if not
//     * Sets $this->>currentPlan for suite
//     * @param SuiteEvent $e
//     * @return void
//     */
//    private function initializeCurrentPlan(SuiteEvent $e): void
//    {
//        $plans = $this->api->getPlans();
//
//        $currentPlanNamePrefix = self::PLAN_PREFIX . '[' . $e->getSuite()->getBaseName() . '] ' . $this->version;
//
//        $maxPostfix = 0;
//
//        foreach ($plans as $plan) {
//            if (strpos($plan->getName(), $currentPlanNamePrefix) === 0) {
//                $postfix = explode('#', $plan->getName());
//                $postfix = array_pop($postfix);
//                $maxPostfix = max($postfix, $maxPostfix);
//            }
//        }
//        $maxPostfix++;
//
//        $planName = $currentPlanNamePrefix . ', run #' . $maxPostfix;
//
//        $this->currentPlan = $this->api->addPlan(
//            $planName,
//            self::PLAN_DESCRIPTION_PREFIX . date('Y-m-d H:i:s'),
//            $this->currentMilestone->getId()
//        );
//    }
//
//    /**
//     * Check if suite created and create if not
//     * Sets $this->>currentSuite and empty $this->currentSuiteExistingCases for current suite
//     * Empty files content
//     * @param SuiteEvent $e
//     * @return void
//     */
//    private function initializeCurrentSuite(SuiteEvent $e): void
//    {
//        $suites = $this->api->getSuites();
//
//        foreach ($suites as $suite) {
//            if ($suite->getName() === ucfirst($e->getSuite()->getBaseName())) {
//                $this->currentSuite = $suite;
//            }
//        }
//
//        if (!$this->currentSuite) {
//            $this->currentSuite = $this->api->addSuite(
//                ucfirst($e->getSuite()->getBaseName()),
//                ucfirst($e->getSuite()->getBaseName())
//            );
//        }
//
//        $this->currentSuiteExistingCases = [];
//        $this->filesContent = [];
//    }
//
    /**
     * Create "run" in testRails for current suit
     */
    private function initializeCurrentRun(): void
    {
//        $this->api->addRun($this->currentRun->getId(), )
//        $this->currentRun = $this->api->addPlanEntry(
//            self::RUN_PREFIX . $this->currentSuite->getName(),
//            '',
//            $this->currentMilestone->getId(),
//            $this->currentSuite->getId(),
//            $this->currentPlan->getId()
//        );
    }
//
//    /**
//     * Check if milestone created and create if not
//     * Sets $this->>currentMilestone for suite
//     * @return void
//     */
//    private function initializeCurrentMilestone(): void
//    {
//        if (!$this->currentMilestone) {
//            $milestones = $this->api->getMilestones();
//
//            $currentMilestoneName = self::MILESTONE_PREFIX . $this->version;
//
//            foreach ($milestones as $milestone) {
//                if ($milestone->getName() === $currentMilestoneName) {
//                    $this->currentMilestone = $milestone;
//                }
//            }
//
//            if (!$this->currentMilestone) {
//                $milestone = $this->api->addMilestone($currentMilestoneName, self::MILESTONE_DESCRIPTION);
//                $this->currentMilestone = $milestone;
//            }
//        }
//    }
}
