<?php
/**
 * Nextcloud - Collaboard
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2022
 */

namespace OCA\Collaboard\Controller;

use Exception;
use OCA\Collaboard\AppInfo\Application;
use OCA\Collaboard\Service\CollaboardAPIService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;

use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;

class CollaboardAPIController extends Controller {

	private IConfig $config;
	private CollaboardAPIService $collaboardAPIService;
	private ?string $userId;
	private IURLGenerator $urlGenerator;

	public function __construct(
		string               $appName,
		IRequest             $request,
		IConfig              $config,
		CollaboardAPIService $collaboardAPIService,
		IURLGenerator        $urlGenerator,
		?string              $userId
	) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->collaboardAPIService = $collaboardAPIService;
		$this->userId = $userId;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Response
	 * @throws Exception
	 */
	public function getUserPhoto(string $url, string $userName): Response {
		if (!$this->collaboardAPIService->isUserConnected($this->userId)) {
			return new DataResponse('not connected', Http::STATUS_BAD_REQUEST);
		}
		$apiUrl = $this->config->getAppValue(Application::APP_ID, 'admin_api_url', Application::DEFAULT_COLLABOARD_API) ?: Application::DEFAULT_COLLABOARD_API;
		if (substr($url, 0, strlen($apiUrl)) === $apiUrl) {
			$image = $this->collaboardAPIService->getImage($url);
			if (isset($image['body'], $image['headers'])) {
				$response = new DataDisplayResponse(
					$image['body'],
					Http::STATUS_OK,
					['Content-Type' => $image['headers']['Content-Type'][0] ?? 'image/jpeg']
				);
				$response->cacheFor(60 * 60 * 24, false, true);
				return $response;
			}
		}

		$fallbackAvatarUrl = $this->urlGenerator->linkToRouteAbsolute('core.GuestAvatar.getAvatar', ['guestName' => $userName, 'size' => 44]);
		return new RedirectResponse($fallbackAvatarUrl);
	}

	/**
	 * @NoAdminRequired
	 * @return DataResponse
	 * @throws Exception
	 */
	public function getProjects(): DataResponse {
		if (!$this->collaboardAPIService->isUserConnected($this->userId)) {
			return new DataResponse('not connected', Http::STATUS_BAD_REQUEST);
		}
		$result = $this->collaboardAPIService->getProjects($this->userId);
		if (isset($result['error'])) {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		} else {
			return new DataResponse($result);
		}
	}

	/**
	 * @NoAdminRequired
	 * @param string $name
	 * @return DataResponse
	 */
	public function createProject(string $name): DataResponse {
		if (!$this->collaboardAPIService->isUserConnected($this->userId)) {
			return new DataResponse('not connected', Http::STATUS_BAD_REQUEST);
		}
		$result = $this->collaboardAPIService->createProject($this->userId, $name);
		if (isset($result['error'])) {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		} else {
			return new DataResponse($result);
		}
	}

	/**
	 * @NoAdminRequired
	 * @param int $projectId
	 * @return DataResponse
	 */
	public function deleteProject(int $projectId): DataResponse {
		if (!$this->collaboardAPIService->isUserConnected($this->userId)) {
			return new DataResponse('not connected', Http::STATUS_BAD_REQUEST);
		}
		$result = $this->collaboardAPIService->deleteProject($this->userId, $projectId);
		if (isset($result['error'])) {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		} else {
			return new DataResponse($result);
		}
	}

	/**
	 * @NoAdminRequired
	 * @param int $projectId
	 * @param string $invitationUrl
	 * @param int $memberPermission
	 * @param string $password
	 * @param int $validForMinutes
	 * @param bool $guestIdentificationRequired
	 * @param int $guestPermission
	 * @return DataResponse
	 */
	public function createInvitationLink(int $projectId,
		string $invitationUrl, int $memberPermission, int $validForMinutes,
		bool $guestIdentificationRequired, int $guestPermission, ?string $password = null): DataResponse {
		if (!$this->collaboardAPIService->isUserConnected($this->userId)) {
			return new DataResponse('not connected', Http::STATUS_BAD_REQUEST);
		}
		$result = $this->collaboardAPIService->createInvitationLink(
			$this->userId, $projectId,
			$invitationUrl, $memberPermission, $validForMinutes,
			$guestIdentificationRequired, $guestPermission, $password
		);
		if (isset($result['error'])) {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		} else {
			return new DataResponse($result);
		}
	}
}
