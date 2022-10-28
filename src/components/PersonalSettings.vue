<template>
	<div id="collaboard_prefs" class="section">
		<h2>
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
			<div v-show="showLoginPassword" class="field">
				<label for="collaboard-login">
					<AccountIcon :size="20" class="icon" />
					{{ t('integration_collaboard', 'Login') }}
				</label>
				<input id="collaboard-login"
					v-model="login"
					type="text"
					:placeholder="t('integration_collaboard', 'Collaboard login')"
					@keyup.enter="onConnectClick">
			</div>
			<div v-show="showLoginPassword" class="field">
				<label for="collaboard-password">
					<LockIcon :size="20" class="icon" />
					{{ t('integration_collaboard', 'Password or OTP Code') }}
				</label>
				<input id="collaboard-password"
					v-model="password"
					type="password"
					:placeholder="t('integration_collaboard', 'Collaboard password')"
					@keyup.enter="onConnectClick">
			</div>
			<div v-show="showLoginPassword && !twoFactorRequired">
				<br>
				<p class="settings-hint">
					<InformationOutlineIcon :size="24" class="icon" />
					{{ t('integration_collaboard', 'You can ignore the "Preferred second factor" setting if you don\'t have "Two factor authentication" enabled in Collaboard') }}
				</p>
				<div class="field">
					<label for="collaboard-2fa-method">
						<LockIcon :size="20" class="icon" />
						{{ t('integration_collaboard', 'Preferred second factor') }}
					</label>
					<select id="collaboard-2fa-method"
						v-model="state.sfa_method"
						@change="on2FAMethodChange">
						<option value="otp">
							{{ t('integration_collaboard', 'OTP client app') }}
						</option>
						<option value="email">
							{{ t('integration_collaboard', 'Email') }}
						</option>
						<!--option value="sms">
							{{ t('integration_collaboard', 'SMS') }}
						</option-->
					</select>
				</div>
			</div>
			<div v-show="showLoginPassword && twoFactorRequired" class="field">
				<label for="collaboard-2fa">
					<LockIcon :size="20" class="icon" />
					{{ t('integration_collaboard', 'Second authentication factor') }}
				</label>
				<input id="collaboard-2fa"
					ref="sfa_input"
					v-model="twoFactorCode"
					type="text"
					placeholder="xxxxxx"
					@keyup.enter="onConnectClick">
			</div>
			<NcButton v-if="!connected"
				id="collaboard-connect"
				:disabled="loading === true || !(login && password)"
				:class="{ loading, field: true }"
				@click="onConnectClick">
				<template #icon>
					<OpenInNewIcon />
				</template>
				{{ t('integration_collaboard', 'Connect to Collaboard') }}
			</NcButton>
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
import LockIcon from 'vue-material-design-icons/Lock.vue'
import AccountIcon from 'vue-material-design-icons/Account.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'

import CollaboardIcon from './icons/CollaboardIcon.vue'

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
		LockIcon,
		InformationOutlineIcon,
	},

	props: [],

	data() {
		return {
			state: loadState('integration_collaboard', 'user-config'),
			loading: false,
			login: '',
			password: '',
			twoFactorRequired: false,
			twoFactorCode: '',
		}
	},

	computed: {
		connected() {
			return !!this.state.token && !!this.state.url && !!this.state.user_name
		},
		connectedDisplayName() {
			return this.state.user_displayname + ' (' + this.state.user_name + ')'
		},
		showLoginPassword() {
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
				if (response.data.user_name !== undefined) {
					this.state.user_name = response.data.user_name
					if (this.login && this.password && response.data.user_name === '') {
						if (response.data.two_factor_required) {
							this.twoFactorRequired = true
							this.$nextTick(() => {
								this.$refs.sfa_input.focus()
							})
							showError(t('integration_collaboard', 'Collaboard second factor is required'))
						} else {
							if (this.twoFactorRequired) {
								showError(t('integration_collaboard', 'Invalid login/password or second factor'))
							} else {
								showError(t('integration_collaboard', 'Invalid login/password'))
							}
						}
					} else if (response.data.user_name) {
						showSuccess(t('integration_collaboard', 'Successfully connected to Collaboard!'))
						this.state.user_name = response.data.user_name
						this.state.user_displayname = response.data.user_displayname
						this.state.token = 'dumdum'
						this.twoFactorCode = ''
						this.twoFactorRequired = false
					}
				} else {
					showSuccess(t('integration_collaboard', 'Collaboard options saved'))
				}
			}).catch((error) => {
				showError(
					t('integration_collaboard', 'Failed to save Collaboard options')
					+ ': ' + (error.response?.request?.responseText ?? '')
				)
				console.error(error)
			}).then(() => {
				this.loading = false
			})
		},
		onConnectClick() {
			if (this.login && this.password) {
				this.connectWithCredentials()
			}
		},
		connectWithCredentials() {
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
