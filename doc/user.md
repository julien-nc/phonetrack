[[_TOC_]]

# Logging methods

If you know a good and free ([as in "free speech"](https://www.gnu.org/philosophy/free-sw.en.html)) app which can log position to custom URL in background, [create an issue](https://gitlab.com/eneiluj/phonetrack-oc/issues/new?issue%5Bassignee_id%5D=&issue%5Bmilestone_id%5D=) to let us know !

**Warning** : part of what follows is extremely subjective or ironic :grin:.

## Recommended :+1: 

### [PhoneTrack-Android](https://gitlab.com/eneiluj/phonetrack-android)

(available on F-Droid)

Am i objective to judge this one ? It is different than all the other loggers because you can log to multiple destinations with different settings. Positions are stored if there is no network (**\***). It has a [very small impact on battery life](https://gitlab.com/eneiluj/phonetrack-oc/issues/175#note_130338568). To log with this app, create a new "PhoneTrack log job". If account settings are configured, just select a session and let the magic happen. Otherwise set the log job fields manually or import any PhoneTrack logging URL. Check the [PhoneTrack-Android user doc](https://gitlab.com/eneiluj/phonetrack-android/wikis/userdoc) for more details.

Pros :

* multiple simultaneous settings
* bufferize positions (**\***)
* easier to configure as it gets sessions information from the server
* very small impact on battery
* option to keep gps on between fixes to get better accuracy
* simple interface
* Free Software, it's as yours as mine

Cons :

* it does not clean my house

### [µlogger](https://f-droid.org/packages/net.fabiszewski.ulogger/)

(Android, available on F-Droid)

Very light. Able to bufferize positions (**\***). To use µlogger, set the corresponding logging URL provided by phonetrack-oc as the "server URL" and put **any** value (it won't be used) as username / password. This app well designed, simple to use and focuses on logging.

Pros :

* bufferize positions (**\***)
* very small impact on battery
* simple interface
* Free Software

Cons :

* NO option to keep gps on between fixes to get better accuracy
* does not send battery level with positions
* it was designed to be used with Ulogger server so it looks like a trick to configure it for PhoneTrack

### [GpsLogger](http://code.mendhak.com/gpslogger/#features) 

(Android)

Setup in : Options -> Logging details -> Log to custom URL . Able to bufferize positions (**\***).

Pros :

* bufferize positions (**\***)
* quite small impact on battery
* option to keep gps on between fixes to get better accuracy
* simple interface
* Free Software

Cons :

* it asks for too many permissions (contacts)
* it promotes services that do not respect user's privacy
* too many options (or at least too much to read)

### [OsmAnd gpx recording plugin](https://osmand.net/features?id=trip-recording-plugin#Online_tracking)

(Android)

OsmAnd is able to log to a custom URL with GET method. IOS version does not include recording plugin. Tested and approved.

To log, configure the gpx recording plugin. The important settings for us are in "Online tracking" chapter : "Online tracking web address" and "Online tracking (GPX required)". Just copy the OsmAnd logging URL from PhoneTrack web interface to the "Online tracking web address" field. Enable "Online tracking (GPX required)". Change the interval options. Then, on the map view, start GPX recording to start logging.

Pros :

* bufferize positions (**\***)
* full-featured mapping app
* Free Software

Cons :

* big impact on battery life
* a little bit tricky to find out how to configure it to log

### Overland IOS

This AFAIK the only FOSS and free tracker app on IOS that actually works.

I just made a few tests to make the compatibility part but didn't use it enough to give pros and cons.

More information on https://overland.p3k.app/

### HTTP request

You can build your own logging system and make GET or POST HTTP requests to PhoneTrack.

Here is an example of logging URL with POST:

`https://your.server.org/NC_PATH_IF_NECESSARY/index.php/apps/phonetrack/logPost/TOKEN/DEVNAME`

and with GET:

`https://your.server.org/NC_PATH_IF_NECESSARY/index.php/apps/phonetrack/logGet/TOKEN/DEVNAME`

The POST or GET parameters are:

* lat (decimal latitude)
* lon (decimal longitude)
* alt (altitude in meters)
* timestamp (epoch timestamp in seconds)
* acc (accuracy in meters)
* bat (battery level in percent)
* sat (number of satellites)
* useragent (device user agent)
* speed (speed in meter per second)
* bearing (bearing in decimal degrees)

## Almost ok :neutral\_face: 

Anyone thinks of a better name for this category ? I offer a reward :wink:.

### With a web browser

Visit the "public browser logging URL" with your favourite browser.
Then check "Log my position in this session" (works better on Android than on IOS...).

This method is only recommended if you're using Firefox or any Free Software browser. Otherwise you can't know if your browser is spying on you.

### [Traccar](https://www.traccar.org/client/)

(IOS/Android)

Quite good, not very verbose. Able to bufferize positions (**\***). It is designed to log to Traccar server which does not make it natural to configure for PhoneTrack.

## Others :grimacing: 

This category is for those who don't want to participate to "make the world a better place" (M.Jackson :man\_dancing: ), those who suffer from strong pragmatism and those who developed a [Stockholm syndrome](https://en.wikipedia.org/wiki/Stockholm_syndrome) with google and co.

Following methods are not recommended because they involve proprietary software or promote services  that don't respect your privacy. Use them at your own risks :smile\_cat:.

### [Owntracks](http://owntracks.org/)

(IOS/Android)

This app does not work without google services installed on Android. Quite ironic to provide an app for those who want to keep control of their tracking information but force them to use google services which are known to be...intrusive.

If you pay the price of your freedom :wink:, it has plenty of features, i've heard.


### [OpenGTS](http://opengts.org/)

which is more a standard than an app. I successfully used [GpsLogger](http://code.mendhak.com/gpslogger/#features) (in OpenGTS mode) and [CelltrackGTS/free](http://www.geotelematic.com/CelltracGTS/Free.html) (a few bugs with this one).

### [LocusMap](https://www.locusmap.eu/)

(Android)

which i never tried because it's proprietary and accessible just in amazon and google stores...

\* : When device looses connectivity, the app stores positions and sends everything when back online.

## Untested :neutral\_face:

### Location reporter for Raspberry Pi

Here is the project page: https://gitlab.com/larsfp/locationreporter

Here is a detailed article about it: https://0p.no/2018/05/25/car_logger_part_3.html

Feel free to create an issue if you used it and want to share your impressions.

# Phone logging apps comparison table :bar_chart:

Android clients:

| **Client** | license | Google Play dependency | battery life impact | offline buffering | multiple simultaneous settings | time/distance limit | sending to evil server by default | simple to configure to log to Nextcloud PhoneTrack | extra features
| ---- | --- | ---- | --- | - | - | - | - | - | - |
|PhoneTrack-Android | GPLv3 :heavy_check_mark:  | no :heavy_check_mark:  | little :heavy_check_mark:  | yes :heavy_check_mark: | yes :heavy_check_mark: | yes :heavy_check_mark: | no :heavy_check_mark: | yeah! :heavy_check_mark:  | motion detection, SMS remote control :heavy_check_mark:  |
|OsmAnd | GPLv3 :heavy_check_mark:  | no :heavy_check_mark:  | big :x:  | yes :heavy_check_mark: | no :x: | yes :heavy_check_mark: | no :heavy_check_mark: | yes :heavy_check_mark:  | :x: |
|µLogger |  GPLv3 :heavy_check_mark:  | no :heavy_check_mark:  | little :heavy_check_mark: | yes :heavy_check_mark: | no :x: | yes :heavy_check_mark: | no :heavy_check_mark: | yes :heavy_check_mark:  | :x: |
|GpsLogger | GPLv2 :heavy_check_mark:  | no :heavy_check_mark:  | little :heavy_check_mark: | yes :heavy_check_mark: | no :x: | yes :heavy_check_mark: | no :heavy_check_mark: | no :x: | motion detection :heavy_check_mark: |
|Traccar | Apachev2 :heavy_check_mark:  | no :heavy_check_mark:  | little :heavy_check_mark: | yes :heavy_check_mark: | no :x: | yes :heavy_check_mark: | no :heavy_check_mark: | yes :heavy_check_mark:  | :x: |
|OwnTracks | Eclipsev1 :heavy_check_mark:  | yes :x: | dunnow | yes :heavy_check_mark: | no :x: | dunnow | no :heavy_check_mark: | nope :x: | :x: |
|LocusMaps | proprietary :x: | yes :x: | dunnow | dunnow | no :x: | dunnow | no :heavy_check_mark: | nope :x: | :x: |

iOS clients:

| **Client** | license | battery life impact | offline buffering | multiple simultaneous settings | time/distance limit | sending to evil server by default | simple to configure to log to Nextcloud PhoneTrack | extra features
| ---- | --- | --- | - | - | - | - | - | - |
|Traccar | Apachev2 :heavy_check_mark: | dunnow | yes :heavy_check_mark: | no :x: | yes :heavy_check_mark: | no :heavy_check_mark: | yes :heavy_check_mark:  | :x: |
|OwnTracks | MIT :heavy_check_mark:  | dunnow | yes :heavy_check_mark: | no :x: | dunnow | no :heavy_check_mark: | nope :x: | :x: |
|Overland | Apachev2 :heavy_check_mark:  | dunnow | yes :heavy_check_mark: | no :x: | yes :heavy_check_mark:  | no :heavy_check_mark: | yes :heavy_check_mark: | significant motion (didn't try it) |

# Logging apps comparison by @slaver

* Android 7.1 (stock samsung firmware):
    1. All apps don't work without Location services enabled.
    2. Only Traccar can operate with Location services via WiFi/Bluetooth/Network.

* Android 7.1 (LineageOS):
    1. All apps don't work without Location services enabled.
    2. All apps don't work with Location services via WiFi/Bluetooth/Network.
    3. Traccar v.5.8/5.7/5.6 doesn't work at all (even no GPS requests from that app).
    4. Traccar v.5.5 is OK.
    5. GPSlogger is a "champion" of battery usage, Traccar and uLogger eat far less.
    6. Traccar shows terrible track accuracy.
    7. GPSlogger has the best accuracy.
    ![screenshot](/uploads/8d8d8718e5913eae8d7c241122d0b795/screenshot.jpeg)

# Device name reservation

## What is it ?

By default, there is no restriction and everyone who has the session's logging URL can choose whatever device name he wants to log. This means anyone can cheat on his identity. As the session's owner, if you want to prevent that, you can reserve some device names in PhoneTrack's user interface. To reserve a name means that only one person (that you choose) will be able to log with this name.

## How do i do it ?

To actually reserve a name : type the name in the reservation input field and type "ENTER". It will show you a "name token" associated with the reserved name you just chose. Using this "name token" as device name in a logging URL (or in PhoneTrack-Android log job's device name field) is now the ONLY way to log under the reserved name with logging URLs. This means anyone trying to use the reserved name directly in the logging URL will see his logging attempts refused.

## Example

I created a session to share positions with Alice and Bob. I want to make it impossible for Alice to log points with "Bob" as device name and Bob to log with "Alice" as device name.
Here is the Ulogger logging URL is : ```https://mynextcloud.host.com/apps/phonetrack/log/ulogger/48947ce5d37d947f38724fb8b20d43d/yourname```. Here are the reservation token i made for Alice and Bob :
```
Alice : ac98bb9b15afc3d028d845b7fce4ab2c
Bob : 8691aa01a76bb2d8b0db2d80e9b1ec8d
```

Here is the URL i can send to Alice for her to use it with Ulogger :

`https://mynextcloud.host.com/apps/phonetrack/log/ulogger/48947ce5d37d947f38724fb8b20d43d/ac98bb9b15afc3d028d845b7fce4ab2c`

and the one for Bob :

`https://mynextcloud.host.com/apps/phonetrack/log/ulogger/48947ce5d37d947f38724fb8b20d43d/8691aa01a76bb2d8b0db2d80e9b1ec8d`.

Let's be clear, this URL will NOT WORK :

`https://mynextcloud.host.com/apps/phonetrack/log/ulogger/48947ce5d37d947f38724fb8b20d43d/Bob` 

because the name "Bob" is reserved.

This way you don't make it impossible for Alice or Bob to log as any other name but at least you know Bob can't log as Alice and Alice can't log as Bob.

# Geofences

A geofence is a square shaped zone defined for a device. When the device enters or leaves the geofence, an alert is sent.

When defining a geofence, you can set :

* the zone coordinates (duh)
* choose if an email should be sent
* choose the destination email address(es)
* the URL to visit when the device enter or leaves the zone
* if the URL should be queried with POST method or not (GET)

# Proximity alert

A proximity alert is defined with a pair of devices, a small and a big distance. The alert is triggered when the two devices get closer than the small distance or when they get farther than the big distance.

For both geofences and proximity alerts, user email is used if the email address field is left empty.

Emails will be sent only if Nextcloud's email settings are properly set.

# Sharing

There are multiple ways to share a session with PhoneTrack.

## With other Nextcloud users

Just add users you want to share the session with in the session's "share" options. They will have read-only access to the session.

## Public session URL

Each session can be set public and then can be shared with a public URL. This URL allows the viewers to see all session points but everything is read-only.

## Public filtered share

You can create multiple public filtered shares for a session. First set and enable the filters, then create the public filtered share. The generated URL allows the viewers to see the session with those filters applied.

For each public filtered share, additional options are available :

### last positions only

If this is enabled, the public share will only show last devices positions.

### geofencify

If this is enabled, all points located inside a geofence will be showed at the geofence center. It is a kind of location simplification.

### Show one device only

You can restrict the public share to only display one device.

# Options

## Minimum distance to cut between two points

If this option is set to 1000m (for example), lines between two points which distance from each other is more than 1000m won't be displayed. The statistic table is also impacted.

## Minimum time to cut between two points

Same as previous option but with a duration. Those two options are not mutually exclusive, then can both be set at the same time.

# Point quota

Nextcloud admin can set a point number quota for PhoneTrack users. When a user reaches this amount of points, he/she can choose what happens :

* block logging : no new points can be logged
* delete user's oldest point each time a new one is logged : this will find the oldest point logged in any user's session and delete it each time a new point is logged. This way, the quota will never be exceeded.
* delete device's oldest point each time a new one is logged : each time a point is logged for a device, the oldest point of this device will be deleted before logging the new one. If this is the first point logged for this device, the oldest point of all existing device will be deleted.

# Session auto export

Users can choose to automatically export a session periodically. This action will be triggered by Nextcloud "cron tasks" at the requested frequency. Users can choose the path where automatic exports will be saved.

# Session auto purge

Automatic purge means periodical automatic point deletion for a session. For example, if weekly auto purge is set for a session, last complete week points will be deleted at the beginning of each new week.
