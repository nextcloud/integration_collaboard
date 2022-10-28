<?php
namespace OCA\Collaboard\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Settings\ISettings;

use OCA\Collaboard\AppInfo\Application;

class Personal implements ISettings {

	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var IInitialState
	 */
	private $initialStateService;
	/**
	 * @var string|null
	 */
	private $userId;

	public function __construct(IConfig $config,
								IInitialState $initialStateService,
								?string $userId) {
		$this->config = $config;
		$this->initialStateService = $initialStateService;
		$this->userId = $userId;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');
		$collaboardUserName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name');
		$collaboardUserDisplayName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_displayname');
		$adminUrl = $this->config->getAppValue(Application::APP_ID, 'admin_instance_url', Application::DEFAULT_COLLABOARD_URL) ?: Application::DEFAULT_COLLABOARD_URL;
		$url = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', $adminUrl) ?: $adminUrl;

		$userConfig = [
			'token' => $token ? 'dummyTokenContent' : '',
			'url' => $url,
			'user_name' => $collaboardUserName,
			'user_displayname' => $collaboardUserDisplayName,
		];
		$this->initialStateService->provideInitialState('user-config', $userConfig);
		return new TemplateResponse(Application::APP_ID, 'personalSettings');
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 10;
	}
}
