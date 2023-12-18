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

	public function __construct(IConfig       $config,
		IInitialState $initialStateService) {
		$this->config = $config;
		$this->initialStateService = $initialStateService;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$adminUrl = $this->config->getAppValue(Application::APP_ID, 'admin_instance_url', Application::DEFAULT_COLLABOARD_URL) ?: Application::DEFAULT_COLLABOARD_URL;
		$adminInviteUrl = $this->config->getAppValue(Application::APP_ID, 'admin_invite_url', Application::DEFAULT_COLLABOARD_INVITE_URL) ?: Application::DEFAULT_COLLABOARD_INVITE_URL;

		$adminConfig = [
			'admin_instance_url' => $adminUrl,
			'admin_invite_url' => $adminInviteUrl,
			'default_instance_url' => Application::DEFAULT_COLLABOARD_URL,
			'default_invite_url' => Application::DEFAULT_COLLABOARD_INVITE_URL,
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
