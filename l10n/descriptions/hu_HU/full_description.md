# PhoneTrack Nextcloud alkalmazás

A PhoneTrack egy Nextcloud alkalmazás a mobil eszközök helyzetének követésére és tárolására.

🗺 It receives information from mobile phones logging apps and displays it dynamically on a map.

🌍 Segíts nekünk lefordítani ezt az alkalmazást a [PhoneTrack Crowdin projektben](https://crowdin.com/project/phonetrack).

⚒ Nézd meg a [hozzájárulási irányelvekben](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md) a segítségnyújtás egyéb módjait.

A PhoneTrack használata:

- Hozz létre egy nyomkövetési munkamenetet.
- Add át a naplózási linket\* a mobil eszközöknek. Válaszd ki a kívánt [naplózási módszert](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#logging-methods).
- Figyeld a munkamenet eszközeinek helyzetét valós időben (vagy késleltetve) a PhoneTrack-ben, vagy oszd meg nyilvános oldalakkal.

(\*) Ne felejtsd el az eszköz nevét a linkben beállítani (nem pedig a naplózó alkalmazás beállításaiban). Írd be a „yourname” helyett a kívánt eszköz nevét.
Az eszköznév beállítása a naplózó alkalmazás beállításaiban csak az Owntracks, Traccar és OpenGTS esetén működik.

A PhoneTrack főoldalán munkamenet figyelése közben a következöket teheted:

- 📍Helyelőzmények megjelenítése
- ⛛ Filter points
- ✎ Manually edit/add/delete points
- ✎ Edit devices (rename, change color/shape, move to another session)
- ⛶ Define geofencing zones for devices
- ⚇ Define proximity alerts for device pairs
- 🖧 Share a session to other Nextcloud users or with a public link (read-only)
- 🔗 Nyilvános megosztási linkek létrehozása opcionális korlátozásokkal (szűrők, eszköznév, csak utolsó pozíciók, geokerítés egyszerűsítése)
- 🖫 Import/export a session in GPX format (one file with one track per device or one file per device)
- 🗠 Display sessions statistics
- 🔒 [Foglalj le egy eszköznevet](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#device-name-reservation), hogy csak az engedélyezett felhasználók tudjanak ezzel a névvel bejelentkezni
- 🗓 Munkamenet automatikus exportálása és automatikus törlése (napi/heti/havi)
- ◔ Choose what to do when point number quota is reached (block logging or delete oldest point)

A nyilvános oldal és a nyilvános szűrt oldal a főoldalhoz hasonlóan működik, de csak egy munkamenetet mutat, minden csak olvasható, és bejelentkezés nem szükséges.

Ezt az alkalmazást a Nextcloud 17‑en tesztelték Firefox 57+ és Chromium böngészőkkel.

Ez az alkalmazás kompatibilis a témaszínekkel és az akadálymentesítési témákkal!

Ez az alkalmazás fejlesztés alatt áll.

## Telepítés

A telepítés részleteit lásd az [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) dokumentumban.

A változásokról és a következő kiadás újdonságairól a [CHANGELOG](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log) fájlban tájékozódhatsz.

A szerzők teljes listáját az [AUTHORS](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors) fájlban találod.

## Ismert problémák

- A PhoneTrack már akkor is működik, ha a Nextcloud csoportkorlátozás be van kapcsolva. Lásd az [admindoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud) fájlt.

Minden visszajelzést nagyra értékelünk.

