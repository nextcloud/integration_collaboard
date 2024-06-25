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

use DateTime;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use OCA\Collaboard\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\PreConditionNotMetException;
use Psr\Log\LoggerInterface;
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

	public function isUserConnected(string $userId): bool {
		$url = $this->config->getAppValue(Application::APP_ID, 'admin_api_url', Application::DEFAULT_COLLABOARD_API) ?: Application::DEFAULT_COLLABOARD_API;

		$userName = $this->config->getUserValue($userId, Application::APP_ID, 'user_name');
		$token = $this->config->getUserValue($userId, Application::APP_ID, 'token');
		$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token');
		return $url !== '' && $userName && $token && $refreshToken;
	}

	public function getImage(string $url): array {
		$response = $this->client->get($url);
		return [
			'body' => $response->getBody(),
			'headers' => $response->getHeaders(),
		];
	}

	public function getProjectOwner(string $userId, string $projectId): array {
		$params = [
			'PageSize' => 100,
			'PageNumber' => 1,
		];
		$response = $this->request($userId, 'public/api/public/v2.0/collaborationhub/projects/' . urlencode($projectId) . '/users', $params);
		if (isset($response['error']) || !isset($response['Results']) || !is_array($response['Results'])) {
			return [];
		}
		foreach ($response['Results'] as $participant) {
			if (is_array($participant) && isset($participant['ParticipationType']) && $participant['ParticipationType'] === 1) {
				return $participant['User'];
			}
		}
		return [];
	}

	/**
	 * @param string $userId
	 * @return array|string[]
	 * @throws Exception
	 */
	public function getProjects(string $userId): array {
		$params = [
			'pageSize' => 100,
			'pageNumber' => 1,
		];
		$projectsResult = $this->request($userId, 'public/api/public/v2.0/collaborationhub/projects/participating', $params, 'GET');
		if (isset($projectsResult['error'])) {
			return $projectsResult;
		}
		$thumbnailRequestOptions = [
			'headers' => [
				'User-Agent' => Application::INTEGRATION_USER_AGENT,
			],
		];
		$client = $this->client;
		if (isset($projectsResult['Results']) && is_array($projectsResult['Results'])) {
			// the owner info used to be included in the project list, now we need to get it from the project participant list
			$remoteProjects = array_map(function (array $remoteProject) use ($userId) {
				$remoteProject['Owner'] = $this->getProjectOwner($userId, $remoteProject['Project']['ProjectId']);
				return $remoteProject;
			}, $projectsResult['Results']);
			$logger = $this->logger;
			return array_map(static function (array $remoteProject) use ($thumbnailRequestOptions, $client, $logger) {
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
	public function createProject(string $userId, string $name): array {
		$params = [
			'Description' => $name,
		];
		return $this->request($userId, 'public/api/public/v2.0/collaborationhub/projects', $params, 'POST');
	}

	/**
	 * @param string $userId
	 * @param int $projectId
	 * @return string[]
	 * @throws Exception
	 */
	public function deleteProject(string $userId, int $projectId): array {
		$params = [];
		return $this->request($userId, 'public/api/public/v2.0/collaborationhub/projects/' . $projectId, $params, 'DELETE');
	}

	/**
	 * TODO could be replaced by
	 *  curl https://api.collaboard.app/public/api/public/v2.0/collaborationhub/auth/userinfo
	 *
	 * @param string $userId
	 * @return array|string[]
	 * @throws Exception
	 */
	public function getUserInfo(string $userId): array {
		return $this->request($userId, 'public/api/public/v2.0/collaborationhub/auth/userinfo');
	}

	/**
	 * @param string $userId
	 * @return array|string[]
	 * @throws Exception
	 */
	public function getUserLicenseInfo(string $userId): array {
		$params = [];
		return $this->request($userId, 'public/api/public/v2.0/collaborationhub/subscriptions/license', $params);
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
		return $this->request($userId, 'public/api/public/v2.0/collaborationhub/projects/'.$projectId.'/invitationlink', $params, 'POST');
	}

	/**
	 * @param string $userId
	 * @param string $endPoint
	 * @param array $params
	 * @param string $method
	 * @param bool $jsonResponse
	 * @return array
	 * @throws PreConditionNotMetException
	 */
	public function request(
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
		$url = $this->config->getAppValue(Application::APP_ID, 'admin_api_url', Application::DEFAULT_COLLABOARD_API) ?: Application::DEFAULT_COLLABOARD_API;
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
			} elseif ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} elseif ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} elseif ($method === 'DELETE') {
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

	private function refreshToken(string $userId): bool {
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id');
		$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token');
		if (!$refreshToken) {
			$this->logger->error('No Collaboard refresh token found', ['app' => Application::APP_ID]);
			return false;
		}
		$result = $this->requestOAuthAccessToken([
			'client_id' => $clientID,
			'grant_type' => 'refresh_token',
			'refresh_token' => $refreshToken,
		], 'POST');
		if (isset($result['access_token'])) {
			$this->logger->info('Collaboard access token successfully refreshed', ['app' => Application::APP_ID]);
			$accessToken = $result['access_token'];
			$refreshToken = $result['refresh_token'];
			$this->config->setUserValue($userId, Application::APP_ID, 'token', $accessToken);
			$this->config->setUserValue($userId, Application::APP_ID, 'refresh_token', $refreshToken);
			if (isset($result['expires_in'])) {
				$nowTs = (new Datetime())->getTimestamp();
				$expiresAt = $nowTs + (int) $result['expires_in'];
				$this->config->setUserValue($userId, Application::APP_ID, 'token_expires_at', $expiresAt);
			}
			return true;
		} else {
			// impossible to refresh the token
			$this->logger->error(
				'Token is not valid anymore. Impossible to refresh it. '
					. $result['error'] . ' '
					. $result['error_description'] ?? '[no error description]',
				['app' => Application::APP_ID]
			);
			return false;
		}
	}

	public function requestOAuthAccessToken(array $params = [], string $method = 'GET'): array {
		try {
			$baseUrl = $this->config->getAppValue(Application::APP_ID, 'admin_api_url', Application::DEFAULT_COLLABOARD_API) ?: Application::DEFAULT_COLLABOARD_API;
			$url = $baseUrl . '/auth/oauth2/token';
			$options = [
				'headers' => [
					'User-Agent' => Application::INTEGRATION_USER_AGENT,
				]
			];

			if (count($params) > 0) {
				if ($method === 'GET') {
					$paramsContent = http_build_query($params);
					$url .= '?' . $paramsContent;
				} else {
					$options['body'] = $params;
				}
			}

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} elseif ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} elseif ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} elseif ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			} else {
				return ['error' => $this->l10n->t('Bad HTTP method')];
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('OAuth access token refused')];
			} else {
				return json_decode($body, true);
			}
		} catch (Exception $e) {
			$this->logger->warning('Collaboard OAuth error : '.$e->getMessage(), ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}

	public function revokeToken(string $userId): bool {
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id');
		$token = $this->config->getUserValue($userId, Application::APP_ID, 'token');
		$revokeResponse = $this->request($userId, 'auth/oauth2/token/revoke', [
			'client_id' => $clientID,
			'token' => $token,
		], 'POST', false);
		return $revokeResponse === '';
	}
}
