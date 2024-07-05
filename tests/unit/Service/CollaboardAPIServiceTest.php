<?php

namespace OCA\Collaboard\Tests;

use DateTime;
use OCA\Collaboard\AppInfo\Application;
use OCA\Collaboard\Service\CollaboardAPIService;
use OCP\App\IAppManager;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CollaboardAPIServiceTest extends TestCase {

	/**
	 * @var LoggerInterface|MockObject
	 */
	private $logger;
	/**
	 * @var IL10N|MockObject
	 */
	private $l10n;
	/**
	 * @var IConfig|MockObject
	 */
	private $config;
	/**
	 * @var IClientService|MockObject
	 */
	private $clientService;
	/**
	 * @var IClient|MockObject
	 */
	private $client;
	/**
	 * @var IAppManager
	 */
	private $appManager;
	/**
	 * @var CollaboardAPIService
	 */
	private $service;

	/**
	 * @var IResponse|MockObject
	 */
	private $jsonResponse;

	/**
	 * @var IResponse|MockObject
	 */
	private $projectsResponse;
	/**
	 * @var IResponse|MockObject
	 */
	private $projectUsersResponse;

	/**
	 * @var IResponse|MockObject
	 */
	private $projectResponse;

	private $defaultOptions = [
		'headers' => [
			'User-Agent' => Application::INTEGRATION_USER_AGENT,
			'Authorization' => 'Bearer secret token',
			'Content-Type' => 'application/json',
		],
	];

	/**
	 * @throws Exception
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->logger = $this->createMock(LoggerInterface::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->config = $this->createMock(IConfig::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->client = $this->createMock(IClient::class);

		$this->clientService->expects($this->once())->method('newClient')->willReturn($this->client);

		$userValues = [];
		for ($i = 1; $i <= 3; $i++) {
			$user = 'user' . $i;
			$userValues = array_merge($userValues, [
				[$user, Application::APP_ID, 'refresh_token', '', 'secret refresh token'],
				[$user, Application::APP_ID, 'token_expires_at', '', (new Datetime())->getTimestamp() + 3600],
				[$user, Application::APP_ID, 'token', '', 'secret token'],
			]);
		}
		$this->config->method('getUserValue')->willReturnMap($userValues);

		$this->jsonResponse = $this->createMock(IResponse::class);
		$this->jsonResponse->method('getStatusCode')->willReturn(200);
		$this->jsonResponse->method('getBody')->willReturn('{"ErrorCode": 0}');

		$this->projectsResponse = $this->createMock(IResponse::class);
		$this->projectsResponse->method('getStatusCode')->willReturn(200);
		$this->projectsResponse->method('getBody')->willReturn('{"Results": [{ "Project": { "ProjectId": 1, "Description": "board1", "CreationDate": "2024-05-15T00:00:20.853", "LastUpdate": "2024-05-15T00:00:20.853" } }], "ErrorCode": 0}');

		$this->projectUsersResponse = $this->createMock(IResponse::class);
		$this->projectUsersResponse->method('getStatusCode')->willReturn(200);
		$this->projectUsersResponse->method('getBody')->willReturn('{"Results": [{ "ParticipationType": 1, "User": { "UserId": 1, "UserName": "user1", "PhotoUrl": "photoUrl" } }], "ErrorCode": 0}');

		$this->projectResponse = $this->createMock(IResponse::class);
		$this->projectResponse->method('getStatusCode')->willReturn(200);
		$this->projectResponse->method('getBody')->willReturn('{"ProjectId": 1, "ErrorCode": 0}');

		$this->service = new CollaboardAPIService(
			"integration_collaboard",
			$this->logger,
			$this->l10n,
			$this->config,
			$this->appManager,
			$this->clientService,
		);
	}

	public function testGetMyProjects() {
		$this->client->expects($this->exactly(2))->method('get')->willReturnMap([
			[Application::DEFAULT_COLLABOARD_API . '/public/api/public/v2.0/collaborationhub/projects/participating?pageSize=100&pageNumber=1', $this->defaultOptions, $this->projectsResponse],
			[Application::DEFAULT_COLLABOARD_API . '/public/api/public/v2.0/collaborationhub/projects/1/users?pageSize=100&pageNumber=1', $this->defaultOptions, $this->projectUsersResponse],
		]);

		$this->assertEquals([['Project' => [
			'ProjectId' => 1, 'Description' => 'board1', 'CreationDate' => '2024-05-15T00:00:20.853', 'LastUpdate' => '2024-05-15T00:00:20.853'
		], 'Owner' => ['UserId' => 1, 'UserName' => 'user1', 'PhotoUrl' => 'photoUrl'], 'name' => 'board1', 'id' => 1, 'created_at' => '2024-05-15T00:00:20.853', 'owned_by' => ['userName' => 'user1', 'photoUrl' => 'photoUrl'], 'updated_at' => '2024-05-15T00:00:20.853', 'trash' => false]], $this->service->getProjects('user2'));
	}

	public function testCreateProject() {
		$this->client->expects($this->exactly(1))->method('post')->willReturnMap([
			[Application::DEFAULT_COLLABOARD_API . '/public/api/public/v2.0/collaborationhub/projects', array_merge($this->defaultOptions, ['body' => json_encode(['Description' => 'board1'])]), $this->projectResponse],
		]);

		$this->assertEquals(['ProjectId' => 1, 'ErrorCode' => 0], $this->service->createProject('user2', 'board1'));
	}

	public function testDeleteBoard() {
		$this->client->expects($this->exactly(1))->method('delete')->willReturnMap([
			[Application::DEFAULT_COLLABOARD_API . '/public/api/public/v2.0/collaborationhub/projects/1', $this->defaultOptions, $this->jsonResponse],
		]);

		$this->assertEquals(['ErrorCode' => 0], $this->service->deleteProject('user1', 1));
	}
}
