# Nextcloud aplikace PhoneTrack

📱 PhoneTrack je aplikace pro Nextcloud, určená pro sledování a uchovávání pozic mobilních zařízení.

🗺 Informace získává ze záznamových aplikací pro mobilní telefony a průběžně je zobrazuje na mapě.

🌍 Pomozte nám s překládáním textů v rozhraní této aplikace v rámci [projektu PhoneTrack na službě Crowdin](https://crowdin.com/project/phonetrack).

⚒ Podívejte se na další způsoby, jak pomoci v [pokynech pro přispěvatele](https://github.com/julien-nc/phonetrack/blob/main/CONTRIBUTING.md).

Jak PhoneTrack používat:

* Vytvořte relaci sledování.
* Zadejte odkaz na úložiště záznamů\* do mobilních zařízení. Zvolte vámi upřednostňovanou [metodu zaznamenávání](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#logging-methods).
* Sledujte pozice zařízení v rámci dané relace v reálném čase (nebo ne) v PhoneTrack nebo ji sdílejte prostřednictvím veřejných stránek.

(\*) Nezapomeňte do odkazu zadat také název zařízení (namísto v nastavení zaznamenávající aplikace). Nahraďte „vasnazev“ požadovaným názvem zařízení. Nastavení názvu zařízení v záznamové aplikaci funguje pouze v případě Owntracks, Traccar a OpenGTS.

Na hlavní stránce PhoneTrack můžete během sledování relace:

* 📍 Zobrazit historii polohy
* ⛛ Filtrovat body
* ✎ Ručně upravovat/přidávat/mazat body
* ✎ Upravovat zařízení (přejmenovávat, měnit barvu/tvar, přesouvat do jiné relace)
* ⛶ Definovat oblasti vymezených oblastí pro zařízení
* ⚇ Definovat výstrahy při přiblížení se pro dvojice zařízení
* 🖧 Nasdílet relaci ostatním uživatelům Nextcloud nebo prostřednictvím veřejného odkazu (pouze pro čtení)
* 🔗 Vytvářet veřejné odkazy na sdílení s volitelnými omezeními (filtry, název zařízení, poslední pozice, zjednodušení vymezené oblasti)
* 🖫 Importovat/exportovat relace ve formátu GPX (pro jednotlivá zařízení buď zvlášť soubor pro každou stopu, nebo jeden se všemi stopami z daného zařízení)
* 🗠 Zobrazit statistiky relace
* 🔒 [Zarezervovat název zařízení](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#device-name-reservation) a zajistit tak, že pomocí něj bude moci zaznamenávat pouze pověřený uživatel
* 🗓 Vypnout/zapnout automatický export relace a automatické čištění (denně/týdně/měsíčně)
* ◔ Zvolit co dělat, když je dosaženo kvóty počtu bodů (blokovat zaznamenávání nebo mazat od nejstaršího bodu)

Veřejná a veřejná filtrovaná stránka fungují stejně jako hlavní stránka, ale je zobrazena pouze jedna relace, vše je pouze pro čtení a není třeba být přihlášeni.

Tato aplikace je testovaná na Nextcloud 17 a prohlížečích Firefox verze 57 (a novějších) a Chromium.

Tato aplikace je kompatibilní s barvami motivu vzhledu a motivy pro zpřístupnění!

Na této aplikaci stále ještě probíhá intenzivní vývoj.

## Instalace

Podrobnosti ohledně instalace naleznete v [dokumentaci pro správce](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc).

Co je nového a co se chystá v příštím vydání naleznete v souboru [CHANGELOG](https://github.com/julien-nc/phonetrack/blob/main/CHANGELOG.md#change-log).

Všechny autory naleznete v souboru [AUTHORS](https://github.com/julien-nc/phonetrack/blob/main/AUTHORS.md#authors).

## Známé problémy

* PhoneTrack **nyní funguje** i při zapnutém omezení na skupiny v Nextcloud. Viz [dokumentace pro správce](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Jakákoliv zpětná vazba bude vítána.