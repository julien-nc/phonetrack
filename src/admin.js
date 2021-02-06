import { generateUrl } from '@nextcloud/router'
import $ from 'jquery'
import axios from '@nextcloud/axios'

(function() {
	if (!OCA.PhoneTrack) {
		OCA.PhoneTrack = {}
	}
})()

function setPhoneTrackQuota(val) {
	const url = generateUrl('/apps/phonetrack/setPointQuota')
	const req = {
		quota: val,
	}
	axios.post(url, req)
		.then((response) => {
			OC.Notification.showTemporary(
				t('phonetrack', 'Quota was successfully saved')
			)
		})
		.catch((error) => {
			console.error(error)
			OC.Notification.showTemporary(
				t('phonetrack', 'Failed to save quota')
			)
		})
}

$(function() {
	$('body').on('change', 'input#phonetrackPointQuota', function(e) {
		setPhoneTrackQuota($(this).val())
	})
})
