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
use OCP\AppFramework\Http;
use OCP\IConfig;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\Collaboard\Service\CollaboardAPIService;
use OCA\Collaboard\AppInfo\Application;

class CollaboardAPIController extends Controller {

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
}
