<template>
	<div id="collaboard_prefs" class="section">
		<h2>
			<CollaboardIcon class="icon" />
			{{ t('integration_collaboard', 'Collaboard integration') }}
		</h2>
		<div class="collaboard-content">
			<div class="field">
				<label for="collaboard-instance">
					<EarthIcon :size="20" class="icon" />
					{{ t('integration_collaboard', 'Default Collaboard server') }}
				</label>
				<input id="collaboard-instance"
					v-model="state.admin_instance_url"
					:class="{ 'greyed-out-text': !usingCustomInstanceUrl, 'invalid-url': !isInstanceUrlValid }"
					type="text"
					placeholder="https://..."
					@input="onInput"
					@blur="fillEmptyUrls">
				<transition name="fade">
					<div v-if="usingCustomInviteUrl && !usingCustomInstanceUrl" class="custom-address-notice">
						<InformationOutlineIcon :size="20" class="icon" />
						<span class="notice-text">
							{{ t('integration_collaboard', 'You have specified a custom invite address. Did you forget to specify a custom instance address?') }}
						</span>
					</div>
				</transition>
			</div>
			<div class="field">
				<label for="collaboard-invite-url">
					<EarthIcon :size="20" class="icon" />
					{{ t('integration_collaboard', 'Default Collaboard invite url') }}
				</label>
				<input id="collaboard-instance"
					v-model="state.admin_invite_url"
					:class="{ 'greyed-out-text': !usingCustomInviteUrl, 'invalid-url': !isInviteUrlValid }"
					type="text"
					placeholder="https://..."
					@input="onInput"
					@blur="fillEmptyUrls">
				<transition name="fade">
					<div v-if="usingCustomInstanceUrl && !usingCustomInviteUrl" class="custom-address-notice">
						<InformationOutlineIcon :size="20" class="icon" />
						<span class="notice-text">
							{{ t('integration_collaboard', 'You have specified a custom instance address. Did you forget to specify a custom invite address?') }}
						</span>
					</div>
				</transition>
			</div>
		</div>
	</div>
</template>

<script>
import EarthIcon from 'vue-material-design-icons/Earth.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'

import CollaboardIcon from './icons/CollaboardIcon.vue'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils.js'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'AdminSettings',

	components: {
		CollaboardIcon,
		EarthIcon,
		InformationOutlineIcon,
	},

	props: [],

	data() {
		return {
			state: loadState('integration_collaboard', 'admin-config'),
			// to prevent some browsers to fill fields with remembered passwords
			readonly: true,
		}
	},

	computed: {
		isInstanceUrlValid() {
			return this.isValidUrl(this.state.admin_instance_url)
		},
		isInviteUrlValid() {
			return this.isValidUrl(this.state.admin_invite_url)
		},
		usingCustomUrls() {
			return this.usingCustomInviteUrl || this.usingCustomInstanceUrl
		},
		usingCustomInviteUrl() {
			return this.state.admin_invite_url !== this.state.default_invite_url && this.state.admin_invite_url !== ''
		},
		usingCustomInstanceUrl() {
			return this.state.admin_instance_url !== this.state.default_instance_url && this.state.admin_instance_url !== ''
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onInput() {
			delay(() => {
				this.saveOptions({
					admin_instance_url: this.state.admin_instance_url === '' ? this.default_instance_url : this.state.admin_instance_url,
					admin_invite_url: this.state.admin_invite_url === '' ? this.default_invite_url : this.state.admin_invite_url,
				})
			}, 2000)()
		},
		fillEmptyUrls() {
			if (this.state.admin_instance_url === '') {
				this.state.admin_instance_url = this.state.default_instance_url
			}
			if (this.state.admin_invite_url === '') {
				this.state.admin_invite_url = this.state.default_invite_url
			}
		},
		isValidUrl(url) {
			const regex = /^(https?:\/\/)?([\da-z.-]+)\.([a-z.]{2,6})([/\w .-]*)*\/?$/
			return regex.test(url)
		},
		saveOptions(values) {
			// Validate the urls, the error is shown in the border color of the input
			if (!this.isValidUrl(values.admin_instance_url) || !this.isValidUrl(values.admin_invite_url)) {
				return
			}

			const req = {
				values,
			}
			const url = generateUrl('/apps/integration_collaboard/admin-config')
			axios.put(url, req).then((response) => {
				showSuccess(t('integration_collaboard', 'Collaboard admin options saved'))
			}).catch((error) => {
				showError(
					t('integration_collaboard', 'Failed to save Collaboard admin options')
					+ ': ' + (error.response?.request?.responseText ?? ''),
				)
				console.debug(error)
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
}
</style>
