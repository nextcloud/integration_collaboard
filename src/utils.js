import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'

export function Timer(callback, mydelay) {
	let timerId
	let start
	let remaining = mydelay

	this.pause = function() {
		window.clearTimeout(timerId)
		remaining -= new Date() - start
	}

	this.resume = function() {
		start = new Date()
		window.clearTimeout(timerId)
		timerId = window.setTimeout(callback, remaining)
	}

	this.resume()
}

let mytimer = 0
export function delay(callback, ms) {
	return function() {
		const context = this
		const args = arguments
		clearTimeout(mytimer)
		mytimer = setTimeout(function() {
			callback.apply(context, args)
		}, ms || 0)
	}
}

export function oauthConnect(clientId, apiUrl, oauthOrigin, usePopup = false) {
	const redirectUri
    = window.location.protocol
    + '//'
    + window.location.host
    + generateUrl('/apps/integration_collaboard/oauth-redirect')

	// const oauthState = Math.random().toString(36).substring(3)
	const requestUrl
    = apiUrl
    + '/auth/oauth2/authorize'
    + '?client_id='
    + encodeURIComponent(clientId)
    + '&redirect_uri='
    + encodeURIComponent(redirectUri)
    + '&response_type=code'
	// + '&state=' + encodeURIComponent(oauthState)
	// + '&scope=' + encodeURIComponent('read_user read_api read_repository')

	const req = {
		values: {
			// oauth_state: oauthState,
			redirect_uri: redirectUri,
			oauth_origin: usePopup ? undefined : oauthOrigin,
		},
	}
	const url = generateUrl('/apps/integration_collaboard/config')
	return new Promise((resolve, reject) => {
		axios
			.put(url, req)
			.then((response) => {
				if (usePopup) {
					const ssoWindow = window.open(
						requestUrl,
						t('integration_collaboard', 'Sign in with Collaboard'),
						'toolbar=no, menubar=no, width=600, height=700',
					)
					ssoWindow.focus()
					window.addEventListener('message', (event) => {
						console.debug('Child window message received', event)
						resolve(event.data)
					})
				} else {
					window.location.replace(requestUrl)
				}
			})
			.catch((error) => {
				showError(
					t('integration_collaboard', 'Failed to save Collaboard OAuth state')
            + ': '
            + (error.response?.request?.responseText ?? ''),
				)
				console.error(error)
			})
	})
}

export function humanFileSize(bytes, approx = false, si = false, dp = 1) {
	const thresh = si ? 1000 : 1024

	if (Math.abs(bytes) < thresh) {
		return bytes + ' B'
	}

	const units = si
		? ['kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB']
		: ['KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB']
	let u = -1
	const r = 10 ** dp

	do {
		bytes /= thresh
		++u
	} while (Math.round(Math.abs(bytes) * r) / r >= thresh && u < units.length - 1)

	if (approx) {
		return Math.floor(bytes) + ' ' + units[u]
	} else {
		return bytes.toFixed(dp) + ' ' + units[u]
	}
}
