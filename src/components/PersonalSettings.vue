<template>
	<div id="collaboard_prefs" class="section">
		<h2 v-if="showTitle">
			<CollaboardIcon class="icon" />
			{{ t('integration_collaboard', 'Collaboard integration') }}
		</h2>
		<p v-if="!showOAuth && !connected" class="settings-hint">
			{{ t('integration_collaboard', 'Ask your administrator to configure the Collaboard integration in Nextcloud.') }}
		</p>
		<div id="collaboard-content">
			<NcButton v-if="!connected && showOAuth"
				id="collaboard-connect"
				class="field"
				:disabled="loading === true"
				:class="{ loading }"
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
import CloseIcon from 'vue-material-design-icons/Close.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'

import CollaboardIcon from './icons/CollaboardIcon.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import { oauthConnect } from '../utils.js'

export default {
	name: 'PersonalSettings',

	components: {
		CollaboardIcon,
		NcButton,
		OpenInNewIcon,
		CloseIcon,
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
		}
	},

	computed: {
		showOAuth() {
			return !!this.state.client_id && !!this.state.client_secret
		},
		connected() {
			return !!this.state.token && !!this.state.user_name
		},
		connectedDisplayName() {
			return this.state.user_displayname + ' (' + this.state.user_name + ')'
		},
	},

	watch: {
	},

	mounted() {
		const paramString = window.location.search.substr(1)
		// eslint-disable-next-line
		const urlParams = new URLSearchParams(paramString)
		const glToken = urlParams.get('collaboardToken')
		if (glToken === 'success') {
			showSuccess(t('integration_collaboard', 'Successfully connected to Collaboard!'))
		} else if (glToken === 'error') {
			showError(t('integration_collaboard', 'Error connecting to Collaboard:') + ' ' + urlParams.get('message'))
		}
	},

	methods: {
		onLogoutClick() {
			this.state.token = ''
			this.saveOptions({ token: '' })
		},
		saveOptions(values) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/integration_collaboard/config')
			axios.put(url, req).then((response) => {
				console.debug(response.data)
				if (response.data?.user_name !== undefined) {
					this.state.user_name = response.data.user_name
					showSuccess(t('integration_collaboard', 'Successfully connected to Collaboard!'))
					this.state.user_displayname = response.data.user_displayname
					this.state.token = 'dumdum'
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
		onConnectClick() {
			if (this.showOAuth) {
				this.connectWithOauth()
			}
		},
		connectWithOauth() {
			if (this.state.use_popup) {
				oauthConnect(this.state.client_id, this.state.admin_api_url, null, true)
					.then((data) => {
						this.state.token = 'dummyToken'
						this.state.user_name = data.userName
						this.state.user_id = data.userId
					})
			} else {
				oauthConnect(this.state.client_id, this.state.admin_api_url, 'settings')
			}
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

		input {
			width: 450px;
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
