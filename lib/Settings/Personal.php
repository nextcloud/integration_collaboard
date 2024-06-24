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

		// for OAuth
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id');
		// don't expose the client secret to users
		$clientSecret = ($this->config->getAppValue(Application::APP_ID, 'client_secret') !== '');

		$collaboardUserName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name');
		$collaboardUserDisplayName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_displayname');

		$adminUrl = $this->config->getAppValue(Application::APP_ID, 'admin_api_url', Application::DEFAULT_COLLABOARD_API) ?: Application::DEFAULT_COLLABOARD_API;
		$adminDomainUrl = $this->config->getAppValue(Application::APP_ID, 'admin_domain_url', Application::DEFAULT_COLLABOARD_DOMAIN) ?: Application::DEFAULT_COLLABOARD_DOMAIN;

		$userConfig = [
			// we consider the token is not valid until there is also a refresh token
			'token' => ($token && $refreshToken) ? 'dummyTokenContent' : '',
			'client_id' => $clientID,
			'client_secret' => $clientSecret,
			'user_name' => $collaboardUserName,
			'user_displayname' => $collaboardUserDisplayName,

			'admin_api_url' => $adminUrl,
			'admin_domain_url' => $adminDomainUrl,
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
