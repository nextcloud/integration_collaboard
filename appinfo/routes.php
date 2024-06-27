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

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

		['name' => 'config#isUserConnected', 'url' => '/is-connected', 'verb' => 'GET'],
		['name' => 'config#oauthRedirect', 'url' => '/oauth-redirect', 'verb' => 'GET'],
		['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
		['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],
		['name' => 'config#popupSuccessPage', 'url' => '/popup-success', 'verb' => 'GET'],

		['name' => 'collaboardAPI#getUserPhoto', 'url' => '/photo', 'verb' => 'GET'],
		['name' => 'collaboardAPI#getProjects', 'url' => '/projects', 'verb' => 'GET'],
		['name' => 'collaboardAPI#createProject', 'url' => '/projects', 'verb' => 'POST'],
		['name' => 'collaboardAPI#deleteProject', 'url' => '/projects/{projectId}', 'verb' => 'DELETE'],
		['name' => 'collaboardAPI#createInvitationLink', 'url' => '/invitation-link', 'verb' => 'POST'],
	]
];
