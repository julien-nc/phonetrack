# GpsPhoneTracking ownCloud/Nextcloud application

GpsPhoneTracking is an app to get tracking information from mobile devices
and display them dynamically on a Leaflet map. Principle is simple :

* create a tracking session
* give the tracking URL to the mobile devices. 3 methods :
    * OsmAnd gpx recording plugin is able to log to a custom URL with GET method
    * GpsLogger (Options -> Logging details -> Log to custom URL)
    * With a web browser, on the session public page, check "Log my position in this session"
    * Does anyone know an IOS app which can log position to custom URL in background ? Create an issue if you do !
* Watch the session's devices positions in real time (or not) in GpsPhoneTracking normal or public page

On GpsPhoneTracking main page, while watching a session, you can select some devices to make the automatic zoom
work only with those devices. Sessions can be exported in GPX format (one subtrack by device) and
saved in Nextcloud/ownCloud files.

Public page works like main page except there is only one session displayed.

If you want to help to translate this app in your language, take the english=>french files in "l10n" directory as examples.

This app is tested under Owncloud 9.0/Nextcloud 11 with Firefox and Chromium.

This app is under development.

Link to Owncloud application website : https://marketplace.owncloud.com/apps/gpsphonetracking

Link to Nextcloud application website : https://apps.nextcloud.com/apps/gpsphonetracking

## Donation

I develop this app during my free time. You can make a donation to me on Paypal. [Click HERE to make a donation](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=66PALMY8SF5JE) (you don't need a paypal account)

## Install

See the [AdminDoc](https://gitlab.com/eneiluj/gpsphonetracking-oc/wikis/admindoc) for more details.

Put gpsphonetracking directory in Owncloud/Nextcloud apps directory to install.
There are several ways to do that :

### Clone the git repository

```
cd /path/to/owncloud/apps
git clone https://gitlab.com/eneiluj/gpsphonetracking-oc.git gpsphonetracking
```

### Download from https://apps.owncloud.com or https://apps.nextcloud.com

Extract gpsphonetracking archive you just downloaded from the website :
```
cd /path/to/owncloud/apps
tar xvf gpsphonetracking-x.x.x.tar.gz
```

## Known issues

Any feedback will be appreciated.
