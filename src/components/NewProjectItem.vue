<template>
	<div class="project"
		@click="$emit('click')">
		<span class="title">
			{{ t('integration_collaboard', 'Create a new project') }}
		</span>
		<PlusIcon :size="24" />
	</div>
</template>

<script>
import PlusIcon from 'vue-material-design-icons/Plus.vue'

export default {
	name: 'NewProjectItem',

	components: {
		PlusIcon,
	},

	props: {
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
.project {
	display: flex;
	flex-direction: column;
	align-items: start;
	box-shadow: 0 0 10px var(--color-box-shadow);
	border-radius: var(--border-radius-large);
	padding: 12px 20px;

	.title {
		font-weight: bold;
	}
}
</style>
