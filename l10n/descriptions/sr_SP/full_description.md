# PhoneTrack Nextcloud апликација

📱 PhoneTrack је Nextcloud апликација за праћење и чување локације мобилних уређаја.

🗺 Прима информације од апликација за праћење локације мобилних телефона и динамички их приказује на мапи.

🌍 Помозите нам да преведемо ову апликацију на [PhoneTrack Crowdin пројекту](https://crowdin.com/project/phonetrack).

⚒ Погледајте друге начине да помогнете у [contribution guidelines](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md).

Како користити PhoneTrack :

* Create a tracking session.
* Give the logging link\* to the mobile devices. Choose the [logging method](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#logging-methods) you prefer.
* Watch the session's devices location in real time (or not) in PhoneTrack or share it with public pages.

(\*) Don't forget to set the device name in the link (rather than in the logging app settings). Replace "yourname" with the desired device name. Setting the device name in logging app settings only works with Owntracks, Traccar and OpenGTS.

On PhoneTrack main page, while watching a session, you can :

* 📍 Прикажи историју локација
* ⛛ Filter points
* ✎ Manually edit/add/delete points
* ✎ Edit devices (rename, change color/shape, move to another session)
* ⛶ Define geofencing zones for devices
* ⚇ Define proximity alerts for device pairs
* 🖧 Share a session to other Nextcloud users or with a public link (read-only)
* 🔗 Generate public share links with optional restrictions (filters, device name, last positions only, geofencing simplification)
* 🖫 Import/export a session in GPX format (one file with one track per device or one file per device)
* 🗠 Display sessions statistics
* 🔒 [Reserve a device name](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#device-name-reservation) to make sure only authorized user can log with this name
* 🗓 Укључите аутоматски извоз сесије и аутоматско чишћење (дневно/недељно/месечно)
* ◔ Choose what to do when point number quota is reached (block logging or delete oldest point)

Јавна страница и јавна филтрерисана страна раде као главна страница, осим што је приказана само једна сесија, све је read-only и нема потребе да се пријављујете.

Ова апликација је тестирана на Nextcloud 17 са Firefox 57+ и Chromium веб претраживачем.

Ова апликација је компатибилна са бојама тема и темама приступачности!

Ова апликација је у развоју.

## Инсталирај

Види [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) за упутсва за инсталацију.

Провери [CHANGELOG](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log) да видиш шта је ново, и шта ново излази у новим верзијама.

Види [AUTHORS](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors) фајл да бих видео листу аутора.

## Познати проблеми

* PhoneTrack **сада ради** са Nextcloud group restriction подставком активном. Види [admindoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Свака повратна информација ће бити цењена.