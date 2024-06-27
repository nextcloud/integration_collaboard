<template>
	<div id="collaboard_prefs" class="section">
		<h2>
			<CollaboardIcon class="icon" />
			{{ t('integration_collaboard', 'Collaboard integration') }}
		</h2>
		<div class="collaboard-content">
			<p class="settings-hint">
				{{ t('integration_collaboard', 'If you want to allow your Nextcloud users to connect to Collaboard via OAuth, get in touch with Collaboard Support to get the ID and secret.') }}
			</p>
			<br>
			<p class="settings-hint">
				<InformationVariantIcon :size="24" class="icon" />
				{{ t('integration_collaboard', 'Make sure you provide the "Redirect URI" to Collaboard Support') }}
				&nbsp;<b> {{ redirect_uri }} </b>
			</p>
			<br>
			<p class="settings-hint">
				{{ t('integration_collaboard', 'Put the "Application ID" and "Application secret" below. Your Nextcloud users will then see a "Connect to Collaboard" button in their personal settings.') }}
			</p>
			<div class="field">
				<label for="collaboard-client-id">
					<KeyIcon :size="20" class="icon" />
					{{ t('integration_collaboard', 'Application ID') }}
				</label>
				<input id="collaboard-client-id"
					v-model="state.client_id"
					type="password"
					:readonly="readonly"
					:placeholder="t('integration_collaboard', 'ID of your Collaboard application')"
					@input="onInput"
					@focus="readonly = false">
			</div>
			<div class="field">
				<label for="collaboard-client-secret">
					<KeyIcon :size="20" class="icon" />
					{{ t('integration_collaboard', 'Application secret') }}
				</label>
				<input id="collaboard-client-secret"
					v-model="state.client_secret"
					type="password"
					:readonly="readonly"
					:placeholder="t('integration_collaboard', 'Client secret of your Collaboard application')"
					@focus="readonly = false"
					@input="onInput">
			</div>
			<!-- <NcCheckboxRadioSwitch
				class="field"
				:checked.sync="state.use_popup"
				@update:checked="onUsePopupChanged">
				{{ t('integration_collaboard', 'Use a popup to authenticate') }}
			</NcCheckboxRadioSwitch> -->

			<div class="env-fields">
				<div class="field">
					<label for="environment">
						<EarthIcon :size="20" class="icon" />
						{{ t('integration_collaboard', 'Environment') }}
					</label>
					<select id="environment" v-model="selectedOption" @change="onInput">
						<option value="WEB">
							WEB
						</option>
						<option value="CH">
							CH
						</option>
						<option value="DE">
							DE
						</option>
						<option value="PREMISE">
							On-premise
						</option>
					</select>
				</div>
				<div v-if="selectedOption === 'PREMISE'">
					<div class="field">
						<label for="collaboard-instance">
							<EarthIcon :size="20" class="icon" />
							{{ t('integration_collaboard', 'Default Collaboard API server') }}
						</label>
						<input id="collaboard-instance"
							v-model="state.admin_api_url"
							:class="{ 'greyed-out-text': !usingCustomApiUrl, 'invalid-url': !isApiUrlValid }"
							type="text"
							placeholder="https://..."
							@input="onInput"
							@blur="fillEmptyUrls">
						<transition name="fade">
							<div v-if="usingCustomDomainUrl && !usingCustomApiUrl" class="custom-address-notice">
								<InformationOutlineIcon :size="20" class="icon" />
								<span class="notice-text">
									{{ t('integration_collaboard', 'You have specified a custom domain address. Did you forget to specify a custom API address?') }}
								</span>
							</div>
						</transition>
					</div>
					<div class="field">
						<label for="collaboard-domain-url">
							<EarthIcon :size="20" class="icon" />
							{{ t('integration_collaboard', 'Default Collaboard domain url') }}
						</label>
						<input id="collaboard-instance"
							v-model="state.admin_domain_url"
							:class="{ 'greyed-out-text': !usingCustomDomainUrl, 'invalid-url': !isDomainUrlValid }"
							type="text"
							placeholder="https://..."
							@input="onInput"
							@blur="fillEmptyUrls">
						<transition name="fade">
							<div v-if="usingCustomApiUrl && !usingCustomDomainUrl" class="custom-address-notice">
								<InformationOutlineIcon :size="20" class="icon" />
								<span class="notice-text">
									{{ t('integration_collaboard', 'You have specified a custom API address. Did you forget to specify a custom domain address?') }}
								</span>
							</div>
						</transition>
					</div>
				</div>
			</div>

			<NcCheckboxRadioSwitch
				class="field"
				:checked.sync="state.override_link_click"
				@update:checked="onOverrideChanged">
				{{ t('integration_collaboard', 'Open Collaboard board links in Nextcloud') }}
			</NcCheckboxRadioSwitch>
		</div>
	</div>
</template>

<script>
import EarthIcon from 'vue-material-design-icons/Earth.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import InformationVariantIcon from 'vue-material-design-icons/InformationVariant.vue'
import KeyIcon from 'vue-material-design-icons/Key.vue'

import CollaboardIcon from './icons/CollaboardIcon.vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import { delay } from '../utils.js'

const ENVS = {
	WEB: {
		adminDomainUrl: 'https://web.collaboard.app',
		adminApiUrl: 'https://api.collaboard.app',
	},
	CH: {
		adminDomainUrl: 'https://ch.collaboard.app',
		adminApiUrl: 'https://ch-api.collaboard.app',
	},
	DE: {
		adminDomainUrl: 'https://de.collaboard.app',
		adminApiUrl: 'https://de.collaboard.app/server',
	},
}

export default {
	name: 'AdminSettings',

	components: {
		CollaboardIcon,
		EarthIcon,
		NcCheckboxRadioSwitch,
		InformationOutlineIcon,
		InformationVariantIcon,
		KeyIcon,
	},

	props: [],

	data() {
		const state = loadState('integration_collaboard', 'admin-config')
		const selectedEnv = Object.entries(ENVS).find(env => env[1].adminApiUrl === state.admin_api_url)
		const selectedOption = selectedEnv ? selectedEnv[0] : 'PREMISE'

		return {
			state,
			// to prevent some browsers to fill fields with remembered passwords
			readonly: true,
			redirect_uri: window.location.protocol + '//' + window.location.host + generateUrl('/apps/integration_collaboard/oauth-redirect'),
			selectedOption,
		}
	},

	computed: {
		isApiUrlValid() {
			return this.isValidUrl(this.state.admin_api_url)
		},
		isDomainUrlValid() {
			return this.isValidUrl(this.state.admin_domain_url)
		},
		usingCustomUrls() {
			return this.usingCustomDomainUrl || this.usingCustomApiUrl
		},
		usingCustomDomainUrl() {
			return this.state.admin_domain_url !== this.state.default_domain_url && this.state.admin_domain_url !== ''
		},
		usingCustomApiUrl() {
			return this.state.admin_api_url !== this.state.default_api_url && this.state.admin_api_url !== ''
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onUsePopupChanged(newValue) {
			this.saveOptions({ use_popup: newValue ? '1' : '0' })
		},
		onOverrideChanged(newValue) {
			this.saveOptions({ override_link_click: newValue ? '1' : '0' })
		},
		onInput() {
			delay(() => {
				let adminApiUrl = ''
				let adminDomainUrl = ''

				if (Object.keys(ENVS).includes(this.selectedOption)) {
					adminApiUrl = ENVS[this.selectedOption].adminApiUrl
					adminDomainUrl = ENVS[this.selectedOption].adminDomainUrl
				} else {
					adminApiUrl = this.state.admin_api_url
					adminDomainUrl = this.state.admin_domain_url
				}

				this.saveOptions({
					client_id: this.state.client_id,
					client_secret: this.state.client_secret,

					admin_api_url: adminApiUrl === '' ? this.default_api_url : adminApiUrl,
					admin_domain_url: adminDomainUrl === '' ? this.default_domain_url : adminDomainUrl,
				})
			}, 2000)()
		},
		fillEmptyUrls() {
			if (this.state.admin_api_url === '') {
				this.state.admin_api_url = this.state.default_api_url
			}
			if (this.state.admin_domain_url === '') {
				this.state.admin_domain_url = this.state.default_domain_url
			}
		},
		isValidUrl(url) {
			const regex = /^(https?:\/\/)?([\da-z.-]+)\.([a-z.]{2,6})([/\w .-]*)*\/?$/
			return regex.test(url)
		},
		saveOptions(values) {
			// Validate the urls, the error is shown in the border color of the input
			if ((values.admin_api_url && !this.isValidUrl(values.admin_api_url)) || (values.admin_domain_url && !this.isValidUrl(values.admin_domain_url))) {
				return
			}

			const req = {
				values,
			}
			const url = generateUrl('/apps/integration_collaboard/admin-config')
			axios.put(url, req).then(() => {
				showSuccess(t('integration_collaboard', 'Collaboard admin options saved'))
			}).catch((error) => {
				showError(
					t('integration_collaboard', 'Failed to save Collaboard admin options')
					+ ': ' + (error.response?.request?.responseText ?? ''),
				)
				console.error(error)
			})
		},
	},
}
</script>

<style scoped lang="scss">
#collaboard_prefs {
	.collaboard-content {
		margin-left: 30px;
	}

	.fade-enter-active, .fade-leave-active {
		transition: opacity 0.5s;
	}
	.fade-enter, .fade-leave-to {
		opacity: 0;
	}
	.fade-enter-to, .fade-leave {
		opacity: 1;
	}

	.field {
		display: flex;
		align-items: center;

		input {
			width: 450px;
		}

		.greyed-out-text {
			color: var(--color-placeholder-light);
		}

		.greyed-out-text:focus {
			color: var(--color-text);
		}

		.invalid-url {
			border-color: var(--color-error);
		}

		label {
			display: flex;
			align-items: center;
			width: 300px;
		}
		.icon {
			margin-right: 8px;
		}

		.custom-address-notice {
			display: flex;
			flex-direction: row;
			margin-left: 24px;
			.notice-text {
				opacity: 0.5;
			}

			.icon {
				margin-right: 8px;
				color: var(--color-warning);
			}
		}
	}

	.settings-hint {
		display: flex;
		align-items: center;
		.icon {
			margin-right: 8px;
		}
	}

	h2 {
		display: flex;
		.icon {
			margin-right: 12px;
		}
	}

	.env-fields {
		margin: 2rem 0;
	}
}
</style>
