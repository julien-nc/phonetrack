# 手機追蹤Nextcloud應用程式

📱 手機追蹤是一款Nextcloud應用程式，用來記錄儲存移動裝置的位置。

🗺   It receives information from mobile phones logging apps and displays it dynamically on a map.

🌍 請利用[PhoneTrack Crowdin 計畫](https://crowdin.com/project/phonetrack)幫助翻譯

⚒ 在 [contribution guidelines](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md)中也有提供協助的方法

如何使用手機追蹤：

- Create a tracking session.
- Give the logging link\* to the mobile devices. Choose the [logging method](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#logging-methods) you prefer.
- Watch the session's devices location in real time (or not) in PhoneTrack or share it with public pages.

(\*) Don't forget to set the device name in the link (rather than in the logging app settings). Replace "yourname" with the desired device name.
Setting the device name in logging app settings only works with Owntracks, Traccar and OpenGTS.

On PhoneTrack main page, while watching a session, you can :

- 📍 顯示位置歷史記錄
- ⛛  Filter points
- ✎  Manually edit/add/delete points
- ✎ 總輯裝置 (重新命名，變更色彩/形狀，移動到其他裝置)
- ⛶ 定義裝置的地理圍欄區域
- ⚇ 定義裝置間近接警報
- 🖧  Share a session to other Nextcloud users or with a public link (read-only)
- 🔗 Generate public share links with optional restrictions (filters, device name, last positions only, geofencing simplification)
- 🖫  Import/export a session in GPX format (one file with one track per device or one file per device)
- 🗠  Display sessions statistics
- 🔒 [鎖定裝置名稱](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#device-name-reservation) 只有被授權才能使用鎖定的名稱
- 🗓 設定追蹤任務的自動匯出及自動清除 (每日/每週/每月)
- ◔  Choose what to do when point number quota is reached (block logging or delete oldest point)

Public page and public filtered page work like main page except there is only one session displayed, everything is read-only and there is no need to be logged in.

本應用在Nextcloud 17主機，配合客戶端瀏覽器Firefox 57+ 及 Chromium測試可運作。

此應用程式支援主題色彩

此應用程式尚在開發中。

## 安裝

安裝細節請查閱[AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc)

下次更新版本中添加的新功能，請查閱[CHANGELOG](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log)。

查閱[AUTHORS](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors)可得知所有作者。

## 已知待解決的問題

- PhoneTrack **now works** with Nextcloud group restriction activated. 手機追蹤**現在可以**給Nextcloud特定群組成員使用。 請查閱[admindoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud) 請查閱[admindoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud)

Any feedback will be appreciated.

