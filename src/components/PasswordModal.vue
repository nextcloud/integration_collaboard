<template>
	<div class="meme-caps-modal">
		<NcModal size="normal"
			name="Collaboard login modal"
			:out-transition="true"
			@close="closeModal">
			<div class="modal-content">
				<h2>
					<CollaboardIcon class="icon" />
					{{ t('integration_collaboard', 'Collaboard login') }}
				</h2>
				<div class="username-display">
					<AccountIcon class="icon" :size="20" />
					{{ t('integration_collaboard', 'Username:') }}
					<div class="username-wrapper">
						<b>{{ login }}</b>
					</div>
				</div>
				<div class="input-wrapper">
					<LockIcon class="icon" :size="20" />
					<label for="password-field">
						{{ t('integration_collaboard', 'Password:') }}
					</label>
					<NcTextField id="password-field"
						class="input-box"
						:value.sync="password"
						type="password"
						placeholder="Password" />
				</div>
				<div v-if="authMode === 3" class="input-wrapper">
					<!-- authMode 3 => username+password+2FA -->
					<LockIcon class="icon" :size="20" />
					<label for="second-factor-field">
						{{ t('integration_collaboard', 'Second factor:') }}
					</label>
					<NcTextField id="second-factor-field"
						class="input-box"
						:value.sync="twoFactorCode"
						type="text"
						placeholder="Second factor code" />
				</div>
				<NcButton v-if="showOtpRequestButton" @click="onAsk2FAClick">
					{{ authMode === 2 ? t('integration_collaboard', 'Send OTP via email') : t('integration_collaboard', 'Send second factor code via email') }}
				</NcButton>
				<div class="vertical-spacer" />
				<div class="footer">
					<NcButton type="primary" @click="submitPassword">
						{{ t('integration_collaboard', 'Login') }}
					</NcButton>
					<NcButton @click="closeModal">
						{{ t('integration_collaboard', 'Cancel') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import LockIcon from 'vue-material-design-icons/Lock.vue'
import AccountIcon from 'vue-material-design-icons/Account.vue'
import CollaboardIcon from './icons/CollaboardIcon.vue'

import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'PasswordModal',
	components: {
		NcModal,
		NcButton,
		NcTextField,
		LockIcon,
		AccountIcon,
		CollaboardIcon,
	},
	props: {
		login: {
			type: String,
			default: '',
			required: true,
		},
		authMode: {
			type: Number,
			default: 0,
			required: true,
		},
	},
	data() {
		return {
			password: '',
			twoFactorCode: '',
		}
	},
	computed: {
		showOtpRequestButton() {
			return this.authMode === 2 || this.authMode === 3
		},
	},
	methods: {
		closeModal() {
			this.$emit('close')
		},
		submitPassword() {
			// Sync the password with the parent component using rsync
			this.$emit('update:password', this.password)
			this.$emit('update:twoFactorCode', this.twoFactorCode)
			this.$emit('submit')
			this.closeModal()
		},
		onAsk2FAClick() {
			const url = generateUrl('/apps/integration_collaboard/email-2fa-password')
			const params = {
				params: {
					login: this.login,
				},
			}
			axios.get(url, params).then((response) => {
				showSuccess(t('integration_collaboard', 'Collaboard OTP code sent via email'))
			}).catch((error) => {
				showError(
					t('integration_collaboard', 'Failed to ask OTP password code by email')
					+ ': ' + (error.response?.request?.responseText ?? ''),
				)
				console.error(error)
			})
		},
	},
}
</script>

<style scoped>
.meme-caps-modal {
	width: 100%;
	align-items: center;
	justify-content: center;
	padding: 12px 0px 0px 12px;
}

.modal-content {
	flex-grow: 1;
	margin: 24px;
	min-height: 300px;
	height: 100%;
	justify-content: start;
	align-items: center;
	display: flex;
	flex-direction: column;

	h2 {
		display: flex;
		align-items: center;

		.icon {
			margin-right: 12px;
		}
	}

	.username-display {
		margin-top: 24px;
		display: flex;
		flex-direction: row;
		width: 100%;

		.username-wrapper {
			display: flex;
			flex-direction: row;
			width: 60%;
			justify-content: center;
			margin-left: auto;
		}
		.icon {
			margin-right: 4px;
		}
	}

	.input-wrapper {
		display: flex;
		flex-direction: row;
		width: 100%;
		margin-top: 24px;
		align-items: center;
		align-content: center;
		white-space: nowrap;

		.input-box {
			width: 60%;
			margin-left: auto;
		}

		.icon {
			margin-right: 4px;
		}
	}

	button {
		margin-top: 24px;
	}

	.vertical-spacer {
		flex-grow: 1;
		display: flex;
		flex-direction: column;
	}

	.footer {
		display: flex;
		justify-content: flex-end;
		align-items: flex-end;
		flex-direction: row;
		margin-top: 32px;
		height: 100%;
		width: 100%;
		button {
			margin-left: 12px;
		}
	}
}
</style>
