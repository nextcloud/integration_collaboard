<?php
/**
 * Nextcloud - Collaboard
 *
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2022
 */

namespace OCA\Collaboard\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;

/**
 * Class Application
 *
 * @package OCA\Collaboard\AppInfo
 */
class Application extends App implements IBootstrap {
	public const APP_ID = 'integration_collaboard';
	public const INTEGRATION_USER_AGENT = 'Nextcloud Collaboard integration';

	public const DEFAULT_COLLABOARD_URL = 'https://api.collaboard.app';
	public const DEFAULT_2FA_METHOD = 'email';
	public const COLLABOARD_APP_VER = '6.1.264';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
	}

	public function boot(IBootContext $context): void {
	}
}

