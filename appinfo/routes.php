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

		['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
		['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],
		['name' => 'config#sendUserOtpPasswordCode', 'url' => '/email-2fa-password', 'verb' => 'GET'],
		['name' => 'config#getAuthenticationMode', 'url' => '/auth-mode', 'verb' => 'GET'],

		['name' => 'collaboardAPI#getUserPhoto', 'url' => '/photo', 'verb' => 'GET'],
		['name' => 'collaboardAPI#getProjects', 'url' => '/projects', 'verb' => 'GET'],
		['name' => 'collaboardAPI#createProject', 'url' => '/projects', 'verb' => 'POST'],
		['name' => 'collaboardAPI#deleteProject', 'url' => '/projects/{projectId}', 'verb' => 'DELETE'],
		['name' => 'collaboardAPI#createInvitationLink', 'url' => '/invitation-link', 'verb' => 'POST'],
	]
];
