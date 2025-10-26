# Aplikácia Nextcloud PhoneTrack

📱 PhoneTrack je Nextcloud aplikácia na sledovanie a ukladanie polohy mobilných zariadení.

🗺 Prijíma informácie z mobilných aplikácií na sledovanie a dynamicky ich zobrazuje na mape.

🌍 Pomôžte nám prekladať túto aplikáciu na [PhoneTrack Crowdin project](https://crowdin.com/project/phonetrack).

⚒ Pozrite si iné možnosti ako pomôcť na [contribution guidelines](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md).

Ako používať PhoneTrack :

- Vytvorte sledovaciu reláciu.
- Give the logging link\* to the mobile devices. Choose the [logging method](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#logging-methods) you prefer.
- Pozerajte si polohu zariadení v relácii v reálnom čase (alebo aj nie) v PhoneTracku a zdieľajte ich na verejných stránkach.

(\*) Don't forget to set the device name in the link (rather than in the logging app settings). Replace "yourname" with the desired device name.
Setting the device name in logging app settings only works with Owntracks, Traccar and OpenGTS.

Na hlavnej stránke PhoneTrack môžeš počas sledovania sedenia:

- 📍 Zobraziť históriu polohy
- ⛛ Filtrovať body
- ✎ Manuálne upravovať/pridávať/mazať body
- ✎ Upravovať zariadenia (premenovať, zmeniť farbu/tvar, presúvať do iného sedenia)
- ⛶ Definovať geofence zóny pre zariadenia
- ⚇ Zadávať výstrahy vzdialenia pre páry zariadení
- 🖧 Zdieľať sedenie s ďalšími Nextcloud používateľmi alebo pomocou verejných odkazov (len na čítanie)
- 🔗 Generujte odkazy verejného zdieľania s voliteľnými obmedzeniami (filtre, názov zariadenia, len posledná pozícia, zjednodušený geofence)
- 🖫 Importujte/exportujte záznamy v GPX formáte (jeden súbor s jedným záznamom alebo jeden súbor s jedným zariadením)
- 🗠 Zobraziť štatistiky záznamu
- 🔒 [Rezervovať názov zariadenia](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#device-name-reservation) pre uistenie sa, že len autorizovaný používateľ sa môže prihlásiť s týmto menom
- 🗓 Zapínať automatické exportovanie záznamu a automatické mazanie (denne/týždenne/mesačne)
- ◔ Vyberať, čo sa stane, keď je dosiahnutý limit počtu bodov (zablokovať záznam alebo vymazať najstarší bod)

Verejná stránka a verejná filtrovaná stránka funguje ako hlavná stránka okrem situácie, keď je zobrazený len jeden záznam, všetko je len na čítanie a nie je potrebné prihlasovať sa.

Táto aplikáca je testovaná na Nextcloud 17 s Firefox 57+ a Chromium.

Táto aplikácia je kompatibilná s farbami šablón a šablónami dostupnosti!

Táto aplikácia je ešte vo vývoji.

## Inštalácia

Pozrite si [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) pre detaily inštalácie.

Pozrite si súbor [CHANGELOG](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log) čo je nové a čo sa chystá do ďalšej verzie.

Pozrite si súbor [AUTHORS](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors) a zobrazte si kompletný list autorov.

## Známe problémy

- PhoneTrack **now works** with Nextcloud group restriction activated. See [admindoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Ocením akúkoľvek spätnú väzbu.

