(function ($, OC) {
    'use strict';

    //////////////// VAR DEFINITION /////////////////////

    var colors = [
        'red', 'cyan', 'purple', 'Lime', 'yellow',
        'orange', 'blue', 'brown', 'Chartreuse',
        'Crimson', 'DeepPink', 'Gold'
    ];
    var colorCode = [
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
    var lastColorUsed = -1;
    var phonetrack = {
        map: {},
        baseLayers: null,
        overlayLayers: null,
        restoredTileLayer: null,
        // indexed by session name, contains dict indexed by deviceid
        sessionLineLayers: {},
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
        currentTimer: null,
        lastTime: {},
        lastZindex: 1000,
        movepointSession: null,
        movepointDevice: null,
        movepointId: null
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

        L.control.scale({metric: true, imperial: true, position: 'topleft'})
        .addTo(phonetrack.map);

        L.control.mousePosition().addTo(phonetrack.map);
        phonetrack.locateControl = L.control.locate({setView: false, locateOptions: {enableHighAccuracy: true}});
        phonetrack.locateControl.addTo(phonetrack.map);
        phonetrack.map.on('locationfound', function(e) {
            locationFound(e);
        });
        phonetrack.map.addControl(new L.Control.LinearMeasurement({
            unitSystem: 'metric',
            color: '#FF0080',
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
                //icon:      'fa-spinner',
                icon:      'fa-line-chart',
                title:     t('phonetrack', 'Click to show movements'),
                onClick: function(btn, map) {
                    $('#viewmove').click();
                    btn.state('move');
                }
            },{
                stateName: 'move',
                icon:      'fa-line-chart',
                title:     t('phonetrack', 'Click to hide movements'),
                onClick: function(btn, map) {
                    $('#viewmove').click();
                    btn.state('nomove');
                }
            }]
        });
        phonetrack.moveButton.addTo(phonetrack.map);

        if ($('#viewmove').is(':checked')) {
            phonetrack.moveButton.state('move');
            $(phonetrack.moveButton.button).addClass('easy-button-green').removeClass('easy-button-red');
        }
        else {
            phonetrack.moveButton.state('nomove');
            $(phonetrack.moveButton.button).addClass('easy-button-red').removeClass('easy-button-green');
        }

        phonetrack.zoomButton = L.easyButton({
            position: 'bottomright',
            states: [{
                stateName: 'nozoom',
                //icon:      'fa-spinner',
                icon:      'fa-search',
                title:     t('phonetrack', 'Click to activate automatic zoom'),
                onClick: function(btn, map) {
                    $('#autozoom').click();
                    btn.state('zoom');
                }
            },{
                stateName: 'zoom',
                icon:      'fa-search-plus',
                title:     t('phonetrack', 'Click to disable automatic zoom'),
                onClick: function(btn, map) {
                    $('#autozoom').click();
                    btn.state('nozoom');
                }
            }]
        });
        phonetrack.zoomButton.addTo(phonetrack.map);

        if ($('#autozoom').is(':checked')) {
            phonetrack.zoomButton.state('zoom');
            $(phonetrack.zoomButton.button).addClass('easy-button-green').removeClass('easy-button-red');
        }
        else {
            phonetrack.zoomButton.state('nozoom');
            $(phonetrack.zoomButton.button).addClass('easy-button-red').removeClass('easy-button-green');
        }

        phonetrack.timeButton = L.easyButton({
            position: 'bottomright',
            states: [{
                stateName: 'noshowtime',
                //icon:      'fa-spinner',
                icon:      'fa-circle-o',
                title:     t('phonetrack', 'Click to show time'),
                onClick: function(btn, map) {
                    $('#showtime').click();
                    btn.state('showtime');
                }
            },{
                stateName: 'showtime',
                icon:      'fa-clock-o',
                title:     t('phonetrack', 'Click to hide time'),
                onClick: function(btn, map) {
                    $('#showtime').click();
                    btn.state('noshowtime');
                }
            }]
        });
        phonetrack.timeButton.addTo(phonetrack.map);

        if ($('#showtime').is(':checked')) {
            phonetrack.timeButton.state('showtime');
            $(phonetrack.timeButton.button).addClass('easy-button-green').removeClass('easy-button-red');
        }
        else {
            phonetrack.timeButton.state('noshowtime');
            $(phonetrack.timeButton.button).addClass('easy-button-red').removeClass('easy-button-green');
        }

        phonetrack.doZoomButton = L.easyButton({
            position: 'bottomright',
            states: [{
                stateName: 'no-importa',
                icon:      'fa-search',
                title:     t('phonetrack', 'Zoom on all markers'),
                onClick: function(btn, map) {
                    zoomOnDisplayedMarkers();
                }
            }]
        });
        phonetrack.doZoomButton.addTo(phonetrack.map);
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
        editPointDB(token, deviceid, pid, lat, lon, entry.altitude, entry.accuracy, entry.satellites, entry.batterylevel, entry.timestamp, entry.useragent);
        leaveMovePointMode();
    }

    function dragPointEnd(e) {
        var m = e.target;
        var entry = phonetrack.sessionPointsEntriesById[m.session][m.device][m.pid];
        editPointDB(m.session, m.device, m.pid, m.getLatLng().lat, m.getLatLng().lng, entry.altitude, entry.accuracy, entry.satellites, entry.batterylevel, entry.timestamp, entry.useragent);
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
        addPointDB(e.latlng.lat.toFixed(6), e.latlng.lng.toFixed(6), -1, -1, -1, -1, moment());
        leaveAddPointMode();
    }

    function deleteMultiplePoints(bounds=null) {
        var pid;
        var s = $('#deletePointSession option:selected').attr('token');
        var d = $('#deletePointDevice').val();
        // if session is watched, if device exists, for all displayed points
        if ($('.session[token=' + s + '] .watchbutton i').hasClass('fa-eye')) {
            if (d === '') {
                for (d in phonetrack.sessionPointsLayers[s]) {
                    var pidlist = [];
                    phonetrack.sessionPointsLayers[s][d].eachLayer(function(l) {
                        if (bounds === null || bounds.contains(l.getLatLng())) {
                            pidlist.push(l.getLatLng().alt);
                        }
                    });
                    for (pid in pidlist) {
                        deletePointDB(s, d, pidlist[pid]);
                    }
                }
            }
            else{
                if (phonetrack.sessionLineLayers[s].hasOwnProperty(d)) {
                    var pidlist = [];
                    phonetrack.sessionPointsLayers[s][d].eachLayer(function(l) {
                        if (bounds === null || bounds.contains(l.getLatLng())) {
                            pidlist.push(l.getLatLng().alt);
                        }
                    });
                    for (pid in pidlist) {
                        deletePointDB(s, d, pidlist[pid]);
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
        $('#loadingpc').text('');
        $('#loading').show();
    }

    function hideLoadingAnimation() {
        //$('div#logo').removeClass('spinning');
        $('#loading').hide();
    }

    //////////////// PUBLIC DIR/FILE /////////////////////

    function pageIsPublicWebLog() {
        return (document.URL.indexOf('/publicWebLog') !== -1);
    }

    function pageIsPublicSessionWatch() {
        return (document.URL.indexOf('/publicSessionWatch') !== -1);
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
            OC.dialogs.alert(t('phonetrack', 'Server name or server url should not be empty'),
                             t('phonetrack', 'Impossible to add tile server'));
            return;
        }
        if ($('#'+type+'serverlist ul li[servername="' + sname + '"]').length > 0) {
            OC.dialogs.alert(t('phonetrack', 'A server with this name already exists'),
                             t('phonetrack', 'Impossible to add tile server'));
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
            OC.Notification.showTemporary(t('phonetrack', 'Failed to add tile server "{ts}"', {ts: sname}));
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
            OC.Notification.showTemporary(t('phonetrack', 'Failed to delete tile server "{ts}"', {ts: sname}));
        });
    }

    //////////////// SAVE/RESTORE OPTIONS /////////////////////

    function restoreOptions() {
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
            if (optionsValues) {
                if (optionsValues.updateinterval !== undefined) {
                    $('#updateinterval').val(optionsValues.updateinterval);
                }
                if (optionsValues.linewidth !== undefined) {
                    $('#linewidth').val(optionsValues.linewidth);
                }
                if (optionsValues.pointlinealpha !== undefined) {
                    $('#pointlinealpha').val(optionsValues.pointlinealpha);
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
                if (optionsValues.acccirclecheck !== undefined) {
                    $('#acccirclecheck').prop('checked', optionsValues.acccirclecheck);
                }
                if (optionsValues.tilelayer !== undefined) {
                    phonetrack.restoredTileLayer = optionsValues.tilelayer;
                }
            }
            // quite important ;-)
            main();
        }).fail(function() {
            OC.dialogs.alert(
                t('phonetrack', 'Failed to restore options values') + '. ' +
                t('phonetrack', 'Reload this page')
                ,
                t('phonetrack', 'Error')
            );
        });
    }

    function saveOptions() {
        var optionsValues = {};
        optionsValues.updateinterval = $('#updateinterval').val();
        optionsValues.linewidth = $('#linewidth').val();
        optionsValues.pointlinealpha = $('#pointlinealpha').val();
        optionsValues.pointradius = $('#pointradius').val();
        optionsValues.viewmove = $('#viewmove').is(':checked');
        optionsValues.autozoom = $('#autozoom').is(':checked');
        optionsValues.showtime = $('#showtime').is(':checked');
        optionsValues.dragcheck = $('#dragcheck').is(':checked');
        optionsValues.acccirclecheck = $('#acccirclecheck').is(':checked');
        optionsValues.tilelayer = phonetrack.activeLayers.getActiveBaseLayer().name;
        //alert('to save : '+JSON.stringify(optionsValues));

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
            //alert(response);
        }).fail(function() {
            OC.dialogs.alert(
                t('phonetrack', 'Failed to save options values'),
                t('phonetrack', 'Error')
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
            OC.Notification.showTemporary(t('phonetrack', 'Failed to create session'));
        });
    }

    function getSessionName(token) {
        return $('div.session[token="' + token + '"] .sessionBar .sessionName').text();
    }

    function addSession(token, name, publicviewtoken, isPublic, sharedWith=[], selected=false, isFromShare=false, isSharedBy='') {
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
            'timestamp={2}';
        osmandurl = window.location.origin + osmandurl;

        var publicTrackUrl = OC.generateUrl('/apps/phonetrack/publicWebLog/' + token + '/yourname');
        publicTrackUrl = window.location.origin + publicTrackUrl;

        var publicWatchUrl = OC.generateUrl('/apps/phonetrack/publicSessionWatch/' + publicviewtoken);
        publicWatchUrl = window.location.origin + publicWatchUrl;

        var watchicon = 'fa-eye-slash';
        if (selected) {
            watchicon = 'fa-eye';
        }
        var divtxt = '<div class="session" token="' + token + '"' +
           ' publicviewtoken="' + publicviewtoken + '"' +
           ' shared="' + (isFromShare?1:0) + '"' +
            '>';
        divtxt = divtxt + '<div class="sessionBar">';
        divtxt = divtxt + '<button class="watchbutton" title="' + t('phonetrack', 'Watch this session') + '">' +
            '<i class="fa ' + watchicon + '" aria-hidden="true"></i></button>';

        var sharedByText = '';
        if (isSharedBy !== '') {
            sharedByText = ' (' +
                t('phonetrack', 'shared by {u}', {u: isSharedBy}) +
                ')';
        }
        divtxt = divtxt + '<div class="sessionName" title="' + name + sharedByText + '">' + name + '</div>';
        if (!pageIsPublic()) {
            divtxt = divtxt + '<button class="dropdownbutton" title="'+t('phonetrack', 'More actions')+'">' +
                '<i class="fa fa-bars" aria-hidden="true"></i></button>';
        }
        divtxt = divtxt + ' <button class="zoomsession" ' +
            'title="' + t('phonetrack', 'Zoom on this session') + '">' +
            '<i class="fa fa-search"></i></button>';
        if (!pageIsPublic() && !isFromShare) {
            divtxt = divtxt + '<button class="sharesession" title="'+t('phonetrack', 'Show link to share session')+'">' +
                '<i class="fa fa-share-alt" aria-hidden="true"></i></button>';
        }
        if (!pageIsPublicSessionWatch() && !isFromShare) {
            divtxt = divtxt + '<button class="moreUrlsButton" title="' + t('phonetrack', 'Show URLs for logging apps') + '">' +
                '<i class="fa fa-link"></i></button>';
        }
        divtxt = divtxt + '</div>';
        if (!pageIsPublic()) {
            divtxt = divtxt + '<div class="dropdown-content">';

            if (!isFromShare) {
                divtxt = divtxt + '<button class="removeSession">' +
                    '<i class="fa fa-trash" aria-hidden="true"></i> ' + t('phonetrack', 'Delete session') + '</button>';
                divtxt = divtxt + '<button class="editsessionbutton" title="' + t('phonetrack', 'Rename session') + '">' +
                    '<i class="fa fa-pencil"></i> ' + t('phonetrack', 'Rename session') + '</button>';
            }
            divtxt = divtxt + '<button class="export" title="' + t('phonetrack', 'Export to gpx') + '">' +
                '<i class="fa fa-floppy-o" aria-hidden="true"></i> ' + t('phonetrack', 'Export to gpx') + '</button>';

            divtxt = divtxt + '</div>';

            if (!isFromShare) {
                divtxt = divtxt + '<div class="editsessiondiv">' +
                    '<input role="editsessioninput" type="text" value="' + name + '"/>' +
                    '<button class="editsessionok"><i class="fa fa-check" style="color:green;"></i> ' +
                    t('phonetrack', 'Rename') + '</button>' +
                    '<button class="editsessioncancel"><i class="fa fa-undo" style="color:red;"></i> ' +
                    t('phonetrack', 'Cancel') + '</button>' +
                    '</div>';
            }
        }
        if (!pageIsPublic() && !isFromShare) {
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

            var titlePublic = t('phonetrack', 'If session is not public, position are not showed in public browser logging page');
            var icon = 'fa-eye-slash';
            var pubtext = t('phonetrack', 'Make session public');
            if (parseInt(isPublic) === 1) {
                icon = 'fa-eye';
                pubtext = t('phonetrack', 'Make session private');
            }
            divtxt = divtxt + '<button class="publicsessionbutton" title="' + titlePublic + '">';
            divtxt = divtxt + '<i class="fa ' + icon + '"></i> <b>' + pubtext + '</b></button>';
            divtxt = divtxt + '<div class="publicWatchUrlDiv">';
            divtxt = divtxt + '<p class="publicWatchUrlLabel">' + t('phonetrack', 'Public watch URL') + ' :</p>';
            divtxt = divtxt + '<input class="ro" role="publicWatchUrl" type="text" value="' + publicWatchUrl + '"></input>';
            divtxt = divtxt + '</div>';
            divtxt = divtxt + '</div>';
        }
        if (!pageIsPublicSessionWatch() && !isFromShare) {
            divtxt = divtxt + '<div class="moreUrls">';
            divtxt = divtxt + '<p class="urlhint">' + t('phonetrack', 'Replace \'yourname\' with the desired device name') + '</p>';
            divtxt = divtxt + '<p>' + t('phonetrack', 'Public browser logging URL') + ' :</p>';
            divtxt = divtxt + '<input class="ro" role="publicTrackUrl" type="text" value="' + publicTrackUrl + '"></input>';
            divtxt = divtxt + '<p>' + t('phonetrack', 'OsmAnd URL') + ' :</p>';
            divtxt = divtxt + '<input class="ro" role="osmandurl" type="text" value="' + osmandurl + '"></input>';
            divtxt = divtxt + '<p>' + t('phonetrack', 'GpsLogger GET and POST URL') + ' :</p>';
            divtxt = divtxt + '<input class="ro" role="gpsloggerurl" type="text" value="' + gpsloggerUrl + '"></input>';
            divtxt = divtxt + '<p>' + t('phonetrack', 'Owntracks (HTTP mode) URL') + ' :</p>';
            divtxt = divtxt + '<input class="ro" role="owntracksurl" type="text" value="' + owntracksurl + '"></input>';
            divtxt = divtxt + '<p>' + t('phonetrack', 'Ulogger URL') + ' :</p>';
            divtxt = divtxt + '<input class="ro" role="uloggerurl" type="text" value="' + uloggerurl + '"></input>';
            divtxt = divtxt + '<p>' + t('phonetrack', 'Traccar URL') + ' :</p>';
            divtxt = divtxt + '<input class="ro" role="traccarurl" type="text" value="' + traccarurl + '"></input>';
            divtxt = divtxt + '<p>' + t('phonetrack', 'OpenGTS URL') + ' :</p>';
            divtxt = divtxt + '<input class="ro" role="opengtsurl" type="text" value="' + opengtsurl + '"></input>';
            divtxt = divtxt + '</div>';
        }
        divtxt = divtxt + '<ul class="devicelist" token="' + token + '"></ul></div>';

        $('div#sessions').append($(divtxt).fadeIn('slow')).find('input.ro[type=text]').prop('readonly', true);
        $('.session[token="' + token + '"]').find('.sharediv').hide();
        $('.session[token="' + token + '"]').find('.moreUrls').hide();
        $('.session[token="' + token + '"]').find('.editsessiondiv').hide();
        if (parseInt(isPublic) === 0) {
            $('.session[token="' + token + '"]').find('.publicWatchUrlDiv').hide();
        }
            //.find('input[type=text]').prop('readonly', false);
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
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to delete session'));
        });
    }

    function deleteDevice(token, device, sessionName) {
        var req = {
            token: token,
            device: device
        };
        var url = OC.generateUrl('/apps/phonetrack/deleteDevice');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done === 1) {
                removeDevice(token, device);
                OC.Notification.showTemporary(t('phonetrack', 'Device \'{d}\' of session \'{s}\' has been deleted', {d: device, s: sessionName}));
            }
            else if (response.done === 2) {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to delete device \'{d}\' of session \'{s}\'', {d: device, s: sessionName}));
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
        delete phonetrack.sessionLatlngs[token][device];
        phonetrack.sessionPointsLayers[token][device].unbindTooltip().remove();
        delete phonetrack.sessionPointsLayers[token][device];
        delete phonetrack.lastTime[token][device];
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
            OC.Notification.showTemporary(t('phonetrack', 'Failed to rename session') + ' ' + oldname);
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
            // marker tooltip
            to = phonetrack.sessionMarkerLayers[token][d].getTooltip()._content;
            to = to.replace(
                t('phonetrack', 'session') + ' ' + oldname,
                t('phonetrack', 'session') + ' ' + newname
            );
            phonetrack.sessionMarkerLayers[token][d].unbindTooltip();
            phonetrack.sessionMarkerLayers[token][d].bindTooltip(to, {permanent: perm, offset: offset, className: 'tooltip' + token + d.replace(' ', '')});
            // marker popup
            if (!pageIsPublic()
                && !isSessionShared(token)
                && $('.session[token='+token+'] .devicelist li[device="'+d+'"] .toggleDetail').hasClass('on')
            ) {
                p = phonetrack.sessionMarkerLayers[token][d].getPopup().getContent();
                phonetrack.sessionMarkerLayers[token][d].unbindPopup();
                p = p.replace('sessionname="' + oldname + '"', 'sessionname="' + newname + '"');
                phonetrack.sessionMarkerLayers[token][d].bindPopup(p, {closeOnClick: false});
            }

            // line tooltip
            to = phonetrack.sessionLineLayers[token][d].getTooltip()._content;
            to = to.replace(
                t('phonetrack', 'session') + ' ' + oldname,
                t('phonetrack', 'session') + ' ' + newname
            );
            phonetrack.sessionLineLayers[token][d].unbindTooltip();
            phonetrack.sessionLineLayers[token][d].bindTooltip(
                to,
                {
                    permanent: false,
                    sticky: true,
                    className: 'tooltip' + token + d.replace(' ', '')
                }
            );
            for (id in phonetrack.sessionPointsLayersById[token][d]) {
                l = phonetrack.sessionPointsLayersById[token][d][id];
                // line points tooltips
                to = l.getTooltip()._content;
                to = to.replace(
                    t('phonetrack', 'session') + ' ' + oldname,
                    t('phonetrack', 'session') + ' ' + newname
                );
                l.unbindTooltip();
                l.bindTooltip(to, {permanent: false, offset: offset, className: 'tooltip' + token + d.replace(' ', '')});

                // line points popups
                p = l.getPopup().getContent();
                l.unbindPopup();
                p = p.replace('sessionname="' + oldname + '"', 'sessionname="' + newname + '"');
                l.bindPopup(p, {closeOnClick: false});
            }
        }
    }

    function getSessions() {
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
                    // TODO adapt to shared sessions
                    if (response.sessions[s].length < 4) {
                        addSession(
                            response.sessions[s][1],
                            response.sessions[s][0],
                            '',
                            0,
                            [],
                            false,
                            true,
                            response.sessions[s][2]
                        );
                    }
                    else {
                        addSession(
                            response.sessions[s][1],
                            response.sessions[s][0],
                            response.sessions[s][2],
                            response.sessions[s][3],
                            response.sessions[s][4]
                        );
                    }
                }
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to get sessions'));
        });
    }

    function refresh() {
        var url;
        var sessionsToWatch = [];
        // get new positions for all watched sessions
        $('.watchbutton i.fa-eye').each(function() {
            var token = $(this).parent().parent().parent().attr('token');
            var lastTimes = phonetrack.lastTime[token] || '';
            sessionsToWatch.push([token, lastTimes]);
        });

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
            $.ajax({
                type: 'POST',
                url: url,
                data: req,
                async: true
            }).done(function (response) {
                displayNewPoints(response.sessions);
            }).always(function() {
                hideLoadingAnimation();
            }).fail(function() {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to refresh sessions'));
            });
        }
        else {
            showHideSelectedSessions();
        }

        // launch refresh again
        var uiVal = $('#updateinterval').val();
        var updateinterval = 5000;
        if (uiVal !== '' && !isNaN(uiVal) && parseInt(uiVal) > 1) {
            var updateinterval = parseInt(uiVal) * 1000;
        }
        phonetrack.currentTimer = new Timer(function() {
            refresh();
        }, updateinterval);
    }

    function filterEntry(entry) {
        var filtersEnabled = $('#applyfilters').is(':checked');
        var timestampMin, timestampMax;

        if (filtersEnabled) {
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

            var datemaxstr = tab.find('input[role=datemax]').val();
            if (datemaxstr) {
                var hourmaxstr = parseInt(tab.find('input[role=hourmax]').val()) || 23;
                var minmaxstr = parseInt(tab.find('input[role=minutemax]').val()) || 59;
                var secmaxstr = parseInt(tab.find('input[role=secondmax]').val()) || 59;
                var completeDateMaxStr = datemaxstr + ' ' + pad(hourmaxstr) + ':' + pad(minmaxstr) + ':' + pad(secmaxstr);
                var momMax = moment(completeDateMaxStr);
                timestampMax = momMax.unix();
            }

            var satellitesmin = parseInt($('input[role=satellitesmin]').val());
            var satellitesmax = parseInt($('input[role=satellitesmax]').val());
            var batterymin    = parseInt($('input[role=batterymin]').val());
            var batterymax    = parseInt($('input[role=batterymax]').val());
            var elevationmin  = parseInt($('input[role=elevationmin]').val());
            var elevationmax  = parseInt($('input[role=elevationmax]').val());
            var accuracymin   = parseInt($('input[role=accuracymin]').val());
            var accuracymax   = parseInt($('input[role=accuracymax]').val());
        }
        return (
            !filtersEnabled
            || (
                   (!dateminstr || parseInt(entry.timestamp) > timestampMin)
                && (!datemaxstr || parseInt(entry.timestamp) < timestampMax)
                && (!elevationmax || entry.altitude >= elevationmax)
                && (!elevationmin || entry.altitude <= elevationmin)
                && (!batterymin || entry.batterylevel >= batterymin)
                && (!batterymax || entry.batterylevel <= batterymax)
                && (!satellitesmin || entry.satellites >= satellitesmin)
                && (!satellitesmax || entry.satellites <= satellitesmax)
                && (!accuracymin || entry.accuracy >= accuracymin)
                && (!accuracymax || entry.accuracy <= accuracymax)
            )
        );
    }

    function filterList(list, token, deviceid) {
        var filtersEnabled = $('#applyfilters').is(':checked');
        var timestampMin, timestampMax, resList, resDateList;

        if (filtersEnabled) {
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

            var datemaxstr = tab.find('input[role=datemax]').val();
            if (datemaxstr) {
                var hourmaxstr = parseInt(tab.find('input[role=hourmax]').val()) || 23;
                var minmaxstr = parseInt(tab.find('input[role=minutemax]').val()) || 59;
                var secmaxstr = parseInt(tab.find('input[role=secondmax]').val()) || 59;
                var completeDateMaxStr = datemaxstr + ' ' + pad(hourmaxstr) + ':' + pad(minmaxstr) + ':' + pad(secmaxstr);
                var momMax = moment(completeDateMaxStr);
                timestampMax = momMax.unix();
            }

            var satellitesmin = parseInt($('input[role=satellitesmin]').val());
            var satellitesmax = parseInt($('input[role=satellitesmax]').val());
            var batterymin    = parseInt($('input[role=batterymin]').val());
            var batterymax    = parseInt($('input[role=batterymax]').val());
            var elevationmin  = parseInt($('input[role=elevationmin]').val());
            var elevationmax  = parseInt($('input[role=elevationmax]').val());
            var accuracymin   = parseInt($('input[role=accuracymin]').val());
            var accuracymax   = parseInt($('input[role=accuracymax]').val());

            resDateList = [];
            resList = [];
            var i = 0;
            ////// DATES
            // we avoid everything under the min
            if (dateminstr) {
                while (i < list.length
                       && (parseInt(phonetrack.sessionPointsEntriesById[token][deviceid][list[i][2]].timestamp) < timestampMin)
                ) {
                    i++;
                }
            }
            // then we copy everything under the max
            if (datemaxstr) {
                while (i < list.length
                       && (parseInt(phonetrack.sessionPointsEntriesById[token][deviceid][list[i][2]].timestamp) < timestampMax)
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

    function changeApplyFilter() {
        var filtersEnabled = $('#applyfilters').is(':checked');
        $('#filterPointsTable input[type=number]').prop('disabled', filtersEnabled);
        $('#filterPointsTable input[type=date]').prop('disabled', filtersEnabled);
        var s, d, id, i, displayedLatlngs;
        var dragenabled = $('#dragcheck').is(':checked');

        // simpler case : no filter
        if (!filtersEnabled) {
            for (s in phonetrack.sessionLineLayers) {
                for (d in phonetrack.sessionLineLayers[s]) {
                    // put all coordinates in lines
                    phonetrack.sessionLineLayers[s][d].setLatLngs(
                        phonetrack.sessionLatlngs[s][d]
                    );

                    // add line points from sessionPointsLayersById in sessionPointsLayers
                    for (id in phonetrack.sessionPointsLayersById[s][d]) {
                        if (!phonetrack.sessionPointsLayers[s][d].hasLayer(phonetrack.sessionPointsLayersById[s][d][id])) {
                            phonetrack.sessionPointsLayers[s][d].addLayer(phonetrack.sessionPointsLayersById[s][d][id]);
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
                    phonetrack.sessionLineLayers[s][d].setLatLngs(displayedLatlngs);

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
    }

    function updateMarker(s, d, sessionname) {
        var perm = $('#showtime').is(':checked');
        var mla, mln, mid, mentry, displayedLatlngs, oldlatlng;
        displayedLatlngs = phonetrack.sessionLineLayers[s][d].getLatLngs();
        // if session is not watched or if there is no points to see
        if (!$('div.session[token='+s+'] .watchbutton i').hasClass('fa-eye') || displayedLatlngs.length === 0) {
            if (phonetrack.map.hasLayer(phonetrack.sessionMarkerLayers[s][d])) {
                phonetrack.sessionMarkerLayers[s][d].remove();
            }
        }
        else {
            mla = displayedLatlngs[displayedLatlngs.length - 1].lat;
            mln = displayedLatlngs[displayedLatlngs.length - 1].lng;
            mid = displayedLatlngs[displayedLatlngs.length - 1].alt;
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

            // we update tooltip and popup anyway, in case any value has changed
            // tooltip
            phonetrack.sessionMarkerLayers[s][d].unbindTooltip();
            phonetrack.sessionMarkerLayers[s][d].bindTooltip(
                getPointTooltipContent(mentry, sessionname),
                {permanent: perm, offset: offset, className: 'tooltip' + s + d.replace(' ', '')}
            );
            // popup
            if (!pageIsPublic()
                && !isSessionShared(s)
                && $('.session[token='+s+'] .devicelist li[device="'+d+'"] .toggleDetail').hasClass('on')
            ) {
                phonetrack.sessionMarkerLayers[s][d].unbindPopup();
                phonetrack.sessionMarkerLayers[s][d].bindPopup(
                    getPointPopup(s, d, mentry, sessionname),
                    {closeOnClick: false}
                );
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


    function displayNewPoints(sessions) {
        var s, i, d, entry, device, timestamp, mom, icon,
            markertooltip, colorn, rgbc,
            textcolor, sessionname;
        var perm = $('#showtime').is(':checked');
        for (s in sessions) {
            sessionname = getSessionName(s);
            if (! phonetrack.sessionLineLayers.hasOwnProperty(s)) {
                phonetrack.sessionLineLayers[s] = {};
                phonetrack.sessionLatlngs[s] = {};
                phonetrack.sessionPointsLayers[s] = {};
                phonetrack.sessionPointsLayersById[s] = {};
                phonetrack.sessionPointsEntriesById[s] = {};
            }
            if (! phonetrack.sessionMarkerLayers.hasOwnProperty(s)) {
                phonetrack.sessionMarkerLayers[s] = {};
            }
            // for all devices
            for (d in sessions[s]) {
                // add line and marker if necessary
                if (! phonetrack.sessionLineLayers[s].hasOwnProperty(d)) {
                    addDevice(s, d, sessionname);
                }
                // for all new entries of this session
                for (i in sessions[s][d]) {
                    entry = sessions[s][d][i];
                    entry.altitude = parseInt(entry.altitude);
                    entry.satellites = parseInt(entry.satellites);
                    entry.accuracy = parseInt(entry.accuracy);
                    entry.batterylevel = parseInt(entry.batterylevel);
                    appendEntryToDevice(s, d, entry, sessionname);
                }
            }
        }
        if ($('#togglestats').is(':checked')) {
            updateStatTable();
        }
        // in case user click is between ajax request and response
        showHideSelectedSessions();
    }

    function changeDeviceStyle(s, d, colorcode) {
        var rgbc = hexToRgb(colorcode);
        var textcolor = 'black';
        if (rgbc.r + rgbc.g + rgbc.b < 3 * 80) {
            textcolor = 'white';
        }
        var opacity = $('#pointlinealpha').val();
        $('style[tokendevice="' + s + d + '"]').html(
            '.color' + s + d.replace(' ', '') + ' { ' +
            'background: rgba(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ', ' + opacity + ');' +
            'color: ' + textcolor + '; font-weight: bold;' +
            ' }' +
            '.poly' + s + d.replace(' ', '') + ' {' +
            'stroke: ' + colorcode + ';' +
            'opacity: ' + opacity + ';' +
            '}' +
            '.tooltip' + s + d.replace(' ', '') + ' {' +
            'background: rgba(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ', 0.5);' +
            'color: ' + textcolor + '; font-weight: bold; }' +
            '.opaquetooltip' + s + d.replace(' ', '') + ' {' +
            'background: rgba(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ');' +
            'color: ' + textcolor + '; font-weight: bold;' +
            '}'
        );
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
        changeDeviceStyle(s, d, color);
    }

    function addDevice(s, d, sessionname) {
        var colorn, textcolor, rgbc, linetooltip;
        colorn = ++lastColorUsed % colorCode.length;
        phonetrack.sessionColors[s + d] = colorCode[colorn];
        rgbc = hexToRgb(colorCode[colorn]);
        textcolor = 'black';
        if (rgbc.r + rgbc.g + rgbc.b < 3 * 80) {
            textcolor = 'white';
        } 
        var opacity = $('#pointlinealpha').val();
        $('<style tokendevice="' + s + d + '">.color' + s + d.replace(' ', '') + ' { ' +
            'background: rgba(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ', ' + opacity + ');' +
                'color: ' + textcolor + '; font-weight: bold;' +
                ' }' +
                '.poly' + s + d.replace(' ', '') + ' {' +
                'stroke: ' + colorCode[colorn] + ';' +
                'opacity: ' + opacity + ';' +
                '}' +
                '.tooltip' + s + d.replace(' ', '') + ' {' +
                'background: rgba(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ', 0.5);' +
                'color: ' + textcolor + '; font-weight: bold; }' +
                '.opaquetooltip' + s + d.replace(' ', '') + ' {' +
                'background: rgb(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ');' +
                'color: ' + textcolor + '; font-weight: bold;' +
                '}</style>').appendTo('body');
        var deleteLink = '';
        if (!pageIsPublic() && !isSessionShared(s)) {
            deleteLink = ' <button class="deleteDevice" token="' + s + '" device="' + d + '" ' +
                'title="' + t('phonetrack', 'Delete this device') + '">' +
                '<i class="fa fa-trash" aria-hidden="true"></i></button>';
        }
        var detailLink = ' <button class="toggleDetail off" token="' + s + '" device="' + d + '" ' +
            'title="' + t('phonetrack', 'Toggle detail/edition points') + '">' +
            '<i class="fa fa-dot-circle-o" aria-hidden="true"></i></button>';
        var lineDeviceLink = ' <button class="toggleLineDevice on" token="' + s + '" device="' + d + '" ' +
            'title="' + t('phonetrack', 'Toggle lines') + '">' +
            '<i class="fa fa-line-chart" aria-hidden="true"></i></button>';
        $('div.session[token="' + s + '"] ul.devicelist').append(
            '<li device="' + d + '" token="' + s + '">' +
                '<div class="devicecolor opaquetooltip' + s + d.replace(' ', '') + '"></div> ' +
                '<div class="deviceLabel" title="' +
                t('phonetrack', 'Center map on device') + ' ' + d + '">' + d + '</div> ' +
                deleteLink +
                '<button class="zoomdevicebutton" title="' +
                t('phonetrack', 'Center map on device') + ' ' + d + '">' +
                '<i class="fa fa-search" aria-hidden="true"></i></button>' +
                detailLink +
                lineDeviceLink +
                '<input class="followdevice" type="checkbox" ' + 'title="' +
                t('phonetrack', 'Follow this device (autozoom)') + '"/>' +
                '</li>');

        phonetrack.sessionPointsLayers[s][d] = L.featureGroup();
        phonetrack.sessionPointsLayersById[s][d] = {};
        phonetrack.sessionPointsEntriesById[s][d] = {};
        phonetrack.sessionLatlngs[s][d] = [];
        var linewidth = $('#linewidth').val();
        phonetrack.sessionLineLayers[s][d] = L.polyline([], {weight: linewidth, className: 'poly' + s + d.replace(' ', '')});
        linetooltip = t('phonetrack', 'session') + ' ' + sessionname + ' | ' +
            t('phonetrack', 'device') + ' ' + d;
        phonetrack.sessionLineLayers[s][d].bindTooltip(
            linetooltip,
            {
                permanent: false,
                sticky: true,
                className: 'tooltip' + s + d.replace(' ', '')
            }
        );
        var radius = $('#pointradius').val();
        var icon = L.divIcon({
            iconAnchor: [radius, radius],
            className: 'roundmarker color' + s + d.replace(' ', ''),
            html: '<b>' + d[0] + '</b>'
        });

        phonetrack.sessionMarkerLayers[s][d] = L.marker([], {icon: icon});
        phonetrack.sessionMarkerLayers[s][d].on('dragend', dragPointEnd);
        phonetrack.sessionMarkerLayers[s][d].session = s;
        phonetrack.sessionMarkerLayers[s][d].device = d;
        phonetrack.sessionMarkerLayers[s][d].pid = null;
        phonetrack.sessionMarkerLayers[s][d].setZIndexOffset(phonetrack.lastZindex++);
        phonetrack.sessionMarkerLayers[s][d].on('mouseover', function(e) {
            markerMouseover(e);
        });
        phonetrack.sessionMarkerLayers[s][d].on('mouseout', function(e) {
            markerMouseout(e);
        });
    }

    function appendEntryToDevice(s, d, entry, sessionname) {
        var timestamp, device, pointtooltip;
        var filter = filterEntry(entry);
        timestamp = parseInt(entry.timestamp);
        device = entry.deviceid;
        pointtooltip = getPointTooltipContent(entry, sessionname);
        if (!phonetrack.lastTime.hasOwnProperty(s)) {
            phonetrack.lastTime[s] = {};
        }
        if ((!phonetrack.lastTime[s].hasOwnProperty(device)) ||
            timestamp > phonetrack.lastTime[s][device])
        {
            phonetrack.lastTime[s][device] = timestamp;
        }
        // increment lines
        if (filter) {
            phonetrack.sessionLineLayers[s][d].addLatLng([entry.lat, entry.lon, entry.id]);
        }
        phonetrack.sessionLatlngs[s][d].push([entry.lat, entry.lon, entry.id]);

        var radius = $('#pointradius').val();
        var icon = L.divIcon({
            iconAnchor: [radius, radius],
            className: 'roundmarker color' + s + d.replace(' ', ''),
            html: ''
        });

        var m = L.marker([entry.lat, entry.lon, entry.id],
            {icon: icon}
        );
        m.session = s;
        m.device = d;
        m.pid = entry.id;
        m.on('mouseover', function(e) {
            markerMouseover(e);
        });
        m.on('mouseout', function(e) {
            markerMouseout(e);
        });
        m.on('dragend', dragPointEnd);
        m.bindTooltip(pointtooltip, {className: 'tooltip' + s + d.replace(' ', '')});
        phonetrack.sessionPointsEntriesById[s][d][entry.id] = entry;
        phonetrack.sessionPointsLayersById[s][d][entry.id] = m;
        if (filter) {
            phonetrack.sessionPointsLayers[s][d].addLayer(m);
        }
        if (!pageIsPublic() && !isSessionShared(s)) {
            m.bindPopup(getPointPopup(s, d, entry, sessionname), {closeOnClick: false});
        }
    }

    function markerMouseover(e) {
        if ($('#acccirclecheck').is(':checked')) {
            var latlng = e.target.getLatLng();
            var pid = e.target.pid;
            var d = e.target.device;
            var s = e.target.session;
            var acc = parseInt(phonetrack.sessionPointsEntriesById[s][d][pid].accuracy);
            if (acc !== -1) {
                phonetrack.currentPrecisionCircle = L.circle(latlng, {radius: acc});
                phonetrack.map.addLayer(phonetrack.currentPrecisionCircle);
            }
            else {
                phonetrack.currentPrecisionCircle = null;
            }
        }
    }

    function markerMouseout(e) {
        if (phonetrack.currentPrecisionCircle !== null
            && phonetrack.map.hasLayer(phonetrack.currentPrecisionCircle)
        ) {
            phonetrack.map.removeLayer(phonetrack.currentPrecisionCircle);
            phonetrack.currentPrecisionCircle = null;
        }
    }

    function isSessionShared(s) {
        return ($('div.session[token="' + s + '"]').attr('shared') === '1');
    }

    function editPointDB(token, deviceid, pointid, lat, lon, alt, acc, sat, bat, timestamp, useragent) {
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
            useragent: useragent
        };
        var url = OC.generateUrl('/apps/phonetrack/updatePoint');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done === 1) {
                updatePointMap(token, deviceid, pointid, lat, lon, alt, acc, sat, bat, timestamp, useragent);
            }
            else if (response.done === 2) {
                OC.Notification.showTemporary(t('phonetrack', 'The point you want to edit does not exist or you\'re not allowed to edit it'));
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to edit point'));
        });
    }

    function updatePointMap(token, deviceid, pointid, lat, lon, alt, acc, sat, bat, timestamp, useragent) {
        var perm = $('#showtime').is(':checked');
        var i;

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

        var filter = filterEntry(entry);

        // update line point tooltip
        phonetrack.sessionPointsLayersById[token][deviceid][pointid].unbindTooltip();
        phonetrack.sessionPointsLayersById[token][deviceid][pointid].bindTooltip(
            getPointTooltipContent(entry, sessionname),
            {permanent: false, offset: offset, className: 'tooltip' + token + deviceid.replace(' ', '')}
        );

        // update line point popup
        phonetrack.sessionPointsLayersById[token][deviceid][pointid].unbindPopup();
        phonetrack.sessionPointsLayersById[token][deviceid][pointid].bindPopup(
            getPointPopup(token, deviceid, entry, sessionname),
            {closeOnClick: false}
        );
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
            phonetrack.sessionLineLayers[token][deviceid].setLatLngs(filteredlatlngs);

            // lastTime is independent from filters
            phonetrack.lastTime[token][deviceid] =
                phonetrack.sessionPointsEntriesById[token][deviceid][newlatlngs[newlatlngs.length - 1][2]].timestamp;
        }

        updateMarker(token, deviceid, sessionname);
        if ($('#togglestats').is(':checked')) {
            updateStatTable();
        }

        phonetrack.map.closePopup();
    }

    function deletePointDB(s, d, pid) {
        var token = s;
        var deviceid = d;
        var pointid = pid;
        var req = {
            token: token,
            deviceid: deviceid,
            pointid: pointid
        };
        var url = OC.generateUrl('/apps/phonetrack/deletePoint');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done === 1) {
                deletePointMap(s, d, pid);
            }
            else if (response.done === 2) {
                OC.Notification.showTemporary(t('phonetrack', 'The point you want to delete does not exist or you\'re not allowed to delete it'));
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to delete point'));
        });
    }

    function deletePointMap(s, d, pid) {
        var perm = $('#showtime').is(':checked');
        var i, lat, lng, p;
        var sn = getSessionName(s);
        var intpid = parseInt(pid);
        var entry = phonetrack.sessionPointsEntriesById[s][d][pid];
        // remove associated point from sessionPointsLayers
        var m = phonetrack.sessionPointsLayersById[s][d][pid];
        phonetrack.sessionPointsLayers[s][d].removeLayer(m);
        delete phonetrack.sessionPointsLayersById[s][d][pid];
        delete phonetrack.sessionPointsEntriesById[s][d][pid];

        // remove point in the line
        //var latlngs = phonetrack.sessionLineLayers[s][d].getLatLngs();
        var latlngs = phonetrack.sessionLatlngs[s][d];
        var newlatlngs = [];
        i = 0;
        while (parseInt(latlngs[i][2]) !== intpid) {
            newlatlngs.push([latlngs[i][0], latlngs[i][1], latlngs[i][2]]);
            i++;
        }
        i++;
        while (i < latlngs.length) {
            newlatlngs.push([latlngs[i][0], latlngs[i][1], latlngs[i][2]]);
            i++;
        }

        phonetrack.sessionLatlngs[s][d] = newlatlngs;
        var filteredlatlngs = filterList(newlatlngs, s, d);
        phonetrack.sessionLineLayers[s][d].setLatLngs(filteredlatlngs);

        updateMarker(s, d, sn);

        // update lastTime : new last point time (independent from filter)
        if (newlatlngs.length > 0) {
        phonetrack.lastTime[s][d] =
            phonetrack.sessionPointsEntriesById[s][d][newlatlngs[newlatlngs.length - 1][2]].timestamp;
        }
        else {
            // there is no point left for this device : delete the device
            deleteDevice(s, d, sn);
        }
        if ($('#togglestats').is(':checked')) {
            updateStatTable();
        }

        phonetrack.map.closePopup();
    }

    function addPointDB(plat='', plon='', palt='', pacc='', psat='', pbat='', pmoment='') {
        var lat, lon, alt, acc, sat, bat, mom;
        var tab = $('#addPointTable');
        var token = $('#addPointSession option:selected').attr('token');
        var deviceid = $('#addPointDevice').val();
        lat = plat;
        lon = plon;           
        alt = palt;
        acc = pacc;
        sat = psat;
        bat = pbat;
        mom = pmoment;
        var timestamp = mom.unix();
        var req = {
            token: token,
            deviceid: deviceid,
            timestamp: timestamp,
            lat: lat,
            lon: lon,
            alt: alt,
            acc: acc,
            bat: bat,
            sat: sat,
            useragent: 'Manually added'
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
                    addPointMap(response.id, lat, lon, alt, acc, sat, bat, timestamp);
                }
            }
            else if (response.done === 2) {
                OC.Notification.showTemporary(t('phonetrack', 'Impossible to add this point'));
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to add point'));
        });
    }

    function addPointMap(id, lat, lon, alt, acc, sat, bat, timestamp) {
        var perm = $('#showtime').is(':checked');
        var tab = $('#addPointTable');
        var token = $('#addPointSession option:selected').attr('token');
        var deviceid = $('#addPointDevice').val();
        var useragent = 'Manually added';

        var entry = {id: id};
        entry.deviceid = deviceid;
        entry.timestamp = timestamp;
        entry.lat = lat;
        entry.lon = lon;
        entry.altitude = alt;
        entry.batterylevel = bat;
        entry.satellites = sat;
        entry.accuracy = acc;
        entry.useragent = useragent;

        var filter = filterEntry(entry);

        var sessionname = getSessionName(token);

        // add device if it does not exist
        if (! phonetrack.sessionLineLayers[token].hasOwnProperty(deviceid)) {
            addDevice(token, deviceid, sessionname);
            appendEntryToDevice(token, deviceid, entry, sessionname);
        }
        // insert entry correctly ;)
        else {
            // add line point
            var pointtooltip = getPointTooltipContent(entry, sessionname);
            var radius = $('#pointradius').val();
            var icon = L.divIcon({
                iconAnchor: [radius, radius],
                className: 'roundmarker color' + token + deviceid.replace(' ', ''),
                html: ''
            });
            var m = L.marker(
                [entry.lat, entry.lon, entry.id],
                {icon: icon}
            );
            m.session = token;
            m.device = deviceid;
            m.pid = entry.id;
            m.on('mouseover', function(e) {
                markerMouseover(e);
            });
            m.on('mouseout', function(e) {
                markerMouseout(e);
            });
            m.on('dragend', dragPointEnd);
            m.bindTooltip(pointtooltip, {className: 'markertooltip tooltip' + token + deviceid.replace(' ', '')});
            phonetrack.sessionPointsEntriesById[token][deviceid][entry.id] = entry;
            phonetrack.sessionPointsLayersById[token][deviceid][entry.id] = m;
            if (filter) {
                phonetrack.sessionPointsLayers[token][deviceid].addLayer(m);
            }
            if (!pageIsPublic() && !isSessionShared(token)) {
                m.bindPopup(getPointPopup(token, deviceid, entry, sessionname), {closeOnClick: false});
            }

            // update line

            //var latlngs = phonetrack.sessionLineLayers[token][deviceid].getLatLngs();
            var latlngs = phonetrack.sessionLatlngs[token][deviceid];
            var newlatlngs = [];
            var i = 0;
            // we copy until we get to the right place to insert new point
            while (i < latlngs.length
                   && timestamp > parseInt(phonetrack.sessionPointsEntriesById[token][deviceid][latlngs[i][2]].timestamp)
            ) {
                // copy
                newlatlngs.push([latlngs[i][0], latlngs[i][1], latlngs[i][2]]);
                i++;
            }
            // put the edited point
            newlatlngs.push([lat, lon, id]);
            // finish the copy
            while (i < latlngs.length) {
                // copy
                newlatlngs.push([latlngs[i][0], latlngs[i][1], latlngs[i][2]]);
                i++;
            }
            // modify line
            phonetrack.sessionLatlngs[token][deviceid] = newlatlngs;
            var filteredlatlngs = filterList(newlatlngs, token, deviceid);
            phonetrack.sessionLineLayers[token][deviceid].setLatLngs(filteredlatlngs);

            // update lastTime
            phonetrack.lastTime[token][deviceid] =
                phonetrack.sessionPointsEntriesById[token][deviceid][newlatlngs[newlatlngs.length - 1][2]].timestamp;
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
        res = res + '<tr>';
        res = res + '<td>' + t('phonetrack', 'Date') + '</td>';
        res = res + '<td><input role="date" type="date" value="' + dateval + '"/></td>';
        res = res + '</tr><tr>';
        res = res + '<td>' + t('phonetrack', 'Time') + '</td>';
        res = res + '<td><input role="hour" type="number" value="' + hourval + '" min="0" max="23"/>h' +
            '<input role="minute" type="number" value="' + minval + '" min="0" max="59"/>' +
            'min<input role="second" type="number" value="' + secval + '" min="0" max="59"/>sec</td>';
        res = res + '</tr><tr>';
        res = res + '<td>' + t('phonetrack', 'Altitude') + '</td>';
        res = res + '<td><input role="altitude" type="number" value="' + entry.altitude + '" min="-1"/></td>';
        res = res + '</tr><tr>';
        res = res + '<td>' + t('phonetrack', 'Precision') + '</td>';
        res = res + '<td><input role="precision" type="number" value="' + entry.accuracy + '" min="-1"/></td>';
        res = res + '</tr><tr>';
        res = res + '<td>' + t('phonetrack', 'Satellites') + '</td>';
        res = res + '<td><input role="satellites" type="number" value="' + entry.satellites + '" min="-1"/></td>';
        res = res + '</tr><tr>';
        res = res + '<td>' + t('phonetrack', 'Battery level') + '</td>';
        res = res + '<td><input role="battery" type="number" value="' + entry.batterylevel + '" min="-1" max="100"/></td>';
        res = res + '</tr><tr>';
        res = res + '<td>' + t('phonetrack', 'User agent') + '</td>';
        res = res + '<td><input role="useragent" type="text" value="' + entry.useragent + '" min="-1" max="100"/></td>';
        res = res + '</tr>';
        res = res + '</table>';
        res = res + '<button class="valideditpoint"><i class="fa fa-save" aria-hidden="true" style="color:blue;"></i> ' + t('phonetrack', 'Save') + '</button>';
        res = res + '<button class="deletepoint"><i class="fa fa-trash" aria-hidden="true" style="color:red;"></i> ' + t('phonetrack', 'Delete point') + '</button>';
        res = res + '<button class="movepoint"><i class="fa fa-arrows" aria-hidden="true" style="color:blue;"></i> ' + t('phonetrack', 'Move point') + '</button>';
        res = res + '<button class="canceleditpoint"><i class="fa fa-undo" aria-hidden="true" style="color:red;"></i> ' + t('phonetrack', 'Cancel') + '</button>';
        return res;
    }

    function getPointTooltipContent(entry, sn) {
        var mom;
        var pointtooltip = t('phonetrack', 'session') + ' ' + sn +
            ' | ' + t('phonetrack', 'device') + ' ' + entry.deviceid + '';
        if (entry.timestamp) {
            mom = moment.unix(parseInt(entry.timestamp));
            pointtooltip = pointtooltip + '<br/>' + t('phonetrack', 'Date') +
                ' : ' + mom.format('YYYY-MM-DD HH:mm:ss (Z)');
        }
        if (entry.altitude && parseInt(entry.altitude) !== -1) {
            pointtooltip = pointtooltip + '<br/>' +
                t('phonetrack', 'Altitude') + ' : ' + entry.altitude;
        }
        if (entry.accuracy && parseInt(entry.accuracy) !== -1) {
            pointtooltip = pointtooltip + '<br/>' +
                t('phonetrack', 'Precision') + ' : ' + entry.accuracy;
        }
        if (entry.satellites && parseInt(entry.satellites) !== -1) {
            pointtooltip = pointtooltip + '<br/>' +
                t('phonetrack', 'Satellites') + ' : ' + entry.satellites;
        }
        if (entry.batterylevel && parseInt(entry.batterylevel) !== -1) {
            pointtooltip = pointtooltip + '<br/>' +
                t('phonetrack', 'Battery level') + ' : ' + entry.batterylevel;
        }
        if (entry.useragent && entry.useragent !== '' && entry.useragent !== 'nothing') {
            pointtooltip = pointtooltip + '<br/>' +
                t('phonetrack', 'User agent') + ' : ' + entry.useragent;
        }

        return pointtooltip;
    }

    function showHideSelectedSessions() {
        var token, d, displayedLatlngs, sessionname;
        var displayedMarkers = [];
        var viewLines = $('#viewmove').is(':checked');
        $('.watchbutton i').each(function() {
            token = $(this).parent().parent().parent().attr('token');
            sessionname = getSessionName(token);
            if ($(this).hasClass('fa-eye')) {
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
                        }
                    }
                }
                for (d in phonetrack.sessionMarkerLayers[token]) {
                    updateMarker(token, d, sessionname);
                    displayedLatlngs = phonetrack.sessionLineLayers[token][d].getLatLngs();
                    if (displayedLatlngs.length !== 0) {
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
    }

    function zoomOnDisplayedMarkers(selectedSessionToken='') {
        var token, d;
        var markersToZoomOn = [];

        // first we check if there are devices selected for zoom
        var devicesToFollow = {};
        var nbDevicesToFollow = 0
        $('.followdevice:checked').each(function() {
            // we only take those for session which are watched
            var viewSessionCheck = $(this).parent().parent().parent().find('.watchbutton i');
            if (viewSessionCheck.hasClass('fa-eye')) {
                var token = $(this).parent().parent().attr('token');
                var device = $(this).parent().attr('device');
                if (!devicesToFollow.hasOwnProperty(token)) {
                    devicesToFollow[token] = [];
                }
                devicesToFollow[token].push(device);
                nbDevicesToFollow++;
            }
        });

        $('.watchbutton i').each(function() {
            token = $(this).parent().parent().parent().attr('token');
            if ($(this).hasClass('fa-eye') && (selectedSessionToken === '' || token === selectedSessionToken)) {
                for (d in phonetrack.sessionMarkerLayers[token]) {
                    // if no device is followed => all devices are taken
                    // if some devices are followed, just take them
                    if (nbDevicesToFollow === 0
                        || (devicesToFollow.hasOwnProperty(token) && devicesToFollow[token].indexOf(d) !== -1)
                    ) {
                        markersToZoomOn.push(phonetrack.sessionMarkerLayers[token][d].getLatLng());
                    }
                }
            }
        });

        // ZOOM
        if (markersToZoomOn.length > 0) {
            phonetrack.map.fitBounds(markersToZoomOn, {
                animate: true,
                maxZoom: 16,
                paddingTopLeft: [parseInt($('#sidebar').css('width')),0]}
            );
        }
    }

    function changeTooltipStyle() {
        var perm = $('#showtime').is(':checked');
        var s, d, m, t;
        for (s in phonetrack.sessionMarkerLayers) {
            for (d in phonetrack.sessionMarkerLayers[s]) {
                m = phonetrack.sessionMarkerLayers[s][d];
                t = m.getTooltip()._content;
                m.unbindTooltip();
                m.bindTooltip(t, {permanent: perm, offset: offset, className: 'tooltip' + s + d.replace(' ', '')});
            }
        }
    }

    function importSession(path) {
        if (! endsWith(path, '.gpx')) {
            OC.Notification.showTemporary(t('phonetrack', 'File extension must be \'.gpx\' to be imported'));
        }
        else {
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
                    addSession(response.token, response.sessionName, response.publicviewtoken, [], 1);
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
            }).fail(function() {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to import session'));
            });
        }
    }

    function saveAction(name, token, targetPath) {
        var req = {
            name: name,
            token: token,
            target: targetPath
        };
        var url = OC.generateUrl('/apps/phonetrack/export');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done) {
                OC.Notification.showTemporary(t('phonetrack', 'Successfully exported session in') +
                    ' ' + targetPath + '/' + name + '.gpx');
            }
            else {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to export session'));
            }
        }).always(function() {
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

    function toggleLineDevice(elem) {
        var viewmove = $('#viewmove').is(':checked');
        var d = elem.parent().attr('device');
        var s = elem.parent().attr('token');
        var id;

        // line points
        if (viewmove) {
            if (phonetrack.map.hasLayer(phonetrack.sessionLineLayers[s][d])) {
                phonetrack.sessionLineLayers[s][d].remove();
                elem.addClass('off').removeClass('on');
            }
            else{
                phonetrack.sessionLineLayers[s][d].addTo(phonetrack.map);
                elem.addClass('on').removeClass('off');
            }
        }
    }

    function toggleDetailDevice(elem) {
        var viewmove = $('#viewmove').is(':checked');
        var d = elem.parent().attr('device');
        var s = elem.parent().attr('token');
        var id;

        // line points
        if (viewmove) {
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
        }
        // marker
        if (!pageIsPublic()
            && !isSessionShared(s)
            && phonetrack.map.hasLayer(phonetrack.sessionMarkerLayers[s][d])
        ) {
            if (elem.hasClass('off')) {
                phonetrack.sessionMarkerLayers[s][d].unbindPopup();
                phonetrack.sessionMarkerLayers[s][d].dragging.disable();
            }
            else {
                if ($('#dragcheck').is(':checked')) {
                    // if marker is displayed (not filtered)
                        phonetrack.sessionMarkerLayers[s][d].dragging.enable();
                    }
                var sessionname = getSessionName(s);
                var mid = phonetrack.sessionMarkerLayers[s][d].getLatLng().alt;
                var mentry = phonetrack.sessionPointsEntriesById[s][d][mid];

                phonetrack.sessionMarkerLayers[s][d].bindPopup(
                    getPointPopup(s, d, mentry, sessionname),
                    {closeOnClick: false}
                );
            }
        }
    }

    function zoomOnDevice(elem) {
        var id, dd, t, b, l;
        var perm = $('#showtime').is(':checked');
        var viewmove = $('#viewmove').is(':checked');
        var d = elem.parent().attr('device');
        var s = elem.parent().attr('token');
        var m = phonetrack.sessionMarkerLayers[s][d];

        // if we show movement lines :
        // bring it to front, show/hide points
        // get correct zoom bounds
        if (viewmove) {
            l = phonetrack.sessionLineLayers[s][d];
            l.bringToFront();

            b = l.getBounds();
        }
        else {
            b = L.latLngBounds(m.getLatLng(), m.getLatLng);
        }
        phonetrack.map.fitBounds(b, {
            animate: true,
            maxZoom: 16,
            paddingTopLeft: [parseInt($('#sidebar').css('width')),0]
        });

        for (id in phonetrack.sessionPointsLayersById[s][d]) {
            phonetrack.sessionPointsLayersById[s][d][id].setZIndexOffset(phonetrack.lastZindex);
        }
        phonetrack.lastZindex++;

        m.setZIndexOffset(phonetrack.lastZindex++);
        t = m.getTooltip()._content;
        m.unbindTooltip();
        m.bindTooltip(t, {permanent: perm, offset: offset, className: 'opaquetooltip' + s + d.replace(' ', ''), opacity: 1});
    }

    function hideAllDropDowns() {
        var dropdowns = document.getElementsByClassName('dropdown-content');
        var i;
        for (i = 0; i < dropdowns.length; i++) {
            var openDropdown = dropdowns[i];
            if (openDropdown.classList.contains('show')) {
                openDropdown.classList.remove('show');
            }
        }
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
            OC.Notification.showTemporary(t('phonetrack', 'Failed to add user share'));
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
            OC.Notification.showTemporary(t('phonetrack', 'Failed to delete user share'));
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
            OC.Notification.showTemporary(t('phonetrack', 'Failed to get user list'));
        });
    }

    function updateStatTable() {
        var s, d, id, dist, time, i, ll, t1, t2;
        var diff, years, days, hours, minutes, seconds;
        var table = '';
        for (s in phonetrack.sessionLineLayers) {
            // if session is watched
            if ($('div.session[token='+s+'] .watchbutton i').hasClass('fa-eye')) {
                table = table + '<h3>' + getSessionName(s) + '</h3>';
                table = table + '<table class="stattable"><tr><th>device name</th><th>distance (km)</th><th>time</th></tr>';
                for (d in phonetrack.sessionLineLayers[s]) {
                    ll = phonetrack.sessionLineLayers[s][d].getLatLngs();
                    dist = 0;
                    for (i = 1; i < ll.length; i++) {
                        dist = dist + phonetrack.map.distance(ll[i-1], ll[i]);
                    }

                    if (ll.length > 1) {
                        t1 = moment.unix(phonetrack.sessionPointsEntriesById[s][d][ll[0].alt].timestamp);
                        t2 = moment.unix(phonetrack.sessionPointsEntriesById[s][d][ll[ll.length-1].alt].timestamp);
                        diff = t2.diff(t1, 'seconds');
                        years = 0;
                        days = 0;
                        // if more than one year
                        if (diff >= 31536000) {
                            years = Math.floor(diff / 31536000);
                        }
                        // if more than one day
                        if (diff >= 86400) {
                            days = Math.floor((diff % 31536000) / 86400);
                        }
                        hours = Math.floor((diff % 86400) / 3600);
                        minutes = Math.floor((diff % 3600) / 60);
                        seconds = Math.floor(diff % 60);
                    }
                    else {
                        years = days = hours = minutes = seconds = 0;
                    }

                    table = table + '<tr><td class="roundmarker color' + s + d.replace(' ', '') +'">'+escapeHTML(d)+'</td>';
                    table = table + '<td>'+formatDistance(dist)+'</td>';
                    table = table + '<td>';
                    if (years > 0) {
                        table = table + years + ' ' + t('phonetrack', 'years') + ' ';
                    }
                    if (days > 0) {
                        table = table + days + ' ' + t('phonetrack', 'days') + ' ';
                    }
                    table = table + pad(hours) + ':' + pad(minutes) + ':' + pad(seconds) + '</td></tr>';
                }
                table = table + '</table>';
            }
        }
        $('#statdiv').html(table);
    }

    function formatDistance(d) {
        return (d / 1000).toFixed(2);
    }

    //////////////// MAIN /////////////////////

    $(document).ready(function() {
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

        $('body').on('click','.watchbutton', function(e) {
            if (!pageIsPublic()) {
                var icon = $(this).find('i');
                if (icon.hasClass('fa-eye')) {
                    icon.addClass('fa-eye-slash').removeClass('fa-eye');
                    $(this).parent().parent().find('.devicelist').slideUp('slow');
                    $(this).parent().parent().find('.sharediv').slideUp('slow');
                    $(this).parent().parent().find('.moreUrls').slideUp('slow');
                    $(this).parent().parent().find('.toggleDetail').addClass('off').removeClass('on');
                    $(this).parent().parent().find('.toggleLineDevice').addClass('on').removeClass('off');
                }
                else {
                    icon.addClass('fa-eye').removeClass('fa-eye-slash');
                    $(this).parent().parent().find('.devicelist').slideDown('slow');
                }
                phonetrack.currentTimer.pause();
                phonetrack.currentTimer = null;
                refresh();
            }
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
                }
            }
        });

        $('#autozoom').click(function() {
            if (!pageIsPublic()) {
                saveOptions();
            }
            if ($(this).is(':checked')) {
                phonetrack.zoomButton.state('zoom');
                $(phonetrack.zoomButton.button).addClass('easy-button-green').removeClass('easy-button-red');
            }
            else {
                phonetrack.zoomButton.state('nozoom');
                $(phonetrack.zoomButton.button).addClass('easy-button-red').removeClass('easy-button-green');
            }
        });

        $('#showtime').click(function() {
            changeTooltipStyle();
            if (!pageIsPublic()) {
                saveOptions();
            }
            if ($(this).is(':checked')) {
                phonetrack.timeButton.state('showtime');
                $(phonetrack.timeButton.button).addClass('easy-button-green').removeClass('easy-button-red');
            }
            else {
                phonetrack.timeButton.state('noshowtime');
                $(phonetrack.timeButton.button).addClass('easy-button-red').removeClass('easy-button-green');
            }
        });

        $('#acccirclecheck').click(function() {
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
                $(phonetrack.moveButton.button).addClass('easy-button-green').removeClass('easy-button-red');
            }
            else {
                phonetrack.moveButton.state('nomove');
                $(phonetrack.moveButton.button).addClass('easy-button-red').removeClass('easy-button-green');
            }
        });

        $('body').on('change', '#updateinterval', function() {
            if (!pageIsPublic()) {
                saveOptions();
            }
        });

        $('body').on('click', '.export', function() {
            var name = $(this).parent().parent().find('.sessionBar .sessionName').text();
            var token = $(this).parent().parent().attr('token');
            var filename = name + '.gpx';
            OC.dialogs.filepicker(
                t('phonetrack', 'Where to save') +
                    ' <b>' + filename + '</b>',
                function(targetPath) {
                    saveAction(name, token, targetPath);
                },
                false, 'httpd/unix-directory', true
            );
        });

        $('body').on('click', 'button.zoomsession', function(e) {
            var sessionName = $(this).parent().parent().attr('token');
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
            zoomOnDevice($(this));
        });

        $('body').on('click', 'ul.devicelist li .toggleDetail', function(e) {
            toggleDetailDevice($(this));
        });

        $('body').on('click', 'ul.devicelist li .toggleLineDevice', function(e) {
            toggleLineDevice($(this));
        });

        $('body').on('click','.moreUrlsButton', function(e) {
            var urlDiv = $(this).parent().parent().find('.moreUrls');
            var sharediv = $(this).parent().parent().find('.sharediv')
            var editdiv = $(this).parent().parent().find('.editsessiondiv')
            if (urlDiv.is(':visible')) {
                urlDiv.slideUp('slow');
            }
            else{
                urlDiv.slideDown('slow').css('display', 'grid');
                sharediv.slideUp('slow');
                editdiv.slideUp('slow');
            }
        });

        $('body').on('click','.sharesession', function(e) {
            var sharediv = $(this).parent().parent().find('.sharediv')
            var moreurldiv = $(this).parent().parent().find('.moreUrls')
            var editdiv = $(this).parent().parent().find('.editsessiondiv')
            if (sharediv.is(':visible')) {
                sharediv.slideUp('slow');
            }
            else {
                sharediv.slideDown('slow');
                moreurldiv.slideUp('slow');
                editdiv.slideUp('slow');
            }
        });

        $('body').on('click','.deleteDevice', function(e) {
            var sessionName = $(this).parent().parent().parent().find('.sessionBar .sessionName').text();
            var token = $(this).attr('token');
            var device = $(this).attr('device');
            OC.dialogs.confirm(
                t('phonetrack',
                    'Are you sure you want to delete the device {device} ?',
                    {device: device}
                ),
                t('phonetrack','Confirm device deletion'),
                function (result) {
                    if (result) {
                        deleteDevice(token, device, sessionName);
                    }
                },
                true
            );
        });

        $('body').on('click','.editsessionbutton', function(e) {
            var editdiv = $(this).parent().parent().find('.editsessiondiv');
            if (editdiv.is(':visible')) {
                editdiv.slideUp('slow');
            }
            else {
                editdiv.slideDown('slow');
            }
            var urldiv = $(this).parent().parent().find('.moreUrls');
            if (urldiv.is(':visible')) {
                urldiv.slideUp('slow');
            }
            var sharediv = $(this).parent().parent().find('.sharediv');
            if (sharediv.is(':visible')) {
                sharediv.slideUp('slow');
            }
        });

        $('body').on('click','.editsessionok', function(e) {
            var token = $(this).parent().parent().attr('token');
            var oldname = $(this).parent().parent().find('.sessionBar .sessionName').text();
            var newname = $(this).parent().find('input[role=editsessioninput]').val();
            renameSession(token, oldname, newname);
            var editdiv = $(this).parent().parent().find('.editsessiondiv');
            editdiv.slideUp('slow');
        });

        $('body').on('click','.editsessioncancel', function(e) {
            var editdiv = $(this).parent().parent().find('.editsessiondiv');
            editdiv.slideUp('slow');
        });

        $('body').on('click','.publicsessionbutton', function(e) {
            var buttext = $(this).find('b');
            var icon = $(this).find('i');
            var pub = icon.hasClass('fa-eye-slash');
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
                    if (pub) {
                        icon.addClass('fa-eye').removeClass('fa-eye-slash');
                        buttext.text(t('phonetrack', 'Make session private'));
                        $('.session[token="' + token + '"]').find('.publicWatchUrlDiv').slideDown();
                    }
                    else {
                        icon.addClass('fa-eye-slash').removeClass('fa-eye');
                        buttext.text(t('phonetrack', 'Make session public'));
                        $('.session[token="' + token + '"]').find('.publicWatchUrlDiv').slideUp();
                    }
                }
                else if (response.done === 2) {
                    OC.Notification.showTemporary(t('phonetrack', 'Failed to toggle session public, session does not exist'));
                }
            }).fail(function() {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to toggle session public'));
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
            var deviceid = tab.attr('deviceid');
            var pointid = tab.attr('pid');
            // unchanged latlng
            var lat = phonetrack.sessionPointsEntriesById[token][deviceid][pointid].lat;
            var lon = phonetrack.sessionPointsEntriesById[token][deviceid][pointid].lon;
            var alt = parseInt(tab.find('input[role=altitude]').val());
            var acc = parseInt(tab.find('input[role=precision]').val());
            var sat = parseInt(tab.find('input[role=satellites]').val());
            var bat = parseInt(tab.find('input[role=battery]').val());
            var useragent = tab.find('input[role=useragent]').val();
            var datestr = tab.find('input[role=date]').val();
            var hourstr = parseInt(tab.find('input[role=hour]').val());
            var minstr = parseInt(tab.find('input[role=minute]').val());
            var secstr = parseInt(tab.find('input[role=second]').val());
            var completeDateStr = datestr + ' ' + pad(hourstr) + ':' + pad(minstr) + ':' + pad(secstr);
            var mom = moment(completeDateStr);
            var timestamp = mom.unix();
            editPointDB(token, deviceid, pointid, lat, lon, alt, acc, sat, bat, timestamp, useragent);
        });

        $('body').on('click','.deletepoint', function(e) {
            var tab = $(this).parent().find('table');
            var s = tab.attr('token');
            var d = tab.attr('deviceid');
            var pid = tab.attr('pid');
            deletePointDB(s, d, pid);
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
        });
        changeApplyFilter();

        window.onclick = function(event) {
            if (!event.target.matches('.dropdownbutton') && !event.target.matches('.dropdownbutton i')) {
                hideAllDropDowns();
            }
        }

        $('body').on('click','.dropdownbutton', function(e) {
            var dcontent;
            if (e.target.nodeName === 'BUTTON') {
                dcontent = $(e.target).parent().parent().find('.dropdown-content');
            }
            else {
                dcontent = $(e.target).parent().parent().parent().find('.dropdown-content');
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

        $('button[role=datemintoday]').click(function() {
            var mom = moment();
            $('input[role=datemin]').val(mom.format('YYYY-MM-DD'));
            changeApplyFilter();
        });

        $('button[role=datemaxtoday]').click(function() {
            var mom = moment();
            $('input[role=datemax]').val(mom.format('YYYY-MM-DD'));
            changeApplyFilter();
        });

        $('button[role=dateminplus]').click(function() {
            var mom = moment($('input[role=datemin]').val());
            mom.add(1, 'days');
            $('input[role=datemin]').val(mom.format('YYYY-MM-DD'));
            changeApplyFilter();
        });

        $('button[role=dateminminus]').click(function() {
            var mom = moment($('input[role=datemin]').val());
            mom.subtract(1, 'days');
            $('input[role=datemin]').val(mom.format('YYYY-MM-DD'));
            changeApplyFilter();
        });

        $('button[role=datemaxplus]').click(function() {
            var mom = moment($('input[role=datemax]').val());
            mom.add(1, 'days');
            $('input[role=datemax]').val(mom.format('YYYY-MM-DD'));
            changeApplyFilter();
        });

        $('button[role=datemaxminus]').click(function() {
            var mom = moment($('input[role=datemax]').val());
            mom.subtract(1, 'days');
            $('input[role=datemax]').val(mom.format('YYYY-MM-DD'));
            changeApplyFilter();
        });

        $('button[role=dateminmaxplus]').click(function() {
            var mom = moment($('input[role=datemin]').val());
            mom.add(1, 'days');
            $('input[role=datemin]').val(mom.format('YYYY-MM-DD'));

            mom = moment($('input[role=datemax]').val());
            mom.add(1, 'days');
            $('input[role=datemax]').val(mom.format('YYYY-MM-DD'));

            changeApplyFilter();
        });

        $('button[role=dateminmaxminus]').click(function() {
            var mom = moment($('input[role=datemin]').val());
            mom.subtract(1, 'days');
            $('input[role=datemin]').val(mom.format('YYYY-MM-DD'));

            mom = moment($('input[role=datemax]').val());
            mom.subtract(1, 'days');
            $('input[role=datemax]').val(mom.format('YYYY-MM-DD'));

            changeApplyFilter();
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
        });
        $('#togglestats').prop('checked', false);

        $('body').on('change', '#colorinput', function(e) {
            okColor();
        });
        $('body').on('click', '.devicelist .devicecolor', function(e) {
            var s = $(this).parent().attr('token');
            var d = $(this).parent().attr('device');
            showColorPicker(s, d);
        });

        var radius = $('#pointradius').val();
        var diam = 2 * radius;
        $('<style role="roundmarker">.roundmarker { ' +
            'width: ' + diam + 'px !important;' +
            'height: ' + diam + 'px !important;' +
            'line-height: ' + diam + 'px;' +
            '}</style>').appendTo('body');

        $('#pointradius').change(function() {
            if (!pageIsPublic()) {
                saveOptions();
            }
            var radius = $(this).val();
            var diam = 2 * radius;
            $('style[role=roundmarker]').html(
                '.roundmarker { ' +
                'width: ' + diam + 'px !important;' +
                'height: ' + diam + 'px !important;' +
                'line-height: ' + diam + 'px;' +
                '}</style>'
            );
            // change iconanchor
            var s, d, pid, icon, iconMarker;
            for (s in phonetrack.sessionMarkerLayers) {
                for (d in phonetrack.sessionMarkerLayers[s]) {
                    iconMarker = L.divIcon({
                        iconAnchor: [radius, radius],
                        className: 'roundmarker color' + s + d.replace(' ', ''),
                        html: '<b>' + d[0] + '</b>'
                    });
                    phonetrack.sessionMarkerLayers[s][d].setIcon(iconMarker);

                    icon = L.divIcon({
                        iconAnchor: [radius, radius],
                        className: 'roundmarker color' + s + d.replace(' ', ''),
                        html: ''
                    });
                    for (pid in phonetrack.sessionPointsLayersById[s][d]) {
                        phonetrack.sessionPointsLayersById[s][d][pid].setIcon(icon);
                    }
                }
            }
        });

        $('#pointlinealpha').change(function() {
            if (!pageIsPublic()) {
                saveOptions();
            }
            var opacity = $(this).val();
            var s, d, styletxt;
            for (s in phonetrack.sessionMarkerLayers) {
                for (d in phonetrack.sessionMarkerLayers[s]) {
                    styletxt = $('style[tokendevice="' + s + d + '"]').html();
                    styletxt = styletxt.replace(/rgba\((\d+), (\d+), (\d+), (\d+(\.\d+)?)\)/, 'rgba($1, $2, $3, ' + opacity + ')');
                    styletxt = styletxt.replace(/opacity: (\d+(\.\d+)?);/, 'opacity: ' + opacity + ';');
                    $('style[tokendevice="' + s + d + '"]').html(styletxt);
                }
            }
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
            var name = $('#publicsessionname').text();
            phonetrack.publicName = name;
            addSession(token, name, publicviewtoken, null, [], true);
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
            $(phonetrack.zoomButton.button).addClass('easy-button-green').removeClass('easy-button-red');

            if (pageIsPublicSessionWatch()) {
                $('#sidebar').toggleClass('collapsed');
                $('div#header').hide();
                $('div#content-wrapper').css('padding-top', '0px');
            }
        }

        refresh();

    }

})(jQuery, OC);
