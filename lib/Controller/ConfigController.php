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
use OCP\IConfig;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use Psr\Log\LoggerInterface;

use OCA\Collaboard\Service\CollaboardAPIService;
use OCA\Collaboard\AppInfo\Application;
use OCP\PreConditionNotMetException;

class ConfigController extends Controller
{

	private IConfig $config;
	private CollaboardAPIService $collaboardAPIService;
	private ?string $userId;
	private LoggerInterface $logger;

	public function __construct(
		string $appName,
		IRequest $request,
		IConfig $config,
		CollaboardAPIService $collaboardAPIService,
		?string $userId,
		LoggerInterface $logger
	) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->collaboardAPIService = $collaboardAPIService;
		$this->userId = $userId;
		$this->logger = $logger;
	}

	/**
	 * set config values
	 * @NoAdminRequired
	 *
	 * @param array $values
	 * @return DataResponse
	 * @throws PreConditionNotMetException
	 */
	public function setConfig(array $values): DataResponse
	{

		$result = [];
		if (isset($values['url'], $values['login'], $values['password'])) {
			$this->config->setUserValue($this->userId, Application::APP_ID, 'url', $values['url']);
			$secondFactor = ($values['two_factor_code'] ?? null) ?: null;

			$this->config->deleteUserValue($this->userId, Application::APP_ID, 'refresh_token');
			$this->config->deleteUserValue($this->userId, Application::APP_ID, 'token_expires_at');

			$result = $this->collaboardAPIService->login($this->userId, $values['login'], $values['password'], $secondFactor);
		}

		foreach ($values as $key => $value) {
			// Do not store sensitive data
			if (in_array($key, ['password', 'two_factor_code'])) {
				continue;
			}

			$this->config->setUserValue($this->userId, Application::APP_ID, $key, $value);
		}

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

		$this->logger->warning('Result: ' . print_r($result, true));
		return new DataResponse($result);
	}
	/*
	 * @NoAdminRequired
	 * @param string $login
	 * @return DataResponse
	 */
	public function getAuthenticationMode(string $login): DataResponse
	{
		$result = $this->collaboardAPIService->getAuthenticationMode($this->userId, $login);
		return new DataResponse($result);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $login
	 * @return DataResponse
	 */
	public function sendUserOtpPasswordCode(string $login): DataResponse
	{
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
	public function setAdminConfig(array $values): DataResponse
	{
		foreach ($values as $key => $value) {
			$this->config->setAppValue(Application::APP_ID, $key, $value);
		}
		return new DataResponse(1);
	}
}
