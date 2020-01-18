# PhoneTrack Nextcloud App

📱 PhoneTrack ist eine Nextcloud-Anwendung zur Verfolgung und Speicherung von Standorten mobiler Geräte.

🗺 Sie erfasst Informationen von Protokollierungs-Apps auf Mobiltelefonen und zeigt diese dynamisch auf einer Karte an.

🌍 Helfen Sie uns, diese App auf [PhoneTrack Crowdin Projekt](https://crowdin.com/project/phonetrack) zu übersetzen.

⚒ Schauen Sie sich weitere Möglichkeiten in den [Mitwirkungsrichtlinien](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md) an, wie Sie mitwirken können.

Wie PhoneTrack verwendet wird:

* Tracking-Sitzung erstellen
* Protokollierungslink\* an die mobilen Geräte weitergeben Bevorzugte [Protokollierungsmethode](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#logging-methods) auswählen
* Beobachten Sie den Gerätestandort der Sitzung in Echtzeit (oder später) in PhoneTrack oder teilen Sie ihn auf öffentlichen Webseiten.

(\*) Vergessen Sie nicht, den Gerätenamen im Link (und nicht in den Einstellungen der Protokollierungsanwendung) einzustellen. Ersetzen Sie „MeinName” mit dem gewünschten Gerätenamen. Das Einstellen des Gerätenamens in den Einstellungen der Protokollieruns-App funktioniert nur mit Owntracks, Traccar und OpenGTS.

Auf der Hauptseite von PhoneTrack können Sie während einer Sitzung:

* 📍 Standortverlauf anzeigen
* ⛛ Datenpunkte filtern
* ✎ Datenpunkte manuell bearbeiten/hinzufügen/löschen
* ✎ Geräte bearbeiten (umbenennen, Farbe und Form ändern, in andere Sitzung verschieben)
* ⛶ Geofence-Zonen für Geräte festlegen
* ⚇ Annäherungsbenachrichtigung für Gerätepaare festlegen
* 🖧 Teilen Sie eine Sitzung mit anderen Nextcloud-Benutzern oder mit einem öffentlichen Link (nur lesend)
* 🔗 Öffentliche Links mit optionalen Einschränkungen (Filter, Gerätename, letzte Positionen, Geofence-Vereinfachung) teilen
* 🖫 Sitzung im GPX-Format importieren/exportieren (eine Datei mit einer Aufzeichnung pro Gerät oder eine Datei pro Gerät)
* 🗠 Sitzungsstatistiken anzeigen
* 🔒 [Reservieren Sie einen Gerätenamen](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#device-name-reservation) um sicherzustellen, dass nur autorisierte Benutzer sich mit diesem Namen anmelden können
* 🗓 Umschalten zwischen „Automatisch exportieren” und „Automatisch bereinigen” der Sitzung (täglich/wöchentlich/monatlich)
* ◔ Jeder Benutzer kann wählen, was passieren soll, wenn die Menge der erlaubten Datenpunkte erreicht ist (Protokollierung unterbrechen oder ältesten Punkt löschen)

Öffentliche Seite und öffentlich gefilterte Seite funktionieren wie die Hauptseite, außer dass nur eine Sitzung angezeigt wird, alles schreibgeschützt ist und keine Anmeldung erforderlich ist.

Diese App wurde unter Nextcloud 17 mit Firefox 57+ und Chromium getestet.

Diese App ist kompatibel mit Farbthemen und Themen zur Barrierefreiheit!

Die App wird aktiv weiterentwickelt.

## Installieren

Siehe [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) für Installationsdetails.

Überprüfen Sie das [Änderungsprotokoll](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log), um zu erfahren, was neu ist und was in der nächsten Version kommt.

[Autoren](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors)-Datei auswählen, um eine Liste aller Autoren anzuzeigen.

## Bekannte Probleme

* PhoneTrack **funktioniert nun** mit der aktivierten Nextcloud-Gruppenbeschränkung. Siehe [admindoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Jegliches Feedback ist willkommen.