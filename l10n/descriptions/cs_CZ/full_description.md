# Nextcloud aplikace PhoneTrack

PhoneTrack je aplikace pro Nextcloud pro sledování a uchovávání pozice mobilních zařízení.

Informace získává ze záznamových aplikací pro mobilní telefony a průběžně je zobrazuje na mapě.

🌍 Pomozte nám s překládáním textů v rozhraní této aplikace v rámci [projektu PhoneTrack na službě Crowdin](https://crowdin.com/project/phonetrack).

⚒ Podívejte se na další způsoby, jak pomoci v [pokynech pro přispěvatele](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md).

Jak PhoneTrack používat:

* Vytvořit relaci sledování.
* Give the logging link\* to the mobile devices. Choose the [logging method](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#logging-methods) you prefer.
* Watch the session's devices location in real time (or not) in PhoneTrack or share it with public pages.

(\*) Don't forget to set the device name in the link (rather than in the logging app settings). Replace "yourname" with the desired device name. Setting the device name in logging app settings only works with Owntracks, Traccar and OpenGTS.

Na hlavní stránce PhoneTrack můžete během sledování relace:

* 📍 Zobrazí historii polohy
* ⛛ Filtrovat body
* ✎ Ruční upravování/přidávání/mazání bodů
* ✎ Upravit zařízení (přejmenovat, změnit barvu/tvar, přesunout do jiné relace)
* ⛶ Definovat oblasti geooplocení pro zařízení
* ⚇ Definovat výstrahy přiblížení pro dvojice zařízení
* 🖧 Sdílet relaci ostatním uživatelům Nextcloud nebo veřejným odkazem (pouze pro čtení)
* 🔗 Generovat veřejné odkazy s volitelnými omezeními (filtry, název zařízení, poslední pozice, geooplocení)
* 🖫 Importovat/Exportovat relace ve formátu GPX (jeden soubor s jednou trasou nebo jeden soubor na zařízení)
* 🗠 Zobrazit statistiky relace
* 🔒 [Zarezervovat název zařízení](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#device-name-reservation) abyste se ujistili, že pouze pověřený uživatel se může tímto názvem přihlásit
* 🗓 Přepnout automatický export relace a automatické vyčištění (denně/týdně/měsíčně)
* ◔ Zvolte co dělat, když je dosaženo kvóty počtu bodů (blokovat zaznamenávání nebo smazání nejstaršího bodu)

Veřejná a veřejně filtrovaná stránka fungují stejně jako hlavní stránka ale je zobrazena pouze jedna relace, vše je pouze pro čtení a není třeba být přihlášen.

Tato aplikace je zkoušená na Nextcloud 17 a prohlížečích Firefox 57 a novějším a Chromium.

Tato aplikace je kompatibilní s barvami motivu vzhledu a motivy pro zpřístupnění!

Na této aplikaci stále ještě probíhá intenzivní vývoj.

## Nainstalovat

Podrobnosti ohledně instalace naleznete v [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc).

Co je nového a co se chystá v příštím vydání naleznete v souboru [CHANGELOG](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log).

Všechny autory naleznete v souboru [AUTHORS](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors).

## Známé problémy

* PhoneTrack **nyní funguje** s aktivním skupinovým omezením v Nextcloud. Viz [dokumntace pro administrátory](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Jakákoliv zpětná vazba bude vítána.