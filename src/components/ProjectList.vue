<template>
	<div class="project-list">
		<ProjectItem v-for="p in projects"
			:key="p.Project.ProjectId"
			:project="p" />
	</div>
</template>

<script>

import ProjectItem from './ProjectItem.vue'
export default {
	name: 'ProjectList',

	components: {
		ProjectItem,
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
	> * {
		margin: 20px;
	}
}
</style>
