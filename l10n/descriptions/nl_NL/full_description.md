# PhoneTrack Nextcloud applicatie

📱 PhoneTrack is een Nextcloud applicatie voor het bijhouden en opslaan van locaties van mobiele apparaten.

🗺 Het ontvangt informatie van logging apps van mobiele telefoons en geeft deze dynamisch weer op een kaart.

🌍 Help ons deze app te vertalen op [PhoneTrack Crowdin project](https://crowdin.com/project/phonetrack).

⚒ Bekijk andere manieren om te helpen in de [bijdragerichtlijnen](https://github.com/julien-nc/phonetrack/blob/main/CONTRIBUTING.md).

Hoe gebruik je PhoneTrack:

* Maak een tracking sessie.
* Geef de logging link\* aan de mobiele apparaten. Kies de [logging methode](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#logging-methods) van jouw voorkeur.
* Bekijk de locatie van sessieapparaten in realtime (of niet) in PhoneTrack of deel deze met publieke links.

(\*) Vergeet niet om de apparaatnaam in de link te zetten (in plaats van in de logging app-instellingen). Vervang 'yourname' met jouw gewenste apparaatnaam. Het instellen van de apparaatnaam in de logging app-instellingen werkt alleen met Owntracks, Traccar en OpenGTS.

Op de PhoneTrack hoofdpagina, kun je terwijl je een sessie bekijkt:

* 📍 Locatiegeschiedenis weergeven
* ⛛ Punten filteren
* ✎ Handmatig bewerken/toevoegen/verwijderen van punten
* ✎ Apparaten bewerken (hernoemen, kleur/vorm wijzigen, naar een andere sessie verplaatsen)
* ⛶ Geofencing zones definiëren voor apparaten
* ⚇ Nabijheidswaarschuwingen voor apparaatparen definiëren
* 🖧 Een sessie delen met andere Nextcloud gebruikers of met een publieke link (alleen-lezen)
* 🔗 Openbare share links genereren met optionele beperkingen (filters, apparaatnaam, enkel laatste posities, geofencing vereenvoudiging)
* 🖫 Een sessie importeren/exporteren in GPX-formaat (één bestand met één track per apparaat of één bestand per apparaat)
* 🗠 Sessiestatistieken weergeven
* 🔒 [Een apparaatnaam reserveren](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#device-name-reservation) om ervoor te zorgen dat alleen geautoriseerde gebruikers met deze naam kunnen inloggen
* 🗓 Activeren van automatische sessie export en automatisch opruimen (dagelijks/wekelijks/maandelijks)
* ◔ Kies wat er moet gebeuren als het puntentotaal is bereikt (blokkering van de logging of verwijdering van het oudste punt)

Openbare pagina's en openbare gefilterde pagina's werken zoals de hoofdpagina, behalve dat er slechts één sessie wordt weergegeven, alles wordt alleen-lezen en er hoeft niet ingelogd te worden.

Deze app is getest op Nextcloud 17 met Firefox 57+ en Chromium.

Deze app is compatibel met themakleuren en toegankelijkheidsthema's !

Deze app is in ontwikkeling.

## Installatie

Zie de [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) voor de installatiedetails.

Controleer [CHANGELOG](https://github.com/julien-nc/phonetrack/blob/main/CHANGELOG.md#change-log) bestand om te zien wat er nieuw is en wat er in de volgende release komt.

Controleer het bestand [AUTHORS](https://github.com/julien-nc/phonetrack/blob/main/AUTHORS.md#authors) om de volledige lijst van auteurs te zien.

## Bekende problemen

* PhoneTrack **werkt nu** met Nextcloud groepsbeperking geactiveerd. Zie [admindoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Elke feedback wordt gewaardeerd.