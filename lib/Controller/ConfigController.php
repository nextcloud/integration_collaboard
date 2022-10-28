<?php
/**
 * Nextcloud - Collaboard
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Collaboard\Controller;

use DateTime;
use OCP\IConfig;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\Collaboard\Service\CollaboardAPIService;
use OCA\Collaboard\AppInfo\Application;
use OCP\PreConditionNotMetException;

class ConfigController extends Controller {

	private IConfig $config;
	private CollaboardAPIService $collaboardAPIService;
	private ?string $userId;

	public function __construct(string               $appName,
								IRequest             $request,
								IConfig              $config,
								CollaboardAPIService $collaboardAPIService,
								?string              $userId) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->collaboardAPIService = $collaboardAPIService;
		$this->userId = $userId;
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
		if (isset($values['url'], $values['login'], $values['password'])) {
			$this->config->setUserValue($this->userId, Application::APP_ID, 'url', $values['url']);
			$secondFactor = ($values['two_factor_code'] ?? null) ?: null;
			if ($secondFactor) {
				return $this->loginWithSecondFactor($values['login'], $values['password'], $secondFactor);
			} else {
				return $this->loginWithCredentials($values['login'], $values['password']);
			}
		}

		foreach ($values as $key => $value) {
			$this->config->setUserValue($this->userId, Application::APP_ID, $key, $value);
		}
		$result = [];

		if (isset($values['token'])) {
			if ($values['token'] === '') {
				$this->config->deleteUserValue($this->userId, Application::APP_ID, 'user_name');
				$this->config->deleteUserValue($this->userId, Application::APP_ID, 'user_displayname');
				$this->config->deleteUserValue($this->userId, Application::APP_ID, 'token');
				$result['user_name'] = '';
				$result['user_displayname'] = '';
			}
			// if the token is set, cleanup refresh token and expiration date
			$this->config->deleteUserValue($this->userId, Application::APP_ID, 'refresh_token');
			$this->config->deleteUserValue($this->userId, Application::APP_ID, 'token_expires_at');
		}
		return new DataResponse($result);
	}

	/**
	 * @param string $login
	 * @param string $password
	 * @return DataResponse
	 * @throws PreConditionNotMetException
	 */
	private function loginWithCredentials(string $login, string $password): DataResponse {
		// cleanup refresh token and expiration date on classic login
		$this->config->deleteUserValue($this->userId, Application::APP_ID, 'refresh_token');
		$this->config->deleteUserValue($this->userId, Application::APP_ID, 'token_expires_at');

		$result = $this->collaboardAPIService->login($this->userId, $login, $password);
		if (isset(
			$result['User'],
			$result['User']['UserName'],
			$result['User']['FirstName'],
			$result['User']['LastName'],
			$result['AuthorizationToken'],
			$result['RefreshToken'],
			$result['ExpiresIn'],
			$result['AuthenticationMode']
		)) {
			// do we need 2FA?
			if ($result['AuthenticationMode'] !== 1) {
				return new DataResponse([
					'user_name' => '',
					'user_displayname' => '',
					'error' => 'login response says 2fa is required (AuthenticationMode === ' . $result['AuthenticationMode'] . ')',
					'two_factor_required' => true,
				]);
			}

			$this->config->setUserValue($this->userId, Application::APP_ID, 'token', $result['AuthorizationToken']);
			$this->config->setUserValue($this->userId, Application::APP_ID, 'refresh_token', $result['RefreshToken']);
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_name', $result['User']['UserName']);
			$displayName = $result['User']['FirstName'] . ' ' . $result['User']['LastName'];
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_displayname', $displayName);

			$nowTs = (new DateTime())->getTimestamp();
			$tokenExpireAt = $nowTs + (int)($result['ExpiresIn']);
			$this->config->setUserValue($this->userId, Application::APP_ID, 'token_expires_at', (string)$tokenExpireAt);

			return new DataResponse([
				'user_name' => $result['User']['UserName'],
				'user_displayname' => $displayName,
			]);
		}
		return new DataResponse([
			'user_name' => '',
			'user_displayname' => '',
			'error' => 'invalid login/password',
			'details' => $result,
		]);
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
}
