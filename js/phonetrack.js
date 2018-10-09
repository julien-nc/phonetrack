(function ($, OC) {
    'use strict';

    //////////////// VAR DEFINITION /////////////////////

    var colorCodeBright = [
        '#ff0000',
        '#00ffff',
        '#800080',
        '#00ff00',
        '#ffff00',
        '#ffa500',
        '#0000ff',
        '#a52a2a',
        '#7fff00',
        '#dc143c',
        '#ff1493',
        '#ffd700'
    ];
    var colorCodePastel = [
        '#ACD941',
        '#C5B4CC',
        '#FFB904',
        '#FF7679',
        '#FFBEAF',
        '#94C6F8',
        '#EF3F3D',
        '#6B8200',
        '#FFA100',
        '#979cf7',
        '#fca2ab',
        '#d8fca2',
        '#77AFFF',
        '#a2fcf3',
        '#857BA7',
        '#c6a2fc'
    ];
    var colorCodeDark = [
        '#004081',
        '#634733',
        '#6D2403',
        '#3A240A',
        '#293A2E',
        '#400D31',
        '#424437',
        '#1E0E15'
    ];


    var lastColorUsed = -1;

    var phonetrack = {
        map: {},
        baseLayers: null,
        overlayLayers: null,
        restoredTileLayer: null,
        // indexed by session name, contains dict indexed by deviceid
        sessionLineLayers: {},
        // just the positions (the displayed ones, filtered, with the cut : list of lists)
        sessionDisplayedLatlngs: {},
        // just the positions (non-filtered)
        sessionLatlngs: {},
        // the featureGroups of line points
        sessionPointsLayers: {},
        // the same line points but indexed by their ID
        sessionPointsLayersById: {},
        sessionPointsEntriesById: {},
        // the last position markers
        sessionMarkerLayers: {},
        sessionColors: {},
        currentRefreshAjax: null,
        currentTimer: null,
        // remember the oldest and newest point of each device
        lastTime: {},
        firstTime: {},
        lastZindex: 1000,
        movepointSession: null,
        movepointDevice: null,
        movepointId: null,
        // to avoid checking the dom too many times
        isSessionShared: {},
        // indexed by token, then by deviceid
        deviceNames: {},
        deviceAliases: {},
        devicePointIcons: {},
        // indexed by token, then by devicename
        deviceIds: {},
        filtersEnabled: false,
        filterValues: {},
        optionsValues: {},
        NSEWClick: {}
    };

    var offset = L.point(-7, 0);

    var hoverStyle = {
        weight: 12,
        opacity: 0.7,
        color: 'black'
    };
    var defaultStyle = {
        weight: 5,
        opacity: 1
    };

    var symbolSelectClasses = {
        'Dot, White': 'dot-select',
        'Pin, Blue': 'pin-blue-select',
        'Pin, Green': 'pin-green-select',
        'Pin, Red': 'pin-red-select',
        'Flag, Green': 'flag-green-select',
        'Flag, Red': 'flag-red-select',
        'Flag, Blue': 'flag-blue-select',
        'Block, Blue': 'block-blue-select',
        'Block, Green': 'block-green-select',
        'Block, Red': 'block-red-select',
        'Blue Diamond': 'diamond-blue-select',
        'Green Diamond': 'diamond-green-select',
        'Red Diamond': 'diamond-red-select',
        'Residence': 'residence-select',
        'Drinking Water': 'drinking-water-select',
        'Trail Head': 'hike-select',
        'Bike Trail': 'bike-trail-select',
        'Campground': 'campground-select',
        'Bar': 'bar-select',
        'Skull and Crossbones': 'skullcross-select',
        'Geocache': 'geocache-select',
        'Geocache Found': 'geocache-open-select',
        'Medical Facility': 'medical-select',
        'Contact, Alien': 'contact-alien-select',
        'Contact, Big Ears': 'contact-bigears-select',
        'Contact, Female3': 'contact-female3-select',
        'Contact, Cat': 'contact-cat-select',
        'Contact, Dog': 'contact-dog-select',
    };

    var symbolIcons = {
        'Dot, White': L.divIcon({
                iconSize: L.point(7,7),
        }),
        'Pin, Blue': L.divIcon({
            className: 'pin-blue',
            iconAnchor: [5, 30]
        }),
        'Pin, Green': L.divIcon({
            className: 'pin-green',
            iconAnchor: [5, 30]
        }),
        'Pin, Red': L.divIcon({
            className: 'pin-red',
            iconAnchor: [5, 30]
        }),
        'Flag, Green': L.divIcon({
            className: 'flag-green',
            iconAnchor: [1, 25]
        }),
        'Flag, Red': L.divIcon({
            className: 'flag-red',
            iconAnchor: [1, 25]
        }),
        'Flag, Blue': L.divIcon({
            className: 'flag-blue',
            iconAnchor: [1, 25]
        }),
        'Block, Blue': L.divIcon({
            className: 'block-blue',
            iconAnchor: [8, 8]
        }),
        'Block, Green': L.divIcon({
            className: 'block-green',
            iconAnchor: [8, 8]
        }),
        'Block, Red': L.divIcon({
            className: 'block-red',
            iconAnchor: [8, 8]
        }),
        'Blue Diamond': L.divIcon({
            className: 'diamond-blue',
            iconAnchor: [9, 9]
        }),
        'Green Diamond': L.divIcon({
            className: 'diamond-green',
            iconAnchor: [9, 9]
        }),
        'Red Diamond': L.divIcon({
            className: 'diamond-red',
            iconAnchor: [9, 9]
        }),
        'Residence': L.divIcon({
            className: 'residence',
            iconAnchor: [12, 12]
        }),
        'Drinking Water': L.divIcon({
            className: 'drinking-water',
            iconAnchor: [12, 12]
        }),
        'Trail Head': L.divIcon({
            className: 'hike',
            iconAnchor: [12, 12]
        }),
        'Bike Trail': L.divIcon({
            className: 'bike-trail',
            iconAnchor: [12, 12]
        }),
        'Campground': L.divIcon({
            className: 'campground',
            iconAnchor: [12, 12]
        }),
        'Bar': L.divIcon({
            className: 'bar',
            iconAnchor: [10, 12]
        }),
        'Skull and Crossbones': L.divIcon({
            className: 'skullcross',
            iconAnchor: [12, 12]
        }),
        'Geocache': L.divIcon({
            className: 'geocache',
            iconAnchor: [11, 10]
        }),
        'Geocache Found': L.divIcon({
            className: 'geocache-open',
            iconAnchor: [11, 10]
        }),
        'Medical Facility': L.divIcon({
            className: 'medical',
            iconAnchor: [13, 11]
        }),
        'Contact, Alien': L.divIcon({
            className: 'contact-alien',
            iconAnchor: [12, 12]
        }),
        'Contact, Big Ears': L.divIcon({
            className: 'contact-bigears',
            iconAnchor: [12, 12]
        }),
        'Contact, Female3': L.divIcon({
            className: 'contact-female3',
            iconAnchor: [12, 12]
        }),
        'Contact, Cat': L.divIcon({
            className: 'contact-cat',
            iconAnchor: [12, 12]
        }),
        'Contact, Dog': L.divIcon({
            className: 'contact-dog',
            iconAnchor: [12, 12]
        }),
    };

    var METERSTOMILES = 0.0006213711;
    var METERSTOFOOT = 3.28084;
    var METERSTONAUTICALMILES = 0.000539957;

    //////////////// UTILS /////////////////////

    function pad(n) {
        return (n < 10) ? ('0' + n) : n;
    }

    function endsWith(str, suffix) {
        return str.indexOf(suffix, str.length - suffix.length) !== -1;
    }

    function basename(str) {
        var base = new String(str).substring(str.lastIndexOf('/') + 1);
        if (base.lastIndexOf(".") !== -1) {
            base = base.substring(0, base.lastIndexOf("."));
        }
        return base;
    }

    function hexToRgb(hex) {
        var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : null;
    }

    function brify(str, linesize) {
        var res = '';
        var words = str.split(' ');
        var cpt = 0;
        var toAdd = '';
        for (var i=0; i<words.length; i++) {
            if ((cpt + words[i].length) < linesize) {
                toAdd += words[i] + ' ';
                cpt += words[i].length + 1;
            }
            else{
                res += toAdd + '<br/>';
                toAdd = words[i] + ' ';
                cpt = words[i].length + 1;
            }
        }
        res += toAdd;
        return res;
    }

    function Timer(callback, delay) {
        var timerId, start, remaining = delay;

        this.pause = function() {
            window.clearTimeout(timerId);
            remaining -= new Date() - start;
        };

        this.resume = function() {
            start = new Date();
            window.clearTimeout(timerId);
            timerId = window.setTimeout(callback, remaining);
        };

        this.resume();
    }

    function toDegreesMinutesAndSeconds(coordinate) {
        var absolute = Math.abs(coordinate);
        var degrees = Math.floor(absolute);
        var minutesNotTruncated = (absolute - degrees) * 60;
        var minutes = Math.floor(minutesNotTruncated);
        var seconds = Math.floor((minutesNotTruncated - minutes) * 60);

        return degrees + "°" + minutes + "'" + seconds + escapeHTML('"');
    }

    function convertDMS(lat, lng) {
        var latitude = toDegreesMinutesAndSeconds(lat);
        var latitudeCardinal = Math.sign(lat) >= 0 ? 'N' : 'S';

        var longitude = toDegreesMinutesAndSeconds(lng);
        var longitudeCardinal = Math.sign(lng) >= 0 ? 'E' : 'W';

        return latitude + ' ' + latitudeCardinal + ' ' + longitude + ' ' + longitudeCardinal;
    }

    //////////////// MAP /////////////////////

    function load_map() {
        // change meta to send referrer
        // usefull for IGN tiles authentication !
        $('meta[name=referrer]').attr('content', 'origin');

        var layer = getUrlParameter('layer');
        var default_layer = 'OpenStreetMap';
        if (phonetrack.restoredTileLayer !== null) {
            default_layer = phonetrack.restoredTileLayer;
        }
        else if (typeof layer !== 'undefined') {
            default_layer = layer;
        }

        var baseLayers = {};

        // add base layers
        $('#basetileservers li[type=tile]').each(function() {
            var sname = $(this).attr('name');
            var surl = $(this).attr('url');
            var minz = parseInt($(this).attr('minzoom'));
            var maxz = parseInt($(this).attr('maxzoom'));
            var sattrib = $(this).attr('attribution');
            var stransparent = ($(this).attr('transparent') === 'true');
            var sopacity = $(this).attr('opacity');
            if (typeof sopacity !== typeof undefined && sopacity !== false && sopacity !== '') {
                sopacity = parseFloat(sopacity);
            }
            else {
                sopacity = 1;
            }
            baseLayers[sname] = new L.TileLayer(surl, {minZoom: minz, maxZoom: maxz, attribution: sattrib, opacity: sopacity, transparent: stransparent});
        });
        $('#basetileservers li[type=tilewms]').each(function() {
            var sname = $(this).attr('name');
            var surl = $(this).attr('url');
            var slayers = $(this).attr('layers') || '';
            var sversion = $(this).attr('version') || '1.1.1';
            var stransparent = ($(this).attr('transparent') === 'true');
            var sformat = $(this).attr('format') || 'image/png';
            var sopacity = $(this).attr('opacity');
            if (typeof sopacity !== typeof undefined && sopacity !== false && sopacity !== '') {
                sopacity = parseFloat(sopacity);
            }
            else {
                sopacity = 1;
            }
            var sattrib = $(this).attr('attribution') || '';
            baseLayers[sname] = new L.tileLayer.wms(surl, {layers: slayers, version: sversion, transparent: stransparent, opacity: sopacity, format: sformat, attribution: sattrib});
        });
        // add custom layers
        $('#tileserverlist li').each(function() {
            var sname = $(this).attr('servername');
            var surl = $(this).attr('url');
            var sminzoom = $(this).attr('minzoom') || '1';
            var smaxzoom = $(this).attr('maxzoom') || '20';
            var sattrib = $(this).attr('attribution') || '';
            baseLayers[sname] = new L.TileLayer(surl,
                    {minZoom: sminzoom, maxZoom: smaxzoom, attribution: sattrib});
        });
        $('#tilewmsserverlist li').each(function() {
            var sname = $(this).attr('servername');
            var surl = $(this).attr('url');
            var sminzoom = $(this).attr('minzoom') || '1';
            var smaxzoom = $(this).attr('maxzoom') || '20';
            var slayers = $(this).attr('layers') || '';
            var sversion = $(this).attr('version') || '1.1.1';
            var sformat = $(this).attr('format') || 'image/png';
            var sattrib = $(this).attr('attribution') || '';
            baseLayers[sname] = new L.tileLayer.wms(surl,
                    {format: sformat, version: sversion, layers: slayers, minZoom: sminzoom, maxZoom: smaxzoom, attribution: sattrib});
        });
        phonetrack.baseLayers = baseLayers;

        var baseOverlays = {};

        // add base overlays
        $('#basetileservers li[type=overlay]').each(function() {
            var sname = $(this).attr('name');
            var surl = $(this).attr('url');
            var minz = parseInt($(this).attr('minzoom'));
            var maxz = parseInt($(this).attr('maxzoom'));
            var sattrib = $(this).attr('attribution');
            var stransparent = ($(this).attr('transparent') === 'true');
            var sopacity = $(this).attr('opacity');
            if (typeof sopacity !== typeof undefined && sopacity !== false && sopacity !== '') {
                sopacity = parseFloat(sopacity);
            }
            else {
                sopacity = 0.4;
            }
            baseOverlays[sname] = new L.TileLayer(surl, {minZoom: minz, maxZoom: maxz, attribution: sattrib, opacity: sopacity, transparent: stransparent});
        });
        $('#basetileservers li[type=overlaywms]').each(function() {
            var sname = $(this).attr('name');
            var surl = $(this).attr('url');
            var slayers = $(this).attr('layers') || '';
            var sversion = $(this).attr('version') || '1.1.1';
            var stransparent = ($(this).attr('transparent') === 'true');
            var sopacity = $(this).attr('opacity');
            if (typeof sopacity !== typeof undefined && sopacity !== false && sopacity !== '') {
                sopacity = parseFloat(sopacity);
            }
            else {
                sopacity = 0.4;
            }
            var sformat = $(this).attr('format') || 'image/png';
            var sattrib = $(this).attr('attribution') || '';
            baseOverlays[sname] = new L.tileLayer.wms(surl, {layers: slayers, version: sversion, transparent: stransparent, opacity: sopacity, format: sformat, attribution: sattrib});
        });
        // add custom overlays
        $('#overlayserverlist li').each(function() {
            var sname = $(this).attr('servername');
            var surl = $(this).attr('url');
            var sminzoom = $(this).attr('minzoom') || '1';
            var smaxzoom = $(this).attr('maxzoom') || '20';
            var stransparent = ($(this).attr('transparent') === 'true');
            var sopacity = $(this).attr('opacity');
            if (typeof sopacity !== typeof undefined && sopacity !== false && sopacity !== '') {
                sopacity = parseFloat(sopacity);
            }
            else {
                sopacity = 0.4;
            }
            var sattrib = $(this).attr('attribution') || '';
            baseOverlays[sname] = new L.TileLayer(surl,
                    {minZoom: sminzoom, maxZoom: smaxzoom, transparent: stransparent, opcacity: sopacity, attribution: sattrib});
        });
        $('#overlaywmsserverlist li').each(function() {
            var sname = $(this).attr('servername');
            var surl = $(this).attr('url');
            var sminzoom = $(this).attr('minzoom') || '1';
            var smaxzoom = $(this).attr('maxzoom') || '20';
            var slayers = $(this).attr('layers') || '';
            var sversion = $(this).attr('version') || '1.1.1';
            var sformat = $(this).attr('format') || 'image/png';
            var stransparent = ($(this).attr('transparent') === 'true');
            var sopacity = $(this).attr('opacity');
            if (typeof sopacity !== typeof undefined && sopacity !== false && sopacity !== '') {
                sopacity = parseFloat(sopacity);
            }
            else {
                sopacity = 0.4;
            }
            var sattrib = $(this).attr('attribution') || '';
            baseOverlays[sname] = new L.tileLayer.wms(surl, {layers: slayers, version: sversion, transparent: stransparent, opacity: sopacity, format: sformat, attribution: sattrib, minZoom: sminzoom, maxZoom: smaxzoom});
        });
        phonetrack.overlayLayers = baseOverlays;

        phonetrack.map = new L.Map('map', {
            zoomControl: true
        });

        var notificationText = '<div id="loadingnotification"><i class="fa fa-spinner fa-pulse fa-3x fa-fw display"></i><b id="loadingpc"></b></div>';
        phonetrack.notificationDialog = L.control.dialog({
            anchor: [0, -65],
            position: 'topright',
            //minSize: [70, 70],
            //maxSize: [70, 70],
            size: [55, 55]
        })
        .setContent(notificationText)

        L.control.scale({metric: true, imperial: true, position: 'topleft'})
        .addTo(phonetrack.map);

        L.control.mousePosition().addTo(phonetrack.map);
        phonetrack.locateControl = L.control.locate({setView: false, locateOptions: {enableHighAccuracy: true}});
        phonetrack.locateControl.addTo(phonetrack.map);
        phonetrack.map.on('locationfound', locationFound);
        var linearcolor = '#FF0080';
        if (OCA.Theming) {
            linearcolor = OCA.Theming.color;
        }
        phonetrack.map.addControl(new L.Control.LinearMeasurement({
            unitSystem: 'metric',
            color: linearcolor,
            type: 'line'
        }));
        L.control.sidebar('sidebar').addTo(phonetrack.map);

        phonetrack.map.setView(new L.LatLng(27, 5), 3);

        if (! baseLayers.hasOwnProperty(default_layer)) {
            default_layer = 'OpenStreetMap';
        }
        phonetrack.map.addLayer(baseLayers[default_layer]);

        phonetrack.activeLayers = L.control.activeLayers(baseLayers, baseOverlays);
        phonetrack.activeLayers.addTo(phonetrack.map);

        //phonetrack.map.on('contextmenu',rightClick);
        //phonetrack.map.on('popupclose',function() {});
        //phonetrack.map.on('viewreset',updateTrackListFromBounds);
        //phonetrack.map.on('dragend',updateTrackListFromBounds);
        //phonetrack.map.on('moveend', updateTrackListFromBounds);
        //phonetrack.map.on('zoomend', updateTrackListFromBounds);
        //phonetrack.map.on('baselayerchange', updateTrackListFromBounds);
        if (! pageIsPublic()) {
            phonetrack.map.on('baselayerchange', saveOptions);
        }

        phonetrack.moveButton = L.easyButton({
            position: 'bottomright',
            states: [{
                stateName: 'nomove',
                icon:      'fa networkicon',
                title:     t('phonetrack', 'Show lines'),
                onClick: function(btn, map) {
                    $('#viewmove').click();
                    btn.state('move');
                }
            },{
                stateName: 'move',
                icon:      'fa networkicon nc-theming-main-background',
                title:     t('phonetrack', 'Hide lines'),
                onClick: function(btn, map) {
                    $('#viewmove').click();
                    btn.state('nomove');
                }
            }]
        });
        phonetrack.moveButton.addTo(phonetrack.map);

        if ($('#viewmove').is(':checked')) {
            phonetrack.moveButton.state('move');
        }
        else {
            phonetrack.moveButton.state('nomove');
        }

        phonetrack.zoomButton = L.easyButton({
            position: 'bottomright',
            states: [{
                stateName: 'nozoom',
                icon:      'fa autozoomicon',
                title:     t('phonetrack', 'Activate automatic zoom'),
                onClick: function(btn, map) {
                    $('#autozoom').click();
                    btn.state('zoom');
                }
            },{
                stateName: 'zoom',
                icon:      'fa autozoomicon nc-theming-main-background',
                title:     t('phonetrack', 'Disable automatic zoom'),
                onClick: function(btn, map) {
                    $('#autozoom').click();
                    btn.state('nozoom');
                }
            }]
        });
        phonetrack.zoomButton.addTo(phonetrack.map);

        if ($('#autozoom').is(':checked')) {
            phonetrack.zoomButton.state('zoom');
        }
        else {
            phonetrack.zoomButton.state('nozoom');
        }

        phonetrack.timeButton = L.easyButton({
            position: 'bottomright',
            states: [{
                stateName: 'noshowtime',
                icon:      'fa pointtooltipicon',
                title:     t('phonetrack', 'Show last point tooltip'),
                onClick: function(btn, map) {
                    $('#showtime').click();
                    btn.state('showtime');
                }
            },{
                stateName: 'showtime',
                icon:      'fa pointtooltipicon nc-theming-main-background',
                title:     t('phonetrack', 'Hide last point tooltip'),
                onClick: function(btn, map) {
                    $('#showtime').click();
                    btn.state('noshowtime');
                }
            }]
        });
        phonetrack.timeButton.addTo(phonetrack.map);

        if ($('#showtime').is(':checked')) {
            phonetrack.timeButton.state('showtime');
        }
        else {
            phonetrack.timeButton.state('noshowtime');
        }

        phonetrack.doZoomButton = L.easyButton({
            position: 'bottomright',
            states: [{
                stateName: 'no-importa',
                icon:      'fa normalzoomicon',
                title:     t('phonetrack', 'Zoom on all devices'),
                onClick: function(btn, map) {
                    zoomOnDisplayedMarkers();
                }
            }]
        });
        phonetrack.doZoomButton.addTo(phonetrack.map);
        $(phonetrack.doZoomButton.button).addClass('easy-button-inactive');
    }

    function enterMovePointMode() {
        $('.leaflet-container').css('cursor','crosshair');
        phonetrack.map.on('click', movePoint);
        OC.Notification.showTemporary(t('phonetrack', 'Click on the map to move the point, press ESC to cancel'));
    }

    function leaveMovePointMode() {
        $('.leaflet-container').css('cursor','grab');
        phonetrack.map.off('click', movePoint);
        phonetrack.movepointSession = null;
        phonetrack.movepointDevice = null;
        phonetrack.movepointId = null;
    }

    function movePoint(e) {
        var lat = e.latlng.lat;
        var lon = e.latlng.lng;
        var token = phonetrack.movepointSession;
        var deviceid = phonetrack.movepointDevice;
        var pid = phonetrack.movepointId;
        var entry = phonetrack.sessionPointsEntriesById[token][deviceid][pid];
        editPointDB(
            token,
            deviceid,
            pid,
            lat,
            lon,
            entry.altitude,
            entry.accuracy,
            entry.satellites,
            entry.batterylevel,
            entry.timestamp,
            entry.useragent,
            entry.speed,
            entry.bearing
        );
        leaveMovePointMode();
    }

    function dragPointEnd(e) {
        var m = e.target;
        var entry = phonetrack.sessionPointsEntriesById[m.session][m.device][m.pid];
        editPointDB(
            m.session,
            m.device,
            m.pid,
            m.getLatLng().lat,
            m.getLatLng().lng,
            entry.altitude,
            entry.accuracy,
            entry.satellites,
            entry.batterylevel,
            entry.timestamp,
            entry.useragent,
            entry.speed,
            entry.bearing
        );
    }

    function enterNSEWMode(but) {
        $('.leaflet-container').css('cursor','crosshair');
        var s = but.parent().parent().parent().parent().attr('token');
        var d = but.parent().parent().parent().parent().attr('device');
        var ne = but.hasClass('geonortheastbutton');
        phonetrack.NSEWClick = {s: s, d: d, ne: ne};
        phonetrack.map.on('click', NSEWClickMap);
    }

    function leaveNSEWMode() {
        $('.leaflet-container').css('cursor','grab');
        phonetrack.map.off('click', NSEWClickMap);
    }

    function NSEWClickMap(e) {
        var lat = e.latlng.lat;
        var lon = e.latlng.lng;
        while (lon < -180) {
            lon = lon + 360;
        }
        lat = lat.toFixed(6);
        lon = lon.toFixed(6);
        var s = phonetrack.NSEWClick.s;
        var d = phonetrack.NSEWClick.d;
        var ne = phonetrack.NSEWClick.ne;
        var geodiv = $('.session[token='+s+'] .devicelist li[device='+d+'] .addgeofencediv');
        if (ne) {
            geodiv.find('.fencenorth').val(lat);
            geodiv.find('.fenceeast').val(lon);
        }
        else {
            geodiv.find('.fencesouth').val(lat);
            geodiv.find('.fencewest').val(lon);
        }
        leaveNSEWMode();
    }

    function enterAddPointMode() {
        $('.leaflet-container').css('cursor','crosshair');
        phonetrack.map.on('click', addPointClickMap);
        $('#canceladdpoint').show();
        $('#explainaddpoint').show();
    }

    function leaveAddPointMode() {
        $('.leaflet-container').css('cursor','grab');
        phonetrack.map.off('click', addPointClickMap);
        $('#canceladdpoint').hide();
        $('#explainaddpoint').hide();
    }

    function addPointClickMap(e) {
        addPointDB(e.latlng.lat.toFixed(6), e.latlng.lng.toFixed(6), null, null, null, null, moment());
        leaveAddPointMode();
    }

    function deleteMultiplePoints(bounds=null) {
        var pid, pidlist, pidsToDelete, cpt, did, dname;
        var s = $('#deletePointSession option:selected').attr('token');
        var dname = $('#deletePointDevice').val();
        var did = getDeviceId(s, dname);
        // if session is watched, if device exists, for all displayed points
        if ($('.session[token=' + s + '] .watchbutton i').hasClass('fa-toggle-on')) {
            if (dname === '') {
                for (did in phonetrack.sessionPointsLayers[s]) {
                    pidlist = [];
                    phonetrack.sessionPointsLayers[s][did].eachLayer(function(l) {
                        if (bounds === null || bounds.contains(l.getLatLng())) {
                            pidlist.push(l.getLatLng().alt);
                        }
                    });
                    // split pidlist in smaller parts
                    cpt = 0;
                    while (cpt < pidlist.length) {
                        pidsToDelete = [];
                        pidsToDelete.push(pidlist[cpt]);
                        cpt++;
                        // make bunch of 500 points
                        while (cpt < pidlist.length && cpt%500 !== 0) {
                            pidsToDelete.push(pidlist[cpt]);
                            cpt++
                        }
                        deletePointsDB(s, did, pidsToDelete);
                    }
                }
            }
            else{
                if (phonetrack.sessionLineLayers[s].hasOwnProperty(did)) {
                    pidlist = [];
                    phonetrack.sessionPointsLayers[s][did].eachLayer(function(l) {
                        if (bounds === null || bounds.contains(l.getLatLng())) {
                            pidlist.push(l.getLatLng().alt);
                        }
                    });
                    // split pidlist in smaller parts
                    cpt = 0;
                    while (cpt < pidlist.length) {
                        pidsToDelete = [];
                        pidsToDelete.push(pidlist[cpt]);
                        cpt++;
                        // make bunch of 500 points
                        while (cpt < pidlist.length && cpt%500 !== 0) {
                            pidsToDelete.push(pidlist[cpt]);
                            cpt++
                        }
                        deletePointsDB(s, did, pidsToDelete);
                    }
                }
            }
        }
    }

    /*
     * get key events
     */
    function checkKey(e) {
        e = e || window.event;
        var kc = e.keyCode;
        //console.log(kc);

        if (kc === 60 || kc === 220) {
            e.preventDefault();
            $('#sidebar').toggleClass('collapsed');
        }

        if (e.key === 'Escape' && phonetrack.movepointSession !== null) {
            leaveMovePointMode();
        }
    }

    function getUrlParameter(sParam)
    {
        var sPageURL = window.location.search.substring(1);
        var sURLVariables = sPageURL.split('&');
        for (var i = 0; i < sURLVariables.length; i++) 
        {
            var sParameterName = sURLVariables[i].split('=');
            if (sParameterName[0] === sParam) 
            {
                return decodeURIComponent(sParameterName[1]);
            }
        }
    }

    //////////////// ANIMATIONS /////////////////////

    function showLoadingAnimation() {
        phonetrack.notificationDialog.addTo(phonetrack.map);
        $('#loadingpc').text('');
    }

    function hideLoadingAnimation() {
        $('#loadingpc').text('');
        phonetrack.notificationDialog.remove();
    }

    //////////////// PUBLIC DIR/FILE /////////////////////

    function pageIsPublicWebLog() {
        return phonetrack.pageIsPublicWebLog;
    }

    function pageIsPublicSessionWatch() {
        return phonetrack.pageIsPublicSessionWatch;
    }

    function pageIsPublic() {
        return (pageIsPublicWebLog() || pageIsPublicSessionWatch());
    }

    //////////////// USER TILE SERVERS /////////////////////

    function addTileServer(type) {
        var sname = $('#'+type+'servername').val();
        var surl = $('#'+type+'serverurl').val();
        var sminzoom = $('#'+type+'minzoom').val();
        var smaxzoom = $('#'+type+'maxzoom').val();
        var stransparent = $('#'+type+'transparent').is(':checked');
        var sopacity = $('#'+type+'opacity').val() || '';
        var sformat = $('#'+type+'format').val() || '';
        var sversion = $('#'+type+'version').val() || '';
        var slayers = $('#'+type+'layers').val() || '';
        if (sname === '' || surl === '') {
            OC.Notification.showTemporary(
                t('phonetrack', 'Server name or server url should not be empty')
            );
            OC.Notification.showTemporary(
                t('phonetrack', 'Impossible to add tile server')
            );
            return;
        }
        if ($('#'+type+'serverlist ul li[servername="' + sname + '"]').length > 0) {
            OC.Notification.showTemporary(
                t('phonetrack', 'A server with this name already exists')
            );
            OC.Notification.showTemporary(
                t('phonetrack', 'Impossible to add tile server')
            );
            return;
        }
        $('#'+type+'servername').val('');
        $('#'+type+'serverurl').val('');

        var req = {
            servername: sname,
            serverurl: surl,
            type: type,
            layers: slayers,
            version: sversion,
            tformat: sformat,
            opacity: sopacity,
            transparent: stransparent,
            minzoom: sminzoom,
            maxzoom: smaxzoom,
            attribution: ''
        };
        var url = OC.generateUrl('/apps/phonetrack/addTileServer');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done) {
                $('#'+type+'serverlist ul').prepend(
                    '<li style="display:none;" servername="' + escapeHTML(sname) +
                    '" title="' + escapeHTML(surl) + '">' +
                    escapeHTML(sname) + ' <button>' +
                    '<i class="fa fa-trash" aria-hidden="true" style="color:red;"></i> ' +
                    t('phonetrack', 'Delete') +
                    '</button></li>'
                );
                $('#'+type+'serverlist ul li[servername="' + sname + '"]').fadeIn('slow');

                if (type === 'tile') {
                    // add tile server in leaflet control
                    var newlayer = new L.TileLayer(surl,
                        {minZoom: sminzoom, maxZoom: smaxzoom, attribution: ''});
                    phonetrack.activeLayers.addBaseLayer(newlayer, sname);
                    phonetrack.baseLayers[sname] = newlayer;
                }
                else if (type === 'tilewms'){
                    // add tile server in leaflet control
                    var newlayer = new L.tileLayer.wms(surl,
                        {format: sformat, version: sversion, layers: slayers, minZoom: sminzoom, maxZoom: smaxzoom, attribution: ''});
                    phonetrack.activeLayers.addBaseLayer(newlayer, sname);
                    phonetrack.overlayLayers[sname] = newlayer;
                }
                if (type === 'overlay') {
                    // add tile server in leaflet control
                    var newlayer = new L.TileLayer(surl,
                        {minZoom: sminzoom, maxZoom: smaxzoom, transparent: stransparent, opcacity: sopacity, attribution: ''});
                    phonetrack.activeLayers.addOverlay(newlayer, sname);
                    phonetrack.baseLayers[sname] = newlayer;
                }
                else if (type === 'overlaywms'){
                    // add tile server in leaflet control
                    var newlayer = new L.tileLayer.wms(surl,
                        {layers: slayers, version: sversion, transparent: stransparent, opacity: sopacity, format: sformat, attribution: '', minZoom: sminzoom, maxZoom: smaxzoom});
                    phonetrack.activeLayers.addOverlay(newlayer, sname);
                    phonetrack.overlayLayers[sname] = newlayer;
                }
                OC.Notification.showTemporary(t('phonetrack', 'Tile server "{ts}" has been added', {ts: sname}));
            }
            else{
                OC.Notification.showTemporary(t('phonetrack', 'Failed to add tile server "{ts}"', {ts: sname}));
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to add tile server'));
        });
    }

    function deleteTileServer(li, type) {
        var sname = li.attr('servername');
        var req = {
            servername: sname,
            type: type
        };
        var url = OC.generateUrl('/apps/phonetrack/deleteTileServer');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done) {
                li.fadeOut('slow', function() {
                    li.remove();
                });
                if (type === 'tile') {
                    var activeLayerName = phonetrack.activeLayers.getActiveBaseLayer().name;
                    // if we delete the active layer, first select another
                    if (activeLayerName === sname) {
                        $('input.leaflet-control-layers-selector').first().click();
                    }
                    phonetrack.activeLayers.removeLayer(phonetrack.baseLayers[sname]);
                    delete phonetrack.baseLayers[sname];
                }
                else {
                    phonetrack.activeLayers.removeLayer(phonetrack.overlayLayers[sname]);
                    delete phonetrack.overlayLayers[sname];
                }
                OC.Notification.showTemporary(t('phonetrack', 'Tile server "{ts}" has been deleted', {ts: sname}));
            }
            else{
                OC.Notification.showTemporary(t('phonetrack', 'Failed to delete tile server "{ts}"', {ts: sname}));
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to delete tile server'));
        });
    }

    //////////////// SAVE/RESTORE OPTIONS /////////////////////

    function restoreOptions() {
        var mom;
        var url = OC.generateUrl('/apps/phonetrack/getOptionsValues');
        var req = {
        };
        var optionsValues = '{}';
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            optionsValues = response.values;
            optionsValues = $.parseJSON(optionsValues);
            phonetrack.optionsValues = optionsValues;
            if (optionsValues) {
                if (optionsValues.updateinterval !== undefined) {
                    $('#updateinterval').val(optionsValues.updateinterval);
                }
                if (optionsValues.linewidth !== undefined) {
                    $('#linewidth').val(optionsValues.linewidth);
                }
                if (optionsValues.colortheme !== undefined) {
                    $('#colorthemeselect').val(optionsValues.colortheme);
                }
                if (optionsValues.pointlinealpha !== undefined) {
                    $('#pointlinealpha').val(optionsValues.pointlinealpha);
                }
                if (optionsValues.autoexportpath !== undefined) {
                    $('#autoexportpath').val(optionsValues.autoexportpath);
                }
                if (optionsValues.nbpointsload !== undefined) {
                    $('#nbpointsload').val(optionsValues.nbpointsload);
                }
                if (optionsValues.cutdistance !== undefined) {
                    $('#cutdistance').val(optionsValues.cutdistance);
                }
                if (optionsValues.cuttime !== undefined) {
                    $('#cuttime').val(optionsValues.cuttime);
                }
                if (optionsValues.pointradius !== undefined) {
                    $('#pointradius').val(optionsValues.pointradius);
                }
                if (optionsValues.showtime !== undefined) {
                    $('#showtime').prop('checked', optionsValues.showtime);
                }
                if (optionsValues.autozoom !== undefined) {
                    $('#autozoom').prop('checked', optionsValues.autozoom);
                }
                if (optionsValues.viewmove !== undefined) {
                    $('#viewmove').prop('checked', optionsValues.viewmove);
                }
                if (optionsValues.dragcheck !== undefined) {
                    $('#dragcheck').prop('checked', optionsValues.dragcheck);
                }
                if (optionsValues.linearrow !== undefined) {
                    $('#linearrow').prop('checked', optionsValues.linearrow);
                }
                if (optionsValues.stats !== undefined) {
                    $('#togglestats').prop('checked', optionsValues.stats);
                }
                if (optionsValues.linegradient !== undefined) {
                    $('#linegradient').prop('checked', optionsValues.linegradient);
                }
                if (optionsValues.tooltipshowaccuracy !== undefined) {
                    $('#tooltipshowaccuracy').prop('checked', optionsValues.tooltipshowaccuracy);
                }
                if (optionsValues.tooltipshowbearing !== undefined) {
                    $('#tooltipshowbearing').prop('checked', optionsValues.tooltipshowbearing);
                }
                if (optionsValues.tooltipshowspeed !== undefined) {
                    $('#tooltipshowspeed').prop('checked', optionsValues.tooltipshowspeed);
                }
                if (optionsValues.tooltipshowsatellites !== undefined) {
                    $('#tooltipshowsatellites').prop('checked', optionsValues.tooltipshowsatellites);
                }
                if (optionsValues.tooltipshowbattery !== undefined) {
                    $('#tooltipshowbattery').prop('checked', optionsValues.tooltipshowbattery);
                }
                if (optionsValues.tooltipshowelevation !== undefined) {
                    $('#tooltipshowelevation').prop('checked', optionsValues.tooltipshowelevation);
                }
                if (optionsValues.tooltipshowuseragent !== undefined) {
                    $('#tooltipshowuseragent').prop('checked', optionsValues.tooltipshowuseragent);
                }
                if (optionsValues.acccirclecheck !== undefined) {
                    $('#acccirclecheck').prop('checked', optionsValues.acccirclecheck);
                }
                if (optionsValues.exportoneperdev !== undefined) {
                    $('#exportoneperdev').prop('checked', optionsValues.exportoneperdev);
                }
                if (optionsValues.tilelayer !== undefined) {
                    phonetrack.restoredTileLayer = optionsValues.tilelayer;
                }
                if (optionsValues.showsidebar !== undefined) {
                    if (! optionsValues.showsidebar) {
                        $('#sidebar').addClass('collapsed');
                        $('#sidebar li.active').removeClass('active');
                    }
                }
                $('#filterPointsTable input[type=number]').each(function() {
                    if (optionsValues.hasOwnProperty($(this).attr('role'))) {
                        $(this).val(optionsValues[$(this).attr('role')]);
                    }
                });
                $('#filterPointsTable input[type=date]').each(function() {
                    if (optionsValues.hasOwnProperty($(this).attr('role'))
                        && optionsValues[$(this).attr('role')] !== null
                        && optionsValues[$(this).attr('role')] !== ''
                    ) {
                        if (String(optionsValues[$(this).attr('role')]).match(/\d\d\d\d-\d\d-\d\d/g) !== null) {
                            $(this).val(optionsValues[$(this).attr('role')]);
                        }
                        else {
                            try {
                                mom = moment.unix(parseInt(optionsValues[$(this).attr('role')]));
                                $(this).val(mom.format('YYYY-MM-DD'));
                            }
                            catch(err) {
                                $(this).val('');
                            }
                        }
                    }
                    else {
                        $(this).val('');
                    }
                });
                if (optionsValues.applyfilters !== undefined) {
                    $('#applyfilters').prop('checked', optionsValues.applyfilters);
                }
                if (optionsValues.hasOwnProperty('activeSessions')) {
                    phonetrack.sessionsFromSavedOptions = optionsValues.activeSessions;
                }
            }
            // quite important ;-)
            main();
        }).fail(function() {
            OC.Notification.showTemporary(
                t('phonetrack', 'Failed to contact server to restore options values')
            );
            OC.Notification.showTemporary(
                t('phonetrack', 'Reload this page')
            );
        });
    }

    function saveOptions(refreshAfter=false) {
        var optionsValues = {};
        optionsValues.updateinterval = $('#updateinterval').val();
        optionsValues.linewidth = $('#linewidth').val();
        optionsValues.colortheme = $('#colorthemeselect').val();
        optionsValues.pointlinealpha = $('#pointlinealpha').val();
        optionsValues.nbpointsload = $('#nbpointsload').val();
        optionsValues.cutdistance = $('#cutdistance').val();
        optionsValues.cuttime = $('#cuttime').val();
        optionsValues.pointradius = $('#pointradius').val();
        optionsValues.autoexportpath = $('#autoexportpath').val();
        optionsValues.viewmove = $('#viewmove').is(':checked');
        optionsValues.autozoom = $('#autozoom').is(':checked');
        optionsValues.showtime = $('#showtime').is(':checked');
        optionsValues.dragcheck = $('#dragcheck').is(':checked');
        optionsValues.linearrow = $('#linearrow').is(':checked');
        optionsValues.stats = $('#togglestats').is(':checked');
        optionsValues.linegradient = $('#linegradient').is(':checked');
        optionsValues.tooltipshowaccuracy = $('#tooltipshowaccuracy').is(':checked');
        optionsValues.tooltipshowbearing = $('#tooltipshowbearing').is(':checked');
        optionsValues.tooltipshowspeed = $('#tooltipshowspeed').is(':checked');
        optionsValues.tooltipshowsatellites = $('#tooltipshowsatellites').is(':checked');
        optionsValues.tooltipshowbattery = $('#tooltipshowbattery').is(':checked');
        optionsValues.tooltipshowelevation = $('#tooltipshowelevation').is(':checked');
        optionsValues.tooltipshowuseragent = $('#tooltipshowuseragent').is(':checked');
        optionsValues.acccirclecheck = $('#acccirclecheck').is(':checked');
        optionsValues.exportoneperdev = $('#exportoneperdev').is(':checked');
        optionsValues.tilelayer = phonetrack.activeLayers.getActiveBaseLayer().name;
        optionsValues.showsidebar = !$('#sidebar').hasClass('collapsed');
        $('#filterPointsTable input[type=number]').each(function() {
            optionsValues[$(this).attr('role')] = $(this).val();
        });
        $('#filterPointsTable input[type=date]').each(function() {
            optionsValues[$(this).attr('role')] = moment($(this).val()).unix();
        });
        optionsValues.applyfilters = $('#applyfilters').is(':checked');

        optionsValues.activeSessions = {};
        var devs, s, d, zoom, line, point;
        $('.session').each(function() {
            s = $(this).attr('token');
            if (isSessionActive(s)) {
                optionsValues.activeSessions[s] = {};
                $(this).find('.devicelist li').each(function() {
                    d = $(this).attr('device');
                    zoom = $(this).find('.toggleAutoZoomDevice').hasClass('on');
                    line = $(this).find('.toggleLineDevice').hasClass('on');
                    point = $(this).find('.toggleDetail').hasClass('on');
                    optionsValues.activeSessions[s][d] = {
                        zoom: zoom,
                        line: line,
                        point: point
                    }
                });
            }
        });
        //alert('to save : '+JSON.stringify(optionsValues));
        phonetrack.optionsValues = optionsValues;

        var req = {
            optionsValues: JSON.stringify(optionsValues),
        };
        var url = OC.generateUrl('/apps/phonetrack/saveOptionsValues');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (refreshAfter === true) {
                if (phonetrack.currentTimer !== null) {
                    phonetrack.currentTimer.pause();
                    phonetrack.currentTimer = null;
                }
                refresh();
            }
        }).fail(function() {
            OC.Notification.showTemporary(
                t('phonetrack', 'Failed to contact server to save options values')
            );
            OC.Notification.showTemporary(
                t('phonetrack', 'Reload this page')
            );
        });
    }

    //////////////// SYMBOLS /////////////////////

    function fillWaypointStyles() {
        for (var st in symbolIcons) {
            $('select#waypointstyleselect').append('<option value="' + st + '">' + st + '</option>');
        }
        $('select#waypointstyleselect').val('Pin, Blue');
        updateWaypointStyle('Pin, Blue');
    }

    //////////////// SESSIONS ///////////////////

    function createSession() {
        var sessionName = $('#sessionnameinput').val();
        if (!sessionName) {
            OC.Notification.showTemporary(t('phonetrack', 'Session name should not be empty'));
            return;
        }
        var req = {
            name: sessionName
        };
        var url = OC.generateUrl('/apps/phonetrack/createSession');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done === 1) {
                addSession(response.token, sessionName, response.publicviewtoken, [], 1);
            }
            else if (response.done === 2) {
                OC.Notification.showTemporary(t('phonetrack', 'Session name already used'));
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to create session'));
        });
    }

    function getSessionName(token) {
        return $('div.session[token="' + token + '"] .sessionBar .sessionName').text();
    }

    function getDeviceName(sessionid, did) {
        return phonetrack.deviceNames[sessionid][parseInt(did)];
    }

    function getDeviceAlias(sessionid, did) {
        return phonetrack.deviceAliases[sessionid][parseInt(did)];
    }

    function getDeviceId(sessionid, devicename) {
        return phonetrack.deviceIds[sessionid][devicename];
    }

    function addSession(token, name, publicviewtoken, isPublic, devices=[], sharedWith=[],
                        selected=false, isFromShare=false, isSharedBy='',
                        reservedNames=[], publicFilteredShares=[], autoexport='no', autopurge='no') {
        // init names/ids dict
        phonetrack.deviceNames[token] = {};
        phonetrack.deviceAliases[token] = {};
        phonetrack.deviceIds[token] = {};
        phonetrack.devicePointIcons[token] = {};
        phonetrack.lastTime[token] = {};
        phonetrack.firstTime[token] = {};
        // if session is not shared (we have write access)
        if (!isFromShare) {
            $('#addPointSession').append('<option value="' + name + '" token="' + token + '">' + name + '</option>');
            $('#deletePointSession').append('<option value="' + name + '" token="' + token + '">' + name + '</option>');
        }
        var gpsloggerUrl = OC.generateUrl('/apps/phonetrack/log/gpslogger/' + token + '/yourname?');
        var gpsloggerParams = 'lat=%LAT&' +
            'lon=%LON&' +
            'sat=%SAT&' +
            'alt=%ALT&' +
            'acc=%ACC&' +
            'speed=%SPD&' +
            'bearing=%DIR&' +
            'timestamp=%TIMESTAMP&' +
            'bat=%BATT';
        gpsloggerUrl = window.location.origin + gpsloggerUrl + gpsloggerParams;

        var owntracksurl = OC.generateUrl('/apps/phonetrack/log/owntracks/' + token + '/yourname');
        owntracksurl = window.location.origin + owntracksurl;

        var uloggerurl = OC.generateUrl('/apps/phonetrack/log/ulogger/' + token + '/yourname');
        uloggerurl = window.location.origin + uloggerurl;

        var traccarurl = OC.generateUrl('/apps/phonetrack/log/traccar/' + token + '/yourname');
        traccarurl = window.location.origin + traccarurl;

        var opengtsurl = OC.generateUrl('/apps/phonetrack/log/opengts/' + token + '/yourname');
        opengtsurl = window.location.origin + opengtsurl;

        var osmandurl = OC.generateUrl('/apps/phonetrack/log/osmand/' + token + '/yourname?');
        osmandurl = osmandurl +
            'lat={0}&' +
            'lon={1}&' +
            'alt={4}&' +
            'acc={3}&' +
            'timestamp={2}&' +
            'speed={5}&' +
            'bearing={6}';
        osmandurl = window.location.origin + osmandurl;

        var geturl = OC.generateUrl('/apps/phonetrack/logGet/' + token + '/yourname?');
        geturl = geturl +
            'lat=LAT&' +
            'lon=LON&' +
            'alt=ALT&' +
            'acc=ACC&' +
            'bat=BAT&' +
            'sat=SAT&' +
            'speed=SPD&' +
            'bearing=DIR&' +
            'timestamp=TIME';
        geturl = window.location.origin + geturl;

        var publicTrackUrl = OC.generateUrl('/apps/phonetrack/publicWebLog/' + token + '/yourname');
        publicTrackUrl = window.location.origin + publicTrackUrl;

        var publicWatchUrl = OC.generateUrl('/apps/phonetrack/publicSessionWatch/' + publicviewtoken);
        publicWatchUrl = window.location.origin + publicWatchUrl;

        var APIUrl = OC.generateUrl('/apps/phonetrack/APIgetLastPositions/' + publicviewtoken);
        APIUrl = window.location.origin + APIUrl;

        var watchicon = 'fa-toggle-off';
        if (selected) {
            watchicon = 'fa-toggle-on';
        }
        var divtxt = '<div class="session" token="' + token + '"' +
           ' publicviewtoken="' + publicviewtoken + '"' +
           ' shared="' + (isFromShare?1:0) + '"' +
            '>';
        phonetrack.isSessionShared[token] = isFromShare;
        divtxt = divtxt + '<div class="sessionBar">';
        divtxt = divtxt + '<button class="watchbutton" title="' + t('phonetrack', 'Watch this session') + '">' +
            '<i class="fa ' + watchicon + '" aria-hidden="true"></i></button>';

        var sharedByText = '';
        if (isSharedBy !== '') {
            sharedByText = ' (' +
                t('phonetrack', 'shared by {u}', {u: isSharedBy}) +
                ')';
        }
        divtxt = divtxt + '<div class="sessionName" title="' + name + sharedByText + '">' + name + '</div><input class="renameSessionInput" type="text"/>';
        if (!pageIsPublic()) {
            divtxt = divtxt + '<button class="dropdownbutton" title="'+t('phonetrack', 'More actions')+'">' +
                '<i class="fa fa-bars" aria-hidden="true"></i></button>';
        }
        divtxt = divtxt + ' <button class="zoomsession" ' +
            'title="' + t('phonetrack', 'Zoom on this session') + '">' +
            '<i class="fa fa-search"></i></button>';
        if (!pageIsPublic() && !isFromShare) {
            divtxt = divtxt + '<button class="sharesession" title="'+t('phonetrack', 'URL to share session')+'">' +
                '<i class="fa fa-share-alt" aria-hidden="true"></i></button>';
        }
        if (!pageIsPublicSessionWatch() && !isFromShare) {
            divtxt = divtxt + '<button class="moreUrlsButton" title="' + t('phonetrack', 'URLs for logging apps') + '">' +
                '<i class="fa fa-link"></i></button>';
        }
        if (!pageIsPublic() && !isFromShare) {
            divtxt = divtxt + '<button class="reservNameButton" title="' + t('phonetrack', 'Reserve device names') + '">' +
                '<i class="fa fa-male"></i></button>';
        }
        divtxt = divtxt + '</div>';
        if (!pageIsPublic()) {
            divtxt = divtxt + '<div class="dropdown-content">';

            if (!isFromShare) {
                divtxt = divtxt + '<button class="removeSession">' +
                    '<i class="fa fa-trash" aria-hidden="true"></i> ' + t('phonetrack', 'Delete session') + '</button>';
                divtxt = divtxt + '<button class="editsessionbutton">' +
                    '<i class="fa fa-pencil-alt"></i> ' + t('phonetrack', 'Rename session') + '</button>';
            }
            divtxt = divtxt + '<div><button class="export">' +
                '<i class="fa fa-save" aria-hidden="true"></i> ' + t('phonetrack', 'Export to gpx') + '</button>';
            divtxt = divtxt + '<input role="exportname" type="text" value="' + escapeHTML(name) + '.gpx"/></div>';

            if (!isFromShare) {
                divtxt = divtxt + '<div class="autoexportdiv" title="' +
                    t('phonetrack', 'Files are created in \'{exdir}\'', {exdir: escapeHTML($('#autoexportpath').val())}) + '">' +
                    '<div><i class="fa fa-save" aria-hidden="true"></i> ' + t('phonetrack', 'Automatic export') + '</div>';
                divtxt = divtxt + '<select role="autoexport">';
                divtxt = divtxt + '<option value="no">' + t('phonetrack', 'never') + '</option>';
                divtxt = divtxt + '<option value="daily">' + t('phonetrack', 'daily') + '</option>';
                divtxt = divtxt + '<option value="weekly">' + t('phonetrack', 'weekly') + '</option>';
                divtxt = divtxt + '<option value="monthly">' + t('phonetrack', 'monthly') + '</option>';
                divtxt = divtxt + '</select>';
                divtxt = divtxt + '</div>';

                divtxt = divtxt + '<div class="autopurgediv" ' +
                    'title="' + t('phonetrack', 'Automatic purge is triggered daily and will delete points older than selected duration') + '">' +
                    '<div><i class="fa fa-trash" aria-hidden="true"></i> ' + t('phonetrack', 'Automatic purge') + '</div>';
                divtxt = divtxt + '<select role="autopurge">';
                divtxt = divtxt + '<option value="no">' + t('phonetrack', 'don\'t purge') + '</option>';
                divtxt = divtxt + '<option value="day">' + t('phonetrack', 'a day') + '</option>';
                divtxt = divtxt + '<option value="week">' + t('phonetrack', 'a week') + '</option>';
                divtxt = divtxt + '<option value="month">' + t('phonetrack', 'a month') + '</option>';
                divtxt = divtxt + '</select>';
                divtxt = divtxt + '</div>';
            }

            divtxt = divtxt + '</div>';
        }
        if (!pageIsPublic() && !isFromShare) {
            divtxt = divtxt + '<div class="namereservdiv">';
            divtxt = divtxt + '<p class="information">' + t('phonetrack', 'Name reservation is optional.') + '<br/>' +
                t('phonetrack', 'Name can be set directly in logging URL if it is not reserved.') + '<br/>' +
                t('phonetrack', 'To log with a reserved name, use its token in logging URL.') + '<br/>' +
                t('phonetrack', 'If a name is reserved, the only way to log with this name is with its token.') +
                '</p>';

            divtxt = divtxt + '<label class="addnamereservLabel">' + t('phonetrack', 'Reserve this device name') + ' :</label>';
            divtxt = divtxt + '<input class="addnamereserv" type="text" title="' +
                t('phonetrack', 'Type reserved name and press \'Enter\'') + '"></input>';
            divtxt = divtxt + '<ul class="namereservlist">';
            var i;
            for (i = 0; i < reservedNames.length; i++) {
                divtxt = divtxt + '<li name="' + escapeHTML(reservedNames[i].name) + '"><label>' +
                    reservedNames[i].name + ' : ' + reservedNames[i].token + '</label>' +
                    '<button class="deletereservedname"><i class="fa fa-trash"></i></li>';
            }
            divtxt = divtxt + '</ul>';
            divtxt = divtxt + '<hr/></div>';

            divtxt = divtxt + '<div class="sharediv">';

            divtxt = divtxt + '<div class="usersharediv">';
            divtxt = divtxt + '<p class="addusershareLabel">' + t('phonetrack', 'Share with user') + ' :</p>';
            divtxt = divtxt + '<input class="addusershare" type="text" title="' +
                t('phonetrack', 'Type user name and press \'Enter\'') + '"></input>';
            divtxt = divtxt + '<ul class="usersharelist">';
            var i;
            for (i = 0; i < sharedWith.length; i++) {
                divtxt = divtxt + '<li username="' + escapeHTML(sharedWith[i]) + '"><label>' +
                    t('phonetrack', 'Shared with {u}', {'u': sharedWith[i]}) + '</label>' +
                    '<button class="deleteusershare"><i class="fa fa-trash"></i></li>';
            }
            divtxt = divtxt + '</ul>';
            divtxt = divtxt + '</div><hr/>';

            var titlePublic = t('phonetrack', 'A private session is not visible on public browser logging page');
            var icon = 'fa-toggle-off';
            var pubtext = t('phonetrack', 'Make session public');
            if (parseInt(isPublic) === 1) {
                icon = 'fa-toggle-on';
                pubtext = t('phonetrack', 'Make session private');
            }
            divtxt = divtxt + '<button class="publicsessionbutton" title="' + titlePublic + '">';
            divtxt = divtxt + '<i class="fa ' + icon + '"></i> <b>' + pubtext + '</b></button>';
            divtxt = divtxt + '<div class="publicWatchUrlDiv">';
            divtxt = divtxt + '<p class="publicWatchUrlLabel">' + t('phonetrack', 'Public watch URL') + ' :</p>';
            divtxt = divtxt + '<input class="ro" role="publicWatchUrl" type="text" value="' + publicWatchUrl + '"></input>';
            divtxt = divtxt + '<p class="APIUrlLabel">' + t('phonetrack', 'API URL (JSON last positions)') + ' :</p>';
            divtxt = divtxt + '<input class="ro" role="APIUrl" type="text" value="' + APIUrl + '"></input>';
            divtxt = divtxt + '</div><hr/>';

            divtxt = divtxt + '<div class="publicfilteredsharediv">';
            divtxt = divtxt + '<button class="addpublicfilteredshareButton" ' +
                'title="' + t('phonetrack', 'Current active filters will be applied on shared view') + '">' +
                '<i class="fa fa-plus-circle" aria-hidden="true"></i> ' +
                t('phonetrack', 'Add public filtered share') + '</button>';
            divtxt = divtxt + '<ul class="publicfilteredsharelist">';
            divtxt = divtxt + '</ul>';
            divtxt = divtxt + '</div>';

            divtxt = divtxt + '<hr/></div>';
        }
        if (!pageIsPublicSessionWatch() && !isFromShare) {
            divtxt = divtxt + '<div class="moreUrls">';
            divtxt = divtxt + '<p class="urlhint information">' +
                t('phonetrack', 'List of server URLs to configure logging apps.') + '<br/>' +
                t('phonetrack', 'Replace \'yourname\' with the desired device name or with the name reservation token') +
                '</p>';
            divtxt = divtxt + '<p>' + t('phonetrack', 'Public browser logging URL') + ' :</p>';
            divtxt = divtxt + '<input class="ro" role="publicTrackUrl" type="text" value="' + publicTrackUrl + '"></input>';
            divtxt = divtxt + '<p><label>' + t('phonetrack', 'OsmAnd URL') + ' : </label>' +
                '<button class="urlhelpbutton" logger="osmand"><i class="fa fa-question"></i></button>' +
                '</p>';
            divtxt = divtxt + '<input class="ro" role="osmandurl" type="text" value="' + osmandurl + '"></input>';
            divtxt = divtxt + '<p>' + t('phonetrack', 'GpsLogger GET and POST URL') + ' : ' +
                '<button class="urlhelpbutton" logger="gpslogger"><i class="fa fa-question"></i></button>' +
                '</p>';
            divtxt = divtxt + '<input class="ro" role="gpsloggerurl" type="text" value="' + gpsloggerUrl + '"></input>';
            divtxt = divtxt + '<p>' + t('phonetrack', 'Owntracks (HTTP mode) URL') + ' : ' +
                '<button class="urlhelpbutton" logger="owntracks"><i class="fa fa-question"></i></button>' +
                '</p>';
            divtxt = divtxt + '<input class="ro" role="owntracksurl" type="text" value="' + owntracksurl + '"></input>';
            divtxt = divtxt + '<p>' + t('phonetrack', 'Ulogger URL') + ' : ' +
                '<button class="urlhelpbutton" logger="ulogger"><i class="fa fa-question"></i></button>' +
                '</p>';
            divtxt = divtxt + '<input class="ro" role="uloggerurl" type="text" value="' + uloggerurl + '"></input>';
            divtxt = divtxt + '<p>' + t('phonetrack', 'Traccar URL') + ' : ' +
                '<button class="urlhelpbutton" logger="traccar"><i class="fa fa-question"></i></button>' +
                '</p>';
            divtxt = divtxt + '<input class="ro" role="traccarurl" type="text" value="' + traccarurl + '"></input>';
            divtxt = divtxt + '<p>' + t('phonetrack', 'OpenGTS URL') + ' : ' +
                '</p>';
            divtxt = divtxt + '<input class="ro" role="opengtsurl" type="text" value="' + opengtsurl + '"></input>';
            divtxt = divtxt + '<p>' + t('phonetrack', 'HTTP GET URL') + ' : ' +
                '<button class="urlhelpbutton" logger="get"><i class="fa fa-question"></i></button>' +
                '</p>';
            divtxt = divtxt + '<input class="ro" role="geturl" type="text" value="' + geturl + '"></input>';
            divtxt = divtxt + '<hr/></div>';
        }
        divtxt = divtxt + '<ul class="devicelist" token="' + token + '"></ul></div>';

        $('div#sessions').append($(divtxt).fadeIn('slow')).find('input.ro[type=text]').prop('readonly', true);
        if (!selected) {
            $('.session[token="' + token + '"]').find('.devicelist').hide();
        }
        $('.session[token="' + token + '"]').find('.sharediv').hide();
        $('.session[token="' + token + '"]').find('.moreUrls').hide();
        $('.session[token="' + token + '"]').find('.namereservdiv').hide();
        $('.session[token="' + token + '"]').find('select[role=autoexport]').val(autoexport);
        $('.session[token="' + token + '"]').find('select[role=autopurge]').val(autopurge);
        if (parseInt(isPublic) === 0) {
            $('.session[token="' + token + '"]').find('.publicWatchUrlDiv').hide();
        }
            //.find('input[type=text]').prop('readonly', false);
        for (i = 0; i < publicFilteredShares.length; i++) {
            addPublicSessionShare(
                token,
                publicFilteredShares[i].token,
                publicFilteredShares[i].filters,
                publicFilteredShares[i].devicename,
                publicFilteredShares[i].lastposonly,
                publicFilteredShares[i].geofencify
            );
        }
        ///////////////////////////////////////////////////////////
        if (! phonetrack.sessionLineLayers.hasOwnProperty(token)) {
            phonetrack.sessionLineLayers[token] = {};
            phonetrack.sessionDisplayedLatlngs[token] = {};
            phonetrack.sessionLatlngs[token] = {};
            phonetrack.sessionPointsLayers[token] = {};
            phonetrack.sessionPointsLayersById[token] = {};
            phonetrack.sessionPointsEntriesById[token] = {};
        }
        if (! phonetrack.sessionMarkerLayers.hasOwnProperty(token)) {
            phonetrack.sessionMarkerLayers[token] = {};
        }
        ////////////////////////////////////////////////////////////
        // Manage devices from given list
        var ii, dev, devid, devname, devalias, devcolor, devnametoken, devgeofences, devproxims;
        for (ii=0; ii < devices.length; ii++) {
            dev = devices[ii];
            devid = dev[0];
            devname = dev[1];
            devalias = dev[2];
            devcolor = dev[3];
            devnametoken = dev[4];
            devgeofences = dev[5];
            devproxims = dev[6];

            if (phonetrack.sessionsFromSavedOptions
                && phonetrack.sessionsFromSavedOptions.hasOwnProperty(token)
                && phonetrack.sessionsFromSavedOptions[token].hasOwnProperty(devid)) {
                addDevice(
                    token, devid, name, devcolor, devname, devgeofences,
                    phonetrack.sessionsFromSavedOptions[token][devid].zoom,
                    phonetrack.sessionsFromSavedOptions[token][devid].line,
                    phonetrack.sessionsFromSavedOptions[token][devid].point,
                    devalias,
                    devproxims
                );
                // once restored, get rid of the data
                delete phonetrack.sessionsFromSavedOptions[token][devid];
            }
            else {
                addDevice(token, devid, name, devcolor, devname, devgeofences, false, false, false, devalias, devproxims);
            }
        }
    }

    function deleteSession(token) {
        var div = $('div.session[token='+token+']');

        var req = {
            token: token
        };
        var url = OC.generateUrl('/apps/phonetrack/deleteSession');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done === 1) {
                removeSession(div);
            }
            else if (response.done === 2) {
                OC.Notification.showTemporary(t('phonetrack', 'The session you want to delete does not exist'));
            }
            else {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to delete session'));
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to delete session'));
        });
    }

    function deleteDevice(token, deviceid) {
        var sessionName = getSessionName(token);
        var req = {
            token: token,
            deviceid: deviceid
        };
        var url = OC.generateUrl('/apps/phonetrack/deleteDevice');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            var devicename = getDeviceName(token, deviceid);
            if (response.done === 1) {
                removeDevice(token, deviceid);
                OC.Notification.showTemporary(t('phonetrack', 'Device \'{d}\' of session \'{s}\' has been deleted', {d: devicename, s: sessionName}));
            }
            else {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to delete device \'{d}\' of session \'{s}\'', {d: devicename, s: sessionName}));
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to delete device'));
        });
    }

    function removeDevice(token, device) {
        // remove devicelist line
        $('.devicelist li[token="' + token + '"][device="' + device + '"]').fadeOut('slow', function() {
            $(this).remove();
        });
        // remove marker, line and tooltips
        phonetrack.sessionMarkerLayers[token][device].unbindTooltip().remove();
        delete phonetrack.sessionMarkerLayers[token][device];
        phonetrack.sessionLineLayers[token][device].unbindTooltip().remove();
        delete phonetrack.sessionLineLayers[token][device];
        delete phonetrack.sessionDisplayedLatlngs[token][device];
        delete phonetrack.sessionLatlngs[token][device];
        phonetrack.sessionPointsLayers[token][device].unbindTooltip().remove();
        delete phonetrack.sessionPointsLayers[token][device];
        delete phonetrack.lastTime[token][device];
        delete phonetrack.firstTime[token][device];
    }

    function removeSession(div) {
        var d;
        var token = div.attr('token');
        // remove all devices
        for (d in phonetrack.sessionMarkerLayers[token]) {
            removeDevice(token, d);
        }
        // remove things in sidebar
        $('#addPointSession option[token=' + token + ']').remove();
        $('#deletePointSession option[token=' + token + ']').remove();
        div.fadeOut('slow', function() {
            div.remove();
        });
    }

    function renameSession(token, oldname, newname) {
        var req = {
            token: token,
            newname: newname
        };
        var url = OC.generateUrl('/apps/phonetrack/renameSession');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done === 1) {
                renameSessionSuccess(token, oldname, newname);
            }
            else {
                OC.Notification.showTemporary(t('phonetrack', 'Impossible to rename session') + ' ' + oldname);
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to rename session'));
        });
    }

    function renameSessionSuccess(token, oldname, newname) {
        $('#addPointSession option[token=' + token + ']').attr('value', newname);
        $('#addPointSession option[token=' + token + ']').text(newname);
        $('#deletePointSession option[token=' + token + ']').attr('value', newname);
        $('#deletePointSession option[token=' + token + ']').text(newname);
        var perm = $('#showtime').is(':checked');
        var d, to, p, l, id;
        $('.session[token='+token+'] .sessionBar .sessionName').text(newname);
        for (d in phonetrack.sessionMarkerLayers[token]) {
            // line tooltip
            to = phonetrack.sessionLineLayers[token][d].getTooltip()._content;
            to = to.replace(
                oldname + ' | ',
                newname + ' | '
            );
            phonetrack.sessionLineLayers[token][d].unbindTooltip();
            phonetrack.sessionLineLayers[token][d].bindTooltip(
                to,
                {
                    permanent: false,
                    sticky: true,
                    className: 'tooltip' + token + d
                }
            );
        }
    }

    function renameDevice(token, deviceid, oldname, newname) {
        var req = {
            token: token,
            deviceid: deviceid,
            newname: newname
        };
        var url = OC.generateUrl('/apps/phonetrack/renameDevice');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done === 1) {
                renameDeviceSuccess(token, deviceid, oldname, newname);
            }
            else {
                OC.Notification.showTemporary(t('phonetrack', 'Impossible to rename device') + ' ' + escapeHTML(oldname));
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to rename device'));
        });
    }

    function renameDeviceSuccess(token, d, oldname, newname) {
        var perm = $('#showtime').is(':checked');
        var to, p, l, id;
        var sessionName = getSessionName(token);
        var alias = getDeviceAlias(token, d)
        var nameLabelTxt;
        if (newalias !== '') {
            nameLabelTxt = newalias + ' (' + devname + ')';
        }
        else {
            nameLabelTxt = devname;
        }
        $('.session[token=' + token + '] .devicelist li[device="' + d + '"] .deviceLabel').text(nameLabelTxt);

        // manage names/ids
        var intDid = parseInt(d);
        phonetrack.deviceNames[token][intDid] = newname;
        delete phonetrack.deviceIds[token][oldname];
        phonetrack.deviceIds[token][newname] = intDid;

        // line tooltip
        phonetrack.sessionLineLayers[token][d].unbindTooltip();
        phonetrack.sessionLineLayers[token][d].bindTooltip(
            sessionName + ' | ' + nameLabelTxt,
            {
                permanent: false,
                sticky: true,
                className: 'tooltip' + token + d
            }
        );
        // update main marker letter
        var letter;
        if (alias !== '') {
            letter = alias[0];
        }
        else {
            letter = newname[0];
        }
        var radius = phonetrack.optionsValues.pointradius;
        var shape = 'round';
        var iconMarker = L.divIcon({
            iconAnchor: [radius, radius],
            className: shape + 'marker color' + token + d,
            html: '<b>' + letter + '</b>'
        });
        phonetrack.sessionMarkerLayers[token][d].setIcon(iconMarker);
    }

    function setDeviceAlias(token, deviceid, newalias) {
        var req = {
            token: token,
            deviceid: deviceid,
            newalias: newalias
        };
        var url = OC.generateUrl('/apps/phonetrack/setDeviceAlias');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done === 1) {
                setDeviceAliasSuccess(token, deviceid, newalias);
            }
            else {
                OC.Notification.showTemporary(t('phonetrack', 'Impossible to set device alias for {n}'), {'n': getDeviceName(token, deviceid)});
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to set device alias'));
        });
    }

    function setDeviceAliasSuccess(token, d, newalias) {
        var perm = $('#showtime').is(':checked');
        var to, p, l, id;
        var sessionName = getSessionName(token);
        var devname = getDeviceName(token, d);
        var nameLabelTxt;
        if (newalias !== '') {
            nameLabelTxt = newalias + ' (' + devname + ')';
        }
        else {
            nameLabelTxt = devname;
        }
        $('.session[token=' + token + '] .devicelist li[device="' + d + '"] .deviceLabel').text(nameLabelTxt);

        // manage names/ids
        var intDid = parseInt(d);
        phonetrack.deviceAliases[token][intDid] = newalias;

        // line tooltip
        phonetrack.sessionLineLayers[token][d].unbindTooltip();
        phonetrack.sessionLineLayers[token][d].bindTooltip(
            sessionName + ' | ' + nameLabelTxt,
            {
                permanent: false,
                sticky: true,
                className: 'tooltip' + token + d
            }
        );
        // update main marker letter
        var letter;
        if (newalias !== '') {
            letter = newalias[0];
        }
        else {
            letter = devname[0];
        }
        var radius = phonetrack.optionsValues.pointradius;
        var shape = 'round';
        var iconMarker = L.divIcon({
            iconAnchor: [radius, radius],
            className: shape + 'marker color' + token + d,
            html: '<b>' + letter + '</b>'
        });
        phonetrack.sessionMarkerLayers[token][d].setIcon(iconMarker);
    }

    function reaffectDeviceSession(token, deviceid, newSessionId) {
        var req = {
            token: token,
            deviceid: deviceid,
            newSessionId: newSessionId
        };
        var url = OC.generateUrl('/apps/phonetrack/reaffectDevice');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done === 1) {
                reaffectDeviceSessionSuccess(token, deviceid, newSessionId);
            }
            else if (response.done === 3) {
                OC.Notification.showTemporary(t('phonetrack', 'Device already exists in target session'));
            }
            else {
                OC.Notification.showTemporary(t('phonetrack', 'Impossible to move device to another session'));
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to move device'));
        });
    }

    function reaffectDeviceSessionSuccess(token, d, newSessionId) {
        removeDevice(token, d);
        refresh();
    }

    function getSessions() {
        var selected;
        var req = {
        };
        var url = OC.generateUrl('/apps/phonetrack/getSessions');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            var s;
            if (response.sessions.length > 0) {
                for (s in response.sessions) {
                    selected = false;
                    if (phonetrack.sessionsFromSavedOptions
                        && phonetrack.sessionsFromSavedOptions.hasOwnProperty(response.sessions[s][1])
                    ) {
                        selected = true;
                    }
                    // session is shared by someone else
                    if (response.sessions[s].length < 5) {
                        addSession(
                            response.sessions[s][1],
                            response.sessions[s][0],
                            '',
                            0,
                            response.sessions[s][3],
                            [],
                            selected,
                            true,
                            response.sessions[s][2],
                            []
                        );
                    }
                    // session is mine !
                    else {
                        addSession(
                            response.sessions[s][1],
                            response.sessions[s][0],
                            response.sessions[s][2],
                            response.sessions[s][4],
                            response.sessions[s][3],
                            response.sessions[s][5],
                            selected,
                            false,
                            '',
                            response.sessions[s][6],
                            response.sessions[s][7],
                            response.sessions[s][8],
                            response.sessions[s][9]
                        );
                    }
                }
            }
            // in case some sessions are selected
            // refresh but don't loop
            refresh(false);
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to get sessions'));
        });
    }

    function refresh(loop=true) {
        var url;
        var sessionsToWatch = [];
        // get new positions for all watched sessions
        $('.watchbutton i.fa-toggle-on').each(function() {
            var token = $(this).parent().parent().parent().attr('token');
            var lastTimes = phonetrack.lastTime[token];
            if (Object.keys(lastTimes).length === 0) {
                lastTimes = '';
            }
            var firstTimes = phonetrack.firstTime[token];
            if (Object.keys(firstTimes).length === 0) {
                firstTimes = '';
            }
            sessionsToWatch.push([token, lastTimes, firstTimes]);
        });

        if (phonetrack.currentRefreshAjax !== null) {
            phonetrack.currentRefreshAjax.abort();
        }

        if (sessionsToWatch.length > 0) {
            showLoadingAnimation();
            var req = {
                sessions: sessionsToWatch
            };
            if (pageIsPublicSessionWatch()) {
                url = OC.generateUrl('/apps/phonetrack/publicViewTrack');
            }
            else if (pageIsPublicWebLog()) {
                url = OC.generateUrl('/apps/phonetrack/publicWebLogTrack');
            }
            else {
                url = OC.generateUrl('/apps/phonetrack/track');
            }
            phonetrack.currentRefreshAjax = $.ajax({
                type: 'POST',
                url: url,
                data: req,
                async: true,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    xhr.addEventListener('progress', function(evt) {
                        if (evt.lengthComputable) {
                            var percentComplete = evt.loaded / evt.total * 100;
                            $('#loadingpc').text(parseInt(percentComplete) + '%');
                        }
                    }, false);

                    return xhr;
                }
            }).done(function (response) {
                displayNewPoints(response.sessions, response.colors, response.names, response.geofences, response.aliases);
            }).always(function() {
                hideLoadingAnimation();
                phonetrack.currentRefreshAjax = null;
            }).fail(function() {
                // TODO check how to make it work when called from an ajax "done"
                //OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to refresh sessions'));
            });
        }
        // we always update the view
        showHideSelectedSessions();

        var uiVal = parseInt($('#updateinterval').val());
        if (uiVal === 0 || isNaN(uiVal)) {
            if (phonetrack.currentTimer !== null) {
                phonetrack.currentTimer.pause();
                phonetrack.currentTimer = null;
            }
            if ($('#countdown').hasClass('is-countdown')) {
                $('#countdown').countdown('destroy');
            }
        }
        if (loop && uiVal !== 0 && !isNaN(uiVal)) {
            // launch refresh again
            var updateinterval = 5000;
            if (uiVal !== '' && !isNaN(uiVal) && parseInt(uiVal) > 1) {
                var updateinterval = parseInt(uiVal) * 1000;
            }
            // display countdown
            if ($('#countdown').hasClass('is-countdown')) {
                $('#countdown').countdown('destroy');
            }
            var t = new Date();
            t.setSeconds(t.getSeconds() + updateinterval/1000);
            $('#countdown').countdown({until: t, format: 'HMS', compact: true});
            // launch timer
            phonetrack.currentTimer = new Timer(function() {
                refresh();
            }, updateinterval);
        }
    }

    // transform a list of latlngs into multiple segments based on time/distance thresholds
    function segmentLines(ll, s, d) {
        var cuttime = parseInt($('#cuttime').val()) || null;
        var cutdistance = parseInt($('#cutdistance').val()) || null;
        if (ll.length === 0) {
            return [];
        }
        else if (ll.length === 1) {
            return [ll];
        }
        else if (cuttime === null && cutdistance === null) {
            return [ll];
        }
        else {
            var i = 1;
            var segments = [];
            var currentSegment = [ll[0]];
            var lastEntry    = phonetrack.sessionPointsEntriesById[s][d][ll[0][2]];
            var currentEntry = phonetrack.sessionPointsEntriesById[s][d][ll[1][2]];
            while (i < ll.length) {
                // fill current segment while possible
                while (   i < ll.length
                       && (cutdistance === null || phonetrack.map.distance(ll[i-1], ll[i]) < cutdistance)
                       && (cuttime === null || ((currentEntry.timestamp - lastEntry.timestamp) < cuttime))
                ) {
                    currentSegment.push(ll[i]);
                    i++;
                    lastEntry = currentEntry;
                    if (i < ll.length) {
                        currentEntry = phonetrack.sessionPointsEntriesById[s][d][ll[i][2]];
                    }
                }
                // end of segment, add it to segment list
                segments.push(currentSegment);
                // and prepare next segment if there are more points
                if (i < ll.length) {
                    currentSegment = [ll[i]];
                    lastEntry = phonetrack.sessionPointsEntriesById[s][d][ll[i][2]];
                    i++;
                    // there are more points
                    if (i < ll.length) {
                        currentEntry = phonetrack.sessionPointsEntriesById[s][d][ll[i][2]];
                    }
                    // there is no more point after this one
                    else {
                        segments.push(currentSegment);
                    }
                }
            }
            var cl = 0;
            for (i=0; i<segments.length; i++) {
                cl = cl + segments[i].length;
            }
            console.assert(ll.length === cl, 'Warning : segmentation went wrong')
            return segments;
        }
    }

    function filterEntry(entry) {
        var filtersEnabled = phonetrack.filtersEnabled;

        if (filtersEnabled) {
            var satellitesmin = phonetrack.filterValues['satellitesmin'];
            var satellitesmax = phonetrack.filterValues['satellitesmax'];
            var batterymin    = phonetrack.filterValues['batterymin'];
            var batterymax    = phonetrack.filterValues['batterymax'];
            var elevationmin  = phonetrack.filterValues['elevationmin'];
            var elevationmax  = phonetrack.filterValues['elevationmax'];
            var accuracymin   = phonetrack.filterValues['accuracymin'];
            var accuracymax   = phonetrack.filterValues['accuracymax'];
            var bearingmin   = phonetrack.filterValues['bearingmin'];
            var bearingmax   = phonetrack.filterValues['bearingmax'];
            var speedmin   = phonetrack.filterValues['speedmin'] / 3.6;
            var speedmax   = phonetrack.filterValues['speedmax'] / 3.6;

            var timestampMin = phonetrack.filterValues['tsmin'];
            var timestampMax = phonetrack.filterValues['tsmax'];
        }
        return (
            !filtersEnabled
            || (
                   (!timestampMin || parseInt(entry.timestamp) >= timestampMin)
                && (!timestampMax || parseInt(entry.timestamp) <= timestampMax)
                && (!elevationmax || entry.altitude >= elevationmax)
                && (!elevationmin || entry.altitude <= elevationmin)
                && (!batterymin || entry.batterylevel >= batterymin)
                && (!batterymax || entry.batterylevel <= batterymax)
                && (!satellitesmin || entry.satellites >= satellitesmin)
                && (!satellitesmax || entry.satellites <= satellitesmax)
                && (!accuracymin || entry.accuracy >= accuracymin)
                && (!accuracymax || entry.accuracy <= accuracymax)
                && (!bearingmin || entry.bearing >= bearingmin)
                && (!bearingmax || entry.bearing <= bearingmax)
                && (!speedmin || entry.speed >= speedmin)
                && (!speedmax || entry.speed <= speedmax)
            )
        );
    }

    function filterList(list, token, deviceid) {
        var filtersEnabled = phonetrack.filtersEnabled;
        var resList, resDateList;

        if (filtersEnabled) {
            var satellitesmin = phonetrack.filterValues['satellitesmin'];
            var satellitesmax = phonetrack.filterValues['satellitesmax'];
            var batterymin    = phonetrack.filterValues['batterymin'];
            var batterymax    = phonetrack.filterValues['batterymax'];
            var elevationmin  = phonetrack.filterValues['elevationmin'];
            var elevationmax  = phonetrack.filterValues['elevationmax'];
            var accuracymin   = phonetrack.filterValues['accuracymin'];
            var accuracymax   = phonetrack.filterValues['accuracymax'];
            var bearingmin   = phonetrack.filterValues['bearingmin'];
            var bearingmax   = phonetrack.filterValues['bearingmax'];
            var speedmin   = phonetrack.filterValues['speedmin'] / 3.6;
            var speedmax   = phonetrack.filterValues['speedmax'] / 3.6;

            var timestampMin = phonetrack.filterValues['tsmin'];
            var timestampMax = phonetrack.filterValues['tsmax'];

            resDateList = [];
            resList = [];
            var i = 0;
            ////// DATES
            // we avoid everything under the min
            if (timestampMin) {
                while (i < list.length
                       && (parseInt(phonetrack.sessionPointsEntriesById[token][deviceid][list[i][2]].timestamp) <= timestampMin)
                ) {
                    i++;
                }
            }
            // then we copy everything under the max
            if (timestampMax) {
                while (i < list.length
                       && (parseInt(phonetrack.sessionPointsEntriesById[token][deviceid][list[i][2]].timestamp) <= timestampMax)
                ) {
                    resDateList.push(list[i]);
                    i++;
                }
            }
            else {
                while (i < list.length) {
                    resDateList.push(list[i]);
                    i++;
                }
            }
            // filter again with int values
            i = 0;
            var entry;
            while (i < resDateList.length) {
                entry = phonetrack.sessionPointsEntriesById[token][deviceid][resDateList[i][2]];
                if (   (!elevationmax || entry.altitude <= elevationmax)
                    && (!elevationmin || entry.altitude >= elevationmin)
                    && (!batterymin || entry.batterylevel >= batterymin)
                    && (!batterymax || entry.batterylevel <= batterymax)
                    && (!satellitesmin || entry.satellites >= satellitesmin)
                    && (!satellitesmax || entry.satellites <= satellitesmax)
                    && (!accuracymin || entry.accuracy >= accuracymin)
                    && (!accuracymax || entry.accuracy <= accuracymax)
                    && (!bearingmin || entry.bearing >= bearingmin)
                    && (!bearingmax || entry.bearing <= bearingmax)
                    && (!speedmin || entry.speed >= speedmin)
                    && (!speedmax || entry.speed <= speedmax)
                ){
                    resList.push(resDateList[i]);
                }
                i++;
            }
        }
        else {
            resList = list;
        }
        return resList;
    }

    function storeFilters() {
        // simple fields
        $('#filterPointsTable input[type=number]').each(function() {
            phonetrack.filterValues[$(this).attr('role')] = parseInt($(this).val());
        });

        // date fields : we just want tsmin and tsmax
        var timestampMin = null;
        var timestampMax = null;
        var tab = $('#filterPointsTable');
        var dateminstr = tab.find('input[role=datemin]').val();
        if (dateminstr) {
            var hourminstr = parseInt(tab.find('input[role=hourmin]').val()) || 0;
            var minminstr = parseInt(tab.find('input[role=minutemin]').val()) || 0;
            var secminstr = parseInt(tab.find('input[role=secondmin]').val()) || 0;
            var completeDateMinStr = dateminstr + ' ' + pad(hourminstr) + ':' + pad(minminstr) + ':' + pad(secminstr);
            var momMin = moment(completeDateMinStr);
            timestampMin = momMin.unix();
        }
        // if no date is set but hour:min:sec is set, make it today
        else {
            var hourminstr = parseInt(tab.find('input[role=hourmin]').val());
            var minminstr = parseInt(tab.find('input[role=minutemin]').val());
            var secminstr = parseInt(tab.find('input[role=secondmin]').val());
            if (!isNaN(hourminstr) && !isNaN(minminstr) && !isNaN(secminstr)) {
                var momMin = moment();
                momMin.hour(hourminstr);
                momMin.minute(minminstr);
                momMin.second(secminstr);
                timestampMin = momMin.unix();
            }
        }

        var datemaxstr = tab.find('input[role=datemax]').val();
        if (datemaxstr) {
            var hourmaxstr = parseInt(tab.find('input[role=hourmax]').val()) || 23;
            var minmaxstr = parseInt(tab.find('input[role=minutemax]').val()) || 59;
            var secmaxstr = parseInt(tab.find('input[role=secondmax]').val()) || 59;
            var completeDateMaxStr = datemaxstr + ' ' + pad(hourmaxstr) + ':' + pad(minmaxstr) + ':' + pad(secmaxstr);
            var momMax = moment(completeDateMaxStr);
            timestampMax = momMax.unix();
        }
        // if no date is set but hour:min:sec is set, make it today
        else {
            var hourmaxstr = parseInt(tab.find('input[role=hourmax]').val());
            var minmaxstr = parseInt(tab.find('input[role=minutemax]').val());
            var secmaxstr = parseInt(tab.find('input[role=secondmax]').val());
            if (!isNaN(hourmaxstr) && !isNaN(minmaxstr) && !isNaN(secmaxstr)) {
                var momMax = moment();
                momMax.hour(hourmaxstr);
                momMax.minute(minmaxstr);
                momMax.second(secmaxstr);
                timestampMax = momMax.unix();
            }
        }

        var lastdays = parseInt(tab.find('input[role=lastdays]').val());
        var lasthours = parseInt(tab.find('input[role=lasthours]').val());
        var lastmins = parseInt(tab.find('input[role=lastmins]').val());
        var momlast = moment();
        if (lastdays) {
            momlast.subtract(lastdays, 'days');
        }
        if (lasthours) {
            momlast.subtract(lasthours, 'hours');
        }
        if (lastmins) {
            momlast.subtract(lastmins, 'minutes');
        }
        if (lastdays || lasthours || lastmins) {
            var timestampLast = momlast.unix();
            // if there is no time min or if timelast is more recent than timemin
            if (!timestampMin || timestampLast > timestampMin) {
                timestampMin = timestampLast;
            }
        }
        phonetrack.filterValues['tsmin'] = timestampMin;
        phonetrack.filterValues['tsmax'] = timestampMax;
    }

    function changeApplyFilter() {
        var linewidth = parseInt($('#linewidth').val()) || 5;
        var linearrow = $('#linearrow').is(':checked');
        var linegradient = $('#linegradient').is(':checked');
        var filtersEnabled = $('#applyfilters').is(':checked');
        var coordsTmp, j;
        phonetrack.filtersEnabled = filtersEnabled;
        if (filtersEnabled) {
            storeFilters();
            $('#filterPointsTable').addClass('activatedFilters');
        }
        else {
            $('#filterPointsTable').removeClass('activatedFilters');
        }
        //$('#filterPointsTable input[type=number]').prop('disabled', filtersEnabled);
        //$('#filterPointsTable input[type=date]').prop('disabled', filtersEnabled);
        var s, d, id, i, displayedLatlngs, cutLines, line;
        var dragenabled = $('#dragcheck').is(':checked');

        if (filtersEnabled) {
            $('#sidebarFen').show();
            $('#sidebarFdis').hide();
        }
        else {
            $('#sidebarFen').hide();
            $('#sidebarFdis').show();
        }

        // simpler case : no filter
        if (!filtersEnabled) {
            for (s in phonetrack.sessionLineLayers) {
                for (d in phonetrack.sessionLineLayers[s]) {
                    // put all coordinates in lines
                    displayedLatlngs = phonetrack.sessionLatlngs[s][d];
                    cutLines = segmentLines(displayedLatlngs, s, d);
                    phonetrack.sessionLineLayers[s][d].clearLayers();
                    delete phonetrack.sessionDisplayedLatlngs[s][d];
                    phonetrack.sessionDisplayedLatlngs[s][d] = cutLines;

                    drawLine(s, d, cutLines, linegradient, linewidth, linearrow);

                    // add line points from sessionPointsLayersById in sessionPointsLayers
                    for (id in phonetrack.sessionPointsLayersById[s][d]) {
                        if (!phonetrack.sessionPointsLayers[s][d].hasLayer(phonetrack.sessionPointsLayersById[s][d][id])) {
                            phonetrack.sessionPointsLayers[s][d].addLayer(phonetrack.sessionPointsLayersById[s][d][id]);
                            if (!pageIsPublic() && !isSessionShared(s) && $('#dragcheck').is(':checked')
                                && phonetrack.map.hasLayer(phonetrack.sessionPointsLayers[s][d])
                            ) {
                                phonetrack.sessionPointsLayersById[s][d][id].dragging.enable();
                            }
                        }
                    }
                }
            }
            $('#statlabel').text(t('phonetrack', 'Stats of all points'));
        }
        // there is at least a filter
        else {
            for (s in phonetrack.sessionLineLayers) {
                for (d in phonetrack.sessionLineLayers[s]) {
                    // put filtered coordinates in lines
                    displayedLatlngs = filterList(phonetrack.sessionLatlngs[s][d], s, d);
                    cutLines = segmentLines(displayedLatlngs, s, d);
                    phonetrack.sessionLineLayers[s][d].clearLayers();
                    delete phonetrack.sessionDisplayedLatlngs[s][d];
                    phonetrack.sessionDisplayedLatlngs[s][d] = cutLines;

                    drawLine(s, d, cutLines, linegradient, linewidth, linearrow);

                    // filter sessionPointsLayers
                    phonetrack.sessionPointsLayers[s][d].clearLayers();
                    for (i = 0; i < displayedLatlngs.length; i++) {
                        id = displayedLatlngs[i][2];
                        phonetrack.sessionPointsLayers[s][d].addLayer(phonetrack.sessionPointsLayersById[s][d][id]);
                    }
                    // if device is displayed and dragging is enabled : make it happen
                    if (dragenabled && $('.session[token='+s+'] .devicelist li[device="'+d+'"] .toggleDetail').hasClass('on')) {
                        for (i = 0; i < displayedLatlngs.length; i++) {
                            id = displayedLatlngs[i][2];
                            phonetrack.sessionPointsLayersById[s][d][id].dragging.enable();
                        }
                    }
                }
            }
            if (filtersEnabled) {
                $('#statlabel').text(t('phonetrack', 'Stats of filtered points'));
            }
            else {
                $('#statlabel').text(t('phonetrack', 'Stats of all points'));
            }
        }

        // anyway, filter or not, we adapt the markers
        for (s in phonetrack.sessionLineLayers) {
            var sessionname = getSessionName(s);
            for (d in phonetrack.sessionLineLayers[s]) {
                updateMarker(s, d, sessionname);
            }
        }
        if ($('#togglestats').is(':checked')) {
            updateStatTable();
        }
        changeTooltipStyle();
    }

    function updateMarker(s, d, sessionname) {
        var perm = phonetrack.optionsValues.showtime;
        var mla, mln, mid, mentry, displayedLatlngs, oldlatlng;
        // TODO check if there is another way to get list of displayed latlngs
        var pointLayerList = phonetrack.sessionPointsLayers[s][d].getLayers();
        var lastll = null;
        var maxTime = -1;
        var ll;
        for (var i=0; i < pointLayerList.length; i++) {
            ll = pointLayerList[i].getLatLng();
            if (phonetrack.sessionPointsEntriesById[s][d][ll.alt].timestamp > maxTime) {
                maxTime = phonetrack.sessionPointsEntriesById[s][d][ll.alt].timestamp;
                lastll = ll;
            }
        }
        // if session is not watched or if there is no points to see
        if (!$('div.session[token='+s+'] .watchbutton i').hasClass('fa-toggle-on') || pointLayerList.length === 0) {
            if (phonetrack.map.hasLayer(phonetrack.sessionMarkerLayers[s][d])) {
                phonetrack.sessionMarkerLayers[s][d].remove();
            }
        }
        else {
            mla = lastll.lat;
            mln = lastll.lng;
            mid = lastll.alt;
            mentry = phonetrack.sessionPointsEntriesById[s][d][mid];
            oldlatlng = phonetrack.sessionMarkerLayers[s][d].getLatLng();
            // move and update tooltip/popup only if needed (marker has changed or coords are different)
            if (oldlatlng === null
                || parseInt(oldlatlng.alt) !== parseInt(mid)
                || mla !== oldlatlng.lat
                || mln !== oldlatlng.lng
            ) {
                // move
                phonetrack.sessionMarkerLayers[s][d].setLatLng([mla, mln, mid]);
            }

            if (phonetrack.sessionMarkerLayers[s][d].pid === null
                || parseInt(oldlatlng.alt) !== parseInt(mid)
            ) {
                phonetrack.sessionMarkerLayers[s][d].pid = mid;
            }

            // if marker was not already displayed
            if (!phonetrack.map.hasLayer(phonetrack.sessionMarkerLayers[s][d])) {
                phonetrack.map.addLayer(phonetrack.sessionMarkerLayers[s][d]);
                if (!pageIsPublic()
                    && !isSessionShared(s)
                    && $('.session[token='+s+'] .devicelist li[device="'+d+'"] .toggleDetail').hasClass('on')
                ) {
                    phonetrack.sessionMarkerLayers[s][d].dragging.enable();
                }
            }
        }
    }

    function displayNewPoints(sessions, colors, names, geofences={}, aliases={}) {
        var s, i, d, entry, device, timestamp, mom, icon,
            entryArray, dEntries, colorn, rgbc, devcol, devgeofences,
            textcolor, sessionname;
        var perm = $('#showtime').is(':checked');
        for (s in sessions) {
            sessionname = getSessionName(s);
            // for all devices
            for (d in sessions[s]) {
                // add line and marker if necessary
                if (! phonetrack.sessionLineLayers[s].hasOwnProperty(d)) {
                    devcol = '';
                    if (colors.hasOwnProperty(s) && colors[s].hasOwnProperty(d)) {
                        devcol = colors[s][d];
                    }
                    if (geofences.hasOwnProperty(s) && geofences[s].hasOwnProperty(d)) {
                        devgeofences = geofences[s][d];
                    }
                    if (phonetrack.sessionsFromSavedOptions
                        && phonetrack.sessionsFromSavedOptions.hasOwnProperty(s)
                        && phonetrack.sessionsFromSavedOptions[s].hasOwnProperty(d)) {
                        addDevice(
                            s, d, sessionname, devcol, names[s][d], devgeofences,
                            phonetrack.sessionsFromSavedOptions[s][d].zoom,
                            phonetrack.sessionsFromSavedOptions[s][d].line,
                            phonetrack.sessionsFromSavedOptions[s][d].point,
                            aliases[s][d]
                        );
                        // once restored, get rid of the data
                        delete phonetrack.sessionsFromSavedOptions[s][d];
                    }
                    else {
                        addDevice(s, d, sessionname, devcol, names[s][d], devgeofences, false, false, false, aliases[s][d]);
                    }
                }
                // for all new entries of this session
                dEntries = []
                for (i in sessions[s][d]) {
                    entryArray = sessions[s][d][i];
                    entry = {
                        id: entryArray[0],
                        deviceid: d,
                        lat: entryArray[1],
                        lon: entryArray[2],
                        timestamp: entryArray[3],
                        accuracy: entryArray[4],
                        satellites: entryArray[5],
                        altitude: entryArray[6],
                        batterylevel: entryArray[7],
                        useragent: entryArray[8],
                        speed: entryArray[9],
                        bearing: entryArray[10]
                    };
                    dEntries.push(entry);
                }
                appendEntriesToDevice(s, d, dEntries, sessionname);
            }
        }
        if ($('#togglestats').is(':checked')) {
            updateStatTable();
        }
        // in case user click is between ajax request and response
        showHideSelectedSessions();

        if (phonetrack.sessionsFromSavedOptions) {
            zoomOnDisplayedMarkers();
            delete phonetrack.sessionsFromSavedOptions;
        }
    }

    function changeDeviceStyle(s, d, colorcode) {
        var linegradient = $('#linegradient').is(':checked');
        if (linegradient) {
            phonetrack.sessionLineLayers[s][d].eachLayer(function (l) {
                l.options.outlineColor = colorcode;
                l.redraw();
            });
        }
        var rgbc = hexToRgb(colorcode);
        var textcolor = 'black';
        if (rgbc.r + rgbc.g + rgbc.b < 3 * 80) {
            textcolor = 'white';
        }
        var opacity = $('#pointlinealpha').val();
        var shape = 'round';
        var background = '';
        var border = 'border-color: rgba(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ', ' + opacity + ');';
        if (shape !== 'triangle') {
            background = 'background: rgba(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ', ' + opacity + ');';
            border = 'border-color: rgba(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ', 0);';
        }
        $('style[tokendevice="' + s + d + '"]').html(
            '.color' + s + d + ' { ' +
            background +
            border +
            'color: ' + textcolor + '; font-weight: bold;' +
            ' }' +
            '.poly' + s + d + ' {' +
            'stroke: ' + colorcode + ';' +
            'opacity: ' + opacity + ';' +
            '}' +
            '.tooltip' + s + d + ' {' +
            'background: rgba(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ', 0.5);' +
            'color: ' + textcolor + '; font-weight: bold; }' +
            '.opaquetooltip' + s + d + ' {' +
            'background: rgb(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ');' +
            'color: ' + textcolor + '; font-weight: bold;' +
            '}'
        );
        // we apply change in DB
        if (!pageIsPublic()) {
            var req = {
                session: s,
                device: d,
                color: colorcode
            };
            var url = OC.generateUrl('/apps/phonetrack/setDeviceColor');
            $.ajax({
                type: 'POST',
                url: url,
                data: req,
                async: true
            }).done(function (response) {
                if (response.done === 1) {
                    OC.Notification.showTemporary(t('phonetrack', 'Device\'s color successfully changed'));
                }
                else {
                    OC.Notification.showTemporary(t('phonetrack', 'Failed to save device\'s color'));
                }
            }).always(function() {
            }).fail(function() {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to change device\'s color'));
            });
        }
    }

    function showColorPicker(s, d) {
        $('#tracknamecolor').attr('token', s);
        $('#tracknamecolor').attr('deviceid', d);
        var currentColor = phonetrack.sessionColors[s + d];
        $('#colorinput').val(currentColor);
        $('#colorinput').click();
    }

    function okColor() {
        var color = $('#colorinput').val();
        var s = $('#tracknamecolor').attr('token');
        var d = $('#tracknamecolor').attr('deviceid');
        phonetrack.sessionColors[s + d] = color;
        changeDeviceStyle(s, d, color);
    }

    function addDevice(s, d, sessionname, color='', name, geofences=[], zoom=false, line=false, point=false, alias='', proxims=[]) {
        var colorn, textcolor, rgbc, linetooltip;
        if (color === '' || color === null) {
            var theme = $('#colorthemeselect').val();
            var colorCodeArray;
            if (theme === 'dark') {
                colorCodeArray = colorCodeDark;
            }
            else if (theme === 'pastel') {
                colorCodeArray = colorCodePastel;
            }
            else {
                colorCodeArray = colorCodeBright;
            }
            colorn = ++lastColorUsed % colorCodeArray.length;
            phonetrack.sessionColors[s + d] = colorCodeArray[colorn];
            rgbc = hexToRgb(colorCodeArray[colorn]);
        }
        else {
            phonetrack.sessionColors[s + d] = color;
            rgbc = hexToRgb(color);
        }
        textcolor = 'black';
        if (rgbc.r + rgbc.g + rgbc.b < 3 * 80) {
            textcolor = 'white';
        } 
        var opacity = $('#pointlinealpha').val();
        var shape = 'round';
        var background = '';
        var border = 'border-color: rgba(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ', ' + opacity + ');';
        if (shape !== 'triangle') {
            background = 'background: rgba(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ', ' + opacity + ');';
            border = 'border-color: rgba(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ', 0);';
        }
        $('<style tokendevice="' + s + d + '">.color' + s + d + ' { ' +
            background +
            border +
                'color: ' + textcolor + '; font-weight: bold;' +
                ' }' +
                '.poly' + s + d + ' {' +
                'stroke: ' + phonetrack.sessionColors[s + d] + ';' +
                'opacity: ' + opacity + ';' +
                '}' +
                '.tooltip' + s + d + ' {' +
                'background: rgba(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ', 0.5);' +
                'color: ' + textcolor + '; font-weight: bold; }' +
                '.opaquetooltip' + s + d + ' {' +
                'background: rgb(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ');' +
                'color: ' + textcolor + '; font-weight: bold;' +
                '}</style>').appendTo('body');
        var deleteLink = '';
        var renameLink = '';
        var aliasLink = '';
        var geofencesLink = '';
        var geofencesDiv = '';
        var proximLink = '';
        var proximDiv = '';
        var renameInput = '';
        var aliasInput = '';
        var reaffectLink = '';
        var geoLink = '';
        var routingGraphLink = '';
        var routingOsrmLink = '';
        var routingOrsLink = '';
        var reaffectSelect = '';
        var dropdowndevicebutton = '';
        var dropdowndevicecontent = '';
        geoLink = ' <button class="geoLinkDevice" token="' + s + '" device="' + d + '">' +
            '<i class="fa fa-map-marked-alt" aria-hidden="true"></i> ' + t('phonetrack', 'Geo link to open position in other app/software') + '</button>';
        routingGraphLink = ' <button class="routingGraphDevice" token="' + s + '" device="' + d + '">' +
            '<i class="fa fa-route" aria-hidden="true"></i> ' + t('phonetrack', 'Get driving direction to this device with {s}', {'s': 'Graphhopper'}) + '</button>';
        routingOsrmLink = ' <button class="routingOsrmDevice" token="' + s + '" device="' + d + '">' +
            '<i class="fa fa-route" aria-hidden="true"></i> ' + t('phonetrack', 'Get driving direction to this device with {s}', {'s': 'Osrm'}) + '</button>';
        routingOrsLink = ' <button class="routingOrsDevice" token="' + s + '" device="' + d + '">' +
            '<i class="fa fa-route" aria-hidden="true"></i> ' + t('phonetrack', 'Get driving direction to this device with {s}', {'s': 'OpenRouteService'}) + '</button>';
        dropdowndevicebutton = '<button class="dropdowndevicebutton" title="'+t('phonetrack', 'More actions')+'">' +
            '<i class="fa fa-bars" aria-hidden="true"></i></button>';
        if (!pageIsPublic() && !isSessionShared(s)) {
            deleteLink = ' <button class="deleteDevice" token="' + s + '" device="' + d + '">' +
                '<i class="fa fa-trash" aria-hidden="true"></i> ' + t('phonetrack', 'Delete this device') + '</button>';
            renameLink = ' <button class="renameDevice" token="' + s + '" device="' + d + '">' +
                '<i class="fa fa-pencil-alt" aria-hidden="true"></i> ' + t('phonetrack', 'Rename this device') + '</button>';
            renameInput = '<input type="text" class="renameDeviceInput" value="' + escapeHTML(name) + '"/> ';
            aliasLink = ' <button class="aliasDevice" token="' + s + '" device="' + d + '">' +
                '<i class="fa fa-pencil-alt" aria-hidden="true"></i> ' + t('phonetrack', 'Set device alias') + '</button>';
            aliasInput = '<input type="text" class="aliasDeviceInput" value="' + escapeHTML(alias || '') + '"/> ';
            reaffectLink = ' <button class="reaffectDevice" token="' + s + '" device="' + d + '">' +
                '<i class="fa fa-exchange-alt" aria-hidden="true"></i> ' + t('phonetrack', 'Move to another session') + '</button>';
            reaffectSelect = '<div class="reaffectDeviceDiv"><select class="reaffectDeviceSelect"></select>' +
                '<button class="reaffectDeviceOk"><i class="fa fa-check" aria-hidden="true"></i> ' +
                t('phonetrack', 'Ok') + '</button>' +
                '</div>';
        }
        dropdowndevicecontent = '<div class="dropdown-content">' +
            deleteLink +
            renameLink +
            aliasLink +
            reaffectLink +
            geoLink +
            routingGraphLink +
            routingOsrmLink +
            routingOrsLink +
            '</div>';
        if (!pageIsPublic() && !isSessionShared(s)) {
            geofencesLink = ' <button class="toggleGeofences" ' +
                'title="' + t('phonetrack', 'Device geofencing zones') + '">' +
                '</button>';
            geofencesDiv = '<div class="geofencesDiv">' +
                '<div class="addgeofencediv">' +
                '<p>' + t('phonetrack', 'Zoom on geofencing area, then set values, then validate.') + '</p>' +
                '<label for="sendemail'+s+d+'"> ' + t('phonetrack', 'Email notification') + '</label> ' +
                '<input type="checkbox" class="sendemail" id="sendemail'+s+d+'" checked/><br/>' +
                '<label for="urlenter'+s+d+'"><b>' + t('phonetrack', 'URL to request when entering') + '</b></label><br/>' +
                '<span>(<label for="urlenterpost'+s+d+'">' + t('phonetrack', 'Use POST method') +' </label>' +
                '<input type="checkbox" class="urlenterpost" id="urlenterpost'+s+d+'"/>)</span>' +
                '<input type="text" id="urlenter'+s+d+'" class="urlenter" maxlength="500" /><br/>' +
                '<label for="urlleave'+s+d+'"><b>' + t('phonetrack', 'URL to request when leaving') + '</b> </label><br/>' +
                '<span>(<label for="urlleavepost'+s+d+'">' + t('phonetrack', 'Use POST method') +' </label>' +
                '<input type="checkbox" class="urlleavepost" id="urlleavepost'+s+d+'"/>)</span>' +
                '<input type="text" id="urlleave'+s+d+'" class="urlleave" maxlength="500" />' +
                '<label><b>' + t('phonetrack', 'Geofencing zone coordinates') + '</b> ' + '(' + t('phonetrack', 'leave blank to use current map bounds') + ')' + '</label><br/>' +
                '<div class="addgeofenceleft">' +
                '<label for="north'+s+d+'"> ' + t('phonetrack', 'North') + ' </label>' +
                '<input id="north'+s+d+'" class="fencenorth" type="number" value="" min="-90" max="90" step="0.000001"/><br/>' +
                '<label for="south'+s+d+'"> ' + t('phonetrack', 'South') + ' </label>' +
                '<input id="south'+s+d+'" class="fencesouth" type="number" value="" min="-90" max="90" step="0.000001"/>' +
                '</div>' +
                '<div class="addgeofencecenter">' +
                '<button class="geonortheastbutton" title="' + t('phonetrack', 'Set North/East corner by clicking on the map') + '">' +
                '<i class="fa fa-crosshairs" aria-hidden="true"></i> ' + t('phonetrack', 'Set N/E') +
                '</button><br/>' +
                '<button class="geosouthwestbutton" title="' + t('phonetrack', 'Set South/West corner by clicking on the map') + '">' +
                '<i class="fa fa-crosshairs" aria-hidden="true"></i> ' + t('phonetrack', 'Set S/W') +
                '</button>' +
                '</div>' +
                '<div class="addgeofenceright">' +
                '<label for="east'+s+d+'"> ' + t('phonetrack', 'East') + ' </label> ' +
                '<input id="east'+s+d+'" class="fenceeast" type="number" value="" min="-180" max="180" step="0.000001"/><br/>' +
                '<label for="west'+s+d+'"> ' + t('phonetrack', 'West') + ' </label> ' +
                '<input id="west'+s+d+'" class="fencewest" type="number" value="" min="-180" max="180" step="0.000001"/>' +
                '</div>' +
                '<input type="text" class="geofencename" value="' + t('phonetrack', 'Fence name') + '"/>' +
                '<button class="addgeofencebutton" title="' + t('phonetrack', 'Use current map view as geofencing zone') + '">' +
                '<i class="fa fa-plus-circle" aria-hidden="true"></i> ' + t('phonetrack', 'Add zone') +
                '</button>' +
                '</div>' +
                '<ul class="geofencelist"></ul>' +
                '</div>';
            proximLink = ' <button class="toggleProxim" ' +
                'title="' + t('phonetrack', 'Device proximity notifications') + '">' +
                '<i class="fa fa-user-friends" aria-hidden="true"></i></button>';
            proximDiv = '<div class="proximDiv">' +
                '<div class="addproximdiv">' +
                '<p>' + t('phonetrack', 'Select a session, a device name and a distance, set the notification settings, then validate.') + ' ' +
                t('phonetrack', 'You will be notified when distance between devices gets bigger than high limit or smaller than low limit.') + '</p>' +
                '<label>' + t('phonetrack', 'Session') + ' </label> ' +
                '<select class="proximsession"></select>' +
                '<input type="text" class="devicename" value="' + t('phonetrack', 'Device name') + '"/>' +
                '<label for="lowlimit'+s+d+'"> ' + t('phonetrack', 'Low distance limit') + ' </label>' +
                '<input id="lowlimit'+s+d+'" class="lowlimit" type="number" value="500" min="1" max="20000000"/>' +
                t('phonetrack', 'meters') + '<br/>' +
                '<label for="highlimit'+s+d+'"> ' + t('phonetrack', 'High distance limit') + ' </label> ' +
                '<input id="highlimit'+s+d+'" class="highlimit" type="number" value="500" min="1" max="20000000"/>' +
                t('phonetrack', 'meters') + '<br/>' +
                '<label for="sendemail'+s+d+'"> ' + t('phonetrack', 'Email notification') + ' </label>' +
                '<input type="checkbox" class="sendemail" id="sendemail'+s+d+'" checked/><br/>' +
                '<label for="urlclose'+s+d+'"><b>' + t('phonetrack', 'URL to request when devices get close') + '</b></label><br/>' +
                '<span>(<label for="urlclosepost'+s+d+'">' + t('phonetrack', 'Use POST method') +' </label>' +
                '<input type="checkbox" class="urlclosepost" id="urlclosepost'+s+d+'"/>)</span>' +
                '<input type="text" id="urlclose'+s+d+'" class="urlclose" maxlength="500" /><br/>' +
                '<label for="urlfar'+s+d+'"><b>' + t('phonetrack', 'URL to request when devices get far') + '</b> </label><br/>' +
                '<span>(<label for="urlfarpost'+s+d+'">' + t('phonetrack', 'Use POST method') +' </label>' +
                '<input type="checkbox" class="urlfarpost" id="urlfarpost'+s+d+'"/>)</span>' +
                '<input type="text" id="urlfar'+s+d+'" class="urlfar" maxlength="500" />' +
                '<button class="addproximbutton">' +
                '<i class="fa fa-plus-circle" aria-hidden="true"></i> ' + t('phonetrack', 'Add proximity notification') +
                '</button>' +
                '</div>' +
                '<ul class="proximlist"></ul>' +
                '</div>';
        }
        var detailOnOff = 'off';
        if (point) {
            detailOnOff = 'on';
        }
        var detailLink = ' <button class="toggleDetail ' + detailOnOff + '" token="' + s + '" device="' + d + '" ' +
            'title="' + t('phonetrack', 'Toggle detail/edition points') + '">' +
            '<i class="fa fa-circle" aria-hidden="true"></i></button>';
        var lineOnOff = 'off';
        if (line) {
            lineOnOff = 'on nc-theming-main-background';
        }
        var lineDeviceLink = ' <button class="toggleLineDevice ' + lineOnOff + '" ' +
            'token="' + s + '" device="' + d + '" ' +
            'title="' + t('phonetrack', 'Toggle lines') + '">' +
            '</button>';
        var zoomOnOff = 'off';
        if (zoom) {
            zoomOnOff = 'on nc-theming-main-background';
        }
        var autoZoomLink = ' <button class="toggleAutoZoomDevice ' + zoomOnOff + '" ' +
            'token="' + s + '" device="' + d + '" ' +
            'title="' + t('phonetrack', 'Follow this device (autozoom)') + '">' +
            '</button>';
        var nameLabelTxt;
        if (alias !== null && alias !== '') {
            nameLabelTxt = alias + ' (' + name + ')';
        }
        else {
            nameLabelTxt = name;
        }
        $('div.session[token="' + s + '"] ul.devicelist').append(
            '<li device="' + d + '" token="' + s + '">' +
                '<div>' +
                '<div class="devicecolor opaquetooltip' + s + d + '"></div> ' +
                '<div class="deviceLabel" title="' +
                t('phonetrack', 'Center map on device') + '">' + escapeHTML(nameLabelTxt) + '</div> ' +
                renameInput +
                aliasInput +
                dropdowndevicebutton +
                dropdowndevicecontent +
                reaffectSelect +
                proximLink +
                geofencesLink +
                '<button class="zoomdevicebutton" title="' +
                t('phonetrack', 'Center map on device') + ' \'' + escapeHTML(name) + '\'">' +
                '<i class="fa fa-search" aria-hidden="true"></i></button>' +
                autoZoomLink +
                detailLink +
                lineDeviceLink +
                '</div><div style="clear: both;"></div>' +
                geofencesDiv +
                proximDiv +
                '</li>');

        // manage names/ids
        var intDid = parseInt(d);
        phonetrack.deviceNames[s][intDid] = escapeHTML(name);
        phonetrack.deviceAliases[s][intDid] = escapeHTML(alias || '');
        phonetrack.deviceIds[s][name] = intDid;

        phonetrack.sessionPointsLayers[s][d] = L.featureGroup();
        phonetrack.sessionPointsLayersById[s][d] = {};
        phonetrack.sessionPointsEntriesById[s][d] = {};
        phonetrack.sessionLatlngs[s][d] = [];
        phonetrack.sessionDisplayedLatlngs[s][d] = [];
        var linewidth = parseInt($('#linewidth').val()) || 5;
        phonetrack.sessionLineLayers[s][d] = L.featureGroup();
        var nameTxt;
        if (alias !== null && alias !== '') {
            nameTxt = alias + ' (' + name + ')';
        }
        else {
            nameTxt = name;
        }
        linetooltip = sessionname + ' | ' + nameTxt;
        phonetrack.sessionLineLayers[s][d].bindTooltip(
            linetooltip,
            {
                permanent: false,
                sticky: true,
                className: 'tooltip' + s + d
            }
        );
        var radius = $('#pointradius').val();
        var letter;
        if (alias !== null && alias !== '') {
            letter = alias[0];
        }
        else {
            letter = name[0];
        }
        var markerIcon = L.divIcon({
            iconAnchor: [radius, radius],
            className: shape + 'marker color' + s + d,
            html: '<b>' + letter + '</b>'
        });
        var pointIcon = L.divIcon({
            iconAnchor: [radius, radius],
            className: shape + 'marker color' + s + d,
            html: ''
        });
        phonetrack.devicePointIcons[s][d] = pointIcon;

        phonetrack.sessionMarkerLayers[s][d] = L.marker([], {icon: markerIcon});
        phonetrack.sessionMarkerLayers[s][d].on('dragend', dragPointEnd);
        phonetrack.sessionMarkerLayers[s][d].session = s;
        phonetrack.sessionMarkerLayers[s][d].device = d;
        phonetrack.sessionMarkerLayers[s][d].pid = null;
        phonetrack.sessionMarkerLayers[s][d].setZIndexOffset(phonetrack.lastZindex++);
        if (phonetrack.optionsValues.showtime) {
            phonetrack.sessionMarkerLayers[s][d].on('mouseover', markerMouseover);
            phonetrack.sessionMarkerLayers[s][d].on('mouseout', markerMouseout);
        }
        phonetrack.sessionMarkerLayers[s][d].on('click', markerMouseClick);
        $('.session[token="' + s + '"] li[device='+d+']').find('.geofencesDiv').hide();
        $('.session[token="' + s + '"] li[device='+d+']').find('.proximDiv').hide();
        var llb, f, i, pr;
        for (i=0; i < geofences.length; i++) {
            f = geofences[i];
            llb = L.latLngBounds(L.latLng(f.latmin, f.lonmin), L.latLng(f.latmax, f.lonmax));
            addGeoFence(s, d, f.name, f.id, llb, f.urlenter, f.urlleave, f.urlenterpost, f.urlleavepost, f.sendemail);
        }
        for (i=0; i < proxims.length; i++) {
            pr = proxims[i];
            addProxim(s, d, pr.id, pr.sname2, pr.deviceid2, pr.dname2, pr.highlimit, pr.lowlimit, pr.urlclose, pr.urlfar, pr.urlclosepost, pr.urlfarpost, pr.sendemail);
        }
    }

    // append entries ordered by timestamp
    function appendEntriesToDevice(s, d, entries, sessionname) {
        var lastEntryTimestamp, firstEntryTimestamp, device, i, e, entry, ts, m, j, coordsTmp;
        var filter;
        var cutLines, line;
        var linewidth = parseInt($('#linewidth').val()) || 5;
        var linearrow = $('#linearrow').is(':checked');
        var linegradient = $('#linegradient').is(':checked');
        firstEntryTimestamp = parseInt(entries[0].timestamp);
        lastEntryTimestamp = parseInt(entries[entries.length-1].timestamp);
        device = d;
        if ((!phonetrack.lastTime[s].hasOwnProperty(device)) ||
            lastEntryTimestamp > phonetrack.lastTime[s][device])
        {
            phonetrack.lastTime[s][device] = lastEntryTimestamp;
        }
        if ((!phonetrack.firstTime[s].hasOwnProperty(device)) ||
            firstEntryTimestamp < phonetrack.firstTime[s][device])
        {
            phonetrack.firstTime[s][device] = firstEntryTimestamp;
        }

        /////////////////////////// LASTPOSONLY
        // we are in public page which should only display last point of each device
        if (pageIsPublic() && phonetrack.lastposonly === '1') {
            var lastEntryToAdd = entries[entries.length-1];
            var nbExistingEntries = phonetrack.sessionLatlngs[s][d].length;
            var lastExistingEntry = null;
            // we get the last existing entry only if there are entries
            if (nbExistingEntries > 0) {
                lastExistingEntry = phonetrack.sessionPointsEntriesById[s][d][phonetrack.sessionLatlngs[s][d][nbExistingEntries-1][2]];
            }
            // if there is nothing or new entry is more recent than last existing one :
            // only one pos : new entry
            if (nbExistingEntries === 0 || lastEntryToAdd.timestamp > lastExistingEntry.timestamp) {
                phonetrack.sessionPointsEntriesById[s][d][lastEntryToAdd.id] = lastEntryToAdd;
                phonetrack.sessionLatlngs[s][d] = [[lastEntryToAdd.lat, lastEntryToAdd.lon, lastEntryToAdd.id]];

                /////////// update FILTERED coordinates
                // increment lines, insert into displayed layer (sessionLineLayers)
                var displayedLatlngs = filterList(phonetrack.sessionLatlngs[s][d], s, d);
                phonetrack.sessionLineLayers[s][d].clearLayers();
                phonetrack.sessionLineLayers[s] = {};
                delete phonetrack.sessionDisplayedLatlngs[s][d];
                phonetrack.sessionDisplayedLatlngs[s][d] = [displayedLatlngs];

                drawLine(s, d, [displayedLatlngs], linegradient, linewidth, linearrow);

                var radius = phonetrack.optionsValues.pointradius;
                var icon = phonetrack.devicePointIcons[s][d];

                // reset point layers
                phonetrack.sessionPointsLayers[s][d].clearLayers();

                for (e = entries.length-1; e < entries.length; e++) {
                    entry = entries[e];
                    m = L.marker([entry.lat, entry.lon, entry.id],
                        {icon: icon}
                    );
                    m.session = s;
                    m.device = d;
                    m.pid = entry.id;
                    m.on('click', markerMouseClick);
                    m.on('mouseover', markerMouseover);
                    m.on('mouseout', markerMouseout);
                    m.on('dragend', dragPointEnd);
                    phonetrack.sessionPointsLayersById[s][d][entry.id] = m;
                    filter = filterEntry(entry);
                    if (filter) {
                        phonetrack.sessionPointsLayers[s][d].addLayer(m);
                        // no dragging
                    }
                }
            }
        }
        ///////////////////////////// NORMAL
        else {
            /////////// update global coordinates (not filtered)
            // we keep the same i because our points are already ordered
            i = 0;
            for (e = 0; e < entries.length; e++) {
                entry = entries[e];
                // add the entry to global dict
                phonetrack.sessionPointsEntriesById[s][d][entry.id] = entry;
                ts = entry.timestamp;
                while (i < phonetrack.sessionLatlngs[s][d].length
                    // ouch ;-)
                    && ts > phonetrack.sessionPointsEntriesById[s][d][phonetrack.sessionLatlngs[s][d][i][2]].timestamp
                ) {
                    i++;
                }
                phonetrack.sessionLatlngs[s][d].splice(i, 0, [entry.lat, entry.lon, entry.id]);
                i++;
            }

            /////////// update FILTERED coordinates
            // increment lines, insert into displayed layer (sessionLineLayers)
            var displayedLatlngs = filterList(phonetrack.sessionLatlngs[s][d], s, d);
            cutLines = segmentLines(displayedLatlngs, s, d);
            phonetrack.sessionLineLayers[s][d].clearLayers();
            delete phonetrack.sessionDisplayedLatlngs[s][d];
            phonetrack.sessionDisplayedLatlngs[s][d] = cutLines;

            drawLine(s, d, cutLines, linegradient, linewidth, linearrow);

            var radius = phonetrack.optionsValues.pointradius;
            var icon = phonetrack.devicePointIcons[s][d];

            for (e = 0; e < entries.length; e++) {
                entry = entries[e];
                m = L.marker([entry.lat, entry.lon, entry.id],
                    {icon: icon}
                );
                m.session = s;
                m.device = d;
                m.pid = entry.id;
                m.on('click', markerMouseClick);
                m.on('mouseover', markerMouseover);
                m.on('mouseout', markerMouseout);
                m.on('dragend', dragPointEnd);
                phonetrack.sessionPointsLayersById[s][d][entry.id] = m;
                filter = filterEntry(entry);
                if (filter) {
                    phonetrack.sessionPointsLayers[s][d].addLayer(m);
                    // dragging
                    if (!pageIsPublic() && !isSessionShared(s) && phonetrack.optionsValues.dragcheck) {
                        if (phonetrack.map.hasLayer(phonetrack.sessionPointsLayers[s][d])) {
                            m.dragging.enable();
                        }
                    }
                }
            }
        }
    }

    // draw lines for a device, with arrows and gradient if needed
    function drawLine(s, d, linesCoords, linegradient, linewidth, linearrow) {
        var line, i, j;
        for (i = 0; i < linesCoords.length; i++) {
            if (linegradient) {
                var coordsTmp = [];
                for (j=0; j < linesCoords[i].length; j++) {
                    coordsTmp.push([linesCoords[i][j][0], linesCoords[i][j][1], j]);
                }
                line = L.hotline(coordsTmp, {
                    weight: linewidth,
                    outlineWidth: 2,
                    outlineColor: phonetrack.sessionColors[s + d],
                    palette: {0.0: 'white', 1.0: 'black'},
                    min: 0,
                    max: linesCoords[i].length-1
                });
            }
            else {
                line = L.polyline(linesCoords[i], {weight: linewidth, className: 'poly' + s + d});
            }
            phonetrack.sessionLineLayers[s][d].addLayer(line);

            if (linearrow && linesCoords[i].length > 1) {
                var arrows = L.polylineDecorator(line);
                arrows.setPatterns([{
                    offset: 30,
                    repeat: 100,
                    symbol: L.Symbol.arrowHead({
                        pixelSize: 15 + linewidth,
                        polygon: false,
                        pathOptions: {
                            stroke: true,
                            className: 'poly' + s + d,
                            opacity: 1,
                            weight: parseInt(linewidth * 0.6)
                        }
                    })
                }]);
                phonetrack.sessionLineLayers[s][d].addLayer(arrows);
            }
        }
    }

    function markerMouseClick(e) {
        var s = e.target.session;
        var d = e.target.device;
        if (!pageIsPublic()
            && !isSessionShared(s)
            && $('.session[token='+s+'] .devicelist li[device="'+d+'"] .toggleDetail').hasClass('on')
        ) {
            e.target.unbindPopup();
            var pid = e.target.pid;
            var entry = phonetrack.sessionPointsEntriesById[s][d][pid];
            var sessionname = getSessionName(s);
            e.target.bindPopup(getPointPopup(s, d, entry, sessionname), {closeOnClick: false});
            e.target.openPopup();
        }
    }

    function markerMouseover(e) {
        var d = e.target.device;
        var s = e.target.session;
        var pid = e.target.pid;
        var sessionname = getSessionName(s);
        var entry = phonetrack.sessionPointsEntriesById[s][d][pid];
        if ($('#acccirclecheck').is(':checked')) {
            var latlng = e.target.getLatLng();
            var acc = parseInt(phonetrack.sessionPointsEntriesById[s][d][pid].accuracy) || -1;
            if (acc !== -1) {
                phonetrack.currentPrecisionCircle = L.circle(latlng, {radius: acc});
                phonetrack.map.addLayer(phonetrack.currentPrecisionCircle);
            }
            else {
                phonetrack.currentPrecisionCircle = null;
            }
        }
        // tooltips
        var pointtooltip = getPointTooltipContent(entry, sessionname, s);
        e.target.bindTooltip(pointtooltip, {className: 'tooltip' + s + d});
        e.target.openTooltip();
    }

    function markerMouseout(e) {
        if (phonetrack.currentPrecisionCircle !== null
            && phonetrack.map.hasLayer(phonetrack.currentPrecisionCircle)
        ) {
            phonetrack.map.removeLayer(phonetrack.currentPrecisionCircle);
            phonetrack.currentPrecisionCircle = null;
        }
        e.target.unbindTooltip();
        e.target.closeTooltip();
    }

    function isSessionActive(s) {
        return $('.session[token=' + s + '] .watchbutton i').hasClass('fa-toggle-on');
    }

    function isSessionShared(s) {
        return (phonetrack.isSessionShared[s]);
    }

    function editPointDB(token, deviceid, pointid, lat, lon, alt, acc, sat, bat, timestamp, useragent, speed, bearing) {
        var req = {
            token: token,
            deviceid: deviceid,
            pointid: pointid,
            timestamp: timestamp,
            lat: lat,
            lon: lon,
            alt: alt,
            acc: acc,
            bat: bat,
            sat: sat,
            useragent: useragent,
            speed: speed,
            bearing: bearing
        };
        var url = OC.generateUrl('/apps/phonetrack/updatePoint');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done === 1) {
                updatePointMap(token, deviceid, pointid, lat, lon, alt, acc, sat, bat, timestamp, useragent, speed, bearing);
            }
            else {
                OC.Notification.showTemporary(t('phonetrack', 'The point you want to edit does not exist or you\'re not allowed to edit it'));
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to edit point'));
        });
    }

    function updatePointMap(token, deviceid, pointid, lat, lon, alt, acc, sat, bat, timestamp, useragent, speed, bearing) {
        var perm = $('#showtime').is(':checked');
        var linearrow = $('#linearrow').is(':checked');
        var linegradient = $('#linegradient').is(':checked');
        var linewidth = parseInt($('#linewidth').val()) || 5;
        var i, j, coordsTmp;

        var sessionname = getSessionName(token);
        var entry = phonetrack.sessionPointsEntriesById[token][deviceid][pointid];
        // point needs to be moved ?
        var oldlat = parseFloat(entry.lat);
        var oldlon = parseFloat(entry.lon);
        var move = (oldlat !== lat || oldlon !== lon);
        var oldtimestamp = timestamp;
        var dateChanged = (oldtimestamp !== parseInt(entry.timestamp));
        var markerIsNotAnymore = false;
        entry.timestamp = timestamp;
        entry.lat = lat;
        entry.lon = lon;
        entry.altitude = alt;
        entry.batterylevel = bat;
        entry.satellites = sat;
        entry.accuracy = acc;
        entry.useragent = useragent;
        entry.speed = speed;
        entry.bearing = bearing;

        var filter = filterEntry(entry);
        var cutLines, line;

        // move line point
        if (move || dateChanged) {
            phonetrack.sessionPointsLayersById[token][deviceid][pointid].setLatLng([lat, lon, pointid]);
            if (!filter) {
                phonetrack.sessionPointsLayers[token][deviceid].removeLayer(
                    phonetrack.sessionPointsLayersById[token][deviceid][pointid]
                );
            }
        }
        // set new line latlngs if moved or date was modified
        if (move || dateChanged) {
            //var latlngs = phonetrack.sessionLineLayers[token][deviceid].getLatLngs();
            // we work on complete latlngs, not just the displayed one (that can be filtered)
            var latlngs = phonetrack.sessionLatlngs[token][deviceid];
            var newlatlngs = [];
            i = 0;
            // we copy until we get to the right place to insert moved point
            while (i < latlngs.length
                   && ( (parseInt(pointid) === parseInt(latlngs[i][2]))
                         || (timestamp > parseInt(phonetrack.sessionPointsEntriesById[token][deviceid][latlngs[i][2]].timestamp))
                      )
            ) {
                // we don't copy the edited point
                if (parseInt(pointid) !== parseInt(latlngs[i][2])) {
                    // copy
                    newlatlngs.push([latlngs[i][0], latlngs[i][1], latlngs[i][2]]);
                }
                i++;
            }
            // put the edited point
            newlatlngs.push([lat, lon, pointid]);
            // finish the copy
            while (i < latlngs.length) {
                if (parseInt(pointid) !== parseInt(latlngs[i][2])) {
                    // copy
                    newlatlngs.push([latlngs[i][0], latlngs[i][1], latlngs[i][2]]);
                }
                i++;
            }
            phonetrack.sessionLatlngs[token][deviceid] = newlatlngs;
            // modify line
            var filteredlatlngs = filterList(newlatlngs, token, deviceid);
            cutLines = segmentLines(filteredlatlngs, token, deviceid);
            phonetrack.sessionLineLayers[token][deviceid].clearLayers();
            delete phonetrack.sessionDisplayedLatlngs[token][deviceid];
            phonetrack.sessionDisplayedLatlngs[token][deviceid] = cutLines;

            drawLine(token, deviceid, cutLines, linegradient, linewidth, linearrow);

            // lastTime is independent from filters
            phonetrack.lastTime[token][deviceid] =
                phonetrack.sessionPointsEntriesById[token][deviceid][newlatlngs[newlatlngs.length - 1][2]].timestamp;
            phonetrack.firstTime[token][deviceid] =
                phonetrack.sessionPointsEntriesById[token][deviceid][newlatlngs[0][2]].timestamp;
        }

        updateMarker(token, deviceid, sessionname);
        if ($('#togglestats').is(':checked')) {
            updateStatTable();
        }
        changeTooltipStyle();

        phonetrack.map.closePopup();
    }

    function deletePointsDB(s, d, pidlist) {
        var token = s;
        var deviceid = d;
        var req = {
            token: token,
            deviceid: deviceid,
            pointids: pidlist
        };
        var url = OC.generateUrl('/apps/phonetrack/deletePoints');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done === 1) {
                deletePointsMap(s, d, pidlist);
            }
            else {
                OC.Notification.showTemporary(t('phonetrack', 'The point you want to delete does not exist or you\'re not allowed to delete it'));
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to delete point'));
        });
    }

    function deletePointsMap(s, d, pidlist) {
        var perm = $('#showtime').is(':checked');
        var linearrow = $('#linearrow').is(':checked');
        var linegradient = $('#linegradient').is(':checked');
        var linewidth = parseInt($('#linewidth').val()) || 5;
        var i, lat, lng, p, pid, m, j, coordsTmp;
        var cutLines, line;
        var sn = getSessionName(s);
        for (i = 0; i < pidlist.length; i++) {
            pid = pidlist[i];
            // remove associated point from sessionPointsLayers
            m = phonetrack.sessionPointsLayersById[s][d][pid];
            phonetrack.sessionPointsLayers[s][d].removeLayer(m);
            delete phonetrack.sessionPointsLayersById[s][d][pid];
            delete phonetrack.sessionPointsEntriesById[s][d][pid];
        }

        // remove point in the line
        var latlngs = phonetrack.sessionLatlngs[s][d];
        var newlatlngs = [];
        i = 0;
        for (i = 0; i < latlngs.length; i++) {
            if (pidlist.indexOf(latlngs[i][2]) === -1) {
                newlatlngs.push([latlngs[i][0], latlngs[i][1], latlngs[i][2]]);
            }
        }

        phonetrack.sessionLatlngs[s][d] = newlatlngs;
        var filteredlatlngs = filterList(newlatlngs, s, d);
        cutLines = segmentLines(filteredlatlngs, s, d);
        phonetrack.sessionLineLayers[s][d].clearLayers();
        delete phonetrack.sessionDisplayedLatlngs[s][d];
        phonetrack.sessionDisplayedLatlngs[s][d] = cutLines;

        drawLine(s, d, cutLines, linegradient, linewidth, linearrow);

        updateMarker(s, d, sn);

        // update lastTime : new last point time (independent from filter)
        if (newlatlngs.length > 0) {
            phonetrack.lastTime[s][d] =
                phonetrack.sessionPointsEntriesById[s][d][newlatlngs[newlatlngs.length - 1][2]].timestamp;
            phonetrack.firstTime[s][d] =
                phonetrack.sessionPointsEntriesById[s][d][newlatlngs[0][2]].timestamp;
        }
        else {
            // there is no point left for this device
            delete phonetrack.lastTime[s][d];
            delete phonetrack.firstTime[s][d];
        }
        if ($('#togglestats').is(':checked')) {
            updateStatTable();
        }

        phonetrack.map.closePopup();
    }

    function addPointDB(plat='', plon='', palt=null, pacc=null, psat=null, pbat=null, pmoment='', pspeed=null, pbearing=null) {
        var lat, lon, alt, acc, sat, bat, mom, speed, bearing;
        var tab = $('#addPointTable');
        var token = $('#addPointSession option:selected').attr('token');
        var devicename = $('#addPointDevice').val();
        lat = plat;
        lon = plon;
        alt = palt;
        acc = pacc;
        sat = psat;
        bat = pbat;
        mom = pmoment;
        speed = pspeed;
        bearing = pbearing;
        var timestamp = mom.unix();
        var req = {
            token: token,
            devicename: devicename,
            timestamp: timestamp,
            lat: lat,
            lon: lon,
            alt: alt,
            acc: acc,
            bat: bat,
            sat: sat,
            useragent: t('phonetrack', 'Manually added'),
            speed: speed,
            bearing: bearing
        };
        var url = OC.generateUrl('/apps/phonetrack/addPoint');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done === 1) {
                // add the point on the map only if the session was displayed at least once
                if (phonetrack.sessionLineLayers.hasOwnProperty(token)) {
                    addPointMap(response.pointid, lat, lon, alt, acc, sat, bat, speed, bearing, timestamp, response.deviceid);
                }
            }
            else if (response.done === 2) {
                OC.Notification.showTemporary(t('phonetrack', 'Impossible to add this point'));
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to add point'));
        });
    }

    function addPointMap(id, lat, lon, alt, acc, sat, bat, speed, bearing, timestamp, deviceid) {
        var perm = $('#showtime').is(':checked');
        var linearrow = $('#linearrow').is(':checked');
        var linegradient = $('#linegradient').is(':checked');
        var linewidth = parseInt($('#linewidth').val()) || 5;
        var tab = $('#addPointTable');
        var token = $('#addPointSession option:selected').attr('token');
        var devicename = $('#addPointDevice').val();
        var useragent = t('phonetrack', 'Manually added');
        var pid = parseInt(id);
        var cutLines, line;

        var entry = {id: pid};
        entry.deviceid = deviceid;
        entry.timestamp = timestamp;
        entry.lat = lat;
        entry.lon = lon;
        entry.altitude = alt;
        entry.batterylevel = bat;
        entry.satellites = sat;
        entry.accuracy = acc;
        entry.useragent = useragent;
        entry.speed = speed;
        entry.bearing = bearing;

        var filter = filterEntry(entry);

        var sessionname = getSessionName(token);

        // add device if it does not exist
        if (! phonetrack.sessionLineLayers[token].hasOwnProperty(deviceid)) {
            addDevice(token, deviceid, sessionname, '', devicename);
            appendEntriesToDevice(token, deviceid, [entry], sessionname);
        }
        // insert entry correctly ;)
        else {
            // add line point
            var icon = phonetrack.devicePointIcons[token][deviceid];
            var m = L.marker(
                [entry.lat, entry.lon, entry.id],
                {icon: icon}
            );
            m.session = token;
            m.device = deviceid;
            m.pid = entry.id;
            m.on('mouseover', markerMouseover);
            m.on('mouseout', markerMouseout);
            m.on('dragend', dragPointEnd);
            m.on('click', markerMouseClick);
            phonetrack.sessionPointsEntriesById[token][deviceid][entry.id] = entry;
            phonetrack.sessionPointsLayersById[token][deviceid][entry.id] = m;
            if (filter) {
                phonetrack.sessionPointsLayers[token][deviceid].addLayer(m);

                // manage draggable
                // if points are displayed
                if (phonetrack.map.hasLayer(phonetrack.sessionPointsLayers[token][deviceid])) {
                    // if dragging is allowed
                    if (!pageIsPublic() && !isSessionShared(token) && $('#dragcheck').is(':checked')) {
                        m.dragging.enable();
                    }
                }
            }

            // update line

            //var latlngs = phonetrack.sessionLineLayers[token][deviceid].getLatLngs();
            var latlngs = phonetrack.sessionLatlngs[token][deviceid];
            var newlatlngs = [];
            var i = 0;
            var j, coordsTmp;
            // we copy until we get to the right place to insert new point
            while (i < latlngs.length
                   && timestamp > parseInt(phonetrack.sessionPointsEntriesById[token][deviceid][latlngs[i][2]].timestamp)
            ) {
                // copy
                newlatlngs.push([latlngs[i][0], latlngs[i][1], latlngs[i][2]]);
                i++;
            }
            // put the edited point
            newlatlngs.push([lat, lon, pid]);
            // finish the copy
            while (i < latlngs.length) {
                // copy
                newlatlngs.push([latlngs[i][0], latlngs[i][1], latlngs[i][2]]);
                i++;
            }
            // modify line
            phonetrack.sessionLatlngs[token][deviceid] = newlatlngs;
            var filteredlatlngs = filterList(newlatlngs, token, deviceid);
            cutLines = segmentLines(filteredlatlngs, token, deviceid);
            phonetrack.sessionLineLayers[token][deviceid].clearLayers();
            delete phonetrack.sessionDisplayedLatlngs[token][deviceid];
            phonetrack.sessionDisplayedLatlngs[token][deviceid] = cutLines;

            drawLine(token, deviceid, cutLines, linegradient, linewidth, linearrow);

            // update lastTime
            phonetrack.lastTime[token][deviceid] =
                phonetrack.sessionPointsEntriesById[token][deviceid][newlatlngs[newlatlngs.length - 1][2]].timestamp;
            phonetrack.firstTime[token][deviceid] =
                phonetrack.sessionPointsEntriesById[token][deviceid][newlatlngs[0][2]].timestamp;
        }
        updateMarker(token, deviceid, sessionname);
        if ($('#togglestats').is(':checked')) {
            updateStatTable();
        }
    }

    function getPointPopup(s, d, entry, sn) {
        var dateval = '';
        var hourval = '';
        var minval = '';
        var secval = '';
        if (entry.timestamp) {
            var mom = moment.unix(parseInt(entry.timestamp));
            dateval = mom.format('YYYY-MM-DD');
            hourval = mom.format('HH');
            minval = mom.format('mm');
            secval = mom.format('ss');
        }
        var res = '<table class="editPoint" pid="' + entry.id + '"' +
           ' token="' + s + '" deviceid="' + d + '" sessionname="' + sn + '">';
        res = res + '<tr title="' + t('phonetrack', 'Date') + '">';
        res = res + '<td><i class="fa fa-calendar-alt" style="font-size: 20px;"></i></td>';
        res = res + '<td><input role="date" type="date" value="' + dateval + '"/></td>';
        res = res + '</tr><tr title="' + t('phonetrack', 'Time') + '">';
        res = res + '<td><i class="far fa-clock" style="font-size: 20px;"></i></td>';
        res = res + '<td><input role="hour" type="number" value="' + hourval + '" min="0" max="23"/>h' +
            '<input role="minute" type="number" value="' + minval + '" min="0" max="59"/>' +
            'min<input role="second" type="number" value="' + secval + '" min="0" max="59"/>sec</td>';
        res = res + '</tr><tr title="' + t('phonetrack', 'Altitude') + '">';
        res = res + '<td><i class="fa fa-chart-area" style="font-size: 20px;"></td>';
        res = res + '<td><input role="altitude" type="number" value="' + entry.altitude + '" min="-1"/>m</td>';
        res = res + '</tr><tr title="' + t('phonetrack', 'Precision') + '">';
        res = res + '<td><i class="far fa-dot-circle" style="font-size: 20px;"></td>';
        res = res + '<td><input role="precision" type="number" value="' + entry.accuracy + '" min="-1"/>m</td>';
        res = res + '</tr><tr title="' + t('phonetrack', 'Speed') + '">';
        res = res + '<td><i class="fa fa-tachometer-alt" style="font-size: 20px;"></td>';
        var speed_kmph = entry.speed;
        if (entry.speed && parseInt(entry.speed) !== -1) {
            speed_kmph = parseFloat(entry.speed) * 3.6;
            speed_kmph = speed_kmph.toFixed(3);
        }
        res = res + '<td><input role="speed" type="number" value="' + speed_kmph + '" min="-1"/>km/h</td>';
        res = res + '</tr><tr title="' + t('phonetrack', 'Bearing') + '">';
        res = res + '<td><i class="fa fa-compass" style="font-size: 20px;"></td>';
        res = res + '<td><input role="bearing" type="number" value="' + entry.bearing + '" min="-1" max="360"/>°</td>';
        res = res + '</tr><tr title="' + t('phonetrack', 'Satellites') + '">';
        res = res + '<td><i class="fa fa-signal" style="font-size: 20px;"></td>';
        res = res + '<td><input role="satellites" type="number" value="' + entry.satellites + '" min="-1"/></td>';
        res = res + '</tr><tr title="' + t('phonetrack', 'Battery') + '">';
        res = res + '<td><i class="fa fa-battery-half" style="font-size: 20px;"></i></td>';
        res = res + '<td><input role="battery" type="number" value="' + entry.batterylevel + '" min="-1" max="100"/>%</td>';
        res = res + '</tr><tr title="' + t('phonetrack', 'User-agent') + '">';
        res = res + '<td><i class="fa fa-mobile-alt" style="font-size: 35px;"></i></td>';
        res = res + '<td><input role="useragent" type="text" value="' + entry.useragent + '"/></td>';
        res = res + '</tr><tr title="' + t('phonetrack', 'lat : lng') + '">';
        res = res + '<td><i class="fa fa-map-marker-alt" style="font-size: 20px;"></td>';
        res = res + '<td><input role="latlng" type="text" value="' +
            parseFloat(entry.lat).toFixed(5) + ' : ' + parseFloat(entry.lon).toFixed(5) + '" readonly/></td>';
        res = res + '</tr><tr title="' + t('phonetrack', 'DMS coords') + '">';
        res = res + '<td><i class="fa fa-globe" style="font-size: 20px;"></td>';
        res = res + '<td><input role="dms" type="text" value="' + convertDMS(entry.lat, entry.lon) + '" readonly/></td>';
        res = res + '</tr>';
        res = res + '</table>';
        res = res + '<button class="valideditpoint"><i class="fa fa-save" aria-hidden="true"></i> ' + t('phonetrack', 'Save') + '</button>';
        res = res + '<button class="deletepoint"><i class="fa fa-trash" aria-hidden="true" style="color:red;"></i> ' + t('phonetrack', 'Delete') + '</button>';
        res = res + '<br/><button class="movepoint"><i class="fa fa-arrows-alt" aria-hidden="true"></i> ' + t('phonetrack', 'Move') + '</button>';
        res = res + '<button class="canceleditpoint"><i class="fa fa-undo" aria-hidden="true" style="color:red;"></i> ' + t('phonetrack', 'Cancel') + '</button>';
        return res;
    }

    function getPointTooltipContent(entry, sn, s) {
        var mom;
        var name = getDeviceName(s, entry.deviceid);
        var alias = getDeviceAlias(s, entry.deviceid);
        var nameLabelTxt;
        if (alias !== null && alias !== '') {
            nameLabelTxt = alias + ' (' + name + ')';
        }
        else {
            nameLabelTxt = name;
        }
        var pointtooltip = sn + ' | ' + nameLabelTxt;
        if (entry.timestamp) {
            mom = moment.unix(parseInt(entry.timestamp));
            pointtooltip = pointtooltip + '<br/>' +
                mom.format('YYYY-MM-DD HH:mm:ss (Z)');
        }
        if ($('#tooltipshowelevation').is(':checked') && !isNaN(entry.altitude) && entry.altitude !== null) {
            pointtooltip = pointtooltip + '<br/>' +
                t('phonetrack', 'Altitude') + ' : ' + parseFloat(entry.altitude).toFixed(2) + 'm';
        }
        if ($('#tooltipshowaccuracy').is(':checked') && !isNaN(entry.accuracy) && entry.accuracy !== null
            && parseFloat(entry.accuracy) >= 0) {
            pointtooltip = pointtooltip + '<br/>' +
                t('phonetrack', 'Precision') + ' : ' + entry.accuracy + 'm';
        }
        if ($('#tooltipshowspeed').is(':checked') && !isNaN(entry.speed) && entry.speed !== null
            && parseFloat(entry.speed) >= 0) {
            var speed_kmph = parseFloat(entry.speed) * 3.6;
            speed_kmph = speed_kmph.toFixed(3);
            pointtooltip = pointtooltip + '<br/>' +
                t('phonetrack', 'Speed') + ' : ' + speed_kmph + 'km/h';
        }
        if ($('#tooltipshowbearing').is(':checked') && !isNaN(entry.bearing) && entry.bearing !== null
            && parseFloat(entry.bearing) >= 0 && parseFloat(entry.bearing) <= 360) {
            pointtooltip = pointtooltip + '<br/>' +
                t('phonetrack', 'Bearing') + ' : ' + entry.bearing + '°';
        }
        if ($('#tooltipshowsatellites').is(':checked') && !isNaN(entry.satellites) && entry.satellites !== null
            && parseInt(entry.satellites) >= 0) {
            pointtooltip = pointtooltip + '<br/>' +
                t('phonetrack', 'Satellites') + ' : ' + entry.satellites;
        }
        if ($('#tooltipshowbattery').is(':checked') && !isNaN(entry.batterylevel) && entry.batterylevel !== null
            && parseFloat(entry.batterylevel) >= 0) {
            pointtooltip = pointtooltip + '<br/>' +
                t('phonetrack', 'Battery') + ' : ' + entry.batterylevel + '%';
        }
        if ($('#tooltipshowuseragent').is(':checked') && entry.useragent !== '' && entry.useragent !== null && entry.useragent !== 'nothing') {
            pointtooltip = pointtooltip + '<br/>' +
                t('phonetrack', 'User-agent') + ' : ' + escapeHTML(entry.useragent);
        }

        return pointtooltip;
    }

    function showHideSelectedSessions() {
        var token, d, displayedPointsLayers, sessionname;
        var displayedMarkers = [];
        var viewLines = $('#viewmove').is(':checked');
        $('.watchbutton i').each(function() {
            token = $(this).parent().parent().parent().attr('token');
            sessionname = getSessionName(token);
            if ($(this).hasClass('fa-toggle-on')) {
                for (d in phonetrack.sessionLineLayers[token]) {
                    if (viewLines) {
                        if (!phonetrack.map.hasLayer(phonetrack.sessionLineLayers[token][d])) {
                            // if linedevice activated
                            if ($('.session[token='+token+'] .devicelist li[device="'+d+'"] .toggleLineDevice').hasClass('on')) {
                                phonetrack.map.addLayer(phonetrack.sessionLineLayers[token][d]);
                            }
                        }
                    }
                    else {
                        if (phonetrack.map.hasLayer(phonetrack.sessionLineLayers[token][d])) {
                            phonetrack.map.removeLayer(phonetrack.sessionLineLayers[token][d]);
                        }
                    }
                }
                for (d in phonetrack.sessionPointsLayers[token]) {
                    if (!phonetrack.map.hasLayer(phonetrack.sessionPointsLayers[token][d])) {
                        if ($('.session[token='+token+'] .devicelist li[device="'+d+'"] .toggleDetail').hasClass('on')) {
                            phonetrack.map.addLayer(phonetrack.sessionPointsLayers[token][d]);
                            // manage draggable
                            if (!pageIsPublic() && !isSessionShared(token) && $('#dragcheck').is(':checked')) {
                                phonetrack.sessionPointsLayers[token][d].eachLayer(function(l) {
                                    l.dragging.enable();
                                });
                            }
                        }
                    }
                }
                for (d in phonetrack.sessionMarkerLayers[token]) {
                    updateMarker(token, d, sessionname);
                    displayedPointsLayers = phonetrack.sessionPointsLayers[token][d].getLayers();
                    if (displayedPointsLayers.length !== 0) {
                        displayedMarkers.push(phonetrack.sessionMarkerLayers[token][d].getLatLng());
                    }
                }
            }
            else {
                if (phonetrack.sessionLineLayers.hasOwnProperty(token)) {
                    for (d in phonetrack.sessionLineLayers[token]) {
                        if (phonetrack.map.hasLayer(phonetrack.sessionLineLayers[token][d])) {
                            phonetrack.map.removeLayer(phonetrack.sessionLineLayers[token][d]);
                        }
                    }
                }
                if (phonetrack.sessionPointsLayers.hasOwnProperty(token)) {
                    for (d in phonetrack.sessionPointsLayers[token]) {
                        if (phonetrack.map.hasLayer(phonetrack.sessionPointsLayers[token][d])) {
                            phonetrack.map.removeLayer(phonetrack.sessionPointsLayers[token][d]);
                        }
                    }
                }
                if (phonetrack.sessionMarkerLayers.hasOwnProperty(token)) {
                    for (d in phonetrack.sessionMarkerLayers[token]) {
                        if (phonetrack.map.hasLayer(phonetrack.sessionMarkerLayers[token][d])) {
                            phonetrack.map.removeLayer(phonetrack.sessionMarkerLayers[token][d]);
                        }
                    }
                }
            }

        });

        // ZOOM
        if ($('#autozoom').is(':checked') && displayedMarkers.length > 0) {
            zoomOnDisplayedMarkers();
        }
        // show/hide last marker tooltips
        changeTooltipStyle();
    }

    function zoomOnDisplayedMarkers(selectedSessionToken='') {
        var token, d, lls, i;
        var pointLatlngList = []
        var layerList = []
        var boundsToZoomOn;

        // first we check if there are devices selected for zoom
        var devicesToFollow = {};
        var nbDevicesToFollow = 0
        $('.toggleAutoZoomDevice.on').each(function() {
            // we only take those for session which are watched
            var viewSessionCheck = $(this).parent().parent().parent().parent().find('.watchbutton i');
            if (viewSessionCheck.hasClass('fa-toggle-on')) {
                var token = $(this).attr('token');
                var device = $(this).attr('device');
                if (!devicesToFollow.hasOwnProperty(token)) {
                    devicesToFollow[token] = [];
                }
                devicesToFollow[token].push(device);
                nbDevicesToFollow++;
            }
        });

        $('.watchbutton i').each(function() {
            token = $(this).parent().parent().parent().attr('token');
            if ($(this).hasClass('fa-toggle-on') && (selectedSessionToken === '' || token === selectedSessionToken)) {
                for (d in phonetrack.sessionMarkerLayers[token]) {
                    // if no device is followed => all devices are taken
                    // if some devices are followed, just take them
                    if (nbDevicesToFollow === 0
                        || (devicesToFollow.hasOwnProperty(token) && devicesToFollow[token].indexOf(d) !== -1)
                    ) {
                        if (phonetrack.map.hasLayer(phonetrack.sessionMarkerLayers[token][d])) {
                            pointLatlngList.push(phonetrack.sessionMarkerLayers[token][d].getLatLng());
                        }
                        if (phonetrack.map.hasLayer(phonetrack.sessionPointsLayers[token][d])) {
                            layerList.push(phonetrack.sessionPointsLayers[token][d]);
                        }
                        if (phonetrack.map.hasLayer(phonetrack.sessionLineLayers[token][d])) {
                            layerList.push(phonetrack.sessionLineLayers[token][d]);
                        }
                    }
                }
            }
        });

        if (pointLatlngList.length > 0) {
            boundsToZoomOn = L.latLngBounds(pointLatlngList);
            if (layerList.length > 0) {
                for (i=0; i < layerList.length; i++) {
                    boundsToZoomOn.extend(layerList[i].getBounds());
                }
            }

            // ZOOM
            phonetrack.map.fitBounds(boundsToZoomOn, {
                //animate: true,
                maxZoom: 15,
                paddingTopLeft: [parseInt($('#sidebar').css('width')), 50],
                paddingBottomRight: [50, 50]
            });
        }
        else {
            OC.Notification.showTemporary(t('phonetrack', 'Impossible to zoom, there is no point to zoom on for this session'));
        }
    }

    function changeTooltipStyle() {
        var perm = $('#showtime').is(':checked');
        var s, d, m, t, sessionname, entry, pointtooltip;
        for (s in phonetrack.sessionMarkerLayers) {
            for (d in phonetrack.sessionMarkerLayers[s]) {
                m = phonetrack.sessionMarkerLayers[s][d];
                // if there is a marker for this device
                if (m && m.pid) {
                    m.closeTooltip();
                    // if option is set, show permanent tooltip for last marker
                    if (perm) {
                        // is not affected by mouseover anymore
                        m.off('mouseover', markerMouseover);
                        m.off('mouseout', markerMouseout);
                        // bind permanent tooltip
                        entry = phonetrack.sessionPointsEntriesById[s][d][m.pid];
                        sessionname = getSessionName(s);
                        pointtooltip = getPointTooltipContent(entry, sessionname, s);
                        m.bindTooltip(pointtooltip, {permanent: perm, offset: offset, className: 'tooltip' + s + d});
                    }
                    else {
                        m.on('mouseover', markerMouseover);
                        m.on('mouseout', markerMouseout);
                    }
                }
            }
        }
    }

    function importSession(path) {
        if (! endsWith(path, '.gpx')) {
            OC.Notification.showTemporary(t('phonetrack', 'File extension must be \'.gpx\' to be imported'));
        }
        else {
            showLoadingAnimation();
            var req = {
                path: path
            };
            var url = OC.generateUrl('/apps/phonetrack/importSession');
            $.ajax({
                type: 'POST',
                url: url,
                data: req,
                async: true
            }).done(function (response) {
                if (response.done === 1) {
                    addSession(response.token, response.sessionName, response.publicviewtoken, [], response.devices, 1);
                }
                else if (response.done === 2) {
                    OC.Notification.showTemporary(t('phonetrack', 'Failed to create imported session'));
                }
                else if (response.done === 3) {
                    OC.Notification.showTemporary(
                        t('phonetrack', 'Failed to import session') + '. ' +
                        t('phonetrack', 'File is not readable')
                    );
                }
                else if (response.done === 4) {
                    OC.Notification.showTemporary(
                        t('phonetrack', 'Failed to import session') + '. ' +
                        t('phonetrack', 'File does not exist')
                    );
                }
                // TODO 5 : error in gpx parsing
                // 6 : no trk in gpx
            }).always(function() {
                hideLoadingAnimation();
            }).fail(function() {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to import session'));
            });
        }
    }

    function saveAction(name, token, targetPath, filename) {
        showLoadingAnimation();
        var req = {
            name: name,
            token: token,
            target: targetPath+'/'+filename
        };
        var url = OC.generateUrl('/apps/phonetrack/export');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done) {
                OC.Notification.showTemporary(t('phonetrack', 'Session successfully exported in') +
                    ' ' + targetPath + '/' + filename);
            }
            else {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to export session'));
            }
        }).always(function() {
            hideLoadingAnimation();
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to export session'));
        });
    }

    function locationFound(e) {
        if (pageIsPublicWebLog() && $('#logme').is(':checked')) {
            var deviceid = $('#logmedeviceinput').val();
            var lat, lon, alt, acc, timestamp;
            lat = e.latitude;
            lon = e.longitude;
            alt = e.altitude;
            acc = e.accuracy;
            timestamp = e.timestamp;
            var req = {
                lat: lat,
                lon: lon,
                alt: alt,
                acc: acc,
                timestamp: timestamp,
                useragent: 'browser'
            };
            var url = OC.generateUrl('/apps/phonetrack/logPost/' + phonetrack.token + '/' + deviceid);
            $.ajax({
                type: 'POST',
                url: url,
                data: req,
                async: true
            }).done(function (response) {
                //console.log(response);
            }).always(function() {
            }).fail(function() {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to log position'));
            });
        }
    }

    function toggleAutoZoomDevice(elem) {
        if (elem.hasClass('on')) {
            elem.addClass('off').removeClass('on nc-theming-main-background');
        }
        else {
            elem.addClass('on nc-theming-main-background').removeClass('off');
        }
    }

    function toggleLineDevice(elem) {
        var viewmove = $('#viewmove').is(':checked');
        var d = elem.parent().parent().attr('device');
        var s = elem.parent().parent().attr('token');
        var id;

        // line points
        if (viewmove) {
            if (phonetrack.map.hasLayer(phonetrack.sessionLineLayers[s][d])) {
                phonetrack.sessionLineLayers[s][d].remove();
                elem.addClass('off').removeClass('on nc-theming-main-background');
            }
            else{
                phonetrack.sessionLineLayers[s][d].addTo(phonetrack.map);
                elem.addClass('on nc-theming-main-background').removeClass('off');
            }
        }
        else {
            if (elem.hasClass('on')) {
                elem.addClass('off').removeClass('on nc-theming-main-background');
            }
            else {
                elem.addClass('on nc-theming-main-background').removeClass('off');
            }
        }
    }

    function toggleDetailDevice(elem) {
        var d = elem.parent().parent().attr('device');
        var s = elem.parent().parent().attr('token');
        var id;

        // line points
        if (phonetrack.map.hasLayer(phonetrack.sessionPointsLayers[s][d])) {
            phonetrack.sessionPointsLayers[s][d].eachLayer(function(l) {
                l.dragging.disable();
            });
            phonetrack.sessionPointsLayers[s][d].remove();
            elem.addClass('off').removeClass('on');
        }
        else{
            phonetrack.sessionPointsLayers[s][d].addTo(phonetrack.map);
            elem.addClass('on').removeClass('off');
            // manage draggable
            if (!pageIsPublic() && !isSessionShared(s) && $('#dragcheck').is(':checked')) {
                phonetrack.sessionPointsLayers[s][d].eachLayer(function(l) {
                    l.dragging.enable();
                });
            }
        }
        // marker
        if (!pageIsPublic()
            && !isSessionShared(s)
            && phonetrack.map.hasLayer(phonetrack.sessionMarkerLayers[s][d])
        ) {
            if (elem.hasClass('off')) {
                phonetrack.sessionMarkerLayers[s][d].dragging.disable();
            }
            else {
                if ($('#dragcheck').is(':checked')) {
                    // if marker is displayed (not filtered)
                    phonetrack.sessionMarkerLayers[s][d].dragging.enable();
                }
            }
        }
    }

    function zoomOnDevice(elem, t) {
        var id, dd, t, b, l;
        var perm = $('#showtime').is(':checked');
        var viewmove = $('#viewmove').is(':checked');
        var d = elem.parent().parent().attr('device');
        var s = elem.parent().parent().attr('token');
        var m = phonetrack.sessionMarkerLayers[s][d];

        if (phonetrack.sessionPointsLayers[s][d].getLayers().length > 0) {
            // if we show movement lines :
            // bring it to front, show/hide points
            // get correct zoom bounds
            if (phonetrack.map.hasLayer(phonetrack.sessionLineLayers[s][d])) {
                l = phonetrack.sessionLineLayers[s][d];
                // does not work with polylineDecorator
                //l.bringToFront();
                b = l.getBounds();
            }
            else if (phonetrack.map.hasLayer(phonetrack.sessionPointsLayers[s][d])) {
                l = phonetrack.sessionPointsLayers[s][d];
                l.bringToFront();
                b = l.getBounds();
            }
            else {
                b = L.latLngBounds(m.getLatLng(), m.getLatLng());
            }

            // covers all problematic cases
            if (b.getSouthWest().equals(b.getNorthWest())) {
                phonetrack.map.setView(m.getLatLng(), 15, {animate: true});
            }
            else {
                phonetrack.map.fitBounds(b, {
                    animate: true,
                    maxZoom: 15,
                    paddingTopLeft: [parseInt($('#sidebar').css('width')), 50],
                    paddingBottomRight: [50, 50]
                });
            }

            for (id in phonetrack.sessionPointsLayersById[s][d]) {
                phonetrack.sessionPointsLayersById[s][d][id].setZIndexOffset(phonetrack.lastZindex);
            }
            phonetrack.lastZindex += 10;

            m.setZIndexOffset(phonetrack.lastZindex++);
        }
        else {
            OC.Notification.showTemporary(t('phonetrack', 'Impossible to zoom, there is no point to zoom on for this device'));
        }
    }

    function hideAllDropDowns() {
        var dropdowns = document.getElementsByClassName('dropdown-content');
        var reafdropdowns = document.getElementsByClassName('reaffectDeviceDiv');
        var openDropdown;
        var i;
        for (i = 0; i < dropdowns.length; i++) {
            openDropdown = dropdowns[i];
            if (openDropdown.classList.contains('show')) {
                openDropdown.classList.remove('show');
            }
        }
        for (i = 0; i < reafdropdowns.length; i++) {
            openDropdown = reafdropdowns[i];
            if (openDropdown.classList.contains('show')) {
                openDropdown.classList.remove('show');
            }
        }
    }

    function addNameReservationDb(token, devicename) {
        var req = {
            token: token,
            devicename: devicename
        };
        var url = OC.generateUrl('/apps/phonetrack/addNameReservation');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done === 1) {
                addNameReservation(token, devicename, response.nametoken);
            }
            else if (response.done === 2) {
                OC.Notification.showTemporary(t('phonetrack', '\'{n}\' is already reserved', {'n': devicename}));
            }
            else {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to reserve \'{n}\'', {'n': devicename}));
            }
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to reserve device name'));
        });
    }

    function addNameReservation(token, devicename, nametoken) {
        var li = '<li name="' + escapeHTML(devicename) + '"><label>' +
            escapeHTML(devicename) + ' : '+ escapeHTML(nametoken) + '</label>' +
            '<button class="deletereservedname"><i class="fa fa-trash"></i></li>';
        $('.session[token="' + token + '"]').find('.namereservlist').append(li);
        $('.session[token="' + token + '"]').find('.addnamereserv').val('');
    }

    function deleteNameReservationDb(token, devicename) {
        var req = {
            token: token,
            devicename: devicename
        };
        var url = OC.generateUrl('/apps/phonetrack/deleteNameReservation');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done === 1) {
                var li = $('.session[token="' + token + '"]').find('.namereservlist li[name=' + devicename + ']');
                li.fadeOut('slow', function() {
                    li.remove();
                });
            }
            else if (response.done === 2) {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to delete reserved name') +
                '. ' + t('phonetrack', 'This device does not exist'));
            }
            else if (response.done === 3) {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to delete reserved name') +
                '. ' + t('phonetrack', 'This device name is not reserved, please reload this page'));
            }
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to delete reserved name'));
        });
    }

    function addUserShareDb(token, username) {
        var req = {
            token: token,
            username: username
        };
        var url = OC.generateUrl('/apps/phonetrack/addUserShare');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done === 1) {
                addUserShare(token, username);
            }
            else if (response.done === 4) {
                OC.Notification.showTemporary(t('phonetrack', 'User does not exist'));
            }
            else {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to add user share'));
            }
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to add user share'));
        });
    }

    function addUserShare(token, username) {
        var li = '<li username="' + escapeHTML(username) + '"><label>' +
            t('phonetrack', 'Shared with {u}', {'u': username}) + '</label>' +
            '<button class="deleteusershare"><i class="fa fa-trash"></i></li>';
        $('.session[token="' + token + '"]').find('.usersharelist').append(li);
        $('.session[token="' + token + '"]').find('.addusershare').val('');
    }

    function deleteUserShareDb(token, username) {
        var req = {
            token: token,
            username: username
        };
        var url = OC.generateUrl('/apps/phonetrack/deleteUserShare');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done === 1) {
                var li = $('.session[token="' + token + '"]').find('.usersharelist li[username=' + username + ']');
                li.fadeOut('slow', function() {
                    li.remove();
                });
            }
            else {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to delete user share'));
            }
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to delete user share'));
        });
    }

    function setPublicShareGeofencifyDb(token, sharetoken, geofencify) {
        var req = {
            token: token,
            sharetoken: sharetoken,
            geofencify: geofencify
        };
        var url = OC.generateUrl('/apps/phonetrack/setPublicShareGeofencify');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done === 1) {
                OC.Notification.showTemporary(t('phonetrack', 'Public share has been successfully modified'));
            }
            else {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to modify public share'));
            }
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to modify public share'));
        });
    }

    function setPublicShareLastOnlyDb(token, sharetoken, lastposonly) {
        var req = {
            token: token,
            sharetoken: sharetoken,
            lastposonly: lastposonly
        };
        var url = OC.generateUrl('/apps/phonetrack/setPublicShareLastOnly');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done === 1) {
                OC.Notification.showTemporary(t('phonetrack', 'Public share has been successfully modified'));
            }
            else {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to modify public share'));
            }
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to modify public share'));
        });
    }

    function setPublicShareDeviceDb(token, sharetoken, devicename) {
        var req = {
            token: token,
            sharetoken: sharetoken,
            devicename: devicename
        };
        var url = OC.generateUrl('/apps/phonetrack/setPublicShareDevice');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done === 1) {
                OC.Notification.showTemporary(t('phonetrack', 'Device name restriction has been successfully set'));
            }
            else {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to set public share device name restriction'));
            }
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to set public share device name restriction'));
        });
    }

    function addGeoFenceDb(token, device, fencename, mapbounds, urlenter, urlleave, urlenterpost, urlleavepost, sendemail) {
        var latmin = mapbounds.getSouth();
        var latmax = mapbounds.getNorth();
        var lonmin = mapbounds.getWest();
        var lonmax = mapbounds.getEast();
        var req = {
            token: token,
            device: device,
            fencename: fencename,
            latmin: latmin,
            latmax: latmax,
            lonmin: lonmin,
            lonmax: lonmax,
            urlenter: urlenter,
            urlleave: urlleave,
            urlenterpost: urlenterpost,
            urlleavepost: urlleavepost,
            sendemail: sendemail
        };
        var url = OC.generateUrl('/apps/phonetrack/addGeofence');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done === 1 || response.done === 4) {
                addGeoFence(token, device, fencename, response.fenceid, mapbounds, urlenter, urlleave, urlenterpost, urlleavepost, sendemail);
                if (response.done === 4) {
                    OC.Notification.showTemporary(t('phonetrack', 'Warning : User email and server admin email must be set to receive geofencing alerts.'));
                }
            }
            else {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to add geofencing zone'));
            }
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to add geofencing zone'));
        });
    }

    function addGeoFence(token, device, fencename, fenceid, llb, urlenter='', urlleave='', urlenterpost=0, urlleavepost=0, sendemail=1) {
        var enterpostTxt = '';
        var leavepostTxt = '';
        if (parseInt(urlenterpost) !== 0) {
            enterpostTxt = '(POST)';
        }
        if (parseInt(urlleavepost) !== 0) {
            leavepostTxt = '(POST)';
        }
        var urlentertxt = '';
        if (urlenter !== '') {
            urlentertxt = t('phonetrack', 'URL to request when entering') + ' ' + enterpostTxt + ' : ' + escapeHTML(urlenter) + '\n';
        }
        var urlleavetxt = '';
        if (urlleave !== '') {
            urlleavetxt = t('phonetrack', 'URL to request when leaving') + ' ' + leavepostTxt + ' : ' + escapeHTML(urlleave) + '\n';
        }

        var sendemailTxt = t('phonetrack', 'no');
        if (parseInt(sendemail) !== 0) {
            sendemailTxt = t('phonetrack', 'yes');
        }
        var li = '<li fenceid="' + fenceid + '" latmin="' + llb.getSouth() + '" latmax="' + llb.getNorth() + '"' +
            'lonmin="' + llb.getWest() + '" lonmax="'+llb.getEast()+'" ' +
            'title="' +
            urlentertxt +
            urlleavetxt +
            t('phonetrack', 'Email notification') + ' : ' + sendemailTxt +
            '">' +
            '<label class="geofencelabel">'+escapeHTML(fencename)+'</label>' +
            '<button class="deletegeofencebutton"><i class="fa fa-trash"></i></button>' +
            '<button class="zoomgeofencebutton"><i class="fa fa-search"></i></button>' +
            '</li>';
        $('.session[token="' + token + '"] .devicelist li[device='+device+'] .geofencesDiv .geofencelist').append(li);
    }

    function deleteGeoFenceDb(token, device, fenceid) {
        var req = {
            token: token,
            device: device,
            fenceid: fenceid
        };
        var url = OC.generateUrl('/apps/phonetrack/deleteGeofence');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done === 1) {
                var li = $('.session[token="' + token + '"] .devicelist li[device=' + device + '] .geofencelist').find('li[fenceid=' + fenceid + ']');
                li.fadeOut('slow', function() {
                    li.remove();
                });
            }
            else {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to delete geofencing zone'));
            }
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to delete geofencing zone'));
        });
    }

    function addProximDb(token, device, sid, sname, dname, highlimit=500, lowlimit=500, urlclose='', urlfar='', urlclosepost=0, urlfarpost=0, sendemail=1) {
        var req = {
            token: token,
            device: device,
            sid: sid,
            dname: dname,
            lowlimit: lowlimit,
            highlimit: highlimit,
            urlclose: urlclose,
            urlfar: urlfar,
            urlclosepost: urlclosepost,
            urlfarpost: urlfarpost,
            sendemail: sendemail
        };
        var url = OC.generateUrl('/apps/phonetrack/addProxim');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done === 1 || response.done === 4) {
                addProxim(token, device, response.proximid, sname, response.targetdeviceid, dname, highlimit, lowlimit, urlclose, urlfar, urlclosepost, urlfarpost, sendemail);
                if (response.done === 4) {
                    OC.Notification.showTemporary(t('phonetrack', 'Warning : User email and server admin email must be set to receive proximity alerts.'));
                }
            }
            else if (response.done === 3 || response.done === 5) {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to add proximity alert'));
                OC.Notification.showTemporary(t('phonetrack', 'Device or session does not exist'));
            }
            else {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to add proximity alert'));
            }
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to add proximity alert'));
        });
    }

    function addProxim(token, device, proximid, sname, did, dname, highlimit=500, lowlimit=500, urlclose='', urlfar='', urlclosepost=0, urlfarpost=0, sendemail=1) {
        var closepostTxt = '';
        var farpostTxt = '';
        if (parseInt(urlclosepost) !== 0) {
            closepostTxt = '(POST)';
        }
        if (parseInt(urlfarpost) !== 0) {
            farpostTxt = '(POST)';
        }
        var sendemailTxt = t('phonetrack', 'no');
        if (parseInt(sendemail) !== 0) {
            sendemailTxt = t('phonetrack', 'yes');
        }
        var li = '<li proximid="' + proximid + '"' +
            'title="' + t('phonetrack', 'URL to request when devices get close') + ' ' + closepostTxt + ' : ' + escapeHTML(urlclose || '') + '\n' +
            t('phonetrack', 'URL to request when devices get far') + ' ' + farpostTxt + ' : ' + escapeHTML(urlfar || '') + '\n' +
            t('phonetrack', 'Email notification') + ' : ' + sendemailTxt + '\n' +
            t('phonetrack', 'Low distance limit : {nbmeters}m', {'nbmeters': lowlimit}) + '\n' +
            t('phonetrack', 'High distance limit : {nbmeters}m', {'nbmeters': highlimit}) +
            '">' +
            '<label class="proximlabel">'+escapeHTML(sname + ' -> ' + dname)+'</label>' +
            '<button class="deleteproximbutton"><i class="fa fa-trash"></i></button>' +
            '</li>';
        $('.session[token="' + token + '"] .devicelist li[device='+device+'] .proximDiv .proximlist').append(li);
    }

    function deleteProximDb(token, device, proximid) {
        var req = {
            token: token,
            device: device,
            proximid: proximid
        };
        var url = OC.generateUrl('/apps/phonetrack/deleteProxim');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done === 1) {
                var li = $('.session[token="' + token + '"] .devicelist li[device=' + device + '] .proximlist').find('li[proximid=' + proximid + ']');
                li.fadeOut('slow', function() {
                    li.remove();
                });
            }
            else {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to delete proximity alert'));
            }
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to delete proximity alert'));
        });
    }

    function addPublicSessionShareDb(token) {
        var req = {
            token: token,
        };
        var url = OC.generateUrl('/apps/phonetrack/addPublicShare');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done === 1) {
                addPublicSessionShare(token, response.sharetoken, response.filters);
            }
            else {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to add public share'));
            }
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to add public share'));
        });
    }

    function addPublicSessionShare(token, sharetoken, filters, name='', lastposonly=0, geofencify=0) {
        var geofencifyChecked = '';
        if (geofencify === '1') {
            geofencifyChecked = 'checked';
        }
        var lastposonlyChecked = '';
        if (lastposonly === '1') {
            lastposonlyChecked = 'checked';
        }
        var publicurl = window.location.origin +
            OC.generateUrl('/apps/phonetrack/publicSessionWatch/' + sharetoken);
        var li = '<li class="filteredshare" filteredtoken="' + escapeHTML(sharetoken) + '" title="' +
            filtersToTxt(filters) + '">' +
            '<input type="text" class="publicFilteredShareUrl" value="' + publicurl + '"/>' +
            '<button class="deletePublicFilteredShare"><i class="fa fa-trash"></i></button><br/>' +
            '<label>' + t('phonetrack', 'Show this device only') + ' : </label>' +
            '<input type="text" role="device" value="' + escapeHTML(name || '') + '"/>' +
            '<br/><label for="fil'+sharetoken+'">' + t('phonetrack', 'Show last positions only') + ' : </label>' +
            '<input id="fil'+sharetoken+'" type="checkbox" role="lastposonly" ' + lastposonlyChecked + '/>' +
            '<br/><label for="geo'+sharetoken+'">' + t('phonetrack', 'Simplify positions to nearest geofencing zone center') + ' : </label>' +
            '<input id="geo'+sharetoken+'" type="checkbox" role="geofencify" ' + geofencifyChecked + '/>' +
            '</li>';
        $('.session[token="' + token + '"]').find('.publicfilteredsharelist').append(li);
    }

    function filtersToTxt(fstr) {
        var fjson = $.parseJSON(fstr);
        var res = '';
        var k;
        for (k in fjson) {
            if (k === 'tsmin' || k === 'tsmax') {
                res = res + k + ' : ' + moment.unix(fjson[k]).format('YYYY-MM-DD HH:mm:ss (Z)') + '\n';
            }
            else {
                res = res + k + ' : ' + fjson[k] + '\n';
            }
        }
        if (res === '') {
            res = t('phonetrack', 'No filters');
        }
        return res;
    }

    function deletePublicSessionShareDb(token, sharetoken) {
        var req = {
            token: token,
            sharetoken: sharetoken
        };
        var url = OC.generateUrl('/apps/phonetrack/deletePublicShare');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done === 1) {
                var li = $('.session[token="' + token + '"]').find('.publicfilteredsharelist li[filteredtoken=' + sharetoken + ']');
                li.fadeOut('slow', function() {
                    li.remove();
                });
            }
            else {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to delete public share'));
            }
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to delete public share'));
        });
    }

    function addUserAutocompletion(input) {
        var req = {
        };
        var url = OC.generateUrl('/apps/phonetrack/getUserList');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            input.autocomplete({
                source: response.users
            });
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to get user list'));
        });
    }

    function updateStatTable() {
        var s, d, id, dist, time, i, li, coordsList, ll, t1, t2;
        var nbsec, years, days, hours, minutes, seconds;
        var table = '';
        for (s in phonetrack.sessionLineLayers) {
            // if session is watched
            if ($('div.session[token='+s+'] .watchbutton i').hasClass('fa-toggle-on')) {
                table = table + '<b>' + getSessionName(s) + ' :</b>';
                table = table + '<table class="stattable"><tr><th>' +
                    t('phonetrack', 'device name') + '</th><th>' +
                    t('phonetrack', 'distance (km)') + '</th><th>' +
                    t('phonetrack', 'duration') + '</th><th>' +
                    t('phonetrack', '#points') + '</th></tr>';
                for (d in phonetrack.sessionLineLayers[s]) {
                    dist = 0;
                    nbsec = 0;
                    coordsList = phonetrack.sessionDisplayedLatlngs[s][d];
                    for (li = 0; li < coordsList.length; li++) {
                        ll = coordsList[li];
                        for (i = 1; i < ll.length; i++) {
                            dist = dist + phonetrack.map.distance(ll[i-1], ll[i]);
                        }

                        if (ll.length > 1) {
                            t1 = moment.unix(phonetrack.sessionPointsEntriesById[s][d][ll[0][2]].timestamp);
                            t2 = moment.unix(phonetrack.sessionPointsEntriesById[s][d][ll[ll.length-1][2]].timestamp);
                            nbsec = nbsec + t2.diff(t1, 'seconds');
                        }
                    }

                    if (nbsec > 0) {
                        years = 0;
                        days = 0;
                        // if more than one year
                        if (nbsec >= 31536000) {
                            years = Math.floor(nbsec / 31536000);
                        }
                        // if more than one day
                        if (nbsec >= 86400) {
                            days = Math.floor((nbsec % 31536000) / 86400);
                        }
                        hours = Math.floor((nbsec % 86400) / 3600);
                        minutes = Math.floor((nbsec % 3600) / 60);
                        seconds = Math.floor(nbsec % 60);
                    }
                    else {
                        years = days = hours = minutes = seconds = 0;
                    }

                    table = table + '<tr><td class="color' + s +
                        d + '">' + getDeviceName(s, d) + '</td>';
                    table = table + '<td>'+formatDistance(dist)+'</td>';
                    table = table + '<td>';
                    if (years > 0) {
                        table = table + years + ' ' + t('phonetrack', 'years') + ' ';
                    }
                    if (days > 0) {
                        table = table + days + ' ' + t('phonetrack', 'days') + ' ';
                    }
                    table = table + pad(hours) + ':' + pad(minutes) + ':' + pad(seconds) + '</td>';
                    table = table + '<td>' + phonetrack.sessionPointsLayers[s][d].getLayers().length + '</td></tr>';
                }
                table = table + '</table>';
            }
        }
        $('#statdiv').html(table);
    }

    function formatDistance(d) {
        return (d / 1000).toFixed(2);
    }

    function clickUrlHelp(logger, url, sessionName) {
        var loggerName, content;
        if (logger === 'osmand') {
            loggerName = 'OsmAnd';
            content = t('phonetrack', 'In OsmAnd, go to \'Plugins\' in the main menu, then activate \'Trip recording\' plugin and go to its settings.') +
            ' ' + t('phonetrack', 'Copy the URL below into the \'Online tracking web address\' field.');
        }
        else if (logger === 'gpslogger') {
            loggerName = 'GpsLogger';
            content = t('phonetrack', 'In GpsLogger, go to \'Logging details\' in the sidebar menu, then activate \'Log to custom URL\'.') +
                ' ' + t('phonetrack', 'Copy the URL below into the \'URL\' field.');
        }
        else if (logger === 'owntracks') {
            loggerName = 'Owntracks';
            content = 'To be written by a Owntracks user !';
        }
        else if (logger === 'ulogger') {
            loggerName = 'Ulogger';
            content = t('phonetrack', 'In Ulogger, go to settings menu and copy the URL below into the \'Server URL\' field.') +
                ' ' + t('phonetrack', 'Set \'User name\' and \'Password\' mandatory fields to any value as they will be ignored by PhoneTrack.') +
                ' ' + t('phonetrack', 'Activate \'Live synchronization\'.');
        }
        else if (logger === 'traccar') {
            loggerName = 'Traccar';
            content = t('phonetrack', 'In Traccar client, copy the URL below into the \'server URL\' field.');
        }
        else if (logger === 'get') {
            loggerName = 'GET logger';
            content = t('phonetrack', 'You can log with any other client with a simple HTTP request.');
            content = content + ' ' + t('phonetrack', 'Make sure the logging system sets values for at least \'timestamp\', \'lat\' and \'lon\' GET parameters.');
        }
        var title = t('phonetrack',
            'Configure {loggingApp} for logging to session \'{sessionName}\'',
            {sessionName: sessionName, loggingApp: loggerName}
        );

        $('#trackurlinput').val(url);
        $('#trackurllabel').text(content);

        $('#trackurldialog').dialog({
            title: title,
            closeText: 'show',
            width: 500,
            height: 250
        });
        $('#trackurlinput').select();
    }

    function updateProximSessionsSelect(tog) {
        var prSel = tog.parent().parent().find('.proximDiv select.proximsession');
        prSel.html('');
        var s, sname;
        for (s in phonetrack.deviceNames) {
            sname = getSessionName(s);
            prSel.append('<option value="' + s + '" name="' + sname + '">' + sname + '</option>');
        }
    }

    function zoomongeofence(par) {
        var latmin = par.attr('latmin');
        var latmax = par.attr('latmax');
        var lonmin = par.attr('lonmin');
        var lonmax = par.attr('lonmax');
        var llb = L.latLngBounds(L.latLng(latmin, lonmin), L.latLng(latmax, lonmax));
        phonetrack.map.fitBounds(llb, {
            //padding: [10, 10],
            paddingTopLeft: [parseInt($('#sidebar').css('width')) + 30, 50],
            paddingBottomRight: [50, 50]
        });

        var bounds = [[latmin, lonmin], [latmax, lonmax]];
        var rec = L.rectangle(bounds, {color: "#ff7800", weight: 1}).addTo(phonetrack.map);

        setTimeout(function() {phonetrack.map.removeLayer(rec);}, 5000);
    };

    //////////////// MAIN /////////////////////

    $(document).ready(function() {
        phonetrack.pageIsPublicWebLog = (document.URL.indexOf('/publicWebLog') !== -1);
        phonetrack.pageIsPublicSessionWatch = (document.URL.indexOf('/publicSessionWatch') !== -1);
        if ( !pageIsPublic() ) {
            restoreOptions();
        }
        else {
            main();
        }
    });

    function main() {
        phonetrack.username = $('p#username').html();
        phonetrack.token = $('p#token').html();
        load_map();

        $('body').on('change', '#autozoomcheck', function() {
            if (!pageIsPublic()) {
                saveOptions();
            }
        });
        $('body').on('change', '#arrowcheck', function() {
            if (!pageIsPublic()) {
                saveOptions();
            }
        });

        // get key events
        document.onkeydown = checkKey;

        // Custom tile server management
        $('body').on('click', '#tileserverlist button', function(e) {
            deleteTileServer($(this).parent(), 'tile');
        });
        $('#addtileserver').click(function() {
            addTileServer('tile');
        });
        $('body').on('click', '#overlayserverlist button', function(e) {
            deleteTileServer($(this).parent(), 'overlay');
        });
        $('#addoverlayserver').click(function() {
            addTileServer('overlay');
        });

        $('body').on('click', '#tilewmsserverlist button', function(e) {
            deleteTileServer($(this).parent(), 'tilewms');
        });
        $('#addtileserverwms').click(function() {
            addTileServer('tilewms');
        });
        $('body').on('click', '#overlaywmsserverlist button', function(e) {
            deleteTileServer($(this).parent(), 'overlaywms');
        });
        $('#addoverlayserverwms').click(function() {
            addTileServer('overlaywms');
        });

        $('body').on('click','h3.customtiletitle', function(e) {
            var forAttr = $(this).attr('for');
            if ($('#'+forAttr).is(':visible')) {
                $('#'+forAttr).slideUp();
                $(this).find('i').removeClass('fa-angle-double-up').addClass('fa-angle-double-down');
            }
            else{
                $('#'+forAttr).slideDown();
                $(this).find('i').removeClass('fa-angle-double-down').addClass('fa-angle-double-up');
            }
        });

        // in public link and public folder link :
        // hide compare button and custom tiles server management
        if (pageIsPublic()) {
            $('div#tileserverlist').hide();
            $('div#tileserveradd').hide();
        }

        // show/hide options
        $('body').on('click','h3#optiontitle', function(e) {
            if ($('#optionscontent').is(':visible')) {
                $('#optionscontent').slideUp();
                $('#optiontoggle').html('<i class="fa fa-angle-double-down"></i>');
                $('#optiontoggle').animate({'left': 0}, 'slow');
            }
            else{
                $('#optionscontent').slideDown();
                $('#optiontoggle').html('<i class="fa fa-angle-double-up"></i>');
                var offset = parseInt($('#optiontitle').css('width')) -
                    parseInt($('#optiontoggle').css('width')) -
                    parseInt($('#optiontitletext').css('width')) - 5;
                $('#optiontoggle').animate({'left': offset}, 'slow');
            }
        });

        $('#showcreatesession').click(function() {
            var newsessiondiv = $('#newsessiondiv');
            if (newsessiondiv.is(':visible')) {
                newsessiondiv.slideUp('slow');
            }
            else {
                newsessiondiv.slideDown('slow');
            }
        });

        $('#newsession').click(function() {
            createSession();
            $('#newsessiondiv').slideUp('slow');
        });

        $('body').on('click','.removeSession', function(e) {
            var token = $(this).parent().parent().attr('token');
            var sessionname = getSessionName(token);
            OC.dialogs.confirm(
                t('phonetrack',
                    'Are you sure you want to delete the session {session} ?',
                    {session: sessionname}
                ),
                t('phonetrack','Confirm session deletion'),
                function (result) {
                    if (result) {
                        deleteSession(token);
                    }
                },
                true
            );
        });

        $('body').on('click','#refreshButton', function(e) {
            if (phonetrack.currentTimer !== null) {
                phonetrack.currentTimer.pause();
                phonetrack.currentTimer = null;
            }
            refresh();
        });

        $('body').on('click','.watchbutton', function(e) {
            if (!pageIsPublic()) {
                var icon = $(this).find('i');
                if (icon.hasClass('fa-toggle-on')) {
                    icon.addClass('fa-toggle-off').removeClass('fa-toggle-on');
                    $(this).parent().parent().find('.devicelist').slideUp('slow');
                    $(this).parent().parent().find('.sharediv').slideUp('slow');
                    $(this).parent().parent().find('.moreUrls').slideUp('slow');
                    //$(this).parent().parent().find('.toggleDetail').addClass('off').removeClass('on');
                    //$(this).parent().parent().find('.toggleLineDevice').addClass('on').removeClass('off');
                }
                else {
                    icon.addClass('fa-toggle-on').removeClass('fa-toggle-off');
                    $(this).parent().parent().find('.devicelist').slideDown('slow');
                }
                // we stop the refresh loop,
                // we save options and then we refresh
                if (phonetrack.currentTimer !== null) {
                    phonetrack.currentTimer.pause();
                    phonetrack.currentTimer = null;
                }
                refresh();
                saveOptions();
            }
        });

        $('#colorthemeselect').change(function() {
            if (!pageIsPublic()) {
                saveOptions();
            }
        });

        $('#autoexportpath').change(function() {
            if (!pageIsPublic()) {
                saveOptions();
            }
        });

        $('#autoexportpath').focus(function() {
            OC.dialogs.filepicker(
                t('phonetrack', 'Choose auto export target path'),
                function(targetPath) {
                    $('#autoexportpath').val(targetPath);
                    $('#autoexportpath').change();
                },
                false, "httpd/unix-directory", true
            );
        });

        $('#linewidth').change(function() {
            if (!pageIsPublic()) {
                saveOptions();
            }
            var s, d;
            var w = parseInt($(this).val());
            for (s in phonetrack.sessionLineLayers) {
                for (d in phonetrack.sessionLineLayers[s]) {
                    phonetrack.sessionLineLayers[s][d].setStyle({
                        weight: w
                    });
                    // permanent change of arrows
                    phonetrack.sessionLineLayers[s][d].eachLayer(function (l) {
                        if (typeof l.setPatterns === 'function') {
                            l.setPatterns([{
                                offset: 30,
                                repeat: 100,
                                symbol: L.Symbol.arrowHead({
                                    pixelSize: 15 + w,
                                    polygon: false,
                                    pathOptions: {
                                        stroke: true,
                                        className: 'poly' + s + d,
                                        opacity: 1,
                                        weight: parseInt(w * 0.6)
                                    }
                                })
                            }]);
                        }
                    });
                }
            }
        });

        $('#autozoom').click(function() {
            if (!pageIsPublic()) {
                saveOptions();
            }
            if ($(this).is(':checked')) {
                phonetrack.zoomButton.state('zoom');
            }
            else {
                phonetrack.zoomButton.state('nozoom');
            }
        });

        $('#showtime').click(function() {
            changeTooltipStyle();
            if (!pageIsPublic()) {
                saveOptions();
            }
            if ($(this).is(':checked')) {
                phonetrack.timeButton.state('showtime');
            }
            else {
                phonetrack.timeButton.state('noshowtime');
            }
        });

        $('#acccirclecheck').click(function() {
            if (!pageIsPublic()) {
                saveOptions();
            }
        });

        $('#exportoneperdev').click(function() {
            if (!pageIsPublic()) {
                saveOptions();
            }
        });

        $('#tooltipshowaccuracy, #tooltipshowsatellites, #tooltipshowbattery, #tooltipshowelevation, #tooltipshowuseragent, #tooltipshowspeed, #tooltipshowbearing').click(function() {
            if (!pageIsPublic()) {
                saveOptions();
            }
        });

        $('#linearrow, #linegradient, #cutdistance, #cuttime').change(function() {
            if (!pageIsPublic()) {
                saveOptions();
            }
            changeApplyFilter();
        });

        $('#nbpointsload').change(function() {
            if (!pageIsPublic()) {
                saveOptions();
            }
        });

        $('#dragcheck').click(function() {
            if (!pageIsPublic()) {
                saveOptions();
            }
            if (!pageIsPublic()) {
                var dragcheck = $(this).is(':checked');
                var id, s, d;
                $('.toggleDetail.on').each(function() {
                    if (!isSessionShared(s)) {
                        s = $(this).attr('token');
                        d = $(this).attr('device');
                        if (dragcheck) {
                            phonetrack.sessionPointsLayers[s][d].eachLayer(function(l) {
                                l.dragging.enable();
                            });
                            phonetrack.sessionMarkerLayers[s][d].dragging.enable();
                        }
                        else {
                            phonetrack.sessionPointsLayers[s][d].eachLayer(function(l) {
                                l.dragging.disable();
                            });
                            phonetrack.sessionMarkerLayers[s][d].dragging.disable();
                        }
                    }
                });
            }
        });

        $('#viewmove').click(function() {
            showHideSelectedSessions();
            if (!pageIsPublic()) {
                saveOptions();
            }
            if ($(this).is(':checked')) {
                phonetrack.moveButton.state('move');
            }
            else {
                phonetrack.moveButton.state('nomove');
            }
        });

        $('body').on('change', '#updateinterval', function() {
            var val = parseInt($(this).val());
            if (val !== 0 && !isNaN(val) && phonetrack.currentTimer === null) {
                refresh();
            }
            if (!pageIsPublic()) {
                saveOptions();
            }
        });

        $('body').on('change', '#filterPointsTable input[type=number], #filterPointsTable input[type=date]', function() {
            changeApplyFilter();
            if (!pageIsPublic()) {
                saveOptions($('#applyfilters').is(':checked'));
            }
        });

        $('body').on('click', '.export', function() {
            var name = $(this).parent().parent().parent().find('.sessionBar .sessionName').text();
            var token = $(this).parent().parent().parent().attr('token');
            var filename = $(this).parent().find('input[role=exportname]').val().replace('.gpx', '') + '.gpx';
            OC.dialogs.filepicker(
                t('phonetrack', 'Select storage location for \'{fname}\'', {fname: filename}),
                function(targetPath) {
                    saveAction(name, token, targetPath, filename);
                },
                false, 'httpd/unix-directory', true
            );
        });

        $('body').on('click', 'button.zoomsession', function(e) {
            var token = $(this).parent().parent().attr('token');
            zoomOnDisplayedMarkers(token);
        });

        $('#logme').click(function (e) {
            if ($('#logme').is(':checked')) {
                phonetrack.locateControl.start();
            }
            else {
                phonetrack.locateControl.stop();
            }
        });

        $('body').on('click', 'ul.devicelist li .zoomdevicebutton, ul.devicelist li .deviceLabel', function(e) {
            zoomOnDevice($(this), t);
        });

        $('body').on('click', 'ul.devicelist li .toggleDetail', function(e) {
            toggleDetailDevice($(this));
            if (!pageIsPublic()) {
                saveOptions(true);
            }
        });

        $('body').on('click', 'ul.devicelist li .toggleLineDevice', function(e) {
            toggleLineDevice($(this));
            if (!pageIsPublic()) {
                saveOptions(true);
            }
        });

        $('body').on('click', 'ul.devicelist li .toggleAutoZoomDevice', function(e) {
            toggleAutoZoomDevice($(this));
            if (!pageIsPublic()) {
                saveOptions();
            }
        });

        $('body').on('click','.reservNameButton', function(e) {
            var nameDiv = $(this).parent().parent().find('.namereservdiv');
            var urlDiv = $(this).parent().parent().find('.moreUrls');
            var sharediv = $(this).parent().parent().find('.sharediv')
            if (nameDiv.is(':visible')) {
                nameDiv.slideUp('slow');
            }
            else{
                nameDiv.slideDown('slow');
                urlDiv.slideUp('slow');
                sharediv.slideUp('slow');
            }
        });

        $('body').on('click','.moreUrlsButton', function(e) {
            var urlDiv = $(this).parent().parent().find('.moreUrls');
            var nameDiv = $(this).parent().parent().find('.namereservdiv');
            var sharediv = $(this).parent().parent().find('.sharediv')
            if (urlDiv.is(':visible')) {
                urlDiv.slideUp('slow');
            }
            else{
                urlDiv.slideDown('slow').css('display', 'grid');
                nameDiv.slideUp('slow');
                sharediv.slideUp('slow');
            }
        });

        $('body').on('click','.sharesession', function(e) {
            var sharediv = $(this).parent().parent().find('.sharediv')
            var nameDiv = $(this).parent().parent().find('.namereservdiv');
            var moreurldiv = $(this).parent().parent().find('.moreUrls')
            if (sharediv.is(':visible')) {
                sharediv.slideUp('slow');
            }
            else {
                sharediv.slideDown('slow');
                nameDiv.slideUp('slow');
                moreurldiv.slideUp('slow');
            }
        });

        $('body').on('click','.toggleGeofences', function(e) {
            var geoDiv = $(this).parent().parent().find('.geofencesDiv');
            if (geoDiv.is(':visible')) {
                geoDiv.slideUp('slow');
            }
            else{
                $('.geofencesDiv:visible, .proximDiv:visible').each(function() {
                    $(this).slideUp('slow');
                });
                geoDiv.slideDown('slow');
            }
        });

        $('body').on('click','.toggleProxim', function(e) {
            var prDiv = $(this).parent().parent().find('.proximDiv');
            if (prDiv.is(':visible')) {
                prDiv.slideUp('slow');
            }
            else{
                $('.geofencesDiv:visible, .proximDiv:visible').each(function() {
                    $(this).slideUp('slow');
                });
                prDiv.slideDown('slow');
                updateProximSessionsSelect($(this));
            }
        });

        $('body').on('click','.reaffectDevice', function(e) {
            var token = $(this).attr('token');
            var deviceid = $(this).attr('device');
            var reaffectSelect = '';
            $('.session').each(function() {
                if ($(this).attr('token') !== token
                    && !isSessionShared($(this).attr('token'))
                ) {
                    reaffectSelect += '<option value="' + $(this).attr('token') + '">' + $(this).find('.sessionName').text() + '</option>';
                }
            });
            $(this).parent().parent().find('.reaffectDeviceSelect').html(reaffectSelect);

            var dcontent;
            dcontent = $(e.target).parent().parent().find('.reaffectDeviceDiv');
            hideAllDropDowns();
            var isVisible = dcontent.hasClass('show');
            if (!isVisible) {
                dcontent.toggleClass('show');
            }
            $(this).parent().parent().find('.reaffectDeviceSelect').select();
        });

        $('body').on('click','.reaffectDeviceOk', function(e) {
            var token = $(this).parent().parent().parent().attr('token');
            var deviceid = $(this).parent().parent().parent().attr('device');
            var newSessionId = $(this).parent().find('.reaffectDeviceSelect').val();

            $(this).parent().parent().find('.reaffectDeviceDiv').removeClass('show');
            reaffectDeviceSession(token, deviceid, newSessionId);
        });

        $('body').on('click','.geoLinkDevice', function(e) {
            var token = $(this).attr('token');
            var deviceid = $(this).attr('device');
            var ll = phonetrack.sessionLatlngs[token][deviceid];
            var p = ll[ll.length-1];
            var lat = p[0];
            var lon = p[1];
            window.open(
                'geo:' + lat + ',' + lon
            );
        });

        $('body').on('click','.routingGraphDevice', function(e) {
            var token = $(this).attr('token');
            var deviceid = $(this).attr('device');
            var ll = phonetrack.sessionLatlngs[token][deviceid];
            var p = ll[ll.length-1];
            var lat = p[0];
            var lon = p[1];
            window.open(
                'https://graphhopper.com/maps/?point=::where_are_you::&' +
                'point='+lat+'%2C'+lon+'&locale=fr&vehicle=car&' +
                'weighting=fastest&elevation=true&use_miles=false&layer=Omniscale',
                '_blank'
            );
        });

        $('body').on('click','.routingOsrmDevice', function(e) {
            var token = $(this).attr('token');
            var deviceid = $(this).attr('device');
            var ll = phonetrack.sessionLatlngs[token][deviceid];
            var p = ll[ll.length-1];
            var lat = p[0];
            var lon = p[1];
            window.open(
                'https://map.project-osrm.org/?z=12&center='+lat+'%2C'+lon+'&loc=0.000000%2C0.000000&loc='+lat+'%2C'+lon+'&hl=en&alt=0',
                '_blank'
            );
        });

        $('body').on('click','.routingOrsDevice', function(e) {
            var token = $(this).attr('token');
            var deviceid = $(this).attr('device');
            var ll = phonetrack.sessionLatlngs[token][deviceid];
            var p = ll[ll.length-1];
            var lat = p[0];
            var lon = p[1];
            window.open(
                'https://maps.openrouteservice.org/directions?n1='+lat+'&n2='+lon+'&n3=12&a=null,null,'+lat+','+lon+'&b=0&c=0&k1=en-US&k2=km',
                '_blank'
            );
        });

        $('body').on('click','.renameDevice', function(e) {
            var token = $(this).attr('token');
            var deviceid = $(this).attr('device');
            var devicename = getDeviceName(token, deviceid);
            $(this).parent().parent().find('.deviceLabel').hide();
            $(this).parent().parent().find('.renameDeviceInput').show();
            $(this).parent().parent().find('.renameDeviceInput').val(devicename);
            $(this).parent().parent().find('.renameDeviceInput').select();
        });

        $('body').on('click','.aliasDevice', function(e) {
            var token = $(this).attr('token');
            var deviceid = $(this).attr('device');
            var devicealias = getDeviceAlias(token, deviceid);
            $(this).parent().parent().find('.deviceLabel').hide();
            $(this).parent().parent().find('.aliasDeviceInput').show();
            $(this).parent().parent().find('.aliasDeviceInput').val(devicealias);
            $(this).parent().parent().find('.aliasDeviceInput').select();
        });

        $('body').on('keypress','.renameDeviceInput', function(e) {
            if (e.key === 'Escape') {
                $(this).parent().parent().find('.deviceLabel').show();
                $(this).parent().parent().find('.renameDeviceInput').hide();
            }
            else if (e.key === 'Enter') {
                var token = $(this).parent().parent().attr('token');
                var deviceid = $(this).parent().parent().attr('device');
                var oldName = getDeviceName(token, deviceid);
                var newName = $(this).val();
                renameDevice(token, deviceid, oldName, newName);
                $(this).parent().parent().find('.deviceLabel').show();
                $(this).parent().parent().find('.renameDeviceInput').hide();
            }
        });

        $('body').on('keypress','.aliasDeviceInput', function(e) {
            if (e.key === 'Escape') {
                $(this).parent().parent().find('.deviceLabel').show();
                $(this).parent().parent().find('.aliasDeviceInput').hide();
            }
            else if (e.key === 'Enter') {
                var token = $(this).parent().parent().attr('token');
                var deviceid = $(this).parent().parent().attr('device');
                var newalias = $(this).val();
                setDeviceAlias(token, deviceid, newalias);
                $(this).parent().parent().find('.deviceLabel').show();
                $(this).parent().parent().find('.aliasDeviceInput').hide();
            }
        });

        $('body').on('click','.deleteDevice', function(e) {
            var token = $(this).attr('token');
            var deviceid = $(this).attr('device');
            var devicename = getDeviceName(token, deviceid);
            OC.dialogs.confirm(
                t('phonetrack',
                    'Are you sure you want to delete the device {device} ?',
                    {device: devicename}
                ),
                t('phonetrack','Confirm device deletion'),
                function (result) {
                    if (result) {
                        deleteDevice(token, deviceid);
                    }
                },
                true
            );
        });

        $('body').on('click','.editsessionbutton', function(e) {
            var token = $(this).attr('token');
            $(this).parent().parent().find('.sessionName').hide();
            $(this).parent().parent().find('.renameSessionInput').show();
            $(this).parent().parent().find('.renameSessionInput').val(
                $(this).parent().parent().find('.sessionName').text()
            );
            $(this).parent().parent().find('.renameSessionInput').select();
        });

        $('body').on('keypress','.renameSessionInput', function(e) {
            if (e.key === 'Escape') {
                $(this).parent().find('.sessionName').show();
                $(this).parent().find('.renameSessionInput').hide();
            }
            else if (e.key === 'Enter') {
                var token = $(this).parent().parent().attr('token');
                var oldname = $(this).parent().find('.sessionName').text();
                var newname = $(this).val();
                renameSession(token, oldname, newname);
                $(this).parent().find('.sessionName').show();
                $(this).parent().find('.renameSessionInput').hide();
            }
        });

        $('body').on('click','.publicsessionbutton', function(e) {
            var buttext = $(this).find('b');
            var icon = $(this).find('i');
            var pub = icon.hasClass('fa-toggle-off');
            var token = $(this).parent().parent().attr('token');
            var isPublic = 0;
            if (pub) {
                isPublic = 1;
            }
            var req = {
                token: token,
                public: isPublic
            };
            var url = OC.generateUrl('/apps/phonetrack/setSessionPublic');
            $.ajax({
                type: 'POST',
                url: url,
                data: req,
                async: true
            }).done(function (response) {
                if (response.done === 1) {
                }
                else if (response.done === 2) {
                    OC.Notification.showTemporary(t('phonetrack', 'Failed to toggle session public status, session does not exist'));
                }
            }).fail(function() {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to toggle session public status'));
                OC.Notification.showTemporary(t('phonetrack', 'Reload this page'));
            });
            if (pub) {
                icon.addClass('fa-toggle-on').removeClass('fa-toggle-off');
                buttext.text(t('phonetrack', 'Make session private'));
                $('.session[token="' + token + '"]').find('.publicWatchUrlDiv').slideDown();
            }
            else {
                icon.addClass('fa-toggle-off').removeClass('fa-toggle-on');
                buttext.text(t('phonetrack', 'Make session public'));
                $('.session[token="' + token + '"]').find('.publicWatchUrlDiv').slideUp();
            }
        });

        $('body').on('change','select[role=autoexport]', function(e) {
            var val = $(this).val();
            var token = $(this).parent().parent().parent().attr('token');
            var req = {
                token: token,
                value: val
            };
            var url = OC.generateUrl('/apps/phonetrack/setSessionAutoExport');
            $.ajax({
                type: 'POST',
                url: url,
                data: req,
                async: true
            }).done(function (response) {
                if (response.done === 1) {
                }
                else if (response.done === 2) {
                    OC.Notification.showTemporary(
                        t('phonetrack', 'Failed to set session auto export value') +
                        '. ' + t('phonetrack', 'session does not exist')
                    );
                }
            }).fail(function() {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to set session auto export value'));
            });
        });

        $('body').on('change','select[role=autopurge]', function(e) {
            var val = $(this).val();
            var token = $(this).parent().parent().parent().attr('token');
            var req = {
                token: token,
                value: val
            };
            var url = OC.generateUrl('/apps/phonetrack/setSessionAutoPurge');
            $.ajax({
                type: 'POST',
                url: url,
                data: req,
                async: true
            }).done(function (response) {
                if (response.done === 1) {
                }
                else if (response.done === 2) {
                    OC.Notification.showTemporary(
                        t('phonetrack', 'Failed to set session auto purge value') +
                        '. ' + t('phonetrack', 'session does not exist')
                    );
                }
            }).fail(function() {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to set session auto purge value'));
            });
        });

        $('body').on('click','.canceleditpoint', function(e) {
            phonetrack.map.closePopup();
        });

        $('body').on('click','.movepoint', function(e) {
            var tab = $(this).parent().find('table');
            var token = tab.attr('token');
            var deviceid = tab.attr('deviceid');
            var pointid = tab.attr('pid');
            phonetrack.movepointSession = token;
            phonetrack.movepointDevice = deviceid;
            phonetrack.movepointId = pointid;
            enterMovePointMode();
            phonetrack.map.closePopup();
        });

        $('body').on('click','.valideditpoint', function(e) {
            var tab = $(this).parent().find('table');
            var token = tab.attr('token');
            var deviceid = parseInt(tab.attr('deviceid'));
            var pointid = parseInt(tab.attr('pid'));
            // unchanged latlng
            var lat = phonetrack.sessionPointsEntriesById[token][deviceid][pointid].lat;
            var lon = phonetrack.sessionPointsEntriesById[token][deviceid][pointid].lon;
            var alt = parseFloat(tab.find('input[role=altitude]').val());
            if (isNaN(alt)) { alt = null; }
            var acc = parseFloat(tab.find('input[role=precision]').val());
            if (isNaN(acc) || acc < 0) { acc = null; }
            var sat = parseInt(tab.find('input[role=satellites]').val());
            if (isNaN(sat) || sat < 0) { sat = null; }
            var speed = parseFloat(tab.find('input[role=speed]').val());
            if (!isNaN(speed)) {
                speed = speed / 3.6;
                if (speed < 0) {
                    speed = null;
                }
            }
            var bearing = parseFloat(tab.find('input[role=bearing]').val());
            if (isNaN(bearing) || bearing < 0 || bearing > 360) { bearing = null; }
            var bat = parseFloat(tab.find('input[role=battery]').val());
            if (isNaN(bat) || bat < 0 || bat > 100) { bat = null; }
            var useragent = tab.find('input[role=useragent]').val();
            var datestr = tab.find('input[role=date]').val();
            var hourstr = parseInt(tab.find('input[role=hour]').val());
            var minstr = parseInt(tab.find('input[role=minute]').val());
            var secstr = parseInt(tab.find('input[role=second]').val());
            var completeDateStr = datestr + ' ' + pad(hourstr) + ':' + pad(minstr) + ':' + pad(secstr);
            var mom = moment(completeDateStr);
            var timestamp = mom.unix();
            editPointDB(token, deviceid, pointid, lat, lon, alt, acc, sat, bat, timestamp, useragent, speed, bearing);
        });

        $('body').on('click', '.deletepoint', function(e) {
            var tab = $(this).parent().find('table');
            var s = tab.attr('token');
            var d = tab.attr('deviceid');
            var pid = parseInt(tab.attr('pid'));
            deletePointsDB(s, d, [pid]);
        });

        $('body').on('click', '.geonortheastbutton , .geosouthwestbutton', function(e) {
            enterNSEWMode($(this));
        });

        $('#validaddpoint').click(function(e) {
            enterAddPointMode();
        });

        $('#canceladdpoint').click(function(e) {
            leaveAddPointMode();
        });

        $('#validdeletepoint').click(function(e) {
            deleteMultiplePoints();
        });

        $('#validdeletevisiblepoint').click(function(e) {
            var mapbounds = phonetrack.map.getBounds();
            deleteMultiplePoints(mapbounds);
        });

        $('#importsession').click(function(e) {
            OC.dialogs.filepicker(
                t('phonetrack', 'Import gpx session file'),
                function(targetPath) {
                    importSession(targetPath);
                },
                false,
                null,
                true
            );
        });

        $('#applyfilters').click(function(e) {
            changeApplyFilter();
            if (!pageIsPublic()) {
                saveOptions(true);
            }
        });
        changeApplyFilter();

        window.onclick = function(event) {
            if (!event.target.matches('.dropdownbutton') && !event.target.matches('.dropdownbutton i')
                && !event.target.matches('.reaffectDevice') && !event.target.matches('.reaffectDevice i')
                && !event.target.matches('.reaffectDeviceDiv select') && !event.target.matches('.reaffectDeviceDiv')
                && !event.target.matches('.reaffectDeviceDiv select *')
                && !event.target.matches('input[role=exportname]')
                && !event.target.matches('select[role=autoexport]')
                && !event.target.matches('select[role=autoexport] option')
                && !event.target.matches('select[role=autopurge]')
                && !event.target.matches('select[role=autopurge] option')
                && !event.target.matches('.dropdowndevicebutton') && !event.target.matches('.dropdowndevicebutton i')) {
                hideAllDropDowns();
            }
        }

        $('body').on('click','.dropdownbutton', function(e) {
            var dcontent;
            if (e.target.nodeName === 'BUTTON') {
                dcontent = $(e.target).parent().parent().find('>.dropdown-content');
            }
            else {
                dcontent = $(e.target).parent().parent().parent().find('>.dropdown-content');
            }
            var isVisible = dcontent.hasClass('show');
            hideAllDropDowns();
            if (!isVisible) {
                dcontent.toggleClass('show');
            }
        });

        $('body').on('click','.dropdowndevicebutton', function(e) {
            var dcontent;
            if (e.target.nodeName === 'BUTTON') {
                dcontent = $(e.target).parent().find('.dropdown-content');
            }
            else {
                dcontent = $(e.target).parent().parent().find('.dropdown-content');
            }
            var isVisible = dcontent.hasClass('show');
            hideAllDropDowns();
            if (!isVisible) {
                dcontent.toggleClass('show');
            }
        });

        $('body').on('focus','.addusershare', function(e) {
            addUserAutocompletion($(this));
        });

        $('body').on('keypress','.addusershare', function(e) {
            if (e.key === 'Enter') {
                var token = $(this).parent().parent().parent().attr('token');
                var username = $(this).val();
                addUserShareDb(token, username);
            }
        });

        $('body').on('click','.deleteusershare', function(e) {
            var token = $(this).parent().parent().parent().parent().parent().attr('token');
            var username = $(this).parent().attr('username');
            deleteUserShareDb(token, username);
        });

        $('body').on('click','.addpublicfilteredshareButton', function(e) {
            var token = $(this).parent().parent().parent().attr('token');
            addPublicSessionShareDb(token);
        });

        $('body').on('click','.deletePublicFilteredShare', function(e) {
            var token = $(this).parent().parent().parent().parent().parent().attr('token');
            var sharetoken = $(this).parent().attr('filteredtoken');
            deletePublicSessionShareDb(token, sharetoken);
        });

        $('body').on('click','.addgeofencebutton', function(e) {
            var token = $(this).parent().parent().parent().attr('token');
            var device = $(this).parent().parent().parent().attr('device');
            var fencename = $(this).parent().find('.geofencename').val();
            var urlenter = $(this).parent().find('.urlenter').val();
            var urlleave = $(this).parent().find('.urlleave').val();
            var urlenterpost = $(this).parent().find('.urlenterpost').is(':checked') ? 1 : 0;
            var urlleavepost = $(this).parent().find('.urlleavepost').is(':checked') ? 1 : 0;
            var sendemail = $(this).parent().find('.sendemail').is(':checked') ? 1 : 0;
            var north = $(this).parent().find('.fencenorth').val();
            var south = $(this).parent().find('.fencesouth').val();
            var east = $(this).parent().find('.fenceeast').val();
            var west = $(this).parent().find('.fencewest').val();
            var zonebounds;
            if (north && west && east && south) {
                zonebounds = L.latLngBounds(L.latLng(north, west), L.latLng(south, east));
            }
            else {
                zonebounds = phonetrack.map.getBounds();
            }
            addGeoFenceDb(token, device, fencename, zonebounds, urlenter, urlleave, urlenterpost, urlleavepost, sendemail);
        });

        $('body').on('click','.deletegeofencebutton', function(e) {
            var token = $(this).parent().parent().parent().parent().attr('token');
            var device = $(this).parent().parent().parent().parent().attr('device');
            var fenceid = $(this).parent().attr('fenceid');
            deleteGeoFenceDb(token, device, fenceid);
        });

        $('body').on('click','.zoomgeofencebutton', function(e) {
            zoomongeofence($(this).parent());
        });

        $('body').on('click','.geofencelabel', function(e) {
            zoomongeofence($(this).parent());
        });

        $('body').on('click','.addproximbutton', function(e) {
            var s = $(this).parent().parent().parent().attr('token');
            var d = $(this).parent().parent().parent().attr('device');
            var sessiontoken = $(this).parent().find('.proximsession').val();
            var sessionname = $(this).parent().find('.proximsession option:selected').attr('name');
            var devicename = $(this).parent().find('.devicename').val();
            var highlimit = $(this).parent().find('.highlimit').val();
            var lowlimit = $(this).parent().find('.lowlimit').val();
            var urlclose = $(this).parent().find('.urlclose').val();
            var urlfar = $(this).parent().find('.urlfar').val();
            var urlclosepost = $(this).parent().find('.urlclosepost').is(':checked') ? 1 : 0;
            var urlfarpost = $(this).parent().find('.urlfarpost').is(':checked') ? 1 : 0;
            var sendemail = $(this).parent().find('.sendemail').is(':checked') ? 1 : 0;
            addProximDb(s, d, sessiontoken, sessionname, devicename, highlimit, lowlimit, urlclose, urlfar, urlclosepost, urlfarpost, sendemail);
        });

        $('body').on('click','.deleteproximbutton', function(e) {
            var token = $(this).parent().parent().parent().parent().attr('token');
            var device = $(this).parent().parent().parent().parent().attr('device');
            var proximid = $(this).parent().attr('proximid');
            deleteProximDb(token, device, proximid);
        });

        $('body').on('keypress','.addnamereserv', function(e) {
            if (e.key === 'Enter') {
                var token = $(this).parent().parent().attr('token');
                var devicename = $(this).val();
                addNameReservationDb(token, devicename);
            }
        });

        $('body').on('click','.deletereservedname', function(e) {
            var token = $(this).parent().parent().parent().parent().attr('token');
            var devicename = $(this).parent().attr('name');
            deleteNameReservationDb(token, devicename);
        });

        $('button[role=datemintoday]').click(function() {
            var mom = moment();
            $('input[role=datemin]').val(mom.format('YYYY-MM-DD'));
            changeApplyFilter();
            if (!pageIsPublic()) {
                saveOptions($('#applyfilters').is(':checked'));
            }
        });

        $('button[role=datemaxtoday]').click(function() {
            var mom = moment();
            $('input[role=datemax]').val(mom.format('YYYY-MM-DD'));
            changeApplyFilter();
            if (!pageIsPublic()) {
                saveOptions($('#applyfilters').is(':checked'));
            }
        });

        $('button[role=dateminplus]').click(function() {
            if ($('input[role=datemin]').val()) {
                var mom = moment($('input[role=datemin]').val());
                mom.add(1, 'days');
                $('input[role=datemin]').val(mom.format('YYYY-MM-DD'));
                changeApplyFilter();
            }
            if (!pageIsPublic()) {
                saveOptions($('#applyfilters').is(':checked'));
            }
        });

        $('button[role=dateminminus]').click(function() {
            if ($('input[role=datemin]').val()) {
                var mom = moment($('input[role=datemin]').val());
                mom.subtract(1, 'days');
                $('input[role=datemin]').val(mom.format('YYYY-MM-DD'));
                changeApplyFilter();
            }
            if (!pageIsPublic()) {
                saveOptions($('#applyfilters').is(':checked'));
            }
        });

        $('button[role=datemaxplus]').click(function() {
            if ($('input[role=datemax]').val()) {
                var mom = moment($('input[role=datemax]').val());
                mom.add(1, 'days');
                $('input[role=datemax]').val(mom.format('YYYY-MM-DD'));
                changeApplyFilter();
            }
            if (!pageIsPublic()) {
                saveOptions($('#applyfilters').is(':checked'));
            }
        });

        $('button[role=datemaxminus]').click(function() {
            if ($('input[role=datemax]').val()) {
                var mom = moment($('input[role=datemax]').val());
                mom.subtract(1, 'days');
                $('input[role=datemax]').val(mom.format('YYYY-MM-DD'));
                changeApplyFilter();
            }
            if (!pageIsPublic()) {
                saveOptions($('#applyfilters').is(':checked'));
            }
        });

        $('button[role=dateminmaxplus]').click(function() {
            if ($('input[role=datemin]').val()) {
                var mom = moment($('input[role=datemin]').val());
                mom.add(1, 'days');
                $('input[role=datemin]').val(mom.format('YYYY-MM-DD'));
            }

            if ($('input[role=datemax]').val()) {
                mom = moment($('input[role=datemax]').val());
                mom.add(1, 'days');
                $('input[role=datemax]').val(mom.format('YYYY-MM-DD'));
            }

            if ($('input[role=datemax]').val() || $('input[role=datemin]').val()) {
                changeApplyFilter();
            }
            if (!pageIsPublic()) {
                saveOptions($('#applyfilters').is(':checked'));
            }
        });

        $('button[role=dateminmaxminus]').click(function() {
            if ($('input[role=datemin]').val()) {
                var mom = moment($('input[role=datemin]').val());
                mom.subtract(1, 'days');
                $('input[role=datemin]').val(mom.format('YYYY-MM-DD'));
            }

            if ($('input[role=datemax]').val()) {
                mom = moment($('input[role=datemax]').val());
                mom.subtract(1, 'days');
                $('input[role=datemax]').val(mom.format('YYYY-MM-DD'));
            }

            if ($('input[role=datemax]').val() || $('input[role=datemin]').val()) {
                changeApplyFilter();
            }
            if (!pageIsPublic()) {
                saveOptions($('#applyfilters').is(':checked'));
            }
        });

        $('body').on('click','.resetFilterButton', function(e) {
            var tr = $(this).parent().parent();
            tr.find('input[type=date]').val('');
            tr.find('input[type=number]').val('');
            changeApplyFilter();
            if (!pageIsPublic()) {
                saveOptions($('#applyfilters').is(':checked'));
            }
        });

        $('#togglestats').click(function() {
            if ($(this).is(':checked')) {
                $('#statdiv').show();
                $('#statlabel').show();
                updateStatTable();
            }
            else {
                $('#statdiv').hide();
                $('#statlabel').hide();
            }
            if (!pageIsPublic()) {
                saveOptions();
            }
        });

        $('body').on('click', '.urlhelpbutton', function(e) {
            var logger = $(this).attr('logger');
            var sessionName = getSessionName($(this).parent().parent().parent().attr('token'));
            clickUrlHelp(logger, $(this).parent().parent().find('input[role='+logger+'url]').val(), sessionName);
        });

        $('body').on('change', '#colorinput', function(e) {
            okColor();
        });
        $('body').on('click', '.devicelist .devicecolor', function(e) {
            var s = $(this).parent().parent().attr('token');
            var d = $(this).parent().parent().attr('device');
            showColorPicker(s, d);
        });

        var radius = $('#pointradius').val();
        var diam = 2 * radius;
        $('<style role="divmarker">' +
            '.roundmarker, .squaremarker { ' +
            'width: ' + diam + 'px !important;' +
            'height: ' + diam + 'px !important;' +
            'line-height: ' + (diam - 10) + 'px;' +
            '}' +
            '.trianglemarker { ' +
            'width: 0px !important;' +
            'height: 0px !important;' +
            'border-left: ' + radius + 'px solid transparent !important;' +
            'border-right: ' + radius + 'px solid transparent !important;' +
            'border-bottom-width: ' + diam + 'px;' +
            'border-bottom-style: solid;' +
            'line-height: ' + (diam) + 'px;' +
            '}' +
            '</style>').appendTo('body');

        $('#pointradius').change(function() {
            if (!pageIsPublic()) {
                saveOptions();
            }
            var radius = $(this).val();
            var diam = 2 * radius;
            $('style[role=divmarker]').html(
                '.roundmarker, .squaremarker { ' +
                'width: ' + diam + 'px !important;' +
                'height: ' + diam + 'px !important;' +
                'line-height: ' + (diam - 10) + 'px;' +
                '}' +
                '.trianglemarker { ' +
                'width: 0px !important;' +
                'height: 0px !important;' +
                'border-left: ' + radius + 'px solid transparent !important;' +
                'border-right: ' + radius + 'px solid transparent !important;' +
                'border-bottom-width: ' + diam + 'px;' +
                'border-bottom-style: solid;' +
                'line-height: ' + (diam) + 'px;' +
                '}'
            );
            // change iconanchor
            var s, d, pid, icon, iconMarker;
            for (s in phonetrack.sessionMarkerLayers) {
                for (d in phonetrack.sessionMarkerLayers[s]) {
                    var dname = getDeviceName(s, d);
                    var shape = 'round';
                    iconMarker = L.divIcon({
                        iconAnchor: [radius, radius],
                        className: shape + 'marker color' + s + d,
                        html: '<b>' + dname[0] + '</b>'
                    });
                    phonetrack.sessionMarkerLayers[s][d].setIcon(iconMarker);

                    icon = L.divIcon({
                        iconAnchor: [radius, radius],
                        className: shape + 'marker color' + s + d,
                        html: ''
                    });
                    phonetrack.devicePointIcons[s][d] = icon;
                    for (pid in phonetrack.sessionPointsLayersById[s][d]) {
                        phonetrack.sessionPointsLayersById[s][d][pid].setIcon(icon);
                    }
                }
            }
        });

        var buttonColor = '#0000FF';
        if (OCA.Theming) {
            buttonColor = OCA.Theming.color;
        }

        $('<style role="buttons">.fa, .far, .fas { ' +
            'color: ' + buttonColor + '; }' +
            '.dropdown-content button:hover i, ' +
            '.reaffectDeviceDiv button:hover i ' +
            '{ color: ' + buttonColor + '; }' +
            '</style>').appendTo('body');

        var rgbTC = hexToRgb(buttonColor);

        $('<style role="filtertable">.activatedFilters { ' +
            'background: rgba(' + rgbTC.r + ',' + rgbTC.g + ',' + rgbTC.b + ', 0.2); }' +
            '</style>').appendTo('body');

        $('#pointlinealpha').change(function() {
            if (!pageIsPublic()) {
                saveOptions();
            }
            var opacity = $(this).val();
            var s, d, styletxt, shape;
            for (s in phonetrack.sessionMarkerLayers) {
                for (d in phonetrack.sessionMarkerLayers[s]) {
                    shape = 'round';
                    styletxt = $('style[tokendevice="' + s + d + '"]').html();
                    styletxt = styletxt.replace(/opacity: (\d+(\.\d+)?);/, 'opacity: ' + opacity + ';');
                    styletxt = styletxt.replace(/background: rgba\((\d+), (\d+), (\d+), (\d+(\.\d+)?)\)/, 'background: rgba($1, $2, $3, ' + opacity + ')');
                    // if markers is square or round, make border completely transparent
                    if (shape === 'triangle') {
                        styletxt = styletxt.replace(/border-color: rgba\((\d+), (\d+), (\d+), (\d+(\.\d+)?)\)/, 'border-color: rgba($1, $2, $3, ' + opacity + ')');
                    }
                    else {
                        styletxt = styletxt.replace(/border-color: rgba\((\d+), (\d+), (\d+), (\d+(\.\d+)?)\)/, 'border-color: rgba($1, $2, $3, 0)');
                    }
                    $('style[tokendevice="' + s + d + '"]').html(styletxt);
                }
            }
        });

        $('.sidebar-tabs li').click(function() {
            if (!pageIsPublic()) {
                saveOptions();
            }
        });

        $('body').on('mouseenter', '.reservNameButton', function(e) {
            $(this).find('i').addClass('fa-female').removeClass('fa-male');
        });

        $('body').on('mouseleave', '.reservNameButton', function(e) {
            $(this).find('i').addClass('fa-male').removeClass('fa-female');
        });

        $('body').on('keypress','li.filteredshare input[role=device]', function(e) {
            if (e.key === 'Enter') {
                var filteredtoken = $(this).parent().attr('filteredtoken');
                var devicename = $(this).val();
                var token = $(this).parent().parent().parent().parent().parent().attr('token');
                setPublicShareDeviceDb(token, filteredtoken, devicename);
            }
        });

        $('body').on('click', 'input[role=lastposonly]', function(e) {
            var filteredtoken = $(this).parent().attr('filteredtoken');
            var checked = 0;
            if ($(this).is(':checked')) {
                checked = 1;
            }
            var token = $(this).parent().parent().parent().parent().parent().attr('token');
            setPublicShareLastOnlyDb(token, filteredtoken, checked);
        });

        $('body').on('click', 'input[role=geofencify]', function(e) {
            var filteredtoken = $(this).parent().attr('filteredtoken');
            var checked = 0;
            if ($(this).is(':checked')) {
                checked = 1;
            }
            var token = $(this).parent().parent().parent().parent().parent().attr('token');
            setPublicShareGeofencifyDb(token, filteredtoken, checked);
        });

        if (!pageIsPublic()) {
            getSessions();
        }
        // public page
        else {
            var params, token, deviceid, publicviewtoken;
            if (pageIsPublicWebLog()) {
                params = window.location.href.split('publicWebLog/')[1].split('/');
                token = params[0];
                publicviewtoken = '';
                deviceid = params[1];
            }
            else {
                publicviewtoken = window.location.href.split('publicSessionWatch/')[1];
                token = publicviewtoken;
            }
            phonetrack.token = token;
            phonetrack.lastposonly = $('#lastposonly').text();
            // apply filters
            phonetrack.sharefilters = $('#sharefilters').text();
            var filtDict = {}
            if (phonetrack.sharefilters !== '') {
                filtDict = $.parseJSON(phonetrack.sharefilters);
                if (filtDict === null || typeof filtDict === 'undefined') {
                    filtDict = {};
                }
            }
            if (filtDict.hasOwnProperty('lastdays')) {
                $('#filterPointsTable input[role=lastdays]').val(filtDict.lastdays);
            }
            if (filtDict.hasOwnProperty('lasthours')) {
                $('#filterPointsTable input[role=lasthours]').val(filtDict.lasthours);
            }
            if (filtDict.hasOwnProperty('lastmins')) {
                $('#filterPointsTable input[role=lastmins]').val(filtDict.lastmins);
            }
            if (   filtDict.hasOwnProperty('lastmins')
                || filtDict.hasOwnProperty('lasthours')
                || filtDict.hasOwnProperty('lastdays')
            ) {
                $('#applyfilters').prop('checked', true);
                changeApplyFilter();
            }

            var name = $('#publicsessionname').text();
            phonetrack.publicName = name;
            addSession(token, name, publicviewtoken, null, [], [], true);
            $('#addPointDiv').remove();
            $('#deletePointDiv').remove();
            $('.removeSession').remove();
            $('#customtilediv').remove();
            $('#newsessiondiv').remove();
            $('#createimportsessiondiv').remove();
            if (pageIsPublicWebLog()) {
                $('#logmediv').show();
                $('#logmedeviceinput').val(deviceid);
            }
            $('#autozoom').prop('checked', true);
            phonetrack.zoomButton.state('zoom');

            if (pageIsPublicSessionWatch()) {
                $('#sidebar').toggleClass('collapsed');
                $('#sidebar li.active').removeClass('active');
                $('#header').hide();
                $('div#content').css('padding-top', '0px');
            }
        }

        refresh();

    }

})(jQuery, OC);
