<template>
	<NcContent app-name="integration_collaboard">
		<NcAppContent
			:show-details="false"
			@update:showDetails="a = 2">
			<!--template slot="list">
			</template-->
			<div v-if="!connected">
				<NcEmptyContent
					:title="t('integration_collaboard', 'You are not connected to Collaboard')">
					<template #icon>
						<CogIcon />
					</template>
					<!--a :href="configureUrl">
						<NcButton
							class="configureButton">
							<template #icon>
								<CogIcon />
							</template>
							{{ t('integration_collaboard', 'Configure Collaboard integration') }}
						</NcButton>
					</a-->
				</NcEmptyContent>
				<PersonalSettings
					class="settings"
					:show-title="false"
					@connected="onConnected" />
			</div>
			<NcEmptyContent v-else-if="activeProjectCount === 0"
				:title="t('integration_collaboard', 'You haven\'t created any project yet')">
				<template #icon>
					<CollaboardIcon />
				</template>
				<template #action>
					<NcButton
						class="createButton"
						@click="onCreateProjectClick">
						<template #icon>
							<PlusIcon />
						</template>
						{{ t('integration_collaboard', 'Create a project') }}
					</NcButton>
				</template>
			</NcEmptyContent>
			<ProjectList v-else
				:projects="activeProjects" />
		</NcAppContent>
		<NcModal v-if="creationModalOpen"
			size="small"
			@close="closeCreationModal">
			plop
			<!--CreationForm
				:loading="creating"
				focus-on-field="name"
				@ok-clicked="onCreationValidate"
				@cancel-clicked="closeCreationModal" /-->
		</NcModal>
	</NcContent>
</template>

<script>
import CogIcon from 'vue-material-design-icons/Cog.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'

import CollaboardIcon from './components/icons/CollaboardIcon.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'

import ProjectList from './components/ProjectList.vue'

import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { showSuccess, showError, showUndo } from '@nextcloud/dialogs'

// import CreationForm from './components/CreationForm.vue'
import PersonalSettings from './components/PersonalSettings.vue'
import { Timer } from './utils.js'

export default {
	name: 'App',

	components: {
		ProjectList,
		CollaboardIcon,
		PersonalSettings,
		// CreationForm,
		CogIcon,
		PlusIcon,
		NcAppContent,
		NcContent,
		NcModal,
		NcEmptyContent,
		NcButton,
	},

	props: {
	},

	data() {
		return {
			creationModalOpen: false,
			selectedProjectId: '',
			state: loadState('integration_collaboard', 'collaboard-state'),
			configureUrl: generateUrl('/settings/user/connected-accounts'),
			creating: false,
		}
	},

	computed: {
		connected() {
			return !!this.state.url && !!this.state.user_name && !!this.state.token
		},
		activeProjects() {
			return this.state.project_list.filter((b) => !b.trash)
		},
		activeProjectsById() {
			return this.activeProjects.reduce((object, item) => {
				object[item.id] = item
				return object
			}, {})
		},
		activeProjectCount() {
			return this.activeProjects.length
		},
		selectedProject() {
			return this.selectedProjectId
				? this.activeProjectsById[this.selectedProjectId]
				: null
		},
	},

	watch: {
	},

	beforeMount() {
		console.debug('state', this.state)
	},

	mounted() {
	},

	methods: {
		onConnected(userName, baseUrl) {
			this.state.url = baseUrl
			this.state.user_name = userName
			this.state.token = 'plop'
			// window.location.reload()
			this.getProjects()
		},
		getProjects() {
			const url = generateUrl('/apps/integration_collaboard/projects')
			axios.get(url).then((response) => {
				this.state.project_list.push(...response.data)
			}).catch((error) => {
				showError(
					t('integration_collaboard', 'Failed to get projects')
					+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? '')
				)
				console.debug(error)
			}).then(() => {
			})
		},
		onCreateProjectClick() {
			this.creationModalOpen = true
		},
		closeCreationModal() {
			this.creationModalOpen = false
		},
		onCreationValidate(project) {
			this.creating = true
			project.trash = false
			const req = {
				name: project.name,
				password: project.password,
			}
			const url = generateUrl('/apps/integration_collaboard/projects')
			axios.post(url, req).then((response) => {
				showSuccess(t('integration_collaboard', 'New project was created in Collaboard'))
				project.id = response.data?.id
				this.state.project_list.push(project)
				this.selectedProjectId = project.id
				this.creationModalOpen = false
			}).catch((error) => {
				showError(
					t('integration_collaboard', 'Failed to create new project')
					+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? '')
				)
				console.debug(error)
			}).then(() => {
				this.creating = false
			})
		},
		onProjectClicked(projectId) {
			console.debug('select project', projectId)
			this.selectedProjectId = projectId
		},
		deleteProject(projectId) {
			console.debug('DELETE project', projectId)
			const req = {}
			const url = generateUrl('/apps/integration_collaboard/projects/{projectId}', { projectId })
			axios.delete(url, req).then((response) => {
			}).catch((error) => {
				showError(
					t('integration_collaboard', 'Failed to delete the project')
					+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? '')
				)
				console.debug(error)
			})
		},
		onProjectDeleted(projectId) {
			// deselect the project
			if (projectId === this.selectedProjectId) {
				this.selectedProjectId = ''
			}

			// hide the project nav item
			const projectIndex = this.state.project_list.findIndex((b) => b.id === projectId)
			const project = this.state.project_list[projectIndex]
			if (projectIndex !== -1) {
				project.trash = true
			}

			// cancel or delete
			const deletionTimer = new Timer(() => {
				this.deleteProject(projectId)
			}, 10000)
			showUndo(
				t('integration_collaboard', '{name} deleted', { name: project.name }),
				() => {
					deletionTimer.pause()
					project.trash = false
				},
				{ timeout: 10000 }
			)
		},
	},
}
</script>

<style scoped lang="scss">
// TODO in global css loaded by main
body {
	min-height: 100%;
	height: auto;
}

.settings {
	display: flex;
	flex-direction: column;
	align-items: center;
}

.emptyContentWrapper {
	display: flex;
	flex-direction: column;
	align-items: center;
}

.createButton,
.configureButton {
	margin-top: 12px;
}
</style>
