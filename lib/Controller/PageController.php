<?php
/**
 * Nextcloud - Collaboard integration
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2022
 */

namespace OCA\Collaboard\Controller;

use OCA\Collaboard\AppInfo\Application;
use OCA\Collaboard\Service\CollaboardAPIService;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IRequest;

use Psr\Log\LoggerInterface;

class PageController extends Controller {

	private IConfig $config;
	private IAppManager $appManager;
	private IInitialState $initialStateService;
	private LoggerInterface $logger;
	private CollaboardAPIService $collaboardAPIService;
	private ?string $userId;

	public function __construct(string               $appName,
		IRequest             $request,
		IConfig              $config,
		IAppManager          $appManager,
		IInitialState        $initialStateService,
		LoggerInterface      $logger,
		CollaboardAPIService $collaboardAPIService,
		?string              $userId) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->appManager = $appManager;
		$this->initialStateService = $initialStateService;
		$this->logger = $logger;
		$this->collaboardAPIService = $collaboardAPIService;
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return TemplateResponse
	 */
	public function index(): TemplateResponse {
		$token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');
		$collaboardUserName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name');
		$collaboardUserDisplayName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_displayname');

		$adminUrl = $this->config->getAppValue(Application::APP_ID, 'admin_instance_url', Application::DEFAULT_COLLABOARD_URL) ?: Application::DEFAULT_COLLABOARD_URL;
		$url = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', $adminUrl) ?: $adminUrl;

		$adminInviteUrl = $this->config->getAppValue(Application::APP_ID, 'admin_invite_url', Application::DEFAULT_COLLABOARD_INVITE_URL) ?: Application::DEFAULT_COLLABOARD_INVITE_URL;
		$inviteUrl = $this->config->getUserValue($this->userId, Application::APP_ID, 'invite_url', $adminInviteUrl) ?: $adminInviteUrl;

		$sfaMethod = $this->config->getUserValue($this->userId, Application::APP_ID, 'sfa_method', Application::DEFAULT_2FA_METHOD) ?: Application::DEFAULT_2FA_METHOD;

		$talkEnabled = $this->appManager->isEnabledForUser('spreed');

		$licensingInfo = $this->collaboardAPIService->getUserLicenseInfo($this->userId);

		$pageInitialState = [
			// we consider the token is not valid until there is also a refresh token
			'token' => $token ? 'dummyTokenContent' : '',
			'url' => $url,
			'user_name' => $collaboardUserName,
			'user_displayname' => $collaboardUserDisplayName,
			'sfa_method' => $sfaMethod,
			'licensing_info' => $licensingInfo,

			'talk_enabled' => $talkEnabled,
			'project_list' => [],
			'invite_url' => $inviteUrl,
		];

		if ($url !== '' && $token !== '') {
			$projects = $this->collaboardAPIService->getProjects($this->userId);
			if (isset($projects['error'])) {
				$pageInitialState['project_list_error'] = $projects['error'];
			} else {
				$pageInitialState['project_list'] = $projects;
			}
		}
		$this->initialStateService->provideInitialState('collaboard-state', $pageInitialState);
		return new TemplateResponse(Application::APP_ID, 'main', []);
	}
}
