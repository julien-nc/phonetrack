# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) 
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
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

