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
					type="text"
					placeholder="https://..."
					@input="onInput">
			</div>
		</div>
	</div>
</template>

<script>
import EarthIcon from 'vue-material-design-icons/Earth.vue'

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
	},

	props: [],

	data() {
		return {
			state: loadState('integration_collaboard', 'admin-config'),
			// to prevent some browsers to fill fields with remembered passwords
			readonly: true,
		}
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onInput() {
			delay(() => {
				this.saveOptions({
					admin_instance_url: this.state.admin_instance_url,
				})
			}, 2000)()
		},
		saveOptions(values) {
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
