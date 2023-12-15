<template>
	<div id="collaboard_prefs" class="section">
		<PasswordModal v-if="showPasswordModal"
			ref="passwordModal"
			:auth-mode="authMode"
			:login="login"
			:password.sync="password"
			:two-factor-code.sync="twoFactorCode"
			@submit="connectWithCredentials"
			@close="showPasswordModal = false" />
		<h2 v-if="showTitle">
			<CollaboardIcon class="icon" />
			{{ t('integration_collaboard', 'Collaboard integration') }}
		</h2>
		<div id="collaboard-content">
			<transition name="fade">
				<div v-if="usingCustomUrls" class="info">
					<InformationOutlineIcon :size="20" class="icon" />
					<span class="info-text">
						{{ t('integration_collaboard', 'The addresses will default to admin specified default values if left empty.') }}
					</span>
				</div>
			</transition>
			<div class="field">
				<label for="collaboard-url">
					<EarthIcon :size="20" class="icon" />
					{{ t('integration_collaboard', 'Collaboard instance address') }}
				</label>
				<input id="collaboard-url"
					v-model="state.url"
					:class="{ 'greyed-out-text': !usingCustomInstanceUrl, 'invalid-url': !isValidUrl(state.url) }"
					type="text"
					:disabled="connected === true"
					:placeholder="t('integration_collaboard', 'Collaboard instance address')"
					@input="onInput"
					@blur="fillEmptyUrls">
				<transition name="fade">
					<div v-if="usingCustomInviteUrl && !usingCustomInstanceUrl" class="custom-address-notice">
						<InformationOutlineIcon :size="20" class="icon" />
						<span class="info-text">
							{{ t('integration_collaboard', 'You have specified a custom invite address. Did you forget to specify a custom instance address?') }}
						</span>
					</div>
				</transition>
			</div>
			<div class="field">
				<label for="collaboard-invite-url">
					<EarthIcon :size="20" class="icon" />
					{{ t('integration_collaboard', 'Collaboard invite address') }}
				</label>
				<input id="collaboard-invite-url"
					v-model="state.invite_url"
					:class="{ 'greyed-out-text': !usingCustomInviteUrl, 'invalid-url': !isValidUrl(state.invite_url) }"
					type="text"
					:disabled="connected === true"
					:placeholder="t('integration_collaboard', 'Collaboard invite address')"
					@input="onInput"
					@blur="fillEmptyUrls">
				<transition name="fade">
					<div v-if="usingCustomInstanceUrl && !usingCustomInviteUrl" class="custom-address-notice">
						<InformationOutlineIcon :size="20" class="icon" />
						<span class="info-text">
							{{ t('integration_collaboard', 'You have specified a custom instance address. Did you forget to specify a custom invite address?') }}
						</span>
					</div>
				</transition>
			</div>
			<div v-show="showLogin" class="field">
				<label for="collaboard-login">
					<AccountIcon :size="20" class="icon" />
					{{ t('integration_collaboard', 'Username (e-mail)') }}
				</label>
				<input id="collaboard-login"
					v-model="login"
					type="text"
					:placeholder="t('integration_collaboard', 'Collaboard login')"
					@keyup.enter="onLoginClick">
				<NcButton id="collaboard-connect"
					type="primary"
					@click="onLoginClick">
					<template #icon>
						<OpenInNewIcon />
					</template>
					{{ t('integration_collaboard', 'Login') }}
				</NcButton>
			</div>
			<div v-if="connected" class="field">
				<label class="collaboard-connected">
					<a class="icon icon-checkmark-color" />
					{{ t('integration_collaboard', 'Connected as {user}', { user: connectedDisplayName }) }}
				</label>
				<NcButton id="collaboard-rm-cred" @click="onLogoutClick">
					<template #icon>
						<CloseIcon />
					</template>
					{{ t('integration_collaboard', 'Disconnect from Collaboard') }}
				</NcButton>
				<span />
			</div>
		</div>
	</div>
</template>

<script>
import EarthIcon from 'vue-material-design-icons/Earth.vue'
import AccountIcon from 'vue-material-design-icons/Account.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'

import CollaboardIcon from './icons/CollaboardIcon.vue'

import PasswordModal from './PasswordModal.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils.js'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'PersonalSettings',

	components: {
		CollaboardIcon,
		NcButton,
		OpenInNewIcon,
		CloseIcon,
		EarthIcon,
		InformationOutlineIcon,
		AccountIcon,
		PasswordModal,
	},

	props: {
		showTitle: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			state: loadState('integration_collaboard', 'collaboard-state'),
			loading: false,
			login: '',
			password: '',
			twoFactorCode: '',
			authMode: -1,
			showPasswordModal: false,
			showInstanceUrlNotice: false,
			showInviteUrlNotice: false,
		}
	},

	computed: {
		connected() {
			return !!this.state.token && !!this.state.url && !!this.state.user_name
		},
		connectedDisplayName() {
			return this.state.user_displayname + ' (' + this.state.user_name + ')'
		},
		showLogin() {
			return !this.connected
		},
		usingCustomUrls() {
			return this.usingCustomInviteUrl || this.usingCustomInstanceUrl
		},
		usingCustomInviteUrl() {
			return this.state.invite_url !== this.state.admin_invite_url && this.state.invite_url !== ''
		},
		usingCustomInstanceUrl() {
			return this.state.url !== this.state.admin_instance_url && this.state.url !== ''
		},

	},

	watch: {
	},

	mounted() {
		this.fillEmptyUrls()
	},

	methods: {
		onLogoutClick() {
			this.state.token = ''
			this.login = ''
			this.password = ''
			this.twoFactorCode = ''
			this.saveOptions({ token: '' })
		},
		onInput() {
			this.loading = true
			delay(() => {
				this.saveOptions({
					url: this.state.url === this.state.admin_instance_url ? '' : this.state.url,
					invite_url: this.state.invite_url === this.state.admin_invite_url ? '' : this.state.invite_url,
				})
			}, 2000)()
		},
		fillEmptyUrls() {
			if (this.state.url === '') {
				this.state.url = this.state.admin_instance_url
			}
			if (this.state.invite_url === '') {
				this.state.invite_url = this.state.admin_invite_url
			}
		},
		on2FAMethodChange(e) {
			this.saveOptions({
				sfa_method: e.target.value,
			})
		},
		isValidUrl(url) {
			if (url === '') {
				// Empty is considered valid here since it will be filled with the admin default
				return true
			}
			const regex = /^(https?:\/\/)?([\da-z.-]+)\.([a-z.]{2,6})([/\w .-]*)*\/?$/
			return regex.test(url)
		},
		saveOptions(values) {
			// Validate the urls, the error is shown in the border color of the input
			if (!this.isValidUrl(this.state.url) || !this.isValidUrl(this.state.invite_url)) {
				return
			}

			const req = {
				values,
			}
			const url = generateUrl('/apps/integration_collaboard/config')
			axios.put(url, req).then((response) => {
				if (this.login && this.password && response.data?.user_name === undefined) {
					if (response.data?.error !== undefined) {
						showError(t('integration_collaboard', 'Failed to login to Collaboard: ') + response.data.error)
					} else {
						showError(t('integration_collaboard', 'Failed to login to Collaboard'))
					}
				} else if (response.data?.user_name !== undefined) {
					this.state.user_name = response.data.user_name
					showSuccess(t('integration_collaboard', 'Successfully connected to Collaboard!'))
					this.state.user_displayname = response.data.user_displayname
					this.state.token = 'dumdum'
					this.twoFactorCode = ''
					this.twoFactorRequired = false
					this.$emit('connected', this.state.user_name, this.state.url)
				} else {
					showSuccess(t('integration_collaboard', 'Collaboard options saved'))
				}
			}).catch((error) => {
				showError(
					t('integration_collaboard', 'Failed to save Collaboard options')
					+ ': ' + (error.response?.request?.responseText ?? ''),
				)
				console.error(error)
			}).then(() => {
				this.loading = false
			})
		},
		getAuthMode() {
			const url = generateUrl('/apps/integration_collaboard/auth-mode')
			const params = {
				params: {
					login: this.login,
				},
			}
			axios.get(url, params).then((response) => {
				this.authMode = parseInt(response.data)
			}).catch((error) => {
				showError(
					t('integration_collaboard', 'Failed to get Collaboard authentication mode')
					+ ': ' + (error.response?.request?.responseText ?? ''),
				)
				console.error(error)
			})
		},
		onLoginClick() {
			if (this.login !== '') {
				const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
				if (!emailRegex.test(this.login)) {
					showError(t('integration_collaboard', 'Invalid email address'))
					return
				}
				this.getAuthMode()
				this.showPasswordModal = true
			}
		},
		connectWithCredentials() {
			this.showPasswordModal = false
			this.loading = true
			this.saveOptions({
				login: this.login,
				password: this.password,
				url: this.state.url,
				two_factor_code: this.twoFactorCode,
			})
		},
	},
}
</script>

<style scoped lang="scss">
#collaboard_prefs {
	#collaboard-content {
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

	.info-text {
		opacity: 0.5;
	}

	.info {
		display: flex;
		align-items: center;
		margin-bottom: 12px;

		.icon {
			margin-right: 8px;
		}
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

		button {
			margin-left: 24px;
		}

		.icon {
			margin-right: 8px;
		}

		.custom-address-notice {
			display: flex;
			flex-direction: row;
			margin-left: 24px;

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
			margin-right: 4px;
		}
	}

	h2 {
		display: flex;
		.icon {
			margin-right: 12px;
		}
	}
}
</style>
