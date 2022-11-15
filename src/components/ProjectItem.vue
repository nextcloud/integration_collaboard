<template>
	<div class="project">
		<span class="field title">
			{{ project.name }}
		</span>
		<span class="subfield">
			{{ t('integration_collaboard', 'Updated: {date}', { date: formattedUpdated }) }}
		</span>
		<span class="subfield">
			{{ t('integration_collaboard', 'Owner: {user}', { user: project.Project.CreatedByUser }) }}
		</span>
		<div class="thumbnail-wrapper">
			<img v-if="hasImage"
				:src="imgSrc">
			<CollaboardIcon v-else :size="100" class="no-thumbnail-icon" />
		</div>
	</div>
</template>

<script>
import moment from '@nextcloud/moment'
import CollaboardIcon from './icons/CollaboardIcon.vue'

export default {
	name: 'ProjectItem',

	components: {
		CollaboardIcon,
	},

	props: {
		project: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
		}
	},

	computed: {
		hasImage() {
			return this.project.Project.Thumbnail !== null
		},
		imgSrc() {
			return 'data:image/png;base64,' + this.project.Project.Thumbnail
		},
		formattedUpdated() {
			return moment(this.project.Project.LastUpdate).format('L')
		},
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

	.subfield {
		color: var(--color-text-maxcontrast);
	}

	.no-thumbnail-icon {
		color: var(--color-text-maxcontrast);
	}

	.thumbnail-wrapper {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 250px;
		height: 180px;
		margin: 8px 0;
		img {
			background-color: white;
			width: 100%;
			height: 100%;
			object-fit: contain;
		}
	}
}
</style>
