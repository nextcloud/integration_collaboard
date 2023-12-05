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
			<div class="field">
				<label for="collaboard-url">
					<EarthIcon :size="20" class="icon" />
					{{ t('integration_collaboard', 'Collaboard instance address') }}
				</label>
				<input id="collaboard-url"
					v-model="state.url"
					type="text"
					:disabled="connected === true"
					:placeholder="t('integration_collaboard', 'Collaboard instance address')"
					@input="onInput">
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
	},

	watch: {
	},

	mounted() {
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
					url: this.state.url,
				})
			}, 2000)()
		},
		on2FAMethodChange(e) {
			this.saveOptions({
				sfa_method: e.target.value,
			})
		},
		saveOptions(values) {
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

	.field {
		display: flex;
		align-items: center;

		input,
		label {
			width: 300px;
		}

		label {
			display: flex;
			align-items: center;
		}

		button {
			margin-left: 24px;
		}

		.icon {
			margin-right: 8px;
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
