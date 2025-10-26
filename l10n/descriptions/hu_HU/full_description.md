# PhoneTrack Nextcloud alkalmazás

A PhoneTrack egy Nextcloud alkalmazás a mobil eszközök helyzetének követésére és tárolására.

🗺 Információkat fogad a mobiltelefonok naplózó alkalmazásaitól, és dinamikusan jeleníti meg a térképen.

🌍 Segíts nekünk lefordítani ezt az alkalmazást a [PhoneTrack Crowdin projektben](https://crowdin.com/project/phonetrack).

⚒ Tekintsd meg a további segítségnyújtási módokat a [hozzájárulási irányelvekben](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md).

A PhoneTrack használata:

* Hozz létre egy nyomkövetési munkamenetet.
* Add át a naplózási linket\* a mobil eszközöknek. Válaszd ki a számodra megfelelő [naplózási módot](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#logging-methods).
* Figyeld a munkamenet eszközeinek helyzetét valós időben (vagy késleltetve) a PhoneTrack-ben, vagy oszd meg nyilvános oldalakkal.

(\*) Ne felejtsd el az eszköz nevét a linkben beállítani (nem pedig a naplózó alkalmazás beállításaiban). Írd be a „yourname” helyett a kívánt eszköz nevét. Az eszköznév beállítása a naplózó alkalmazás beállításaiban csak az Owntracks, Traccar és OpenGTS esetén működik.

A PhoneTrack főoldalán munkamenet figyelése közben a következöket teheted:

* 📍Helyelőzmények megjelenítése
* ⛛ Pontok szürése
* ✎ Pontok manuális szerkesztése/hozzáadása/törlése
* ✎ Eszközök szerkesztése (átnevezés, szín/forma módosítása, áthelyezés másik munkamenetbe)
* ⛶ Geokerítés‑zónák meghatározása az eszközök számára
* ⚇ Közelségi riasztások meghatározása eszközpárokhoz
* 🖧 Munkamenet megosztása más Nextcloud felhasználókkal vagy nyilvános hivatkozással (csak olvasható)
* 🔗 Nyilvános megosztási linkek létrehozása opcionális korlátozásokkal (szűrők, eszköznév, csak utolsó pozíciók, geokerítés egyszerűsítése)
* 🖫 Munkamenet importálása/exportálása GPX formátumban (eszközönként egy fájl egy nyomkövetéssel vagy eszközönként egy fájl)
* 🗠 Munkamenet statisztikáinak megjelenítése
* 🔒 [Eszköznév lefoglalása](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#device-name-reservation), hogy csak jogosult felhasználó tudjon ezen a néven naplózni
* 🗓 Munkamenet automatikus exportálása és automatikus törlése (napi/heti/havi)
* ◔ Döntés arról, mi történjen a pontszám kvóta elérésekor (naplózás blokkolása vagy a legrégebbi pont törlése)

A nyilvános oldal és a nyilvános szűrt oldal a főoldalhoz hasonlóan működik, de csak egy munkamenetet mutat, minden csak olvasható, és bejelentkezés nem szükséges.

Ezt az alkalmazást a Nextcloud 17‑en tesztelték Firefox 57+ és Chromium böngészőkkel.

Ez az alkalmazás kompatibilis a témaszínekkel és az akadálymentesítési témákkal!

Ez az alkalmazás fejlesztés alatt áll.

## Telepítés

Lásd az [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc)-t a telepítés részleteiért.

Nézd meg a [CHANGELOG](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log)-ot, hogy megtudd, mik az újdonságok, és mi várható a következő kiadásban.

A szerzők teljes listájáért tekintsd meg az [AUTHORS](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors) fájlt.

## Ismert problémák

* A PhoneTrack **már akkor is működik**, ha a Nextcloud csoportkorlátozás be van kapcsolva. Lásd: [admindoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Minden visszajelzést nagyra értékelünk.