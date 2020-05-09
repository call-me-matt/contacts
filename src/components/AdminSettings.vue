<template>
	<div id="contacts" class="section">
		<h2>{{ t('contacts', 'Contacts') }}</h2>
		<p>
			<input
				id="allowSocialSync"
				v-model="allowSocialSync"
				type="checkbox"
				class="checkbox"
				@change="updateSetting('allowSocialSync')">
			<label for="allowSocialSync">{{ t('contacts', 'Allow updating avatars from social media') }}</label>
		</p>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
export default {
	name: 'AdminSettings',
	data() {
		return {
			'allowSocialSync': loadState('contacts', 'allowSocialSync') === 'yes',
		}
	},
	methods: {
		updateSetting(setting) {
			axios.post(generateUrl('apps/contacts/api/v1/social/config/global/' + setting), {
				allow: this[setting] ? 'yes' : 'no',
			})
		},
	},
}
</script>
