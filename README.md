# PhoneTrack Nextcloud application

[![Build status](https://gitlab.com/eneiluj/phonetrack-oc/badges/master/build.svg)](https://gitlab.com/eneiluj/phonetrack-oc/commits/master)
[![coverage report](https://gitlab.com/eneiluj/phonetrack-oc/badges/master/coverage.svg)](https://eneiluj.gitlab.io/phonetrack-oc/coverage/)
[![Crowdin](https://d322cqt584bo4o.cloudfront.net/phonetrack/localized.svg)](https://crowdin.com/project/phonetrack)

Link to Nextcloud application website : https://apps.nextcloud.com/apps/phonetrack

📱 PhoneTrack is a Nextcloud application to track
and store mobile devices locations.

🗺   It receives information from mobile phones logging apps
and displays it dynamically on a map.

🌍 Help us to translate this app on [PhoneTrack Crowdin project](https://crowdin.com/project/phonetrack).

⚒ Check out other ways to help in the [contribution guidelines](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md).

How to use PhoneTrack :

* Create a tracking session.
* Give the logging URL\* to the mobile devices. Choose the [logging method](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#logging-methods) you prefer.
* Watch the session's devices location in real time (or not) in PhoneTrack or share it with public pages.

(\*) Don't forget to set the device name in the URL (rather than in the logging app settings. Replace "yourname" with the desired device name.
Setting the device name in logging app settings only works with Owntracks, Traccar and OpenGTS.

On PhoneTrack main page, while watching a session, you can :

* 📍 Display location history
* ⛛  Filter points
* ✎  Manually edit/add/delete points
* ✎  Edit devices (rename, change color/shape, move to another session)
* ⛶  Define geofencing zones for devices
* ⚇  Define proximity alerts for device pairs
* 🖧  Share a session to other Nextcloud users or with a public link (read-only)
* 🔗 Generate public share links with optional restrictions (filters, device name, last positions only, geofencing simplification)
* 🖫  Import/export a session in GPX format (one file with one track per device or one file per device).
* 🗠  Display sessions statistics
* 🔒 [Reserve a device name](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#device-name-reservation) to make sure only authorized user can log with this name
* 🗓 Toggle session auto export and auto purge (daily/weekly/monthly)
* ◔  Choose what to do when point number quota is reached (block logging or delete oldest point)

Public page and public filtered page work like main page except there is only one session displayed, everything is read-only and there is no need to be logged in.

This app is tested on Nextcloud 15/16 with Firefox 57+ and Chromium.

This app is compatible with theming colors and accessibility themes !

This app is under development.

## Donation

I develop this app during my free time.

* [Paypal : <img src="https://gitlab.com/eneiluj/phonetrack-android/wikis/uploads/3ef4665a2c25662265681d6304f71b43/paypal-donate-button.png" width="100"/>](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=66PALMY8SF5JE) (you don't need a paypal account)
* [Liberapay : ![Donate using Liberapay](https://liberapay.com/assets/widgets/donate.svg)](https://liberapay.com/eneiluj/donate)

## Install

See the [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) for installation details.

Check [CHANGELOG](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log) file to see what's new and what's coming in next release.

Check [AUTHORS](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors) file to see complete list of authors.

## Known issues

* PhoneTrack **now works** with Nextcloud group restriction activated. See [admindoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Any feedback will be appreciated.
