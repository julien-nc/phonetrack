# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
## 0.6.2 – 2020-02-10
### Added
- translations

### Fixed
- add 'locked' field in sessions table in case it's not present
[#308](https://gitlab.com/eneiluj/phonetrack-oc/issues/308) @toastbrot612
- mistake in activity provider

## 0.6.1 – 2020-01-27
### Fixed
- support google takeout json positions for import
[#300](https://gitlab.com/eneiluj/phonetrack-oc/issues/300) @MarkLi

## 0.6.0 – 2020-01-24
### Added
- support google takeout json positions for import
[#300](https://gitlab.com/eneiluj/phonetrack-oc/issues/300) @JDButler

### Changed
- put `<speed>` and `<course>` in `<extensions>` when exporting to gpx
[#285](https://gitlab.com/eneiluj/phonetrack-oc/issues/285) @florom
- accept logging with a user share token
- put correct templates for public pages

### Fixed
- block public logging page if session is not public
[#286](https://gitlab.com/eneiluj/phonetrack-oc/issues/286) @ozinfotech

## 0.5.11 – 2019-11-20
### Fixed
- auto export cron job failing on some setups
[#296](https://gitlab.com/eneiluj/phonetrack-oc/issues/296) @reinoudvleeuwen

## 0.5.10 – 2019-11-03
### Fixed
- missing DB field

## 0.5.8 – 2019-11-03
### Added
- vector tiles support with Mapbox of OpenMapTile server
- Overland IOS compatibility
[#289](https://gitlab.com/eneiluj/phonetrack-oc/issues/289) @MarkusGe
- display interactive elevation chart when zooming on specific device
[#283](https://gitlab.com/eneiluj/phonetrack-oc/issues/283) @Akitou
- activity stream for sharing, geofence, proximity events

### Changed
- unit display in tooltips/popups
- add placeholders
- improve proximity icon
- use NC database migration system

### Fixed
- bug when theming color code is compacted
[#284](https://gitlab.com/eneiluj/phonetrack-oc/issues/284) @DofTNet
- make location DB insertion more robust
[#281](https://gitlab.com/eneiluj/phonetrack-oc/issues/281) @maviar1981

## 0.5.4 – 2019-09-21
### Changed
- CI script now uses NC 17
- revert autoexport/autopurge to previous system

### Fixed
- notifications for NC 17
[#261](https://gitlab.com/eneiluj/phonetrack-oc/issues/261) @nickvergessen
- filter table with small screen size
[#274](https://gitlab.com/eneiluj/phonetrack-oc/issues/274) @amo13
- use NC blue when theming app is disabled
[#276](https://gitlab.com/eneiluj/phonetrack-oc/issues/276) @huste

## 0.5.3 – 2019-07-25
### Changed
- adapt notifications to NC 17
- compatible with NC >= 17
- new smaller screenshots

## 0.5.2 – 2019-07-04
### Added
- distance in tooltips
[!813](https://gitlab.com/eneiluj/phonetrack-oc/merge_requests/813) @GURKE

### Changed
- remove some options
[#221](https://gitlab.com/eneiluj/phonetrack-oc/issues/221) @GURKE
- sort sessions and devices by name
[#247](https://gitlab.com/eneiluj/phonetrack-oc/issues/247) @GURKE
- speedup animations

### Fixed
- problem with userManager with some database setups
[#243](https://gitlab.com/eneiluj/phonetrack-oc/issues/243) @spastis1
- remanent edition marker
[#246](https://gitlab.com/eneiluj/phonetrack-oc/issues/246) @creywood
- show filtered points when hovering
[#246](https://gitlab.com/eneiluj/phonetrack-oc/issues/246) @creywood

## 0.5.1 – 2019-05-06
### Added
- API route to create session
- API route to get last positions
[#241](https://gitlab.com/eneiluj/phonetrack-oc/issues/241) @grandpianisto

### Changed
- use phpunit 8 and adapt tests
- improve CI tests, add jobs with MySQL and PostgreSQL
- improve auto purge and auto export
[!760](https://gitlab.com/eneiluj/phonetrack-oc/merge_requests/760) @robyquin
- sort sessions when front-end gets them
- improve translation automation stuff

### Fixed
- fix sql request DB type compatibility
- show user id and name if necessary when sharing
[#231](https://gitlab.com/eneiluj/phonetrack-oc/issues/231) @fwejklwefnk1a
- apply filters after refreshing points to make sure last days/hours/minutes filter is applied
[#240](https://gitlab.com/eneiluj/phonetrack-oc/issues/240) @kaistian
- share autocomplete select design

## 0.5.0 – 2019-03-30
### Added
- add button to disable session : forbid log
[#222](https://gitlab.com/eneiluj/phonetrack-oc/issues/222) @GURKE

### Changed
- remove point display, make point appear when hovering the line (fixes UI performance issue!)
- focus and select session name input when showing it
- immediately display value when dragging sliders
- improve sliders design
[#213](https://gitlab.com/eneiluj/phonetrack-oc/issues/213) @Valdnet
- add space between settings checkboxes
- improve options design
- improve device and session sidebar design: take as much space as available for the name
[#221](https://gitlab.com/eneiluj/phonetrack-oc/issues/221) @GURKE
- send color when getting last positions with private API
- CI tests with NC16beta2
- lots of style improvements (dropdown menus, borders, colors...)

### Fixed
- fix api to get a public link for a device
- apply dragging option to device main markers
- fix point popup (battery and speed)
[#223](https://gitlab.com/eneiluj/phonetrack-oc/issues/223) @Valdnet
- do not export empty devices or empty sessions (MR from @robyquin)
- hide useless elements in public pages
[#214](https://gitlab.com/eneiluj/phonetrack-oc/issues/214) @robyquin
- replace deprecated addAllowedChildSrcDomain

## 0.4.4 – 2019-02-26
### Added
- add api entry to get last positions when logged in, works with all sessions

### Changed
- update CI to NC 15
- on log multiple points, check geofences and proxims just once with last point
- make app description translatable
- send geofence/proxim notifications to all users sharing the session
[#206](https://gitlab.com/eneiluj/phonetrack-oc/issues/206) @Valdnet
- sliders design

### Fixed
- number of decimal displayed in popup/tooltip values and stats table
[#207](https://gitlab.com/eneiluj/phonetrack-oc/issues/207) @Valdnet
- only check quota once for multiple log
- update stats table after device deletion
- fix LIMIT/OFFSET syntax to be compatible with SQLite, MySQL and PostgreSQL
[#212](https://gitlab.com/eneiluj/phonetrack-oc/issues/212) @lachmanfrantisek
- sidebar logo display in chromium

## 0.4.3 – 2019-01-26
### Added
- lots of translations

## 0.4.2 – 2019-01-25
### Added
- variable in geofence notification URLs (%loc replaced by lat:lon)
[#199](https://gitlab.com/eneiluj/phonetrack-oc/issues/199) @olivier.revelin

### Changed
- api/getlastpositions now gives publicviewtoken of shared sessions, used in PT-android
- add useragent to api getLastPositions
- add isPublic to api getSessions
- replace URL with link or address in translatable strings
- display geofence values to make it more explicit how it works
- improve geofence and proxims UI
[#199](https://gitlab.com/eneiluj/phonetrack-oc/issues/199) @olivier.revelin
- make public session button clearer
[#200](https://gitlab.com/eneiluj/phonetrack-oc/issues/200) @strugee
- use ellipsis as dropdown menus icons

### Fixed
- api/getlastpositions : missing values = null
- design of geofence/proxims

## 0.4.1 – 2019-01-14
### Added
- LocusMap compatibility
[#101](https://gitlab.com/eneiluj/phonetrack-oc/issues/101) @webunraveling
- GET parameters to publicSessionWatch URL
[#190](https://gitlab.com/eneiluj/phonetrack-oc/issues/190) @Lucas.Sichardt
- new log method to receive multiple points in JSON (used by PhoneTrack-Android)

### Changed
- use https for tileservers which support it
[!464](https://gitlab.com/eneiluj/phonetrack-oc/merge_requests/464) @webunraveling
- improve session-user sharing system, consider userId instead of username
[#195](https://gitlab.com/eneiluj/phonetrack-oc/issues/195) @mikoladz

### Fixed
- check email validity for geofences and proxims
[#185](https://gitlab.com/eneiluj/phonetrack-oc/issues/185) @Stat1cV01D

## 0.4.0 – 2018-12-18
### Added
- geofences and proxim now trigger optional NC notifications
[#179](https://gitlab.com/eneiluj/phonetrack-oc/issues/179) @Ryonez
- NC notification when point number quota is reached and when session is shared with a user
[#179](https://gitlab.com/eneiluj/phonetrack-oc/issues/179) @eneiluj
- add opentopomap tile server
- add empty ping API to allow phone client to connect
- add api entry point to get sessions information
- add api entry point to create/get public share for one device

### Changed
- update max zoom for base tileservers

### Fixed
- touch() exported files to make nextcloud display correct sizes
[#181](https://gitlab.com/eneiluj/phonetrack-oc/issues/181) @Valdnet

## 0.3.9 – 2018-11-28
### Added
- add fontawesome brand icons, use gitlab one
- ability to import google timeline kml export
[#172](https://gitlab.com/eneiluj/phonetrack-oc/issues/172) @FloThinksPi
- new feature : save/load filters bookmarks
[#171](https://gitlab.com/eneiluj/phonetrack-oc/issues/171) @CH5525
- add API route to get session list

### Changed
- max NC version : 15
[#176](https://gitlab.com/eneiluj/phonetrack-oc/issues/176) @tacruc

### Fixed
- import/export are now memory efficient, whatever size is the data
[#172](https://gitlab.com/eneiluj/phonetrack-oc/issues/172) @FloThinksPi
- fix email notifications (geofence and proxim) : use alias in priority, then name. works with name reservation
[#173](https://gitlab.com/eneiluj/phonetrack-oc/issues/173) @mychalwipf
- set empty alias to remove was broken
[#173](https://gitlab.com/eneiluj/phonetrack-oc/issues/173) @mychalwipf
- fix : apply filters AFTER having reset the field
[#171](https://gitlab.com/eneiluj/phonetrack-oc/issues/171) @CH5525

## 0.3.8 – 2018-11-18
### Added
- jshint in CI
- allow to set email address(es) for geofences and proxim alerts
[#166](https://gitlab.com/eneiluj/phonetrack-oc/issues/166) @mychalwipf
- add admin settings additional section to set user point number quota
[#154](https://gitlab.com/eneiluj/phonetrack-oc/issues/154) @jookk
- add user options to choose what happens when quota is reached : block, delete oldest point
[#154](https://gitlab.com/eneiluj/phonetrack-oc/issues/154) @jookk
- lots of controller tests (import/export/geofence/proxims/purge/filters : coverage close to 100%

### Changed
- put utf-8 symbols in info.xml and README
- QRcode : use kjua.js, foreground color adapts to theming, darker, round corners, logo, margin
[#120](https://gitlab.com/eneiluj/phonetrack-oc/issues/120) @jookk
[#152](https://gitlab.com/eneiluj/phonetrack-oc/issues/152) @e-alfred
- press Enter on new session name => create
- make it more explicit when there is no avg speed or no max speed
- rewrite user options system : use NC config
- only save option value which just changed (reset all user option to default values)

### Fixed
- theming support : jquery dialog, layer selector
- fix escape key detection : use keyup event instead of keypress,
- CI works again : apply patch to Nextcloud while deploying
- fix speed logged with Ulogger
[#167](https://gitlab.com/eneiluj/phonetrack-oc/issues/167) @nicolasvila
- bugs with geofence URLs
- bug with session zoom and device following
[#169](https://gitlab.com/eneiluj/phonetrack-oc/issues/169) @Valdnet

## 0.3.6 – 2018-10-17
### Added
- add QRcode for logging urls in help dialogs
[#152](https://gitlab.com/eneiluj/phonetrack-oc/issues/152) @e-alfred
- add button in 'device actions' to generate Geo QRcode with last device position
[#120](https://gitlab.com/eneiluj/phonetrack-oc/issues/120) @jookk
- add options to toggle line/point display in public pages through URL GET parameters
[#155](https://gitlab.com/eneiluj/phonetrack-oc/issues/155) @Japhys
- add average and max speed to stats table
[#151](https://gitlab.com/eneiluj/phonetrack-oc/issues/151) @vixu
- add CONTRIBUTING guidelines

### Changed
- browser logging URL and OpenGTS URL now have help dialog too
[#152](https://gitlab.com/eneiluj/phonetrack-oc/issues/152) @e-alfred
- better SQL queries design in controllers
- use css variables to adapt to accessibility theming @earboxer
- load device points even when line/point are disabled
[#151](https://gitlab.com/eneiluj/phonetrack-oc/issues/151) @vixu
- trigger CI in test branch too (easier for contributors who want
  to keep their master clean and run tests for their work

### Fixed
- stat table cell color when shape is triangle
[#159](https://gitlab.com/eneiluj/phonetrack-oc/issues/159) @eneiluj
- secure null values given to escapeHTML (happens when having DB problems)
- session deletion now triggers deletion of all related things
- dirty patch Nextcloud in CI to make PhpUnit tests work again
- make CI more generic so any user (fork) can run it in his/her own master branch
- bug in public filtered share when displaying last positions only
[#164](https://gitlab.com/eneiluj/phonetrack-oc/issues/164) @Valdnet

## 0.3.5 – 2018-10-11
### Added
- new option to toggle letter on last position
[#159](https://gitlab.com/eneiluj/phonetrack-oc/issues/159) @Valdnet
- new option to choose device points shape
[#159](https://gitlab.com/eneiluj/phonetrack-oc/issues/159) @Valdnet

### Changed
- change point radius, line width and opacity input styles to slider
[#159](https://gitlab.com/eneiluj/phonetrack-oc/issues/159) @Valdnet
- reorder and separate options

### Fixed
- fix bad INNER JOIN sql syntax refused by PostgreSQL
[#160](https://gitlab.com/eneiluj/phonetrack-oc/issues/160) @linux571

## 0.3.4 – 2018-10-09
### Added
- add option to set max number of points to load per device on refresh
[#107](https://gitlab.com/eneiluj/phonetrack-oc/issues/107) @e-alfred
- allow to set precise geofence coordinates
[#142](https://gitlab.com/eneiluj/phonetrack-oc/issues/142) @Valdnet
- add geo links to open device positions in external apps/softwares
[#120](https://gitlab.com/eneiluj/phonetrack-oc/issues/120) @jookk
- new feature : proximity notification for device pairs
[#126](https://gitlab.com/eneiluj/phonetrack-oc/issues/126) @poVoq
- notification if zooming on device with no points
[#156](https://gitlab.com/eneiluj/phonetrack-oc/issues/156) @Valdnet
- ability to set geofence zone corners with click on map
[#142](https://gitlab.com/eneiluj/phonetrack-oc/issues/142) @Valdnet

### Changed
- remove pushover support (already possible with POST URL)
[#118](https://gitlab.com/eneiluj/phonetrack-oc/issues/118) @Brakelmann
- make CI use NC14
- make geofencing email notification optional
[#136](https://gitlab.com/eneiluj/phonetrack-oc/issues/136) @abmurski
- manual point adding now also triggers geofencing events
[#136](https://gitlab.com/eneiluj/phonetrack-oc/issues/136) @abmurski
- change device autozoom checkbox into an icon
- change routing icons
- zoom on geofence when fence name is clicked
- remember if stats table is enabled
[#147](https://gitlab.com/eneiluj/phonetrack-oc/issues/147) @Valdnet
- lots of design improvements in filters and geofence definition
- get entire device list when loading session and not lazy-load it when points arrive
[#148](https://gitlab.com/eneiluj/phonetrack-oc/issues/148) @Valdnet
- [filter] if not date is set but hour:min:sec is set, it implicitly concerns today
[#149](https://gitlab.com/eneiluj/phonetrack-oc/issues/149) @vixu
- remove absurd automatic device deletion after deleting its last loaded point
- update leaflet to 1.3.4

### Fixed
- fix NaN displayed in tooltip when values are not set
- bug when zooming on device with only one coordinate
- css for filters
[!150](https://gitlab.com/eneiluj/phonetrack-oc/merge_requests/150) @Valdnet
- css for geofences
- fix header hiding and initial sidebar state in public page
[#139](https://gitlab.com/eneiluj/phonetrack-oc/issues/139) @vixu
- newly manually added point is draggable if possible
- padding when zooming
[#146](https://gitlab.com/eneiluj/phonetrack-oc/issues/146) @Valdnet
- sidebar state when collapsed after loading
- manually add point to reserved name, now works for session owner with normal name (not the token)
[#143](https://gitlab.com/eneiluj/phonetrack-oc/issues/143) @Valdnet
- bug on some actions for newly added devices
[#148](https://gitlab.com/eneiluj/phonetrack-oc/issues/148) @Valdnet
- huge bug when editing recently manually added point and then trying to delete it
- make app pass Nextcloud code check
- device color changing in other browsers than Firefox
[#139](https://gitlab.com/eneiluj/phonetrack-oc/issues/139) @Valdnet
- update leaflet.polylinedecorator to fix zoom performance issue with arrows displayed
- translations when locale is en\_GB
[#128](https://gitlab.com/eneiluj/phonetrack-oc/issues/128) @poVoq

## 0.3.1 – 2018-08-25
### Added
- show other devices in session (only public ones) as owntrack friends
- add link to graphhopper, OSRM and OpenRouteService to get routing information to a device
[#120](https://gitlab.com/eneiluj/phonetrack-oc/issues/120) @jookk
- add geofence notification options : pushover and HTTP POST
[#118](https://gitlab.com/eneiluj/phonetrack-oc/issues/118) @Brakelmann
- compatibility with Nextcloud 14
[#125](https://gitlab.com/eneiluj/phonetrack-oc/issues/125) @eneiluj
- new feature : set device name alias
[#116](https://gitlab.com/eneiluj/phonetrack-oc/issues/116) @stevenhorner
- add option to display arrows along lines
[#99](https://gitlab.com/eneiluj/phonetrack-oc/issues/99) @kaistian

### Changed
- make CI more generic
- get speed and bearing from Ulogger
[#112](https://gitlab.com/eneiluj/phonetrack-oc/issues/112) @Tux12Fun
- move auto purge AFTER auto export
[#109](https://gitlab.com/eneiluj/phonetrack-oc/issues/109) @NoName805
- upgrade fontawesome
- make some public share filters dynamic (lastdays, lasthours and lastmins)
[#104](https://gitlab.com/eneiluj/phonetrack-oc/issues/104) @eghetto

### Fixed
- fix marker update when all device point timestamps are 0
- Fix time-conversion from ms to s on 32-bit systems
- Add speed and bearing to OsmAnd
[#123](https://gitlab.com/eneiluj/phonetrack-oc/issues/123) @Mamie
- fix huge bug, missing point ids in gradient (hot)line coordinates
[#99](https://gitlab.com/eneiluj/phonetrack-oc/issues/99) @kaistian

## 0.2.8 – 2018-05-21
### Added
- options to cut device lines when point are too far (distance or time)
[#94](https://gitlab.com/eneiluj/phonetrack-oc/issues/94) @WNYmathGuy
- ability to send GET requests when device gets in or out of a geofencing zone
[#97](https://gitlab.com/eneiluj/phonetrack-oc/issues/97) @einstein99
- option to draw lines with black and white color gradient surrounded by device's color outline
[#99](https://gitlab.com/eneiluj/phonetrack-oc/issues/99) @0x53A

### Fixed
- bug when refreshing session shared to another user
[#96](https://gitlab.com/eneiluj/phonetrack-oc/issues/96) @mihxx
- escape 'user' in SQL query for PostgreSQL
[#100](https://gitlab.com/eneiluj/phonetrack-oc/issues/100) @r100gs

## 0.2.7 – 2018-03-26
### Added
- auto purge option for sessions (delete points older than a day/week/month)
[#77](https://gitlab.com/eneiluj/phonetrack-oc/issues/77) @CaptainWasabi
- disable auto refresh when set to 0 or anything else than a positive integer
[#78](https://gitlab.com/eneiluj/phonetrack-oc/issues/78) @tessus
- option to export one file per device
- device-specific geofencing
[#79](https://gitlab.com/eneiluj/phonetrack-oc/issues/79) @dan-cristian
- new public share option : only show last position (web page and controllers affected)
[#91](https://gitlab.com/eneiluj/phonetrack-oc/issues/91) @tessus
- new fields speed and bearing (traccar/gpslogger/logPost/logGet). filters, import/export adapted
[#90](https://gitlab.com/eneiluj/phonetrack-oc/issues/90) @mihxx
- new public share option to simplify points to nearest geofencing zone center
[#92](https://gitlab.com/eneiluj/phonetrack-oc/issues/92) @phyks

### Changed
- only get last point if no line/points asked for a device
[#77](https://gitlab.com/eneiluj/phonetrack-oc/issues/77) @CaptainWasabi
- delete points by group of 500 to make it faster
[#77](https://gitlab.com/eneiluj/phonetrack-oc/issues/77) @CaptainWasabi
- default refresh interval : 15 seconds
- improved tests : add a few SQL injection tries
[#86](https://gitlab.com/eneiluj/phonetrack-oc/issues/86) @eneiluj
- update to leaflet 1.3.1
- clarify point values validation
[#93](https://gitlab.com/eneiluj/phonetrack-oc/issues/93) @tessus
- nicer svg icons
- use Leaflet.Dialog to display 'loading' animation on refresh, import and export
[#83](https://gitlab.com/eneiluj/phonetrack-oc/issues/83) @efelon

### Fixed
- bad string point id when manually adding a point
- refuse to log points with non numeric coordinates or timestamp
[#87](https://gitlab.com/eneiluj/phonetrack-oc/issues/87) @phyks
- don't send names, colors or geofences for devices with no points/lasttime in track, publicWebLogTrack and publicViewTrack
- many missing tooltip update after edition, filter change...
- avoid zooming on device with no point
- insert NULL in DB instead of dumb values
[#93](https://gitlab.com/eneiluj/phonetrack-oc/issues/93) @tessus

## 0.2.2 – 2018-02-22
### Fixed
- mistake in tooltips behaviour
- change deviceid DB field type to integer
- add index for deviceid and timestamp in points table
- mistake in public page detection

## 0.2.0 – 2018-02-20
### Added
- help dialogs to configure logging apps
- add option to set auto export path
[#66](https://gitlab.com/eneiluj/phonetrack-oc/issues/66) @TMaddox
- countdown to see when is next refresh
- button to manually refresh
- field to restrict public filtered share to one device name
[#45](https://gitlab.com/eneiluj/phonetrack-oc/issues/45) @Mamie
- unit tests + gitlab CI integration + automatic coverage report
- lots of new translations

### Changed
- show loading animation when importing
- display progression on refresh
- huge interface performance improvements :
- performance improvement : import queries grouped
- performance improvement : smaller track data -50%, faster load
- performance improvement : update lines/points before and after refresh : interface more responsive
- performance improvement : ~12x speedup when adding lots of points, avoid DOM manipulation and generate popups/tooltips only when needed
- performance improvement : filters and options are not red from the DOM anymore
- performance improvement : avoid intermediate function for .on events
- performance improvement : use same icon for all points of a device
[#76](https://gitlab.com/eneiluj/phonetrack-oc/issues/76) @jookk
- cancel refresh if a session is deselected while refreshing

### Fixed
- auto export daily file name mistake
- fix cursor for checkboxes
- word wrap in stat table
- remove useless string replacements

## 0.1.1 – 2017-12-27
### Fixed
- potential bug in data conversion to new database schema
[#65](https://gitlab.com/eneiluj/phonetrack-oc/issues/65) @kaistian

## 0.1.0 – 2017-12-25
### Added
- all points values are now exported/imported
- lots of translations (Polish, Turkish, Slovak, Portuguese Brazilian, Dutch, Spanish) !
- public shares with filters
[#45](https://gitlab.com/eneiluj/phonetrack-oc/issues/45) @Mamie
- daily/weekly/monthly session cron auto export
[#55](https://gitlab.com/eneiluj/phonetrack-oc/issues/55) @Sander8

### Changed
- zoom on normal page load
[#54](https://gitlab.com/eneiluj/phonetrack-oc/issues/54) @GLLM1
- dropdown menu style improved
- better session zoom behaviour, zoom on any available displayed content
- let user choose export file name
[#55](https://gitlab.com/eneiluj/phonetrack-oc/issues/55) @Sander8
- apply current filters when exporting session
[#55](https://gitlab.com/eneiluj/phonetrack-oc/issues/55) @Sander8
- move create/import buttons next to the logo
- make session renaming look like device renaming
- allow filters modification when they are activated
[#55](https://gitlab.com/eneiluj/phonetrack-oc/issues/55) @Sander8
- optimization : only load (from server) data in current filter interval
[#55](https://gitlab.com/eneiluj/phonetrack-oc/issues/55) @Sander8
- change filters background color when activated

### Fixed
- fix GPRMC coordinates parsing
[#58](https://gitlab.com/eneiluj/phonetrack-oc/issues/58) @namekal
- mistake in session export
[#52](https://gitlab.com/eneiluj/phonetrack-oc/issues/52) @tarator
- bad escaping of user id in controllers
- mistake in session zoom, now zooms on currently displayed content
- newly added points were not draggable
- fix point multiple deletion

## 0.0.8 – 2017-11-10
### Added
- button to toggle line for each device
[#21](https://gitlab.com/eneiluj/phonetrack-oc/issues/21) @Mamie
- statistics table
[#25](https://gitlab.com/eneiluj/phonetrack-oc/issues/25) @dbielz
[#42](https://gitlab.com/eneiluj/phonetrack-oc/issues/42) @Mamie
- german translations thanks to @oswolf
- ability to change a device color (saved in DB)
[#28](https://gitlab.com/eneiluj/phonetrack-oc/issues/28) @Mamie
- display precision circle around points on hover
[#26](https://gitlab.com/eneiluj/phonetrack-oc/issues/26) @Mamie
- ability to delete points
[#30](https://gitlab.com/eneiluj/phonetrack-oc/issues/30) @Mamie
- new style options (line width, point radius, line/points opacity)
[#29](https://gitlab.com/eneiluj/phonetrack-oc/issues/29) @Mamie
- new filter : last day:hour:min
[#32](https://gitlab.com/eneiluj/phonetrack-oc/issues/32) @GLLM1
- option to toggle values display in tooltips
[#33](https://gitlab.com/eneiluj/phonetrack-oc/issues/33) @GLLM1
- option theme to change default colors
[#34](https://gitlab.com/eneiluj/phonetrack-oc/issues/34) @GLLM1
- ability to reserver device name (associate with a name token)
[#31](https://gitlab.com/eneiluj/phonetrack-oc/issues/31) @Mamie
- add latlng and DMS coords in popup
[#41](https://gitlab.com/eneiluj/phonetrack-oc/issues/41) @GLLM1
- ability to rename device and reaffect it to another session
[#49](https://gitlab.com/eneiluj/phonetrack-oc/issues/49) @Mamie
- translations are now available on https://crowdin.com/project/phonetrack

### Changed
- improve filters
[#12](https://gitlab.com/eneiluj/phonetrack-oc/issues/12) @Mamie
[#27](https://gitlab.com/eneiluj/phonetrack-oc/issues/27) @Mamie
- tooltip header is now 'sessionname | devicename'
[#34](https://gitlab.com/eneiluj/phonetrack-oc/issues/34) @GLLM1
- elevation is now displayed as an integer
[#34](https://gitlab.com/eneiluj/phonetrack-oc/issues/34) @GLLM1
- style adapts to theming
[#34](https://gitlab.com/eneiluj/phonetrack-oc/issues/34) @GLLM1
- bigger font for marker letter
[#36](https://gitlab.com/eneiluj/phonetrack-oc/issues/36) @GLLM1
- home made button icons
[#39](https://gitlab.com/eneiluj/phonetrack-oc/issues/39) @GLLM1
- options are in a single column, change sidebar tabs order
[#38](https://gitlab.com/eneiluj/phonetrack-oc/issues/38) @GLLM1
- dropdown menu for device
- put icons in popup
[#48](https://gitlab.com/eneiluj/phonetrack-oc/issues/48) @GLLM1
- save/restore filter values, active sessions, devices states, sidebar status
[#46](https://gitlab.com/eneiluj/phonetrack-oc/issues/46) @GLLM1
- adapt sidebar filter icon to filter state

### Fixed
- fix marker not on top of points after device zoom
- fix impossible to toggle lines for a specific device when global lines disabled
- fix OC/NC l10n.pl script to produce .pot file and to generate .js and .json files from .po files

## 0.0.5 – 2017-09-30
### Added
- point edition (drag'n'drop), deletion
[#11](https://gitlab.com/eneiluj/phonetrack-oc/issues/11) @Mamie
- manually add point
[#13](https://gitlab.com/eneiluj/phonetrack-oc/issues/13) @Mamie
- date min/max point filter
[#12](https://gitlab.com/eneiluj/phonetrack-oc/issues/12) @Mamie
- add 'user agent' point field
[#11](https://gitlab.com/eneiluj/phonetrack-oc/issues/11) @Mamie
- import session from gpx file
- session user share system
[#10](https://gitlab.com/eneiluj/phonetrack-oc/issues/10) @ksarnelli

### Changed
- improve session list and device list style
[#15](https://gitlab.com/eneiluj/phonetrack-oc/issues/15) @escoand
- hide device list when session is not followed
- display public view page only if session is public

### Fixed
- update all tooltips and popups after renaming a session
- remove map objects when deleting a session
- display long device/session names

## 0.0.3 – 2017-09-07
### Added
- compatibility with Owntracks and Traccar
[#3](https://gitlab.com/eneiluj/phonetrack-oc/issues/3) @escoand
- compatibility with Ulogger and OpenGTS
- take URL deviceid if it's not default or empty, else take app user/deviceid if it's not empty, else 'unknown'
- make two public pages : one to watch, one to track
- able to rename sessions
- add option to make session public. if not, position are not showed in publicWebLog page
[#5](https://gitlab.com/eneiluj/phonetrack-oc/issues/5) @escoand

### Changed
- change 'precision' table field name to 'accuracy', make accuracy and altitude float
[#2](https://gitlab.com/eneiluj/phonetrack-oc/issues/2) @tcitworld
- for logging URLs : put token and device in URL path instead of a parameter
[#3](https://gitlab.com/eneiluj/phonetrack-oc/issues/3) @escoand
- use a different token for publicSessionWatch to avoid viewers to be able to deduce log URLs
[#5](https://gitlab.com/eneiluj/phonetrack-oc/issues/5) @escoand

### Fixed
- bad osmand parameters
- put default values in GET log
[#2](https://gitlab.com/eneiluj/phonetrack-oc/issues/2) @tcitworld
- bad field types
[#2](https://gitlab.com/eneiluj/phonetrack-oc/issues/2) @tcitworld
- order points by date in SQL query
- remove session name in public URL
- controller warnings

## 0.0.1 – 2017-08-31
### Added
- the app

### Changed
- from nothing, it appeared

### Fixed
- fix the world with this app
