<?php

namespace Codeception\TestRail;


use Codeception\TestRail\Entities\Run;
use Codeception\TestRail\Entities\Suite;
use Codeception\TestRail\Entities\TestCase;
use GuzzleHttp\Client;
use InvalidArgumentException;

class Api
{
    /**
     * @var Client
     */
    private $httpClient;

    /**
     * @var string
     */
    private $apiUrl;

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

    /**
     * @var int
     */
    private $projectId;

    /**
     * Api constructor.
     * @param Client $httpClient
     * @param string $apiUrl
     * @param string $username
     * @param string $password
     * @param int $projectId
     */
    public function __construct(
        Client $httpClient,
        string $apiUrl,
        string $username,
        string $password,
        int $projectId
    ) {
        $this->httpClient = $httpClient;
        $this->apiUrl = $apiUrl;
        $this->username = $username;
        $this->password = $password;
        $this->projectId = $projectId;
    }

    /**
     * @param int $caseId
     * @return TestCase
     */
    public function getCase(int $caseId): TestCase
    {
        $data = $this->getResponse(
            '/api/v2/get_case/' . $caseId,
            'get'
        );

        return new TestCase(
            $data['id'],
            $data['title'],
            $data['section_id'],
            $data['template_id'],
            $data['type_id'],
            $data['priority_id'],
            $data['milestone_id'],
            $data['created_by'],
            $data['created_on'],
            $data['estimate'],
            $data['estimate_forecast'],
            $data['suite_id']
        );
    }

    /**
     * @param int $suiteId
     * @return array
     */
    public function getCases(int $suiteId): array
    {
        $data = $this->getResponse(
            '/api/v2/get_cases/' .  $this->projectId,
            'get',
            [
                'suite_id' => $suiteId
            ]
        );

        $cases = [];

        foreach ($data as $case){
            $cases[$case['id']] = new TestCase(
                $case['id'],
                $case['title'],
                $case['section_id'],
                $case['template_id'],
                $case['type_id'],
                $case['priority_id'],
                $case['milestone_id'],
                $case['created_by'],
                $case['created_on'],
                $case['estimate'],
                $case['estimate_forecast'],
                $case['suite_id']
            );
        }

        return $cases;
    }

    /**
     * @param int $suiteId
     * @param string $name
     * @param string $description
     * @return Run
     */
    public function addRun(int $suiteId, string $name, string $description): Run
    {
        $runs =  $this->getResponse(
            '/api/v2/add_run/' .  $this->projectId,
            'post',
            [
                'suite_id' => $suiteId,
                'name' => $name,
                'description' => $description,
                'include_all' => true
            ]
        );

        return new Run(
            $runs['id'],
            $runs['suite_id'],
            $runs['name'],
            $runs['description'],
            $runs['milestone_id'],
            $runs['assignedto_id'],
            $runs['include_all'],
            $runs['is_completed'],
            $runs['completed_on'],
            $runs['passed_count'],
            $runs['blocked_count'],
            $runs['untested_count'],
            $runs['retest_count'],
            $runs['failed_count'],
            $runs['project_id'],
            $runs['plan_id'],
            $runs['created_on'],
            $runs['created_by'],
            $runs['url']
        );
    }

    /**
     * @return array
     */
    public function getRuns(): array
    {

        $data = $this->getResponse(
            '/api/v2/get_runs/' .  $this->projectId . '&is_completed=0',
            'get'
        );

        $runs = [];

        foreach ($data as $run){

            $runs[$run['name']] = new Run(
                $run['id'],
                $run['suite_id'],
                $run['name'],
                $run['description'],
                $run['milestone_id'],
                $run['assignedto_id'],
                $run['include_all'],
                $run['is_completed'],
                $run['completed_on'],
                $run['passed_count'],
                $run['blocked_count'],
                $run['untested_count'],
                $run['retest_count'],
                $run['failed_count'],
                $run['project_id'],
                $run['plan_id'],
                $run['created_on'],
                $run['created_by'],
                $run['url']
            );

        }
        return $runs;
    }

    /**
     * @param int $run_id
     * @return Run
     */
    public function closeRun(int $run_id): Run
    {
        $data =  $this->getResponse(
            '/api/v2/close_run/' .  $run_id,
            'post'
        );

        return new Run(
            $data['id'],
            $data['suite_id'],
            $data['name'],
            $data['description'],
            $data['milestone_id'],
            $data['assignedto_id'],
            $data['include_all'],
            $data['is_completed'],
            $data['completed_on'],
            $data['passed_count'],
            $data['blocked_count'],
            $data['untested_count'],
            $data['retest_count'],
            $data['failed_count'],
            $data['project_id'],
            $data['plan_id'],
            $data['created_on'],
            $data['created_by'],
            $data['url']
        );
    }

    /**
     * @param int $runId
     * @param int $caseId
     * @param int $statusId
     * @param string $comment
     * @param string $version
     * @param string $elapsed
     */
    public function setTestResult(
        int $runId,
        int $caseId,
        int $statusId,
        string $comment,
        string $version,
        string $elapsed
    ): void {
        $this->getResponse('/api/v2/add_result_for_case/' . $runId . '/' . $caseId,
            'post',
            [
                'status_id' => $statusId,
                'comment' => $comment,
                'version' => $version,
                'elapsed' => $elapsed,
            ]
        );
    }

    /**
     * @param int $suiteId
     * @return Suite
     */
    public function getSuite(int $suiteId): Suite
    {
        $data = $this->getResponse('/api/v2/get_suite/' . $suiteId);

        return new Suite(
            $data['id'],
            $data['name'],
            $data['description'],
            $data['project_id'],
            $data['is_master'],
            $data['is_baseline'],
            $data['is_completed'],
            $data['completed_on'],
            $data['url']);
    }

    /**
     * @param string $path
     * @param string $method
     * @param array $params
     * @return array
     */
    private function getResponse(string $path, string $method = 'get', array $params = []): array
    {
        $uri = $this->apiUrl . $path;
        $params['project_id'] = $this->projectId;
        $parameters['headers']['Content-Type'] = 'application/json';
        $parameters['auth'] = [$this->username, $this->password];

        switch ($method) {
            case 'get':
                $uri .= '&' . http_build_query($params);
                $response = $this->httpClient->get($uri, $parameters);
                break;
            case 'post':
                $parameters['json'] = $params;
                $response = $this->httpClient->post($uri, $parameters);
                break;
            default:
                throw new InvalidArgumentException(sprintf('%s method isn\'t supported', $method));
        }

        $response = json_decode($response->getBody()->getContents(), true);

        return $response;
    }
}
