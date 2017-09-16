# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) 
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Added
- point edition, deletion
[#11](https://gitlab.com/eneiluj/phonetrack-oc/issues/11) @Mamie
- manually add point
[#13](https://gitlab.com/eneiluj/phonetrack-oc/issues/13) @Mamie
- date min/max point filter
[#12](https://gitlab.com/eneiluj/phonetrack-oc/issues/12) @Mamie
- add 'user agent' point field
[#11](https://gitlab.com/eneiluj/phonetrack-oc/issues/11) @Mamie
- import session from gpx file

### Changed
- improve session list and device list style
[#15](https://gitlab.com/eneiluj/phonetrack-oc/issues/15) @escoand
- hide device list when session is not followed

### Fixed
- update all tooltips and popups after renaming a session
- remove map objects when deleting a session

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

