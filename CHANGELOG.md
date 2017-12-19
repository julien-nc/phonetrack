# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) 
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Added
- all values are now exported/imported

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

### Fixed
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

