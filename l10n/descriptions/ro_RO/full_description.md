# Aplicația PhoneTrack Nextcloud

PhoneTrack este o aplicație pentru Nextcloud care ajută la urmărirea și stocarea informațiilor de urmărire a dispozitivelor mobile.

Aplicația primește informații de autentificare de la aplicațiile instalate pe telefonul mobil și le afișează în mod dinamic pe hartă.

Ajută-ne să traducem această aplicație pe [PhoneTrack Crowdin project](https://crowdin.com/project/phonetrack).

Vezi și alte moduri de a ajuta în [ghid de contribuții](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md).

Cum să utilizați PhoneTrack:

- Creați o sesiune de urmărire.
- Give the logging link\* to the mobile devices. Choose the [logging method](https://github.com/julien-nc/phonetrack/blob/main/doc/user.md#logging-methods) you prefer.
- Urmăriți locația dispozitivelor sesiunii în timp real (sau nu) în PhoneTrack sau partajați-o cu pagini publice.

(\*) Don't forget to set the device name in the link (rather than in the logging app settings). Replace "yourname" with the desired device name.
Setting the device name in logging app settings only works with Owntracks, Traccar and OpenGTS.

Pe pagina principală PhoneTrack, în timp ce urmărești o sesiune, puteți să:

- 📍 Afișați istoricul locațiilor
- ⧩ Filtrați punctele înregistrate
- ✎ Editați/adăugați/ștergeți puncte manual
- ✎ Editați dispozitivele (redenumire, schimbare culoare/formă, mutare la o altă sesiune)
- 💠 Definiți zone de geofencing pentru dispozitive
- ⚇ Definiți alerte de proximitate pentru dispozitive pereche
- ⇴ Distribuiți o sesiune către alți utilizatori Nextcloud sau folosind un link public (doar pentru vizualizare)
- 🔗 Generați link-uri de partajare publică cu restricții opționale (filtre, nume de dispozitiv, doar ultimele poziții, simplificare geofencing)
- ⇋ Importați/exportați o sesiune în format GPX (un fișier cu o pistă per dispozitiv sau un fișier per dispozitiv)
- 📈 Afișați statisticile sesiunilor
- 🔒 [Reserve a device name](https://github.com/julien-nc/phonetrack/blob/main/doc/user.md#device-name-reservation) to make sure only authorized user can log with this name
- 🗓 Comutați sesiunile de export automat și ștergere automată (zilnic/săptămânal/lunar)
- ◔ Alegeți ce să faceți când se atinge pragul numeric (blochează logarea de puncte sau șterge cel mai vechi punct)

Pagina publică și pagina publică filtrată funcționează la fel ca și pagina principală, exceptând faptul că o singură sesiune este afișată, că se permite doar citirea și că nu este necesar să fi autentificat.

Această aplicație este în curs de dezvoltare.

## Instalare

Vezi [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) pentru detalii de instalare.

Verificați fișierul [CHANGELOG](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log) pentru a vedea ce este nou și ce urmează în următoarea versiune.

Verificați fișierul [AUTORS](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors) pentru a vedea lista completă a autorilor.

## Probleme cunoscute

- PhoneTrack **now works** with Nextcloud group restriction activated. See [admindoc](https://github.com/julien-nc/phonetrack/blob/main/doc/admin.md#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Orice părere este apreciată.

