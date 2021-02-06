import { generateUrl } from '@nextcloud/router'

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
	$.ajax({
		type: 'POST',
		url,
		data: req,
		async: true,
	}).done(function(response) {
		OC.Notification.showTemporary(
			t('phonetrack', 'Quota was successfully saved')
		)
	}).fail(function() {
		OC.Notification.showTemporary(
			t('phonetrack', 'Failed to save quota')
		)
	})
}

$(document).ready(function() {
	$('body').on('change', 'input#phonetrackPointQuota', function(e) {
		setPhoneTrackQuota($(this).val())
	})
})
