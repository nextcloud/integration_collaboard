<?php
/**
 * Nextcloud - Collaboard
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2022
 */

namespace OCA\Collaboard\Service;

use Datetime;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use OCA\Collaboard\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\Http\Client\IClient;
use OCP\IConfig;
use OCP\IL10N;
use OCP\PreConditionNotMetException;
use Psr\Log\LoggerInterface;
use OCP\Http\Client\IClientService;
use Throwable;

class CollaboardAPIService {
	private LoggerInterface $logger;
	private IL10N $l10n;
	private IConfig $config;
	private IClient $client;
	private string $appVersion;

	/**
	 * Service to make requests to Collaboard API
	 */
	public function __construct (string $appName,
								LoggerInterface $logger,
								IL10N $l10n,
								IConfig $config,
								IAppManager $appManager,
								IClientService $clientService) {
		$this->client = $clientService->newClient();
		$this->appVersion = $appManager->getAppVersion(Application::APP_ID);
		$this->logger = $logger;
		$this->l10n = $l10n;
		$this->config = $config;
	}

	public function isUserConnected(string $userId): bool {
		$adminUrl = $this->config->getAppValue(Application::APP_ID, 'admin_instance_url', Application::DEFAULT_COLLABOARD_URL) ?: Application::DEFAULT_COLLABOARD_URL;
		$url = $this->config->getUserValue($userId, Application::APP_ID, 'url', $adminUrl) ?: $adminUrl;

		$userName = $this->config->getUserValue($userId, Application::APP_ID, 'user_name');
		$token = $this->config->getUserValue($userId, Application::APP_ID, 'token');
		$login = $this->config->getUserValue($userId, Application::APP_ID, 'login');
		$password = $this->config->getUserValue($userId, Application::APP_ID, 'password');
		return $url && $userName && $token && $login && $password;
	}

	/**
	 * @param string $userId
	 * @return array|string[]
	 * @throws Exception
	 */
	public function getUserInfo(string $userId): array {
		return $this->restRequest($userId, 'auth/api/Authorization/GetAuthenticatedUser');
	}

	/**
	 * @param string $userId
	 * @param string $endPoint
	 * @param array $params
	 * @param string $method
	 * @param bool $jsonResponse
	 * @return array|mixed|resource|string|string[]
	 * @throws Exception
	 */
	public function restRequest(string $userId, string $endPoint, array $params = [], string $method = 'GET',
								bool $jsonResponse = true): array {
		$tokenIsOk = $this->checkTokenExpiration($userId);
		if (!$tokenIsOk) {
			return ['error' => $this->l10n->t('Your Collaboard session has expired, please re-authenticate in your user settings.')];
		}
		$adminUrl = $this->config->getAppValue(Application::APP_ID, 'admin_instance_url', Application::DEFAULT_COLLABOARD_URL) ?: Application::DEFAULT_COLLABOARD_URL;
		$url = $this->config->getUserValue($userId, Application::APP_ID, 'url', $adminUrl) ?: $adminUrl;
		$accessToken = $this->config->getUserValue($userId, Application::APP_ID, 'token');
		try {
			$url = $url . '/' . $endPoint;
			$options = [
				'headers' => [
					'User-Agent'  => Application::INTEGRATION_USER_AGENT,
					'Authorization' => 'Bearer ' . $accessToken,
				],
			];

			if ($method === 'GET') {
				if (count($params) > 0) {
					// manage array parameters
					$paramsContent = '';
					foreach ($params as $key => $value) {
						if (is_array($value)) {
							foreach ($value as $oneArrayValue) {
								$paramsContent .= $key . '[]=' . urlencode($oneArrayValue) . '&';
							}
							unset($params[$key]);
						}
					}
					$paramsContent .= http_build_query($params);
					$url .= '?' . $paramsContent;
				}
			} else {
				if (count($params) > 0) {
					$options['body'] = $params;
				}
			}

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} else if ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} else if ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} else if ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			} else {
				return ['error' => $this->l10n->t('Bad HTTP method')];
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials')];
			} else {
				if ($jsonResponse) {
					return json_decode($body, true);
				} else {
					return [
						'body' => $body,
						'headers' => $response->getHeaders(),
					];
				}
			}
		} catch (ClientException $e) {
			$response = $e->getResponse();
			$this->logger->warning('Collaboard API client error : ' . $e->getMessage(), [
				'app' => Application::APP_ID,
				'responseBody' => $response->getBody(),
				'exception' => $e->getMessage(),
			]);
			return ['error' => $this->l10n->t('Collaboard request error')];
		} catch (ServerException $e) {
			$response = $e->getResponse();
			$this->logger->debug('Collaboard API server error : ' . $e->getMessage(), [
				'app' => Application::APP_ID,
				'responseBody' => $response->getBody(),
				'exception' => $e->getMessage(),
			]);
			return ['error' => $this->l10n->t('Collaboard request failure')];
		}
	}

	/**
	 * @param string $userId
	 * @param string $login
	 * @param string $password
	 * @return array
	 */
	public function login(string $userId, string $login, string $password): array {
		$adminUrl = $this->config->getAppValue(Application::APP_ID, 'admin_instance_url', Application::DEFAULT_COLLABOARD_URL) ?: Application::DEFAULT_COLLABOARD_URL;
		$baseUrl = $this->config->getUserValue($userId, Application::APP_ID, 'url', $adminUrl) ?: $adminUrl;
		try {
			$url = $baseUrl . '/auth/api/Authorization/Authenticate';
			$options = [
				'headers' => [
					'Authorization' => 'Basic ' . base64_encode($login . ':' . $password),
					'User-Agent'  => Application::INTEGRATION_USER_AGENT,
					'Content-Type' => 'application/json',
				],
			];
			$response = $this->client->post($url, $options);
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Invalid credentials')];
			} else {
				try {
					return json_decode($body, true);
				} catch (Exception | Throwable $e) {
				}
				$this->logger->warning('Collaboard login error : Invalid response', ['app' => Application::APP_ID]);
				return ['error' => $this->l10n->t('Invalid response')];
			}
		} catch (ServerException $e) {
			$response = $e->getResponse();
			$body = $response->getBody();
			$this->logger->warning('Collaboard login server error : ' . $body, ['app' => Application::APP_ID]);
			return ['error' => $this->l10n->t('Login server error')];
		} catch (Exception | Throwable $e) {
			$this->logger->warning('Collaboard login error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return [
				'error' => $this->l10n->t('Login error'),
				'exception' => $e->getMessage(),
			];
		}
	}

	public function validate2FA(string $userId, string $secondFactor): array {
		$adminUrl = $this->config->getAppValue(Application::APP_ID, 'admin_instance_url', Application::DEFAULT_COLLABOARD_URL) ?: Application::DEFAULT_COLLABOARD_URL;
		$baseUrl = $this->config->getUserValue($userId, Application::APP_ID, 'url', $adminUrl) ?: $adminUrl;
		$accessToken = $this->config->getUserValue($userId, Application::APP_ID, 'token');
		try {
			$url = $baseUrl . '/auth/api/Authorization/ValidateUser2FA';
			$options = [
				'headers' => [
					'Authorization' => 'Bearer ' . $accessToken,
					'User-Agent'  => Application::INTEGRATION_USER_AGENT,
					'Content-Type' => 'application/json',
				],
				'body' => json_encode([
					'OTPCode' => $secondFactor,
				]),
			];
			$response = $this->client->post($url, $options);
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Invalid second factor')];
			} else {
				try {
					return json_decode($body, true);
				} catch (Exception | Throwable $e) {
				}
				$this->logger->warning('Collaboard ValidateUser2FA error : Invalid response', ['app' => Application::APP_ID]);
				return ['error' => $this->l10n->t('Invalid response')];
			}
		} catch (ServerException $e) {
			$response = $e->getResponse();
			$body = $response->getBody();
			$this->logger->warning('Collaboard ValidateUser2FA server error : ' . $body, ['app' => Application::APP_ID]);
			return ['error' => $this->l10n->t('ValidateUser2FA server error')];
		} catch (Exception | Throwable $e) {
			$this->logger->warning('Collaboard ValidateUser2FA error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return [
				'error' => $this->l10n->t('ValidateUser2FA error'),
				'exception' => $e->getMessage(),
			];
		}
	}

	public function sendUserOtpToken(string $userId, string $collaboardUserName, string $sfaMethod): array {
		$adminUrl = $this->config->getAppValue(Application::APP_ID, 'admin_instance_url', Application::DEFAULT_COLLABOARD_URL) ?: Application::DEFAULT_COLLABOARD_URL;
		$baseUrl = $this->config->getUserValue($userId, Application::APP_ID, 'url', $adminUrl) ?: $adminUrl;
		$accessToken = $this->config->getUserValue($userId, Application::APP_ID, 'token');
		try {
			$url = $baseUrl . '/auth/api/Authorization/SendUserOTPToken';
			$options = [
				'headers' => [
					'Authorization' => 'Bearer ' . $accessToken,
					'User-Agent' => Application::INTEGRATION_USER_AGENT,
					'Content-Type' => 'application/json',
				],
				'body' => json_encode([
					'User' => $collaboardUserName,
					// that's what the API doc says, does not work
					// 'MessagingPlatform' => $sfaMethod === 'email' ? 'Email' : 'SMS',
					// here is what the frontend actually does, retro-engineering is always the best
					'MessageTheme' => 'default',
				]),
			];
			$response = $this->client->post($url, $options);
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => 'sendUserOtpToken error'];
			} else {
				try {
					return json_decode($body, true);
				} catch (Exception | Throwable $e) {
				}
				$this->logger->warning('Collaboard sendUserOtpToken error : Invalid response', ['app' => Application::APP_ID]);
				return ['error' => $this->l10n->t('Invalid response')];
			}
		} catch (ServerException $e) {
			$response = $e->getResponse();
			$body = $response->getBody();
			$this->logger->warning('Collaboard sendUserOtpToken server error : ' . $body, ['app' => Application::APP_ID]);
			return ['error' => $this->l10n->t('sendUserOtpToken server error')];
		} catch (Exception | Throwable $e) {
			$this->logger->warning('Collaboard sendUserOtpToken error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return [
				'error' => $this->l10n->t('sendUserOtpToken error'),
				'exception' => $e->getMessage(),
			];
		}
	}

	/**
	 * Check if the auth token has expired and try to refresh it if so
	 * @param string $userId
	 * @return bool true if the token is still valid or we managed to refresh it, false if there was an issue
	 * @throws PreConditionNotMetException
	 */
	public function checkTokenExpiration(string $userId): bool {
		$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token');
		$expireAt = $this->config->getUserValue($userId, Application::APP_ID, 'token_expires_at');
		if ($refreshToken !== '' && $expireAt !== '') {
			$nowTs = (new Datetime())->getTimestamp();
			$expireAt = (int) $expireAt;
			// if token expires in less than a minute or is already expired
			if ($nowTs > $expireAt - 60) {
				return $this->refreshToken($userId);
			}
			return true;
		}
		return false;
	}

	public function refreshToken(string $userId): bool {
		$adminUrl = $this->config->getAppValue(Application::APP_ID, 'admin_instance_url', Application::DEFAULT_COLLABOARD_URL) ?: Application::DEFAULT_COLLABOARD_URL;
		$baseUrl = $this->config->getUserValue($userId, Application::APP_ID, 'url', $adminUrl) ?: $adminUrl;
		$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token');
		try {
			$url = $baseUrl . '/auth/api/Authorization/RefreshToken';
			$options = [
				'headers' => [
					'Authorization' => 'Bearer ' . $refreshToken,
					'User-Agent'  => Application::INTEGRATION_USER_AGENT,
				],
			];
			$response = $this->client->get($url, $options);
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return false;
			} else {
				try {
					$res = json_decode($body, true);
					if (isset($res['AuthorizationToken'], $res['RefreshToken'], $res['ExpiresIn'], $res['AuthenticationMode'])) {
						$this->config->setUserValue($userId, Application::APP_ID, 'token', $res['AuthorizationToken']);
						$this->config->setUserValue($userId, Application::APP_ID, 'refresh_token', $res['RefreshToken']);

						$nowTs = (new DateTime())->getTimestamp();
						$tokenExpireAt = $nowTs + (int)($res['ExpiresIn']);
						$this->config->setUserValue($userId, Application::APP_ID, 'token_expires_at', $tokenExpireAt);
						return true;
					}
				} catch (Exception | Throwable $e) {
				}
				$this->logger->warning('Collaboard login error : Invalid response', ['app' => Application::APP_ID]);
				return false;
			}
		} catch (ServerException $e) {
			$response = $e->getResponse();
			$body = $response->getBody();
			$this->logger->warning('Collaboard login server error : ' . $body, ['app' => Application::APP_ID]);
			return false;
		} catch (Exception | Throwable $e) {
			$this->logger->warning('Collaboard login error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return false;
		}
	}
}
