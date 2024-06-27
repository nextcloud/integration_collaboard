<template>
	<div class="project-list">
		<NewProjectItem @click="$emit('new-project')" />
		<NcButton
			v-tooltip.top="{ content: t('integration_collaboard', 'Reload project list from Collaboard') }"
			@click="$emit('reload')">
			<template #icon>
				<ReloadIcon :size="20" />
			</template>
		</NcButton>
		<ProjectItem v-for="p in projects"
			:key="p.id"
			:project="p"
			@click="$emit('project-click', p.id)"
			@delete="$emit('delete-project', p.id)" />
	</div>
</template>

<script>
import ReloadIcon from 'vue-material-design-icons/Reload.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import NewProjectItem from './NewProjectItem.vue'
import ProjectItem from './ProjectItem.vue'
export default {
	name: 'ProjectList',

	components: {
		NewProjectItem,
		ProjectItem,
		NcButton,
		ReloadIcon,
	},

	props: {
		projects: {
			type: Array,
			required: true,
		},
	},

	data() {
		return {
		}
	},

	computed: {
	},

	watch: {
	},

	beforeMount() {
	},

	mounted() {
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
.project-list {
	padding: 50px;
	display: flex;
	flex-direction: row;
	flex-wrap: wrap;
	align-items: center;
	> * {
		margin: 20px;
	}
}
</style>
