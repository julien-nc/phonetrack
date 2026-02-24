# PhoneTrack Nextcloud application

PhoneTrack הינה אפליקציה העוקבת ושומרת אחרי נתוני המיקום שלך, בצורה חלקה ואמינה.

האפליקציה מקבלת נתונים באמצעות תוכנת טרקר המותקנת במכשירך, ומציגה נתונים אלו בצורה דינמית תחת חשבונך בסביבת ה-NextCloud שלך.

נשמח לקבל עזרה בשיפור האפליקציה.

מספר דרכים שתוכל לעזור לנו בקישורך [בעזרה לפיתוח וקידום האפליקציה](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md).

איך PhoneTrack עובד:

- תחילה, צור טוקן מעקב באמצעות הפאנל שבאפליקציה.
- Give the logging link\* to the mobile devices. Choose the [logging method](https://github.com/julien-nc/phonetrack/blob/main/doc/user.md#logging-methods) you prefer.
- לאחר התקנת האפליקציה, תוכל לראות על המפה את המקום הנוכחי של המכשיר, תלוי באופן ובקצב העידכון שהזנת.

(\*) Don't forget to set the device name in the link (rather than in the logging app settings). Replace "yourname" with the desired device name.
Setting the device name in logging app settings only works with Owntracks, Traccar and OpenGTS.

במסך הראשי של האפליקציה תוכל:

- לראות היסטורית מיקומים
- לסנן מיקומים ע״פ פרמטרים מוגדרים
- להוסיף מיקומים ידנית
- לשנות ולערוך סשנים
- תוכל להגדיר אזורי מיקום למכשירך
- הגדר איזורי קרבה למכשירים
- לשתף מיקום עם משתמשים נוספים מחוץ או בפנים לסביבת NextCloud
- 🔗 Generate public share links with optional restrictions (filters, device name, last positions only, geofencing simplification)
- ליצא וליבא נתונים בפורמט GPX
- להנות מסטיסטיקה אודות שימוש בנתוני מיקום
- 🔒 [Reserve a device name](https://github.com/julien-nc/phonetrack/blob/main/doc/user.md#device-name-reservation) to make sure only authorized user can log with this name
- 🗓 Toggle session auto export and auto purge (daily/weekly/monthly)
- ◔ Choose what to do when point number quota is reached (block logging or delete oldest point)

Public page and public filtered page work like main page except there is only one session displayed, everything is read-only and there is no need to be logged in.

This app is under development.

## Install

See the [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) for installation details.

Check [CHANGELOG](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log) file to see what's new and what's coming in next release.

Check [AUTHORS](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors) file to see complete list of authors.

## Known issues

- PhoneTrack **now works** with Nextcloud group restriction activated. See [admindoc](https://github.com/julien-nc/phonetrack/blob/main/doc/admin.md#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Any feedback will be appreciated.

