import { loadState } from '@nextcloud/initial-state'
import Vue from 'vue'
import './bootstrap.js'
import CollaboardModalWrapper from './components/CollaboardModalWrapper.vue'

function init() {
	if (!OCA.Collaboard) {
		/**
		 * @namespace
		 */
		OCA.Collaboard = {}
	}

	const wrapperId = 'collaboardModalWrapper'
	const wrapperElement = document.createElement('div')
	wrapperElement.id = wrapperId
	document.body.append(wrapperElement)

	const View = Vue.extend(CollaboardModalWrapper)
	OCA.Collaboard.CollaboardModalWrapperVue = new View().$mount('#' + wrapperId)

	OCA.Collaboard.openModal = (roomUrl) => {
		OCA.Collaboard.CollaboardModalWrapperVue.openOn(roomUrl)
	}
}

function listen(baseUrl) {
	const body = document.querySelector('body')
	body.addEventListener('click', (e) => {
		const link = (e.target.tagName === 'A')
			? e.target
			: (e.target.parentElement?.tagName === 'A')
				? e.target.parentElement
				: null
		if (link !== null) {
			const href = link.getAttribute('href')
			if (!href) {
				return
			}
			if (href.startsWith(baseUrl + '/')) {
				e.preventDefault()
				e.stopPropagation()
				OCA.Collaboard.openModal(href)
			}
		}
	})
}

const baseUrl = loadState('integration_collaboard', 'admin_domain_url')
const overrideLinkClick = loadState('integration_collaboard', 'override_link_click')
if (baseUrl) {
	init()
	console.debug('!!! Collaboard standalone modal is ready', baseUrl)
	if (overrideLinkClick) {
		console.debug('Collaboard will handle clicks on links')
		listen(baseUrl)
	}
} else {
	console.debug('!!! Collaboard standalone: disabled')
}
