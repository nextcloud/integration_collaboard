<?php
/**
 * Nextcloud - Collaboard
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
* @author Sami Finnilä <sami.finnila@gmail.com>
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

class CollaboardAPIService
{
	private LoggerInterface $logger;
	private IL10N $l10n;
	private IConfig $config;
	private IClient $client;
	private string $appVersion;

	/**
	 * Service to make requests to Collaboard API
	 */
	public function __construct(
		string $appName,
		LoggerInterface $logger,
		IL10N $l10n,
		IConfig $config,
		IAppManager $appManager,
		IClientService $clientService
	) {
		$this->client = $clientService->newClient();
		$this->appVersion = $appManager->getAppVersion(Application::APP_ID);
		$this->logger = $logger;
		$this->l10n = $l10n;
		$this->config = $config;
	}

	public function isUserConnected(string $userId): bool
	{
		$adminUrl = $this->config->getAppValue(Application::APP_ID, 'admin_instance_url', Application::DEFAULT_COLLABOARD_URL) ?: Application::DEFAULT_COLLABOARD_URL;
		$url = $this->config->getUserValue($userId, Application::APP_ID, 'url', $adminUrl) ?: $adminUrl;

		$userName = $this->config->getUserValue($userId, Application::APP_ID, 'user_name');
		$token = $this->config->getUserValue($userId, Application::APP_ID, 'token');
		$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token');
		return $url && $userName && $token && $refreshToken;
	}

	public function getImage(string $url): array
	{
		$response = $this->client->get($url);
		return [
			'body' => $response->getBody(),
			'headers' => $response->getHeaders(),
		];
	}

	/**
	 * @param string $userId
	 * @return array|string[]
	 * @throws Exception
	 */
	public function getProjects(string $userId): array
	{
		$params = [
			'pageSize' => 100,
			'pageNumber' => 1,
		];
		$projectsResult = $this->restRequest($userId, 'public/api/public/v2.0/collaborationhub/projects/owned', $params, 'GET');
		if (isset($projectsResult['error'])) {
			return $projectsResult;
		}
		$thumbnailRequestOptions = [
			'headers' => [
				'User-Agent'  => Application::INTEGRATION_USER_AGENT,
			],
		];
		$client = $this->client;
		if (isset($projectsResult['Results']) && is_array($projectsResult['Results'])) {
			$remoteProjects = $projectsResult['Results'];
			$logger = $this->logger;
			return array_map(static function(array $remoteProject) use ($thumbnailRequestOptions, $client, $logger) {
				$remoteProject['trash'] = false;
				$remoteProject['name'] = $remoteProject['Project']['Description'];
				$remoteProject['id'] = $remoteProject['Project']['ProjectId'];
				$remoteProject['created_at'] = $remoteProject['Project']['CreationDate'];
				$remoteProject['owned_by'] = [
					'userName' => $remoteProject['Owner']['UserName'] ?? '??',
					'photoUrl' => $remoteProject['Owner']['PhotoUrl'] ?? null,
				];
				$remoteProject['updated_at'] = $remoteProject['Project']['LastUpdate'];
				if (isset($remoteProject['ThumbnailUrl']) && $remoteProject['ThumbnailUrl']) {
					try {
						$response = $client->get($remoteProject['ThumbnailUrl'], $thumbnailRequestOptions);
						$remoteProject['Project']['Thumbnail'] = base64_encode($response->getBody());
					} catch (Exception | Throwable $e) {
						$logger->debug('Collaboard thumbnail error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
					}
				}
				return $remoteProject;
			}, $remoteProjects);
		}
		return [];
	}

	/**
	 *
	 * @param string $userId
	 * @param string $name
	 * @return string[]
	 * @throws Exception
	 */
	public function createProject(string $userId, string $name): array
	{
		$params = [
			'Description' => $name,
		];
		return $this->restRequest($userId, 'public/api/public/v2.0/collaborationhub/projects', $params, 'POST');
	}

	/**
	 * @param string $userId
	 * @param int $projectId
	 * @return string[]
	 * @throws Exception
	 */
	public function deleteProject(string $userId, int $projectId): array {
		$params = [];
		return $this->restRequest($userId, 'public/api/public/v2.0/collaborationhub/projects/' . $projectId, $params, 'DELETE');
	}

	/**
	 * @param string $userId
	 * @return array|string[]
	 * @throws Exception
	 */
	public function getUserInfo(string $userId): array {
		return $this->restRequest($userId, 'public/api/public/v2.0/collaborationhub/auth/userinfo');
	}

	/**
	 * @param string $userId
	 * @return array|string[]
	 * @throws Exception
	 */
	public function getUserLicenseInfo(string $userId): array {
		$params = [];
		return $this->restRequest($userId, 'public/api/public/v2.0/collaborationhub/subscriptions/license', $params);
	}

	/**
	 * @param string $userId
	 * @param int $projectId
	 * @param string $invitationUrl
	 * @param int $memberPermission
	 * @param int $validForMinutes
	 * @param bool $guestIdentificationRequired
	 * @param int $guestPermission
	 * @param string|null $password
	 * @return string[]
	 * @throws Exception
	 */
	public function createInvitationLink(
		string $userId,
		int $projectId,
		string $invitationUrl,
		int $memberPermission,
		int $validForMinutes,
		bool $guestIdentificationRequired,
		int $guestPermission,
		?string $password = null
	): array {
		$params = [
			'InvitationUrl' => $invitationUrl,
			'MemberPermission' => $memberPermission,
			'ValidForMinutes' => $validForMinutes,
			'GuestPermission' => $guestPermission,
			'GuestIdentificationRequired' => $guestIdentificationRequired,
		];
		if ($password !== null) {
			$params['Password'] = $password;
		}
		return $this->restRequest($userId, 'public/api/public/v2.0/collaborationhub/projects/'.$projectId.'/invitationlink', $params, 'POST');
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
	public function restRequest(
		string $userId,
		string $endPoint,
		array $params = [],
		string $method = 'GET',
		bool $jsonResponse = true
	): array {
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
					'User-Agent' => Application::INTEGRATION_USER_AGENT,
					'Authorization' => 'Bearer ' . $accessToken,
					'Content-Type' => 'application/json',
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
					$options['body'] = json_encode($params);
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
			$responseBody = $response->getBody()->getContents();
			try {
				$responseBody = json_decode($responseBody, true);
			} catch (Exception $e) {
			}
			$this->logger->warning('Collaboard API client error : ' . $e->getMessage(), [
				'app' => Application::APP_ID,
				'responseBody' => $responseBody,
				'exception' => $e->getMessage(),
			]);
			return [
				'error' => $this->l10n->t('Collaboard API client error'),
				'responseBody' => $responseBody,
				'exception' => $e->getMessage(),
			];
		} catch (ServerException $e) {
			$response = $e->getResponse();
			$responseBody = $response->getBody();
			$this->logger->debug('Collaboard API server error : ' . $e->getMessage(), [
				'app' => Application::APP_ID,
				'responseBody' => $responseBody,
				'exception' => $e->getMessage(),
			]);
			return [
				'error' => 'Collaboard API server error',
				'responseBody' => $responseBody,
				'exception' => $e->getMessage(),
			];
		}
	}

	/**
	 * Get authentication mode for a login
	 * @param string $userId
	 * @param string $login
	 * @return int
	 */
	public function getAuthenticationMode(string $userId, string $login): int
	{
		$params = ['username' => $login];

		$adminUrl = $this->config->getAppValue(Application::APP_ID, 'admin_instance_url', Application::DEFAULT_COLLABOARD_URL) ?: Application::DEFAULT_COLLABOARD_URL;
		$baseUrl = $this->config->getUserValue($userId, Application::APP_ID, 'url', $adminUrl) ?: $adminUrl;
		try {
			// The public API docs detail a GetAuthorizationMode endpoint, but it returns a 404
			// So we use this one instead (reverse engineered from the frontend of the collaboard.app website)
			$url = $baseUrl . '/auth/api/Authorization/GetUserOptions';
			$options = [
				'headers' => [
					'User-Agent' => Application::INTEGRATION_USER_AGENT,
					'Content-Type' => 'application/json',
				],
				'body' => json_encode($params),
			];
			$response = $this->client->post($url, $options);
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return 0;
			} else {
				try {
					$res = json_decode($body, true);
					if (isset($res['AuthenticationMode'])) {
						return (int) $res['AuthenticationMode'];
					}
				} catch (Exception | Throwable $e) {
				}
				$this->logger->warning('Collaboard login server error : Invalid response', ['app' => Application::APP_ID]);
				return 0;
			}
		} catch (ServerException $e) {
			$response = $e->getResponse();
			$body = $response->getBody();
			$this->logger->warning('Collaboard login server error : ' . $body, ['app' => Application::APP_ID]);
			return 0;
		} catch (Exception | Throwable $e) {
			$this->logger->warning('Collaboard login error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return 0;
		}
	}


	/**
	 * Login and retrieve user options
	 * 
	 * Returns an array with user_name and user_displayname if successful.
	 * Otherwise, returns an array with an error key explaining the error.
	 * 
	 * @param string $userId
	 * @param string $login
	 * @param string $password
	 * @param string|null $secondFactor
	 * @return array
	 */
	public function login(string $userId, string $login, string $password, ?string $secondFactor = null): array
	{
		$params = ['username' => $login];

		$adminUrl = $this->config->getAppValue(Application::APP_ID, 'admin_instance_url', Application::DEFAULT_COLLABOARD_URL) ?: Application::DEFAULT_COLLABOARD_URL;
		$baseUrl = $this->config->getUserValue($userId, Application::APP_ID, 'url', $adminUrl) ?: $adminUrl;

		// First, call the authentication endpoint to get the token
		try {
			$url = $baseUrl . '/auth/api/Authorization/Authenticate';
			$options = [
				'headers' => [
					'Authorization' => 'Basic ' . base64_encode($login . ':' . $password),
					'User-Agent' => Application::INTEGRATION_USER_AGENT,
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
					$authResp = json_decode($body, true);

					if (isset($authResp['AuthorizationToken'], $authResp['ExpiresIn'], $authResp['AuthenticationMode'])) {

						// Sensibility check
						if ($authResp['AuthenticationMode'] > 3 || $authResp['AuthenticationMode'] < 0) {
							return ['error' => $this->l10n->t('Unsupported authentication mode requested from server')];
						}

						$this->config->setUserValue($userId, Application::APP_ID, 'token', $authResp['AuthorizationToken']);

						if (isset($authResp['RefreshToken'])) {
							// Refresh token is not set if the user has 2FA enabled
							$this->config->setUserValue($userId, Application::APP_ID, 'refresh_token', $authResp['RefreshToken']);
						}

						$nowTs = (new DateTime())->getTimestamp();
						$tokenExpireAt = $nowTs + (int) ($authResp['ExpiresIn']);
						$this->config->setUserValue($userId, Application::APP_ID, 'token_expires_at', $tokenExpireAt);
						$this->config->setUserValue($userId, Application::APP_ID, 'user_name', $login);
						$this->config->setUserValue($userId, Application::APP_ID, 'url', $baseUrl);
						$this->config->setUserValue($userId, Application::APP_ID, 'authentication_mode', $authResp['AuthenticationMode']);
					} else
						throw new Exception('Response is missing required keys');
				} catch (Exception | Throwable $e) {
					$this->logger->warning('Collaboard login error : Invalid response. Error: ' . $e->getMessage(), ['app' => Application::APP_ID]);
					return ['error' => $this->l10n->t('Invalid response')];
				}
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


		if ($authResp['AuthenticationMode'] === 3) {
			// 2FA is required
			if ($secondFactor === null || $secondFactor === '') {
				return ['error' => $this->l10n->t('Second factor required')];
			}

			$secondFactorResult = $this->validate2FA($userId, $secondFactor);

			if (isset($secondFactorResult['error'])) {
				return $secondFactorResult;
			}
			$authenticatedToken = $secondFactorResult['token'];
		} else {
			$authenticatedToken = $authResp['AuthorizationToken'];
		}

		// Ok, now we are authenticated, let's get the user info and return that to the frontend:
		// We could use the User object returned by the 'Authenticate' endpoint, but the returning of the User object
		// is undocumented behaviour so let's not rely on that:
		try {
			$url = $baseUrl . '/auth/api/Authorization/GetAuthenticatedUser';
			$options = [
				'headers' => [
					'Authorization' => 'Bearer ' . $authenticatedToken,
					'User-Agent' => Application::INTEGRATION_USER_AGENT,
					'Content-Type' => 'application/json',
				],
				//'body' => json_encode($params),
			];
			$response = $this->client->get($url, $options);
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Could not retrieve user info')];
			} else {
				try {
					$userInfoResp = json_decode($body, true);
					if (
						isset(
						$userInfoResp['Result']['UserName'],
						$userInfoResp['Result']['FirstName'],
						$userInfoResp['Result']['LastName'])
					) {
						$this->config->setUserValue($userId, Application::APP_ID, 'user_name', $userInfoResp['Result']['UserName']);
						$displayName = $userInfoResp['Result']['FirstName'] . ' ' . $userInfoResp['Result']['LastName'];
						$this->config->setUserValue($userId, Application::APP_ID, 'user_displayname', $displayName);

						return [
							'user_name' => $userInfoResp['Result']['UserName'],
							'user_displayname' => $displayName,
						];
					} else
						throw new Exception('Invalid response');
				} catch (Exception | Throwable $e) {
					$this->logger->warning('Collaboard login error : Invalid response', ['app' => Application::APP_ID]);
					return ['error' => $this->l10n->t('Invalid response')];
				}
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

	/**
	 * Will return an array with the 'error' key if there was an issue.
	 * Otherwise will set an array with the 'toke' key set to the new token.
	 * @param string $userId
	 * @param string $secondFactor
	 * @return array
	 */
	private function validate2FA(string $userId, string $secondFactor): array
	{
		$adminUrl = $this->config->getAppValue(Application::APP_ID, 'admin_instance_url', Application::DEFAULT_COLLABOARD_URL) ?: Application::DEFAULT_COLLABOARD_URL;
		$baseUrl = $this->config->getUserValue($userId, Application::APP_ID, 'url', $adminUrl) ?: $adminUrl;
		$accessToken = $this->config->getUserValue($userId, Application::APP_ID, 'token');
		try {
			$url = $baseUrl . '/auth/api/Authorization/ValidateUser2FA';
			$options = [
				'headers' => [
					'Authorization' => 'Bearer ' . $accessToken,
					'User-Agent' => Application::INTEGRATION_USER_AGENT,
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
					$validationResp = json_decode($body, true);
					if (isset($validationResp['AuthorizationToken'], $validationResp['RefreshToken'], $validationResp['ExpiresIn'], $validationResp['AuthenticationMode'])) {
						$this->config->setUserValue($userId, Application::APP_ID, 'token', $validationResp['AuthorizationToken']);
						$this->config->setUserValue($userId, Application::APP_ID, 'refresh_token', $validationResp['RefreshToken']);

						$nowTs = (new DateTime())->getTimestamp();
						$tokenExpireAt = $nowTs + (int) ($validationResp['ExpiresIn']);
						$this->config->setUserValue($userId, Application::APP_ID, 'token_expires_at', $tokenExpireAt);
						return ['token' => $validationResp['AuthorizationToken']];
					}
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

	/**
	 * @param string $userId
	 * @param string $collaboardUserName
	 * @param string|null $sfaMethod
	 * @return array|string[]
	 */
	public function sendUserOtpToken(string $userId, string $collaboardUserName, ?string $sfaMethod = null): array
	{
		$adminUrl = $this->config->getAppValue(Application::APP_ID, 'admin_instance_url', Application::DEFAULT_COLLABOARD_URL) ?: Application::DEFAULT_COLLABOARD_URL;
		$baseUrl = $this->config->getUserValue($userId, Application::APP_ID, 'url', $adminUrl) ?: $adminUrl;
		$accessToken = $this->config->getUserValue($userId, Application::APP_ID, 'token');
		try {
			$url = $baseUrl . '/auth/api/Authorization/SendUserOTPToken';
			$options = [
				'headers' => [
					'User-Agent' => Application::INTEGRATION_USER_AGENT,
					'Content-Type' => 'application/json',
				],
				'body' => json_encode([
					'UserName' => $collaboardUserName,
					// that's what the API doc says, does not work
					// 'MessagingPlatform' => $sfaMethod === 'email' ? 'Email' : 'SMS',
					// here is what the frontend actually does, retro-engineering is always the best
					'MessageTheme' => 'default',

				]),
			];

			// only use the token if we have one
			// difference between OTP code used as password (no token yet) or as second factor (got a token from login/password)
			if ($accessToken) {
				$options['headers']['Authorization'] = 'Bearer ' . $accessToken;
			}

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
	public function checkTokenExpiration(string $userId): bool
	{
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

	public function refreshToken(string $userId): bool
	{
		$adminUrl = $this->config->getAppValue(Application::APP_ID, 'admin_instance_url', Application::DEFAULT_COLLABOARD_URL) ?: Application::DEFAULT_COLLABOARD_URL;
		$baseUrl = $this->config->getUserValue($userId, Application::APP_ID, 'url', $adminUrl) ?: $adminUrl;
		$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token');
		try {
			$url = $baseUrl . '/auth/api/Authorization/RefreshToken';
			$options = [
				'headers' => [
					'Authorization' => 'Bearer ' . $refreshToken,
					'User-Agent' => Application::INTEGRATION_USER_AGENT,
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
						$tokenExpireAt = $nowTs + (int) ($res['ExpiresIn']);
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
