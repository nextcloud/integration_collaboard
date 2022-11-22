<template>
	<div class="link-form">
		<h3>
			<LinkVariantIcon class="icon" :size="20" />
			{{ t('integration_collaboard', 'Invitation link') }}
		</h3>
		<div class="field">
			<label>
				{{ t('integration_collaboard', 'Registered users permissions') }}
			</label>
			<NcMultiselect
				:value="userPermission"
				:options="permissionList"
				label="label"
				:placeholder="t('integration_collaboard', 'Registered users permissions')"
				@input="userPermissionChanged" />
		</div>
		<NcCheckboxRadioSwitch
			:checked.sync="allowGuests"
			type="switch"
			class="ncradio"
			@update:checked="allowGuestsChanged">
			{{ t('integration_collaboard', 'Allow guests') }}
		</NcCheckboxRadioSwitch>
		<div class="field">
			<label :class="{ 'disabled-text': !allowGuests }">
				{{ t('integration_collaboard', 'Guest permissions') }}
			</label>
			<NcMultiselect
				:value="guestPermission"
				:options="permissionList"
				:disabled="!allowGuests"
				label="label"
				:placeholder="t('integration_collaboard', 'Guest permissions')"
				@input="guestPermissionChanged" />
		</div>
		<div class="field">
			<label>
				{{ t('integration_collaboard', 'Password (optional)') }}
			</label>
			<div class="password-input-wrapper">
				<input v-model="password"
					:type="showPassword ? 'text' : 'password'"
					:placeholder="t('integration_collaboard', 'no password')">
				<EyeOutlineIcon v-if="showPassword" @click="showPassword = false" />
				<EyeOffOutlineIcon v-else @click="showPassword = true" />
			</div>
		</div>
		<div class="field">
			<label>
				{{ t('integration_collaboard', 'Registered users permission') }}
			</label>
			<NcMultiselect
				:value="validForMinutes"
				:options="expirations"
				label="label"
				:placeholder="t('integration_collaboard', 'Expires in')"
				@input="validForMinutesChanged" />
		</div>
		<NcButton v-if="link"
			class="bottom-button"
			@click="reset">
			<template #icon>
				<ArrowLeftIcon />
			</template>
			{{ t('integration_collaboard', 'Create another link') }}
		</NcButton>
		<NcButton v-else
			class="bottom-button"
			@click="onCreateClick">
			<template #icon>
				<NcLoadingIcon v-if="creatingLink" />
				<LinkVariantIcon v-else />
			</template>
			{{ t('integration_collaboard', 'Create link') }}
		</NcButton>
		<input v-if="link"
			:value="link"
			type="text"
			class="link-input"
			:readonly="true">
		<div v-if="link && isTalkEnabled"
			class="talk-button-wrapper">
			<NcButton @click="showTalkModal = true">
				<template #icon>
					<TalkIcon :size="20" />
				</template>
				{{ t('integration_collaboard', 'Share link to a Talk conversation') }}
			</NcButton>
			<SendModal v-if="showTalkModal"
				:project="project"
				:project-link="link"
				@close="showTalkModal = false" />
		</div>
	</div>
</template>

<script>
import EyeOutlineIcon from 'vue-material-design-icons/EyeOutline.vue'
import EyeOffOutlineIcon from 'vue-material-design-icons/EyeOffOutline.vue'
import LinkVariantIcon from 'vue-material-design-icons/LinkVariant.vue'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'

import TalkIcon from './talk/TalkIcon.vue'

import SendModal from './talk/SendModal.vue'

import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcMultiselect from '@nextcloud/vue/dist/Components/NcMultiselect.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'

const permissions = {
	none: {
		label: t('integration_collaboard', 'No access'),
		value: 0,
	},
	view: {
		label: t('integration_collaboard', 'Can view'),
		value: 1,
	},
	edit: {
		label: t('integration_collaboard', 'Can edit'),
		value: 2,
	},
}

const expirations = [
	{
		label: t('integration_collaboard', 'Never'),
		value: 14400000,
	},
	{
		label: t('integration_collaboard', '1 hour'),
		value: 60,
	},
	{
		label: t('integration_collaboard', '4 hours'),
		value: 4 * 60,
	},
	{
		label: t('integration_collaboard', '12 hours'),
		value: 12 * 60,
	},
	{
		label: t('integration_collaboard', '1 day'),
		value: 24 * 60,
	},
	{
		label: t('integration_collaboard', '2 days'),
		value: 2 * 24 * 60,
	},
	{
		label: t('integration_collaboard', '5 days'),
		value: 2 * 24 * 60,
	},
	{
		label: t('integration_collaboard', '10 days'),
		value: 2 * 24 * 60,
	},
	{
		label: t('integration_collaboard', '30 days'),
		value: 2 * 24 * 60,
	},
	{
		label: t('integration_collaboard', '60 days'),
		value: 2 * 24 * 60,
	},
]

export default {
	name: 'CreateLinkForm',

	components: {
		NcMultiselect,
		EyeOutlineIcon,
		EyeOffOutlineIcon,
		NcCheckboxRadioSwitch,
		NcButton,
		LinkVariantIcon,
		NcLoadingIcon,
		ArrowLeftIcon,
		TalkIcon,
		SendModal,
	},

	inject: ['isTalkEnabled'],

	props: {
		project: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			showTalkModal: false,
			creatingLink: false,
			permissions,
			expirations,
			userPermission: permissions.view,
			allowGuests: false,
			guestPermission: permissions.none,
			password: '',
			showPassword: false,
			validForMinutes: expirations[0],
			link: '',
		}
	},

	computed: {
		permissionList() {
			return Object.values(this.permissions)
		},
	},

	watch: {
	},

	beforeMount() {
	},

	mounted() {
	},

	methods: {
		allowGuestsChanged(allowed) {
			if (allowed) {
				this.guestPermission = permissions.view
			} else {
				this.guestPermission = permissions.none
			}
		},
		userPermissionChanged(newValue) {
			if (newValue !== null) {
				this.userPermission = newValue
			}
		},
		guestPermissionChanged(newValue) {
			if (newValue !== null) {
				this.guestPermission = newValue
			}
		},
		validForMinutesChanged(newValue) {
			if (newValue !== null) {
				this.validForMinutes = newValue
			}
		},
		reset() {
			this.userPermission = permissions.view
			this.allowGuests = false
			this.guestPermission = permissions.none
			this.password = ''
			this.showPassword = false
			this.validForMinutes = expirations[0]
			this.link = ''
		},
		onCreateClick() {
			this.creatingLink = true
			const req = {
				projectId: this.project.id,
				invitationUrl: 'https://web.collaboard.app/acceptProjectInvitation',
				memberPermission: this.userPermission.value,
				password: this.password ? this.password : undefined,
				validForMinutes: this.validForMinutes.value,
				guestIdentificationRequired: !this.allowGuests,
				guestPermission: this.guestPermission.value,
			}
			const url = generateUrl('/apps/integration_collaboard/invitation-link')
			axios.post(url, req).then((response) => {
				showSuccess(t('integration_collaboard', 'Invitation link was created'))
				this.link = response.data.InvitationUrl
			}).catch((error) => {
				showError(
					t('integration_collaboard', 'Failed to create invitation link')
					+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? '')
				)
				console.debug(error)
			}).then(() => {
				this.creatingLink = false
			})
		},
	},
}
</script>

<style scoped lang="scss">
.link-form {
	display: flex;
	flex-direction: column;

	h3 {
		display: flex;
		justify-content: center;
		.icon {
			margin-right: 8px;
		}
	}

	.field {
		display: flex;
		align-items: center;
		padding: 8px;
		border-radius: var(--border-radius);
		flex-wrap: wrap;

		&:hover {
			background-color: var(--color-background-hover);
		}

		> * {
			width: 47%;
		}
	}

	.talk-button-wrapper,
	.bottom-button {
		align-self: end;
	}

	.link-input {
		width: 100%;
	}

	.password-input-wrapper {
		display: flex;
		align-items: center;
		label,
		input {
			flex-grow: 1;
		}
	}

	.disabled-text {
		color: var(--color-text-maxcontrast);
	}
}
</style>
