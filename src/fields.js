import AccountIcon from 'vue-material-design-icons/Account.vue'
import TextIcon from 'vue-material-design-icons/Text.vue'
import ClockOutlineIcon from 'vue-material-design-icons/ClockOutline.vue'

export const fields = {
	name: {
		icon: TextIcon,
		label: t('integration_collaboard', 'Project name'),
		type: 'text',
		placeholder: t('integration_collaboard', 'Project name'),
		default: t('integration_collaboard', 'New Project'),
		mandatory: true,
	},
	created_at: {
		icon: ClockOutlineIcon,
		label: t('integration_miro', 'Created at'),
		type: 'ncDatetime',
		readonly: true,
	},
	owned_by: {
		icon: AccountIcon,
		label: t('integration_collaboard', 'Created by'),
		type: 'user',
		readonly: true,
	},
	updated_at: {
		icon: ClockOutlineIcon,
		label: t('integration_miro', 'Updated at'),
		type: 'ncDatetime',
		readonly: true,
	},
}
