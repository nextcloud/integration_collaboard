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
				return $this->loginWithSecondFactor($secondFactor);
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
			// $result['RefreshToken'],
			$result['ExpiresIn'],
			$result['AuthenticationMode']
		)) {
			// we can already store the user info
			// and the token even if it is maybe not definitive (if 2FA is required)
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_name', $result['User']['UserName']);
			$displayName = $result['User']['FirstName'] . ' ' . $result['User']['LastName'];
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_displayname', $displayName);
			$this->config->setUserValue($this->userId, Application::APP_ID, 'token', $result['AuthorizationToken']);

			// do we need 2FA?
			if ($result['AuthenticationMode'] === 3) {
				$this->config->setUserValue($this->userId, Application::APP_ID, 'token', $result['AuthorizationToken']);
				// check 2FA method
				$sfaMethod = $this->config->getUserValue($this->userId, Application::APP_ID, 'sfa_method', Application::DEFAULT_2FA_METHOD) ?: Application::DEFAULT_2FA_METHOD;
				$sendOtpResult = null;
				if ($sfaMethod !== 'otp') {
					$sendOtpResult = $this->collaboardAPIService->sendUserOtpToken($this->userId, $result['User']['UserName'], $sfaMethod);
				}
				return new DataResponse([
					'user_name' => '',
					'user_displayname' => '',
					'error' => 'login response says 2fa is required (AuthenticationMode === ' . $result['AuthenticationMode'] . ')',
					'two_factor_required' => true,
					'send_user_otp_token_result' => $sendOtpResult,
				]);
			}
			if ($result['AuthenticationMode'] !== 1 && $result['AuthenticationMode'] !== 2) {
				return new DataResponse([
					'user_name' => '',
					'user_displayname' => '',
					'error' => 'this authentication method is not yet implemented (AuthenticationMode === ' . $result['AuthenticationMode'] . ')',
					'two_factor_required' => false,
					'authenticate_response' => $result,
				]);
			}

			if (!isset($result['RefreshToken'])) {
				return new DataResponse([
					'user_name' => '',
					'user_displayname' => '',
					'error' => 'something went wrong, no refresh token in login response (AuthenticationMode === ' . $result['AuthenticationMode'] . ')',
					'two_factor_required' => false,
				]);
			}

			$this->config->setUserValue($this->userId, Application::APP_ID, 'refresh_token', $result['RefreshToken']);

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

	private function loginWithSecondFactor($secondFactor): DataResponse {
		// this request will use the token we obtained and stored with the login/password auth request
		$result = $this->collaboardAPIService->validate2FA($this->userId, $secondFactor);

		if (isset(
			$result['AuthorizationToken'],
			$result['RefreshToken'],
			$result['ExpiresIn'],
			$result['AuthenticationMode']
		)) {
			$this->config->setUserValue($this->userId, Application::APP_ID, 'token', $result['AuthorizationToken']);
			$this->config->setUserValue($this->userId, Application::APP_ID, 'refresh_token', $result['RefreshToken']);

			$nowTs = (new DateTime())->getTimestamp();
			$tokenExpireAt = $nowTs + (int)($result['ExpiresIn']);
			$this->config->setUserValue($this->userId, Application::APP_ID, 'token_expires_at', (string)$tokenExpireAt);

			// the user info was obtained on the login/password request
			$collaboardUserName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name');
			$collaboardUserDisplayName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_displayname');

			return new DataResponse([
				'user_name' => $collaboardUserName,
				'user_displayname' => $collaboardUserDisplayName,
			]);
		}
		return new DataResponse([
			'user_name' => '',
			'user_displayname' => '',
			'error' => 'invalid second factor',
			'details' => $result,
		]);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $login
	 * @return DataResponse
	 */
	public function sendUserOtpPasswordCode(string $login): DataResponse {
		$result = $this->collaboardAPIService->sendUserOtpToken($this->userId, $login);
		return new DataResponse([
			'send_user_otp_token_result' => $result,
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
