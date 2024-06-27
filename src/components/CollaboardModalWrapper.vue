<template>
	<div id="nuiteqModalWrapper">
		<CollaboardModal v-if="open && boardUrl"
			:board-url="boardUrl"
			@close="onClose" />
	</div>
</template>

<script>
import CollaboardModal from './CollaboardModal.vue'

export default {
	name: 'CollaboardModalWrapper',

	components: {
		CollaboardModal,
	},

	props: {
	},

	data() {
		return {
			boardUrl: null,
			open: false,
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		openOn(boardUrl) {
			this.boardUrl = boardUrl
			this.open = true
		},
		onClose() {
			this.open = false
			this.$emit('close')
		},
	},
}
</script>

<style scoped lang="scss">
// nothing yet
</style>
