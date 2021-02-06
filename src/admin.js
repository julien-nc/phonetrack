import { generateUrl } from '@nextcloud/router'

(function() {
	if (!OCA.PhoneTrack) {
		OCA.PhoneTrack = {}
	}
})()

function setPhoneTrackQuota(val) {
	var url = generateUrl('/apps/phonetrack/setPointQuota')
	var req = {
		quota: val,
	}
	$.ajax({
		type: 'POST',
		url: url,
		data: req,
		async: true,
	}).done(function (response) {
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
