# PhoneTrack Nextcloud alkalmazás

A PhoneTrack egy Nextcloud alkalmazás a mobil eszközök helyzetének követésére és tárolására.

🗺 Információkat fogad a mobiltelefonok naplózó alkalmazásaitól, és dinamikusan jeleníti meg a térképen.

🌍 Help us to translate this app on [PhoneTrack Crowdin project](https://crowdin.com/project/phonetrack).

⚒ Check out other ways to help in the [contribution guidelines](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md).

A PhoneTrack használata:

- Hozz létre egy nyomkövetési munkamenetet.
- Add át a naplózási linket\* a mobil eszközöknek. Choose the [logging method](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#logging-methods) you prefer.
- Figyeld a munkamenet eszközeinek helyzetét valós időben (vagy késleltetve) a PhoneTrack-ben, vagy oszd meg nyilvános oldalakkal.

(\*) Ne felejtsd el az eszköz nevét a linkben beállítani (nem pedig a naplózó alkalmazás beállításaiban). Írd be a „yourname” helyett a kívánt eszköz nevét.
Az eszköznév beállítása a naplózó alkalmazás beállításaiban csak az Owntracks, Traccar és OpenGTS esetén működik.

A PhoneTrack főoldalán munkamenet figyelése közben a következöket teheted:

- 📍Helyelőzmények megjelenítése
- ⛛ Pontok szürése
- ✎ Pontok manuális szerkesztése/hozzáadása/törlése
- ✎ Eszközök szerkesztése (átnevezés, szín/forma módosítása, áthelyezés másik munkamenetbe)
- ⛶ Geokerítés‑zónák meghatározása az eszközök számára
- ⚇ Közelségi riasztások meghatározása eszközpárokhoz
- 🖧 Munkamenet megosztása más Nextcloud felhasználókkal vagy nyilvános hivatkozással (csak olvasható)
- 🔗 Nyilvános megosztási linkek létrehozása opcionális korlátozásokkal (szűrők, eszköznév, csak utolsó pozíciók, geokerítés egyszerűsítése)
- 🖫  Munkamenet importálása/exportálása GPX formátumban (eszközönként egy fájl egy nyomkövetéssel vagy eszközönként egy fájl)
- 🗠 Munkamenet statisztikáinak megjelenítése
- 🔒 [Reserve a device name](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#device-name-reservation) to make sure only authorized user can log with this name
- 🗓 Munkamenet automatikus exportálása és automatikus törlése (napi/heti/havi)
- ◔ Döntés arról, mi történjen a pontszám kvóta elérésekor (naplózás blokkolása vagy a legrégebbi pont törlése)

A nyilvános oldal és a nyilvános szűrt oldal a főoldalhoz hasonlóan működik, de csak egy munkamenetet mutat, minden csak olvasható, és bejelentkezés nem szükséges.

Ezt az alkalmazást a Nextcloud 17‑en tesztelték Firefox 57+ és Chromium böngészőkkel.

Ez az alkalmazás kompatibilis a témaszínekkel és az akadálymentesítési témákkal!

Ez az alkalmazás fejlesztés alatt áll.

## Telepítés

See the [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) for installation details.

Check [CHANGELOG](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log) file to see what's new and what's coming in next release.

Check [AUTHORS](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors) file to see complete list of authors.

## Ismert problémák

- PhoneTrack **now works** with Nextcloud group restriction activated. See [admindoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Minden visszajelzést nagyra értékelünk.

