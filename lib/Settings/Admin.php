<?php

namespace OCA\Collaboard\Settings;

use OCA\Collaboard\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;

use OCP\Settings\ISettings;

class Admin implements ISettings {

	private IConfig $config;
	private IInitialState $initialStateService;

	public function __construct(IConfig $config,
		IInitialState $initialStateService) {
		$this->config = $config;
		$this->initialStateService = $initialStateService;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$adminUrl = $this->config->getAppValue(Application::APP_ID, 'admin_api_url', Application::DEFAULT_COLLABOARD_API) ?: Application::DEFAULT_COLLABOARD_API;
		$adminDomainUrl = $this->config->getAppValue(Application::APP_ID, 'admin_domain_url', Application::DEFAULT_COLLABOARD_DOMAIN) ?: Application::DEFAULT_COLLABOARD_DOMAIN;

		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id');
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret');
		$usePopup = $this->config->getAppValue(Application::APP_ID, 'use_popup', '0') === '1';
		$overrideLinkClick = $this->config->getAppValue(Application::APP_ID, 'override_link_click', '0') === '1';

		$adminConfig = [
			'client_id' => $clientID,
			'client_secret' => $clientSecret,
			'use_popup' => $usePopup,
			'override_link_click' => $overrideLinkClick,

			'admin_api_url' => $adminUrl,
			'admin_domain_url' => $adminDomainUrl,
			'default_api_url' => Application::DEFAULT_COLLABOARD_API,
			'default_domain_url' => Application::DEFAULT_COLLABOARD_DOMAIN,
		];
		$this->initialStateService->provideInitialState('admin-config', $adminConfig);
		return new TemplateResponse(Application::APP_ID, 'adminSettings');
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 10;
	}
}
