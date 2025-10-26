# Aplicació PhoneTrack per Nextcloud

📱 PhoneTrack és una aplicació Nextcloud per rastrejar i emmagatzemar la posició dels dispositius mòbils.

🗺 Rep informació de les aplicacions de registre de telefonia mòbil i la mostra en directe en un mapa.

🌍 Ajuda'ns a traduir aquesta aplicació a [el projecte Crowdin de PhoneTrack](https://crowdin.com/project/phonetrack).

⚒ Trobeu altres maneres d’ajudar en les [indicacions de contribució](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md).

Com fer servir PhoneTrack :

- Crear una sessió de seguiment.
- Doneu l'enllaç de registre \ \* als dispositius mòbils. Choose the [logging method](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#logging-methods) you prefer.
- Consulteu la ubicació dels dispositius de sessió en temps real (o no) a PhoneTrack o compartiu-la amb enllaços públics.

(\*) No oblideu establir el nom del dispositiu a l'enllaç (més que no pas a la configuració de l'aplicació de registre). Substituir "elvostrenom" amb el nom de dispositiu desitjat.
L'establiment del nom del dispositiu a la configuració de l'aplicació de registre només funciona amb Owntracks, Traccar i OpenGTS.

A la pàgina principal de PhoneTrack, en veure una sessió, podeu:

- 📍 Consultar l'historial d'ubicacions
- ⛛ Filtrar punts
- Manualment editar/afegir/esborrar punts
- ✎ Edita els dispositius (canvia el nom, canvia el color / la forma, passa a una altra sessió)
- Definir zones de geolocalització per dispositius
- ⚇ Estableix alertes de proximitat per parells de dispositius
- 🖧 Compartiu una sessió amb altres usuaris Nextcloud o amb un enllaç públic (només de lectura)
- 🔗 Generar enllaços públic per compartir amb restriccions opcionals (filtres, nom del dispositiu, només darreres posicions, simplificació de geolocalització)
- 🖫 Importeu / exporteu una sessió en format GPX (un fitxer amb una pista per dispositiu o un fitxer per dispositiu)
- 🗠 Veure estadístiques de sessió
- 🔒 [Reserva un nom de dispositiu](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#device-name-reservation)per assegurar-se que només l’usuari autoritzat pot iniciar la sessió amb aquest nom
- 🗓 Activa l'exportació automàtica de sessions i la purga automàtica (diària / setmanal / mensual)
- ◔ Trieu què passa quan s’arriba a la quota de número de punts (bloquejar el registre o eliminar el punt més antic)

Les pàgines públiques i les pàgines públiques filtrades funcionen com la pàgina principal, excepte que només es mostra una sessió, tot és de només lectura i no cal connectar-se.

Aquesta aplicació està provada a Nextcloud 17 amb Firefox 57+ i Chromium.

Aquesta aplicació és compatible amb temes de color i temes d’accessibilitat !

Aquesta aplicació està en desenvolupament.

## Instalació

Consulteu [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) per obtenir més informació.

Mireu el fitxer [CHANGELOG](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log) per veure què hi ha de nou i què passa a la següent versió.

Consulteu el fitxer [AUTORS](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors) per veure la llista completa d’autors.

## Problemes comuns

- PhoneTrack **now works** with Nextcloud group restriction activated. See [admindoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Qualsevol feedback serà apreciat.

