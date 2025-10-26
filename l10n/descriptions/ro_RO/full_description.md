# Aplicația PhoneTrack Nextcloud

PhoneTrack este o aplicație pentru Nextcloud care ajută la urmărirea și stocarea informațiilor de urmărire a dispozitivelor mobile.

Aplicația primește informații de autentificare de la aplicațiile instalate pe telefonul mobil și le afișează în mod dinamic pe hartă.

Ajută-ne să traducem această aplicație pe [PhoneTrack Crowdin project](https://crowdin.com/project/phonetrack).

Vezi și alte moduri de a ajuta în [ghid de contribuții](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md).

Cum să utilizați PhoneTrack:

- Creați o sesiune de urmărire.
- Dați linkul de logare\* către dispozitivele mobile. Choose the [logging method](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#logging-methods) you prefer.
- Urmăriți locația dispozitivelor sesiunii în timp real (sau nu) în PhoneTrack sau partajați-o cu pagini publice.

(\*) Nu uitați să setați numele dispozitivului în link (mai degrabă decât în setările aplicației de logare). Înlocuiți "numele" cu numele dispozitivului dorit.
Setarea numelui dispozitivului în setările aplicaţiei de logare funcţionează doar cu Owntracks, Traccar şi OpenGTS.

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
- 🔒 [Rezervați un nume de dispozitiv](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#device-name-reservation) pentru a vă asigura că numai utilizatorul autorizat se poate conecta cu acest nume
- 🗓 Comutați sesiunile de export automat și ștergere automată (zilnic/săptămânal/lunar)
- ◔ Alegeți ce să faceți când se atinge pragul numeric (blochează logarea de puncte sau șterge cel mai vechi punct)

Pagina publică și pagina publică filtrată funcționează la fel ca și pagina principală, exceptând faptul că o singură sesiune este afișată, că se permite doar citirea și că nu este necesar să fi autentificat.

Această aplicație este testată pe Nextcloud 17 cu Firefox 57+ și Chromium.

Această aplicație este compatibilă cu tematica culorilor și temelor de accesibilitate !

Această aplicație este în curs de dezvoltare.

## Instalare

Vezi [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) pentru detalii de instalare.

Verificați fișierul [CHANGELOG](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log) pentru a vedea ce este nou și ce urmează în următoarea versiune.

Verificați fișierul [AUTORS](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors) pentru a vedea lista completă a autorilor.

## Probleme cunoscute

- PhoneTrack **now works** with Nextcloud group restriction activated. See [admindoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Orice părere este apreciată.

