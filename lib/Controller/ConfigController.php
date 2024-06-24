<?php
/**
 * Nextcloud - Collaboard
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @author Sami Finnilä <sami.finnila@gmail.com>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Collaboard\Controller;

use DateTime;
use OCA\Collaboard\AppInfo\Application;
use OCA\Collaboard\Service\CollaboardAPIService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\PreConditionNotMetException;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

class ConfigController extends Controller {

	private IConfig $config;
	private CollaboardAPIService $collaboardAPIService;
	private ?string $userId;
	private LoggerInterface $logger;

	public function __construct(
		string $appName,
		IRequest $request,
		IConfig $config,
		private IURLGenerator $urlGenerator,
		private IL10N $l,
		CollaboardAPIService $collaboardAPIService,
		?string $userId,
		LoggerInterface $logger
	) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->l = $l;
		$this->collaboardAPIService = $collaboardAPIService;
		$this->userId = $userId;
		$this->logger = $logger;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function isUserConnected(): DataResponse {
		$token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');

		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id');
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret');
		$oauthPossible = $clientID !== '' && $clientSecret !== '';
		$usePopup = $this->config->getAppValue(Application::APP_ID, 'use_popup', '0');

		return new DataResponse([
			'connected' => $token !== '',
			'oauth_possible' => $oauthPossible,
			'use_popup' => ($usePopup === '1'),
			'client_id' => $clientID,
		]);
	}

	/**
	 * set config values
	 * @NoAdminRequired
	 *
	 * @param array $values
	 * @return DataResponse
	 * @throws PreConditionNotMetException
	 */
	public function setConfig(array $values): DataResponse {
		// revoke the token
		if (isset($values['token']) && $values['token'] === '') {
			$this->collaboardAPIService->revokeToken($this->userId);
		}

		foreach ($values as $key => $value) {
			$this->config->setUserValue($this->userId, Application::APP_ID, $key, $value);
		}
		$result = [];

		if (isset($values['token'])) {
			if ($values['token'] && $values['token'] !== '') {
				$result = $this->storeUserInfo();
			} else {
				$this->config->deleteUserValue($this->userId, Application::APP_ID, 'token');
				$this->config->deleteUserValue($this->userId, Application::APP_ID, 'user_id');
				$this->config->deleteUserValue($this->userId, Application::APP_ID, 'user_name');
				$this->config->deleteUserValue($this->userId, Application::APP_ID, 'user_displayname');
				$result['user_id'] = '';
				$result['user_name'] = '';
			}
			// if the token is set, cleanup refresh token and expiration date
			$this->config->deleteUserValue($this->userId, Application::APP_ID, 'refresh_token');
			$this->config->deleteUserValue($this->userId, Application::APP_ID, 'token_expires_at');
		}

		$this->logger->debug('setConfig' , [
				'app' => Application::APP_ID,
				'values' => $values,
				'result' => $result,
			]);

		return new DataResponse($result);
	}

	/**
	 * set admin config values
	 *
	 * @param array $values
	 * @return DataResponse
	 */
	public function setAdminConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			$this->config->setAppValue(Application::APP_ID, $key, $value);
		}
		return new DataResponse(1);
	}

	/**
	 * receive oauth code and get oauth access token
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $code
	 * @param string $state
	 * @return RedirectResponse
	 */
	public function oauthRedirect(string $code = '', string $state = ''): RedirectResponse {
		$configState = $this->config->getUserValue($this->userId, Application::APP_ID, 'oauth_state');
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id');
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret');

		$this->logger->debug('oauthRedirect' , [
			'code' => $code,
			'clientID' => $clientID,
			'clientSecret' => $clientSecret,
		]);

		// anyway, reset state
		$this->config->deleteUserValue($this->userId, Application::APP_ID, 'oauth_state');

		if ($clientID && $clientSecret) {
			$redirect_uri = $this->config->getUserValue($this->userId, Application::APP_ID, 'redirect_uri');
			$result = $this->collaboardAPIService->requestOAuthAccessToken([
				'client_id' => $clientID,
				'client_secret' => $clientSecret,
				'code' => $code,
				'redirect_uri' => $redirect_uri,
				'grant_type' => 'authorization_code'
			], 'POST');

			$this->logger->debug('requestOAuthAccessToken' , [
				'app' => Application::APP_ID,
				'result' => $result,
			]);

			if (isset($result['access_token'])) {
				$accessToken = $result['access_token'];
				$refreshToken = $result['refresh_token'] ?? '';
				if (isset($result['expires_in'])) {
					$nowTs = (new Datetime())->getTimestamp();
					$expiresAt = $nowTs + (int) $result['expires_in'];
					$this->config->setUserValue($this->userId, Application::APP_ID, 'token_expires_at', $expiresAt);
				}
				$this->config->setUserValue($this->userId, Application::APP_ID, 'token', $accessToken);
				$this->config->setUserValue($this->userId, Application::APP_ID, 'refresh_token', $refreshToken);

				$userInfo = $this->storeUserInfo();
				$usePopup = $this->config->getAppValue(Application::APP_ID, 'use_popup', '0') === '1';
				if ($usePopup) {
					return new RedirectResponse(
						$this->urlGenerator->linkToRoute('integration_collaboard.config.popupSuccessPage', [
							'user_name' => $userInfo['user_name'] ?? '',
							'user_id' => $userInfo['user_id'] ?? '',
						])
					);
				} else {
					$oauthOrigin = $this->config->getUserValue($this->userId, Application::APP_ID, 'oauth_origin');
					$this->config->deleteUserValue($this->userId, Application::APP_ID, 'oauth_origin');
					if ($oauthOrigin === 'settings') {
						return new RedirectResponse(
							$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
							'?collaboardToken=success'
						);
					} elseif ($oauthOrigin === 'app') {
						return new RedirectResponse(
							$this->urlGenerator->linkToRoute(Application::APP_ID . '.page.index')
						);
					}
				}
			}
			$result = $this->l->t('Error getting OAuth access token. ' . $result['error']);
		} else {
			$result = $this->l->t('Error during OAuth exchanges');
		}
		return new RedirectResponse(
			$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
			'?collaboardToken=error&message=' . urlencode($result)
		);
	}

	/**
	 * @return string
	 */
	private function storeUserInfo(): array {
		$info = $this->collaboardAPIService->getUserInfo($this->userId);
		$this->logger->debug('storeUserInfo' , [
				'app' => Application::APP_ID,
				'info' => $info,
			]);

		if (isset($info["Result"])) {
			$result = $info["Result"];

			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_id', $result['UserId'] ?? '');
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_name', $result['UserName'] ?? '');
			$displayName = $result['FirstName'] . ' ' . $result['LastName'];
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_displayname', $displayName);

			return [
				'user_id' => $result['UserId'] ?? '',
				'user_name' => $result['UserName'] ?? '',
			];
		} else {
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_id', '');
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_name', '');
			return [
				'user_id' => '',
				'user_name' => '',
			];
		}
	}
}
