# PhoneTrack Nextcloud programmet

📱 PhoneTrack er et Nextcloud program for å spore og lagre mobilenheters lokasjoner.

🗺 Det mottar informasjon fra mobiltelefoners loggeprogrammer og viser det dynamisk på kart.

🌍 Hjelpe oss å oversette denne appen på [PhoneTrack Crowdin](https://crowdin.com/project/phonetrack) prosjektet.

⚒ Sjekk ut andre måter å hjelpe i [retningslinjer for bidrag](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md).

Hvordan bruke PhoneTrack:

* Opprett en sporingsøkt.
* Gi loggelinken\ * til mobile enheter. Velg den [loggemetoden](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#logging-methods) du foretrekker.
* Se øktens enhetsplasseringer i sanntid (eller ikke) i PhoneTrack eller del det med offentlige sider.

(\*) Ikke glem å sette enhetsnavnet i koblingen (ikke i appens loggeinnstillinger). Erstatt "dittnavn" med ønsket enhetsnavn. Å angi enhetsnavnet i loggeappen fungerer bare med Owntracks, Traccar og OpenGTS.

På hovedsiden for PhoneTrack kan du, mens du ser en økt:

* 📍 Vise posisjonshistorikk
* ⛛ Filter poeng
* ✎ Manuelt legge til/redigere punkter
* ✎ Redigere enheter (gi nytt navn, endre farge/form, flytte til en annen økt)
* ⛶ Definer geofencing soner for enheter
* ⚇ Definere nærhetsvarsler for enhetspar
* Dele en økt med andre Nextcloud brukere eller med en offentlig lenke (kun lese)
* 🔗 Generate public share links with optional restrictions (filters, device name, last positions only, geofencing simplification)
* 🖫 Import/export a session in GPX format (one file with one track per device or one file per device)
* 🗠 Display sessions statistics
* 🔒 [Reserve a device name](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#device-name-reservation) to make sure only authorized user can log with this name
* 🗓 Toggle session auto export and auto purge (daily/weekly/monthly)
* ◔ Choose what to do when point number quota is reached (block logging or delete oldest point)

Public page and public filtered page work like main page except there is only one session displayed, everything is read-only and there is no need to be logged in.

This app is tested on Nextcloud 15 with Firefox 57+ and Chromium.

This app is compatible with theming colors and accessibility themes !

This app is under development.

## Install

See the [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) for installation details.

Check [CHANGELOG](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log) file to see what's new and what's coming in next release.

Check [AUTHORS](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors) file to see complete list of authors.

## Known issues

* PhoneTrack **now works** with Nextcloud group restriction activated. See [admindoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Any feedback will be appreciated.