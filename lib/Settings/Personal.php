<?php

namespace OCA\Collaboard\Settings;

use OCA\Collaboard\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;

use OCP\Settings\ISettings;

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
		$refreshToken = $this->config->getUserValue($this->userId, Application::APP_ID, 'refresh_token');
		$collaboardUserName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name');
		$collaboardUserDisplayName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_displayname');
		$adminUrl = $this->config->getAppValue(Application::APP_ID, 'admin_instance_url', Application::DEFAULT_COLLABOARD_URL) ?: Application::DEFAULT_COLLABOARD_URL;
		$url = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', '') ?: '';
		$adminInviteUrl = $this->config->getAppValue(Application::APP_ID, 'admin_invite_url', Application::DEFAULT_COLLABOARD_INVITE_URL) ?: Application::DEFAULT_COLLABOARD_INVITE_URL;
		$inviteUrl = $this->config->getUserValue($this->userId, Application::APP_ID, 'invite_url', '') ?: '';

		$sfaMethod = $this->config->getUserValue($this->userId, Application::APP_ID, 'sfa_method', Application::DEFAULT_2FA_METHOD) ?: Application::DEFAULT_2FA_METHOD;

		$userConfig = [
			'token' => $token ? 'dummyTokenContent' : '',
			'url' => $url,
			'user_name' => $collaboardUserName,
			'user_displayname' => $collaboardUserDisplayName,
			'sfa_method' => $sfaMethod,
			// Also, provide the admin url to the frontend as a fall back in case the user has not set an url
			'admin_instance_url' => $adminUrl,
			'invite_url' => $inviteUrl,
			'admin_invite_url' => $adminInviteUrl,
		];
		$this->initialStateService->provideInitialState('collaboard-state', $userConfig);
		return new TemplateResponse(Application::APP_ID, 'personalSettings');
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 10;
	}
}
