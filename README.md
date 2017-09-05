# PhoneTrack ownCloud/Nextcloud application

PhoneTrack is an app to get tracking information from mobile devices
and display them dynamically on a Leaflet map. Principle is simple :

* create a tracking session
* give the tracking URL to the mobile devices. 3 methods :
    * With a web browser, on the session public track page, check "Log my position in this session" (works better on Android than on IOS...)
    * [OsmAnd gpx recording plugin](https://osmand.net/features?id=trip-recording-plugin#Online_tracking) (Android) is able to log to a custom URL with GET method. IOS version does not include recording plugin.
    * [GpsLogger](http://code.mendhak.com/gpslogger/#features) (Android) Perfect ! Setup in : Options -> Logging details -> Log to custom URL
    * [Owntracks](http://owntracks.org/) (IOS/Android) IOS version works, Android version does not show HTTP mode...
    * [µlogger](https://f-droid.org/packages/net.fabiszewski.ulogger/) (Android) The best IMHO. Bufferize positions when device looses connectivity and sends everything when back online.
    * [Traccar](https://www.traccar.org/client/) (IOS/Android)
    * [OpenGTS](http://opengts.org/) which is more a standard than an app. I successfully used [GpsLogger](http://code.mendhak.com/gpslogger/#features) (OpenGTS mode) and [CelltrackGTS/Free](http://www.geotelematic.com/CelltracGTS/Free.html).
    * Does anyone know good and free ([as in "free speech"](https://www.gnu.org/philosophy/free-sw.en.html)) app which can log position to custom URL in background ? Create an issue if you do !
* Watch the session's devices positions in real time (or not) in PhoneTrack normal or public page

On PhoneTrack main page, while watching a session, you can :

* select some devices to make the automatic zoom work only with those devices
* toggle position history display (path lines)
* toggle devices last point date display
* rename a session
* export sessions in GPX format (one subtrack by device). They are saved in Nextcloud/ownCloud files.

Public page works like main page except there is only one session displayed and there is no need to be logged in.

If you want to help to translate this app in your language, take the english=>french files in "l10n" directory as examples.

This app is tested under Owncloud 9.0/Nextcloud 11 with Firefox and Chromium.

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
