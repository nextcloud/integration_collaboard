<?php
/**
 * Nextcloud - Collaboard
 *
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2022
 */

namespace OCA\Collaboard\AppInfo;

use OCA\Collaboard\Listener\AddContentSecurityPolicyListener;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;
use OCP\Util;

class Application extends App implements IBootstrap {
	public const APP_ID = 'integration_collaboard';
	public const INTEGRATION_USER_AGENT = 'Nextcloud Collaboard integration';

	public const DEFAULT_COLLABOARD_API = 'https://api.collaboard.app';
	public const DEFAULT_COLLABOARD_DOMAIN = 'https://web.collaboard.app';
	public const COLLABOARD_APP_VER = '6.4.2';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(AddContentSecurityPolicyEvent::class, AddContentSecurityPolicyListener::class);
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(function (
			IInitialState $initialState,
			IConfig $config,
			?string $userId
		) {
			$overrideClick = $config->getAppValue(Application::APP_ID, 'override_link_click', '0') === '1';

			$initialState->provideInitialState('override_link_click', $overrideClick);
			Util::addScript(self::APP_ID, self::APP_ID . '-standalone');
		});
	}
}
