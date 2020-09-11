# Nextcloud aplikace PhoneTrack

PhoneTrack je aplikace pro Nextcloud pro sledování a uchovávání pozice mobilních zařízení.

Informace získává ze záznamových aplikací pro mobilní telefony a průběžně je zobrazuje na mapě.

🌍 Pomozte nám s překládáním textů v rozhraní této aplikace v rámci [projektu PhoneTrack na službě Crowdin](https://crowdin.com/project/phonetrack).

⚒ Check out other ways to help in the [contribution guidelines](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md).

Jak PhoneTrack používat:

* Vytvořit relaci sledování.
* Give the logging link\* to the mobile devices. Choose the [logging method](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#logging-methods) you prefer.
* Watch the session's devices location in real time (or not) in PhoneTrack or share it with public pages.

(\*) Don't forget to set the device name in the link (rather than in the logging app settings). Replace "yourname" with the desired device name. Setting the device name in logging app settings only works with Owntracks, Traccar and OpenGTS.

On PhoneTrack main page, while watching a session, you can :

* 📍 Zobrazí historii polohy
* ⛛ Filtrovat body
* ✎ Ruční upravování/přidávání/mazání bodů
* ✎ Upravit zařízení (přejmenovat, změnit barvu/tvar, přesunout do jiné relace)
* ⛶ Definovat oblasti geooplocení pro zařízení
* ⚇ Definovat výstrahy přiblížení pro dvojice zařízení
* 🖧 Sdílet relaci ostatním uživatelům Nextcloud nebo veřejným odkazem (pouze pro čtení)
* 🔗 Generate public share links with optional restrictions (filters, device name, last positions only, geofencing simplification)
* 🖫 Import/export a session in GPX format (one file with one track per device or one file per device)
* 🗠 Zobrazit statistiky relace
* 🔒 [Zarezervovat název zařízení](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#device-name-reservation) abyste se ujistili, že pouze pověřený uživatel se může tímto názvem přihlásit
* 🗓 Přepnout automatický export relace a automatické vyčištění (denně/týdně/měsíčně)
* ◔ Zvolte co dělat, když je dosaženo kvóty počtu bodů (blokovat zaznamenávání nebo smazání nejstaršího bodu)

Public page and public filtered page work like main page except there is only one session displayed, everything is read-only and there is no need to be logged in.

Tato aplikace je zkoušená na Nextcloud 17 a prohlížečích Firefox 57 a novějším a Chromium.

Tato aplikace je kompatibilní s barvami motivu vzhledu a motivy pro zpřístupnění!

Na této aplikaci stále ještě probíhá intenzivní vývoj.

## Nainstalovat

Podrobnosti ohledně instalace naleznete v [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc).

Check [CHANGELOG](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log) file to see what's new and what's coming in next release.

Check [AUTHORS](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors) file to see complete list of authors.

## Známé problémy

* PhoneTrack **now works** with Nextcloud group restriction activated. Viz [admindoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Any feedback will be appreciated.