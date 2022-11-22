<template>
	<div class="details-wrapper">
		<div class="projectDetails">
			<NcButton
				v-tooltip.top="{ content: t('integration_collaboard', 'Back to project list') }"
				class="header-button left"
				@click="$emit('back')">
				<template #icon>
					<ArrowLeftIcon :size="20" />
				</template>
			</NcButton>
			<NcButton
				v-tooltip.top="{ content: t('integration_collaboard', 'Delete project') }"
				class="header-button right"
				@click="$emit('delete-project', project.id)">
				<template #icon>
					<DeleteIcon :size="20" />
				</template>
			</NcButton>
			<div class="header">
				<h2>
					{{ project.name }}
				</h2>
			</div>
			<div class="links">
				<div class="link">
					<div class="leftPart">
						<LinkVariantIcon :size="20" />
						<label>
							{{ t('integration_collaboard', 'Project personal link') }}
						</label>
					</div>
					<div class="rightPart linkInputWrapper">
						<input type="text" :readonly="true" :value="projectLink">
						<a :href="projectLink" @click.prevent.stop="copyLink(false)">
							<NcButton v-tooltip.bottom="{ content: t('integration_collaboard', 'Copy to clipboard') }">
								<template #icon>
									<CheckIcon v-if="projectLinkCopied"
										class="copiedIcon"
										:size="16" />
									<ClippyIcon v-else
										:size="16" />
								</template>
							</NcButton>
						</a>
					</div>
				</div>
				<div class="buttons">
					<a :href="projectLink" target="_blank">
						<NcButton>
							<template #icon>
								<OpenInNewIcon :size="20" />
							</template>
							{{ t('integration_collaboard', 'Open in a new tab') }}
						</NcButton>
					</a>
				</div>
			</div>
			<div class="fields">
				<div v-for="(field, fieldId) in fieldsToDisplay"
					:key="fieldId"
					class="field">
					<div class="leftPart">
						<component :is="field.icon"
							v-if="field.icon"
							:size="20" />
						<span v-else class="emptyIcon" />
						<label class="fieldLabel">
							{{ field.label }}
						</label>
					</div>
					<div class="rightPart">
						<label v-if="['ncCheckbox'].includes(field.type)"
							:id="'project-' + fieldId + '-value'"
							class="fieldValue multiple">
							<component :is="field.enabledIcon"
								v-if="project[fieldId] && field.enabledIcon"
								:size="20" />
							<component :is="field.disabledIcon"
								v-else-if="!project[fieldId] && field.disabledIcon"
								:size="20" />
							<CheckboxMarkedIcon v-else-if="project[fieldId]" :size="20" />
							<CheckboxBlankOutlineIcon v-else-if="!project[fieldId]" :size="20" />
							{{ project[fieldId] ? t('integration_collaboard', 'Enabled') : t('integration_collaboard', 'Disabled') }}
						</label>
						<label v-if="['ncSwitch'].includes(field.type)"
							:id="'project-' + fieldId + '-value'"
							class="fieldValue multiple">
							<component :is="field.enabledIcon"
								v-if="project[fieldId] && field.enabledIcon"
								:size="20" />
							<component :is="field.disabledIcon"
								v-else-if="!project[fieldId] && field.disabledIcon"
								:size="20" />
							<ToggleSwitchIcon v-else-if="project[fieldId]" :size="20" />
							<ToggleSwitchOffOutlineIcon v-else-if="!project[fieldId]" :size="20" />
							{{ project[fieldId] ? t('integration_collaboard', 'Enabled') : t('integration_collaboard', 'Disabled') }}
						</label>
						<div v-if="['user'].includes(field.type)"
							:id="'project-' + fieldId + '-value'"
							class="fieldValue user-wrapper">
							<NcAvatar v-if="project[fieldId].photoUrl"
								class="user-avatar"
								:url="getUserPhotoUrl(project[fieldId].photoUrl, project[fieldId].userName)" />
							<label>
								{{ project[fieldId].userName }}
							</label>
						</div>
						<label v-if="['text'].includes(field.type)"
							:id="'project-' + fieldId + '-value'"
							class="fieldValue">
							{{ project[fieldId] }}
						</label>
						<div v-if="['password'].includes(field.type)" class="password-input-wrapper">
							<label
								:id="'project-' + fieldId + '-value'"
								class="fieldValue">
								{{ field.view ? project[fieldId] : discify(project[fieldId]) }}
							</label>
							<EyeOutlineIcon v-if="field.view" @click="field.view = false" />
							<EyeOffOutlineIcon v-else @click="field.view = true" />
						</div>
						<label v-else-if="['ncDate'].includes(field.type)"
							:id="'project-' + fieldId + '-value'"
							class="fieldValue">
							{{ getFormattedDate(project[fieldId]) }}
						</label>
						<label v-else-if="['ncDatetime'].includes(field.type)"
							:id="'project-' + fieldId + '-value'"
							class="fieldValue">
							{{ getFormattedDatetime(project[fieldId]) }}
						</label>
						<label v-else-if="['ncColor'].includes(field.type)"
							:id="'project-' + fieldId + '-value'"
							class="fieldValue">
							<div class="colorDot" :style="{ 'background-color': project[fieldId] }" />
						</label>
						<textarea v-if="['textarea'].includes(field.type)"
							:id="'project-' + fieldId + '-value'"
							class="fieldValue"
							:value="project[fieldId]"
							:readonly="true" />
						<label v-else-if="['select', 'customRadioSet', 'ncRadioSet'].includes(field.type)"
							:for="'project-' + fieldId + '-value'"
							class="fieldValue multiple">
							<component :is="field.options[project[fieldId]].icon"
								v-if="field.options[project[fieldId]].icon"
								:size="20" />
							{{ field.options[project[fieldId]].label }}
						</label>
						<label v-else-if="['ncCheckboxSet'].includes(field.type)"
							:for="'project-' + fieldId + '-value'"
							class="fieldValue multipleVertical">
							<div v-for="optionId in project[fieldId]"
								:key="optionId"
								class="oneValue">
								<component :is="field.options[optionId].icon"
									v-if="field.options[optionId].icon"
									:size="20" />
								{{ field.options[optionId].label }}
							</div>
						</label>
					</div>
				</div>
				<hr>
				<CreateLinkForm
					:project="project" />
			</div>
		</div>
	</div>
</template>

<script>
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import ShieldLinkVariantIcon from 'vue-material-design-icons/ShieldLinkVariant.vue'
import LinkVariantIcon from 'vue-material-design-icons/LinkVariant.vue'
import ToggleSwitchIcon from 'vue-material-design-icons/ToggleSwitch.vue'
import ToggleSwitchOffOutlineIcon from 'vue-material-design-icons/ToggleSwitchOffOutline.vue'
import CheckboxMarkedIcon from 'vue-material-design-icons/CheckboxMarked.vue'
import CheckboxBlankOutlineIcon from 'vue-material-design-icons/CheckboxBlankOutline.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import EyeOutlineIcon from 'vue-material-design-icons/EyeOutline.vue'
import EyeOffOutlineIcon from 'vue-material-design-icons/EyeOffOutline.vue'
import DockWindowIcon from 'vue-material-design-icons/DockWindow.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'

import ClippyIcon from './icons/ClippyIcon.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'

import CreateLinkForm from './CreateLinkForm.vue'

import { Timer } from '../utils.js'
import { fields } from '../fields.js'
import moment from '@nextcloud/moment'
import { generateUrl } from '@nextcloud/router'

import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'ProjectDetails',

	components: {
		CreateLinkForm,
		NcButton,
		NcAvatar,
		ClippyIcon,
		LinkVariantIcon,
		ShieldLinkVariantIcon,
		ToggleSwitchIcon,
		ToggleSwitchOffOutlineIcon,
		CheckboxBlankOutlineIcon,
		CheckboxMarkedIcon,
		CheckIcon,
		EyeOutlineIcon,
		EyeOffOutlineIcon,
		OpenInNewIcon,
		DockWindowIcon,
		ArrowLeftIcon,
		DeleteIcon,
	},

	props: {
		project: {
			type: Object,
			required: true,
		},
		talkEnabled: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			fields,
			projectLinkCopied: false,
		}
	},

	computed: {
		projectLink() {
			return 'https://web.collaboard.app/collaboard/' + this.project.id
		},
		fieldsToDisplay() {
			const result = {}
			Object.keys(this.fields).forEach((fieldId) => {
				const field = this.fields[fieldId]
				// do not display password if not set
				if (!['password'].includes(field.type) || this.project[fieldId]) {
					result[fieldId] = field
				}
			})
			return result
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		async copyLink() {
			const link = this.projectLink
			try {
				await this.$copyText(link)
				this.projectLinkCopied = true
				showSuccess(t('integration_collaboard', 'Project link copied!'))
				// eslint-disable-next-line
				new Timer(() => {
					this.projectLinkCopied = false
				}, 5000)
			} catch (error) {
				console.error(error)
				showError(t('integration_collaboard', 'Link could not be copied to clipboard'))
			}
		},
		getFormattedDate(date) {
			return moment(date).format('LL')
		},
		getFormattedDatetime(date) {
			return moment(date).format('LLL')
		},
		discify(string) {
			return '•'.repeat(string.length)
		},
		getUserPhotoUrl(endUrl, userName) {
			return generateUrl('/apps/integration_collaboard/photo?url={endUrl}&userName={userName}', { endUrl, userName })
		},
	},
}
</script>

<style scoped lang="scss">
.details-wrapper {
	margin: 24px;
	display: flex;
	align-items: center;
	justify-content: center;
}

.projectDetails {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	position: relative;
	min-width: 550px;
	max-width: 700px;
	// background-color: var(--color-primary-element-lighter);
	// background-color: var(--color-primary-light);
	box-shadow: 0 0 10px var(--color-box-shadow);
	border-radius: var(--border-radius-large);
	padding: 16px;

	.header-button {
		position: absolute;
		top: 12px;
		&.left {
			left: 12px;
		}
		&.right {
			right: 12px;
		}
	}

	.header {
		display: flex;
		align-items: center;
		justify-content: center;
		margin-bottom: 32px;
		h2 {
			margin: 0 0 0 12px;
		}
	}
	.fields {
		display: flex;
		flex-direction: column;
		width: 100%;

		hr {
			width: 100%;
			margin-bottom: 24px;
		}

		.field {
			display: flex;
			align-items: center;
			margin: 8px 0;
			padding: 8px;
			border-radius: var(--border-radius);
			flex-wrap: wrap;

			&:hover {
				background-color: var(--color-background-hover);
			}
			.emptyIcon {
				width: 20px;
			}
			.user-wrapper {
				display: flex;
				align-items: center;
				.user-avatar {
					margin-right: 8px;
				}
			}
			.fieldValue {
				&.multiple {
					display: flex;
					> * {
						margin-right: 8px;
					}
				}
				&.multipleVertical {
					display: flex;
					flex-direction: column;
					.oneValue {
						display: flex;
						> * {
							margin-right: 8px;
						}
					}
				}
			}
			textarea.fieldValue {
				width: 300px;
				height: 65px;
				resize: none;
			}
			.colorDot {
				width: 24px;
				height: 24px;
				border-radius: 50%;
			}
			.password-input-wrapper {
				display: flex;
				align-items: center;
				width: 100%;
				label {
					flex-grow: 1;
				}
			}
		}
	}
	.links {
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: center;
		width: 100%;

		.link {
			display: flex;
			align-items: center;
			margin: 6px 0 6px 0;
			width: 100%;
			flex-wrap: wrap;

			> * {
				margin: 0 8px 0 8px;
			}
			.linkInputWrapper {
				display: flex;
				align-items: center;
				justify-content: center;
				input {
					flex-grow: 1;
				}
				a {
					margin-left: 8px;
				}
			}
			.copiedIcon {
				color: var(--color-success);
			}
		}
		.buttons {
			display: flex;
			align-items: center;
			justify-content: center;
			flex-wrap: wrap;
			//max-width: 600px;
			margin-bottom: 8px;
			> * {
				margin: 4px 8px;
			}
			.talk-button-wrapper {
				display: flex;
				justify-content: center;
			}
		}
	}

	.rightPart,
	.leftPart {
		min-width: 200px;
		display: flex;
		flex-grow: 1;

		> * {
			margin: 0 8px 0 8px;
		}
	}
}
</style>
