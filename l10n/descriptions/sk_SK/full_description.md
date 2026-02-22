# Aplikácia Nextcloud PhoneTrack

📱 PhoneTrack je Nextcloud aplikácia na sledovanie a ukladanie polohy mobilných zariadení.

🗺 Prijíma informácie z mobilných aplikácií na sledovanie a dynamicky ich zobrazuje na mape.

🌍 Pomôžte nám preložiť túto aplikáciu na [PhoneTrack Crowdin project](https://crowdin.com/project/phonetrack).

⚒ Pozrite si ďalšie spôsoby, ako pomôcť v [pokynoch pre prispievateľov].(https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md).

Ako používať PhoneTrack :

- Vytvorte sledovaciu reláciu.
- Zadajte sledovací odkaz\* do mobilných zariadení. Vyberte [spôsob zaznamenávania](https://github.com/julien-nc/phonetrack/blob/main/doc/user.md#logging-methods).
- Sledujte polohu zariadení v reálnom čase (alebo nie) v aplikácii PhoneTrack alebo ju zdieľajte na verejných stránkach.

(\*) Nezabudnite nastaviť názov zariadenia v odkaze (radšej ako v nastaveniach logovacej aplikácie). Nahraďte "vasnazov" zvoleným názvom zariadenia.
Nastavenie názvu zariadenia v nastaveniach logovacej aplikácie funguje len s Owntracks, Traccar a OpenGTS.

Na hlavnej stránke PhoneTrack môžete počas sledovania záznamu:

- 📍 Zobraziť históriu polohy
- ⛛ Filtrovať body
- ✎ Manuálne upravovať/pridávať/mazať body
- ✎ Upravovať zariadenia (premenovať, zmeniť farbu/tvar, presúvať do iného sedenia)
- ⛶ Definovať geofence zóny pre zariadenia
- ⚇ Zadávať výstrahy vzdialenia pre páry zariadení
- 🖧 Zdieľať sedenie s ďalšími Nextcloud používateľmi alebo pomocou verejných odkazov (len na čítanie)
- 🔗 Generovať odkazy verejného zdieľania s voliteľnými obmedzeniami (filtre, názov zariadenia, len posledná pozícia, zjednodušený geofence)
- 🖫 Importovať/exportovať záznamy v GPX formáte (jeden súbor s jedným záznamom alebo jeden súbor s jedným zariadením)
- 🗠 Zobraziť štatistiky záznamu
- 🔒 [Rezervujte si názov zariadenia](https://github.com/julien-nc/phonetrack/blob/main/doc/user.md#device-name-reservation), aby sa uistili, že sa s týmto názvom môže prihlásiť len autorizovaný používateľ
- 🗓 Zapínať automatické exportovanie záznamu a automatické mazanie (denne/týždenne/mesačne)
- ◔ Vyberať, čo sa stane, keď je dosiahnutý limit počtu bodov (zablokovať záznam alebo vymazať najstarší bod)

Verejná stránka a verejná filtrovaná stránka funguje ako hlavná stránka okrem situácie, keď je zobrazený len jeden záznam, všetko je len na čítanie a nie je potrebné prihlasovať sa.

Táto aplikácia je ešte vo vývoji.

## Inštalácia

Pozrite si [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) pre detaily inštalácie.

Pozrite si súbor [CHANGELOG](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log) čo je nové a čo sa chystá do ďalšej verzie.

Pozrite si súbor [AUTHORS](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors) a zobrazte si kompletný zoznam autorov.

## Známe problémy

- PhoneTrack **teraz funguje** so zapnutými obmedzeniami pre Nextcloud skupiny. Viac informácií nájdete v [admindoc](https://github.com/julien-nc/phonetrack/blob/main/doc/admin.md#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Ocením akúkoľvek spätnú väzbu.

