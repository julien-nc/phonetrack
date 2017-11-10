# PhoneTrack ownCloud/Nextcloud application

PhoneTrack is an app to get tracking information from mobile devices
and display them dynamically on a Leaflet map.

Go to [PhoneTrack Crowdin project](https://crowdin.com/project/phonetrack) if you want to help to translate this app in your language.

How to use PhoneTrack :

* create a tracking session
* give the tracking URL\* to the mobile devices. Choose the method you prefer :
    * With a web browser, on the session public logging page, check "Log my position in this session" (works better on Android than on IOS...)
    * [OsmAnd gpx recording plugin](https://osmand.net/features?id=trip-recording-plugin#Online_tracking) (Android) is able to log to a custom URL with GET method. IOS version does not include recording plugin. Tested and approved.
    * [GpsLogger](http://code.mendhak.com/gpslogger/#features) (Android) Very good ! Setup in : Options -> Logging details -> Log to custom URL
    * [Owntracks](http://owntracks.org/) (IOS/Android) Both version work. This app does not work without google services installed on Android. Quite funny to provide an app for those who want to keep control of their tracking information and force them to be tracked by google services...
    * [µlogger](https://f-droid.org/packages/net.fabiszewski.ulogger/) (Android) The best IMHO. Very light. Bufferize positions when device looses connectivity and sends everything when back online.
    * [Traccar](https://www.traccar.org/client/) (IOS/Android) Quite good, not very verbose. Also able to bufferize.
    * [OpenGTS](http://opengts.org/) which is more a standard than an app. I successfully used [GpsLogger](http://code.mendhak.com/gpslogger/#features) (OpenGTS mode) and [CelltrackGTS/Free](http://www.geotelematic.com/CelltracGTS/Free.html) (a few bugs with this one).
    * Does anyone know good and free ([as in "free speech"](https://www.gnu.org/philosophy/free-sw.en.html)) app which can log position to custom URL in background ? Create an issue if you do !
* Watch the session's devices positions in real time (or not) in PhoneTrack normal or public page

(\*) Don't forget to set the device name in the URL. Replace "yourname" with the desired device name. Setting the device name in logging app only works with Owntracks, Traccar and OpenGTS.

On PhoneTrack main page, while watching a session, you can :

* edit/add/delete points
* select some devices to make the automatic zoom work only with those devices
* toggle position history display (path lines)
* toggle devices last point tooltip display
* rename a session
* rename a device
* change a device color
* move a device to another session
* share a session to other users (read-only)
* make a session public and share it via a public link. If the session is not public, positions are not visible in web logging page "publicWebLog".
* export sessions in GPX format (one subtrack by device). They are saved in Nextcloud/ownCloud files.
* display session statistics
* filter points (any criterias combination)

Public page works like main page except there is only one session displayed and there is no need to be logged in.

This app is tested with Owncloud 10/Nextcloud 12 with Firefox 56+ and Chromium.

This app is under development.

Link to Owncloud application website : https://marketplace.owncloud.com/apps/phonetrack

Link to Nextcloud application website : https://apps.nextcloud.com/apps/phonetrack

## Donation

I develop this app during my free time. You can make a donation to me on Paypal. [Click HERE to make a donation](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=66PALMY8SF5JE) (you don't need a paypal account)

## Install

See the [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) for more details.

Put phonetrack directory in Owncloud/Nextcloud apps directory to install.
There are several ways to do that :

### Clone the git repository

```
cd /path/to/owncloud/apps
git clone https://gitlab.com/eneiluj/phonetrack-oc.git phonetrack
```

### Download from https://marketplace.owncloud.com or https://apps.nextcloud.com

Extract phonetrack archive you just downloaded from the website :
```
cd /path/to/owncloud/apps
tar xvf phonetrack-x.x.x.tar.gz
```

## Known issues

Any feedback will be appreciated.
