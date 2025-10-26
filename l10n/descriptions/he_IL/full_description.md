# PhoneTrack Nextcloud application

PhoneTrack הינה אפליקציה העוקבת ושומרת אחרי נתוני המיקום שלך, בצורה חלקה ואמינה.

האפליקציה מקבלת נתונים באמצעות תוכנת טרקר המותקנת במכשירך, ומציגה נתונים אלו בצורה דינמית תחת חשבונך בסביבת ה-NextCloud שלך.

נשמח לקבל עזרה בשיפור האפליקציה.

מספר דרכים שתוכל לעזור לנו בקישורך [בעזרה לפיתוח וקידום האפליקציה](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md).

איך PhoneTrack עובד:

* תחילה, צור טוקן מעקב באמצעות הפאנל שבאפליקציה.
* הורד אפליקצית מעקב מחנות האפליקציות או השתמש בגרסאת נייטיב, הזן את הלינק שקיבלת בתהליך יצור הסשן שבאפליקציה לקשר את מכשירך לשרת. תבחר שיטת התחברות.
* לאחר התקנת האפליקציה, תוכל לראות על המפה את המקום הנוכחי של המכשיר, תלוי באופן ובקצב העידכון שהזנת.

אל תשכח להזין שם למכשיר בלינק שיצרת, אחריו תוכל לעקוב. Replace "yourname" with the desired device name. שימוש בשם המכשיר מתאפשר אך ורק עם האפליקציות Owntracks, Traccar ו- OpenGTS.

במסך הראשי של האפליקציה תוכל:

* לראות היסטורית מיקומים
* לסנן מיקומים ע״פ פרמטרים מוגדרים
* להוסיף מיקומים ידנית
* לשנות ולערוך סשנים
* תוכל להגדיר אזורי מיקום למכשירך
* הגדר איזורי קרבה למכשירים
* לשתף מיקום עם משתמשים נוספים מחוץ או בפנים לסביבת NextCloud
* 🔗 Generate public share links with optional restrictions (filters, device name, last positions only, geofencing simplification)
* ליצא וליבא נתונים בפורמט GPX
* להנות מסטיסטיקה אודות שימוש בנתוני מיקום
* 🔒 [Reserve a device name](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#device-name-reservation) to make sure only authorized user can log with this name
* 🗓 Toggle session auto export and auto purge (daily/weekly/monthly)
* ◔ Choose what to do when point number quota is reached (block logging or delete oldest point)

Public page and public filtered page work like main page except there is only one session displayed, everything is read-only and there is no need to be logged in.

האפליקציה נבדקה בקפידה על ידינו בשימוש ב-Firefox57+ ו-Chrome על גבי Nextcloud 17.

This app is compatible with theming colors and accessibility themes !

This app is under development.

## Install

See the [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) for installation details.

Check [CHANGELOG](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log) file to see what's new and what's coming in next release.

Check [AUTHORS](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors) file to see complete list of authors.

## Known issues

* PhoneTrack **now works** with Nextcloud group restriction activated. See [admindoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Any feedback will be appreciated.