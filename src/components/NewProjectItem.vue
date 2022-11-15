<template>
	<div class="project"
		@click="$emit('click')">
		<span class="title">
			{{ t('integration_collaboard', 'Create a new project') }}
			<br>
			<br>
			<br>
		</span>
		<div class="icon-wrapper">
			<PlusIcon :size="100" class="create-icon" />
		</div>
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
	cursor: pointer;

	&:hover {
		box-shadow: 0 0 10px var(--color-text-maxcontrast);
	}

	* {
		cursor: pointer;
	}

	.title {
		font-weight: bold;
	}

	.icon-wrapper {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 250px;
		height: 180px;
		margin: 8px 0;

		.create-icon {
			color: var(--color-text-maxcontrast);
		}
	}
}
</style>
