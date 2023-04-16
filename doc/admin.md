[[_TOC_]]

# Issue with PhoneTrack restricted to some groups in Nextcloud

PhoneTrack **did** not work if it's restricted to some groups. The reason is it's impossible to access public pages of an app (log URLs in our case) because Nextcloud considers the app is disabled when it is accessed without being logged in.

**It has been solved** in [this pull-request](https://github.com/nextcloud/server/pull/8593). It works if you're using Nextcloud >= 14.0.0 !

# Issue with PostgreSQL

As reported here :
https://gitlab.com/eneiluj/phonetrack-oc/issues/82#note_68155878
there were issues when updating PhoneTrack if your Nextcloud instance uses a PostgreSQL database. Solutions are given in the issue thread.

# Installation instructions

Put phonetrack directory in Nextcloud apps directory to install.
There are several ways to do that :

### Use Nextcloud integrated app manager

PhoneTrack is published in official application website. It is available in your Nextcloud admin settings.

### Clone the git repository

If you want to be on the bleeding edge :

```
cd /path/to/nextcloud/apps
git clone https://gitlab.com/eneiluj/phonetrack-oc.git phonetrack
```

### Download from Nextcloud apps website or the project's wiki

* https://apps.nextcloud.com
* [project's wiki](https://gitlab.com/eneiluj/phonetrack-oc/wikis/home#releases-for-nextcloud)

Extract the archive at the right place :
```
cd /path/to/nextcloud/apps
tar xvf phonetrack-x.x.x.tar.gz
```

# Point number quota

Nextcloud admin can set user point number quota value in Nextcloud additional settings. This quota is applied to every Nextcloud users. Users can choose what's to be done when their quota is reached in PhoneTrack user settings.
