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
        lastZindex: 1000
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
            zoomControl: true,
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
                    $('#zoomallbutton').click();
                }
            }]
        });
        phonetrack.doZoomButton.addTo(phonetrack.map);
    }

    /*
     * get key events
     */
    function checkKey(e) {
        e = e || window.event;
        var kc = e.keyCode;
        console.log(kc);

        if (kc === 60 || kc === 220) {
            e.preventDefault();
            $('#sidebar').toggleClass('collapsed');
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
                if (optionsValues.showtime !== undefined) {
                    $('#showtime').prop('checked', optionsValues.showtime);
                }
                if (optionsValues.autozoom !== undefined) {
                    $('#autozoom').prop('checked', optionsValues.autozoom);
                }
                if (optionsValues.viewmove !== undefined) {
                    $('#viewmove').prop('checked', optionsValues.viewmove);
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
        optionsValues.viewmove = $('#viewmove').is(':checked');
        optionsValues.autozoom = $('#autozoom').is(':checked');
        optionsValues.showtime = $('#showtime').is(':checked');
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
                addSession(response.token, sessionName, response.publicviewtoken, 1);
            }
            else if (response.done === 2) {
                OC.Notification.showTemporary(t('phonetrack', 'Session name already used'));
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to create session'));
        });
    }

    function addSession(token, name, publicviewtoken, isPublic, selected=false) {
        $('#addPointSession').append('<option value="' + name + '" token="' + token + '">' + name + '</option>');
        var selhtml = '';
        if (selected) {
            selhtml = ' checked="checked"';
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

        var divtxt = '<div class="session" token="' + token + '" publicviewtoken="' + publicviewtoken + '">';
        divtxt = divtxt + '<h3 class="sessionTitle"><b>' + name + '</b> <button class="zoomsession" ' +
            'title="' + t('phonetrack', 'Zoom on this session') + '">' +
            '<i class="fa fa-search" style="color:blue; font-size:18px"></i></button>';
        if (!pageIsPublic()) {
            divtxt = divtxt + '<button class="editsessionbutton" title="' + t('phonetrack', 'Rename session') + '">' +
                '<i class="fa fa-edit" style="color:blue; font-size:18px"></i></button>';
        }
        divtxt = divtxt + '</h3>';
        if (!pageIsPublic()) {
            divtxt = divtxt + '<div class="editsessiondiv">' +
                '<input role="editsessioninput" type="text" value="' + name + '"/>' +
                '<button class="editsessionok"><i class="fa fa-check" style="color:green;"></i> ' +
                t('phonetrack', 'Rename') + '</button>' +
                '<button class="editsessioncancel"><i class="fa fa-undo" style="color:red;"></i> ' +
                t('phonetrack', 'Cancel') + '</button>' +
                '</div>';
        }
        if (!pageIsPublicWebLog()) {
            divtxt = divtxt + '<p>' + t('phonetrack', 'Public watch URL') + ' :</p>';
            divtxt = divtxt + '<input class="ro" role="publicWatchUrl" type="text" value="' + publicWatchUrl + '"></input>';
        }
        if (!pageIsPublicSessionWatch()) {
            divtxt = divtxt + '<p class="moreUrlsButton"><label>' + t('phonetrack', 'More URLs') +
                '</label> <i class="fa fa-angle-double-down"></i></p>';
            divtxt = divtxt + '<div class="moreUrls">';
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
        if (!pageIsPublic()) {
            var titlePublic = t('phonetrack', 'If session is not public, position are not showed in public browser logging page');
            divtxt = divtxt + '<label for="publicsessioncheck'+token+'" title="' + titlePublic + '">' +
                t('phonetrack', 'Public session') +'</label>';
            var checked = '';
            if (parseInt(isPublic) === 1) {
                checked = ' checked="checked"';
            }
            divtxt = divtxt + '<input type="checkbox"' + checked + ' title="' + titlePublic +
                '" id="publicsessioncheck' + token + '" class="publicsessioncheck"/>';
            divtxt = divtxt + '<button class="removeSession"><i class="fa fa-trash" aria-hidden="true"></i> ' +
                t('phonetrack', 'Delete session') + '</button>';
            divtxt = divtxt + '<button class="export"><i class="fa fa-floppy-o" aria-hidden="true" style="color:blue;"></i> ' + t('phonetrack', 'Export to gpx') +
                '</button>';
        }
        divtxt = divtxt + '<div class="watchlabeldiv"><label class="watchlabel" for="watch' + token + '">' +
            '<i class="fa fa-eye" aria-hidden="true" style="color:blue;"></i> ' +
            t('phonetrack', 'Watch this session') + '</label>' +
            '<input type="checkbox" class="watchSession" id="watch' + token + '" '+
            'token="' + token + '"' + selhtml + '/></div>';
        divtxt = divtxt + '<ul class="devicelist" token="' + token + '"></ul></div>';

        $('div#sessions').append($(divtxt).fadeIn('slow').css('display', 'grid')).find('input.ro[type=text]').prop('readonly', true);
        $('.session[token="' + token + '"]').find('.moreUrls').hide();
        $('.session[token="' + token + '"]').find('.editsessiondiv').hide();
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
        phonetrack.sessionPointsLayers[token][device].unbindTooltip().remove();
        delete phonetrack.sessionPointsLayers[token][device];
    }

    function removeSession(div) {
        var token = div.attr('token');
        $('#addPointSession option[token=' + token + ']').remove();
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
        var perm = $('#showtime').is(':checked');
        var d, to, p;
        $('.session[token='+token+'] .sessionTitle b').text(newname);
        for (d in phonetrack.sessionMarkerLayers[token]) {
            // marker tooltip
            to = phonetrack.sessionMarkerLayers[token][d].getTooltip()._content;
            to = to.replace(
                t('phonetrack', 'session') + ' ' + oldname,
                t('phonetrack', 'session') + ' ' + newname
            );
            phonetrack.sessionMarkerLayers[token][d].unbindTooltip();
            phonetrack.sessionMarkerLayers[token][d].bindTooltip(to, {permanent: perm, offset: offset, className: 'tooltip' + phonetrack.sessionColors[token + d]});
            // marker popup
            p = phonetrack.sessionMarkerLayers[token][d].getPopup().getContent();
            phonetrack.sessionMarkerLayers[token][d].unbindPopup();
            p = p.replace('sessionname="' + oldname + '"', 'sessionname="' + newname + '"');
            phonetrack.sessionMarkerLayers[token][d].bindPopup(p, {closeOnClick: false});

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
                    className: 'tooltip' + phonetrack.sessionColors[token + d]
                }
            );
            phonetrack.sessionPointsLayers[token][d].eachLayer(function(l) {
                // line points tooltips
                to = l.getTooltip()._content;
                to = to.replace(
                    t('phonetrack', 'session') + ' ' + oldname,
                    t('phonetrack', 'session') + ' ' + newname
                );
                l.unbindTooltip();
                l.bindTooltip(to, {permanent: false, offset: offset, className: 'tooltip' + phonetrack.sessionColors[token + d]});

                // line points popups
                p = l.getPopup().getContent();
                l.unbindPopup();
                p = p.replace('sessionname="' + oldname + '"', 'sessionname="' + newname + '"');
                l.bindPopup(p, {closeOnClick: false});
            });
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
                    addSession(response.sessions[s][1], response.sessions[s][0], response.sessions[s][2], response.sessions[s][3]);
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
        $('.watchSession:checked').each(function() {
            var token = $(this).attr('token');
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

    function displayNewPoints(sessions) {
        var s, i, d, entry, device, timestamp, mom, icon,
            markertooltip, colorn, rgbc,
            textcolor, sessionname;
        var perm = $('#showtime').is(':checked');
        for (s in sessions) {
            sessionname = $('div.session[token="' + s + '"] .sessionTitle b').text()
            if (! phonetrack.sessionLineLayers.hasOwnProperty(s)) {
                phonetrack.sessionLineLayers[s] = {};
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
                    appendEntryToDevice(s, d, entry, sessionname);
                }
                // move/create marker
                // entry is the last point for the current device
                if (! phonetrack.sessionMarkerLayers[s].hasOwnProperty(d)) {
                    icon = L.divIcon({
                        iconAnchor: [8, 8],
                        className: 'color' + phonetrack.sessionColors[s + d],
                        html: '<b>' + d[0] + '</b>'
                    });

                    phonetrack.sessionMarkerLayers[s][d] = L.marker([entry.lat, entry.lon, entry.id], {icon: icon});
                }
                else {
                    phonetrack.sessionMarkerLayers[s][d].setLatLng([entry.lat, entry.lon, entry.id]);
                }
                phonetrack.sessionMarkerLayers[s][d].unbindTooltip();
                markertooltip = getPointTooltipContent(entry, sessionname);
                phonetrack.sessionMarkerLayers[s][d].bindTooltip(
                    markertooltip,
                    {permanent: perm, offset: offset, className: 'tooltip' + phonetrack.sessionColors[s + d]}
                );
                if (!pageIsPublic()) {
                    phonetrack.sessionMarkerLayers[s][d].unbindPopup();
                    phonetrack.sessionMarkerLayers[s][d].bindPopup(getPointPopup(s, d, entry, sessionname), {closeOnClick: false});
                }
            }
        }
        // in case user click is between ajax request and response
        showHideSelectedSessions();
    }

    function addDevice(s, d, sessionname) {
        var colorn, textcolor, rgbc, linetooltip;
        colorn = ++lastColorUsed % colorCode.length;
        phonetrack.sessionColors[s + d] = colorn;
        rgbc = hexToRgb(colorCode[colorn]);
        textcolor = 'black';
        if (rgbc.r + rgbc.g + rgbc.b < 3 * 80) {
            textcolor = 'white';
        } 
        $('<style track="' + d + '">.color' + colorn + ' { ' +
            'background: rgba(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ', 0.8);' +
                'color: ' + textcolor + '; font-weight: bold;' +
                'text-align: center;' +
                'width: 16px !important;' +
                'height: 16px !important;' +
                'border-radius: 50%;' +
                'line-height:16px;' +
                ' }' +
                '.tooltip' + colorn + ' {' +
                'background: rgba(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ', 0.5);' +
                'color: ' + textcolor + '; font-weight: bold; }' +
                '.opaquetooltip' + colorn + ' {' +
                'background: rgba(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ', 1);' +
                'color: ' + textcolor + '; font-weight: bold;' +
                '}</style>').appendTo('body');
        var deleteLink = '';
        if (!pageIsPublic()) {
            deleteLink = ' <i class="fa fa-trash deleteDevice" token="' + s + '" aria-hidden="true" title="' +
                t('phonetrack', 'Delete this device') +
                '" device="' + d + '"></i>';
        }
        $('div.session[token="' + s + '"] ul.devicelist').append(
            '<li device="' + d + '" token="' + s + '" style="font-weight: bold; color: ' + textcolor + ';' +
                'background-color:' + colorCode[colorn] + ';"' +
                ' title="' + t('phonetrack', 'Center map on device') + ' ' +
                d + '"><input class="followdevice" type="checkbox" ' +
                'title="' + t('phonetrack', 'Follow this device (autozoom)') +
                '"/><label class="deviceLabel">' + d + '</label> ' + deleteLink +
                '</li>');

        phonetrack.sessionPointsLayers[s][d] = L.featureGroup();
        phonetrack.sessionPointsLayersById[s][d] = {};
        phonetrack.sessionPointsEntriesById[s][d] = {};
        phonetrack.sessionLineLayers[s][d] = L.polyline([], {weight: 4, color: colorCode[colorn]});
        linetooltip = t('phonetrack', 'session') + ' ' + sessionname + ' | ' +
            t('phonetrack', 'device') + ' ' + d;
        phonetrack.sessionLineLayers[s][d].bindTooltip(
            linetooltip,
            {
                permanent: false,
                sticky: true,
                className: 'tooltip' + colorn
            }
        );
    }

    function appendEntryToDevice(s, d, entry, sessionname) {
        var timestamp, device, pointtooltip;
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
        phonetrack.sessionLineLayers[s][d].addLatLng([entry.lat, entry.lon, entry.id]);
        var m = L.circleMarker([entry.lat, entry.lon], {radius: 6, fillOpacity: 1, color: colorCode[phonetrack.sessionColors[s + d]]});
        m.bindTooltip(pointtooltip, {className: 'tooltip' + phonetrack.sessionColors[s + d]});
        phonetrack.sessionPointsEntriesById[s][d][entry.id] = entry;
        phonetrack.sessionPointsLayersById[s][d][entry.id] = m;
        phonetrack.sessionPointsLayers[s][d].addLayer(m);
        if (!pageIsPublic()) {
            m.bindPopup(getPointPopup(s, d, entry, sessionname), {closeOnClick: false});
        }
    }

    function editPointDB(but) {
        var tab = but.parent().find('table');
        var token = tab.attr('token');
        var deviceid = tab.attr('deviceid');
        var pointid = tab.attr('pid');
        var lat = tab.find('input[role=lat]').val();
        var lon = tab.find('input[role=lon]').val();
        var alt = tab.find('input[role=altitude]').val();
        var acc = tab.find('input[role=precision]').val();
        var sat = tab.find('input[role=satellites]').val();
        var bat = tab.find('input[role=battery]').val();
        var datestr = tab.find('input[role=date]').val();
        var hourstr = parseInt(tab.find('input[role=hour]').val());
        var minstr = parseInt(tab.find('input[role=minute]').val());
        var secstr = parseInt(tab.find('input[role=second]').val());
        var completeDateStr = datestr + ' ' + pad(hourstr) + ':' + pad(minstr) + ':' + pad(secstr);
        var mom = moment(completeDateStr);
        var timestamp = mom.unix();
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
            sat: sat
        };
        var url = OC.generateUrl('/apps/phonetrack/updatePoint');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done === 1) {
                updatePointMap(but);
            }
            else if (response.done === 2) {
                OC.Notification.showTemporary(t('phonetrack', 'The point you want to edit does not exist'));
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to edit point'));
        });
    }

    function updatePointMap(but) {
        var perm = $('#showtime').is(':checked');
        var tab = but.parent().find('table');
        var token = tab.attr('token');
        var deviceid = tab.attr('deviceid');
        var pointid = parseInt(tab.attr('pid'));
        var sessionname = tab.attr('sessionname');
        var lat = parseFloat(tab.find('input[role=lat]').val());
        var lon = parseFloat(tab.find('input[role=lon]').val());
        var alt = tab.find('input[role=altitude]').val();
        var acc = tab.find('input[role=precision]').val();
        var sat = tab.find('input[role=satellites]').val();
        var bat = tab.find('input[role=battery]').val();
        var datestr = tab.find('input[role=date]').val();
        var hourstr = parseInt(tab.find('input[role=hour]').val());
        var minstr = parseInt(tab.find('input[role=minute]').val());
        var secstr = parseInt(tab.find('input[role=second]').val());
        var completeDateStr = datestr + ' ' + pad(hourstr) + ':' + pad(minstr) + ':' + pad(secstr);
        var mom = moment(completeDateStr);
        var timestamp = mom.unix();
        var i;

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

        // update line point tooltip
        phonetrack.sessionPointsLayersById[token][deviceid][pointid].unbindTooltip();
        phonetrack.sessionPointsLayersById[token][deviceid][pointid].bindTooltip(
            getPointTooltipContent(entry, sessionname),
            {permanent: false, offset: offset, className: 'tooltip' + phonetrack.sessionColors[token + deviceid]}
        );

        // update line point popup
        phonetrack.sessionPointsLayersById[token][deviceid][pointid].unbindPopup();
        phonetrack.sessionPointsLayersById[token][deviceid][pointid].bindPopup(
            getPointPopup(token, deviceid, entry, sessionname),
            {closeOnClick: false}
        );
        // move line point
        if (move) {
            phonetrack.sessionPointsLayersById[token][deviceid][pointid].setLatLng([lat, lon, pointid]);
        }
        // set new line latlngs if moved or date was modified
        if (move || dateChanged) {
            var latlngs = phonetrack.sessionLineLayers[token][deviceid].getLatLngs();
            var newlatlngs = [];
            i = 0;
            // we copy until we get to the right place to insert moved point
            while (i < latlngs.length
                   && ( (parseInt(pointid) === parseInt(latlngs[i].alt))
                         || (timestamp > parseInt(phonetrack.sessionPointsEntriesById[token][deviceid][latlngs[i].alt].timestamp))
                      )
            ) {
                // we don't copy the edited point
                if (parseInt(pointid) !== parseInt(latlngs[i].alt)) {
                    // copy
                    newlatlngs.push([latlngs[i].lat, latlngs[i].lng, latlngs[i].alt]);
                }
                i++;
            }
            // put the edited point
            newlatlngs.push([lat, lon, pointid]);
            // if we are moving the marker and now it's not the last point anymore
            if (phonetrack.sessionMarkerLayers[token][deviceid].getLatLng().alt === parseInt(pointid)
                && i !== latlngs.length) {
                    markerIsNotAnymore = true;
            }
            // if this is now the last point, update marker and last time
            if (i === latlngs.length) {
                phonetrack.sessionMarkerLayers[token][deviceid].setLatLng([lat, lon, pointid]);
                phonetrack.lastTime[token][deviceid] = timestamp;
            }
            // finish the copy
            while (i < latlngs.length) {
                if (parseInt(pointid) !== parseInt(latlngs[i].alt)) {
                    // copy
                    newlatlngs.push([latlngs[i].lat, latlngs[i].lng, latlngs[i].alt]);
                }
                i++;
            }
            // modify line
            phonetrack.sessionLineLayers[token][deviceid].setLatLngs(newlatlngs);

            // if the marker was changed : move marker, adapt tooltip and popup
            if (markerIsNotAnymore) {
                var mla, mln, mid, mentry;
                mla = newlatlngs[newlatlngs.length - 1][0];
                mln = newlatlngs[newlatlngs.length - 1][1];
                mid = newlatlngs[newlatlngs.length - 1][2];
                mentry = phonetrack.sessionPointsEntriesById[token][deviceid][mid];
                phonetrack.lastTime[token][deviceid] = mentry.timestamp;
                phonetrack.sessionMarkerLayers[token][deviceid].setLatLng([mla, mln, mid]);

                // tooltip
                phonetrack.sessionMarkerLayers[token][deviceid].unbindTooltip();
                phonetrack.sessionMarkerLayers[token][deviceid].bindTooltip(
                    getPointTooltipContent(mentry, sessionname),
                    {permanent: perm, offset: offset, className: 'tooltip' + phonetrack.sessionColors[token + deviceid]}
                );
                // popup
                phonetrack.sessionMarkerLayers[token][deviceid].bindPopup(
                    getPointPopup(token, deviceid, mentry, sessionname),
                    {closeOnClick: false}
                );
            }
        }

        // if edited point is now the last point, update marker tooltip and popup
        if (phonetrack.sessionMarkerLayers[token][deviceid].getLatLng().alt === parseInt(pointid)) {
            // tooltip
            phonetrack.sessionMarkerLayers[token][deviceid].unbindTooltip();
            phonetrack.sessionMarkerLayers[token][deviceid].bindTooltip(
                getPointTooltipContent(entry, sessionname),
                {permanent: perm, offset: offset, className: 'tooltip' + phonetrack.sessionColors[token + deviceid]}
            );
            // popup
            phonetrack.sessionMarkerLayers[token][deviceid].bindPopup(
                getPointPopup(token, deviceid, entry, sessionname),
                {closeOnClick: false}
            );
            // move
            if (move) {
                phonetrack.sessionMarkerLayers[token][deviceid].setLatLng([lat, lon, pointid]);
            }
        }

        phonetrack.map.closePopup();
    }

    function deletePointDB(but) {
        var tab = but.parent().find('table');
        var token = tab.attr('token');
        var deviceid = tab.attr('deviceid');
        var pointid = tab.attr('pid');
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
                deletePointMap(but);
            }
            else if (response.done === 2) {
                OC.Notification.showTemporary(t('phonetrack', 'The point you want to delete does not exist'));
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to delete point'));
        });
    }

    function deletePointMap(but) {
        var perm = $('#showtime').is(':checked');
        var i, lat, lng, p;
        var tab = but.parent().find('table');
        var s = tab.attr('token');
        var d = tab.attr('deviceid');
        var sn = tab.attr('sessionname');
        var pid = tab.attr('pid');
        var intpid = parseInt(pid);
        var entry = phonetrack.sessionPointsEntriesById[s][d][pid];
        // remove associated point from sessionPointsLayers
        var m = phonetrack.sessionPointsLayersById[s][d][pid];
        phonetrack.sessionPointsLayers[s][d].removeLayer(m);

        // remove point in the line
        var latlngs = phonetrack.sessionLineLayers[s][d].getLatLngs();
        var newlatlngs = [];
        i = 0;
        while (latlngs[i].alt !== intpid) {
            newlatlngs.push([latlngs[i].lat, latlngs[i].lng, latlngs[i].alt]);
            i++;
        }
        i++;
        // if it was the last point, move marker and update lasttime
        if (i === latlngs.length) {
            lat = newlatlngs[i-2][0];
            lng = newlatlngs[i-2][1];
            p = newlatlngs[i-2][2]
            phonetrack.sessionMarkerLayers[s][d].setLatLng([lat, lng, p]);
            phonetrack.sessionMarkerLayers[s][d].unbindTooltip();
            phonetrack.sessionMarkerLayers[s][d].unbindPopup();
            phonetrack.sessionMarkerLayers[s][d].bindPopup(
                getPointPopup(s, d, phonetrack.sessionPointsEntriesById[s][d][p], sn),
                {closeOnClick: false}
            );
            phonetrack.sessionMarkerLayers[s][d].bindTooltip(
                getPointTooltipContent(phonetrack.sessionPointsEntriesById[s][d][p], sn),
                {permanent: perm, offset: offset, className: 'tooltip' + phonetrack.sessionColors[s + d]}
            );
            // update lasttime : new last point time
            phonetrack.lastTime[s][d] = phonetrack.sessionPointsEntriesById[s][d][p].timestamp;
        }
        // else we continue to copy the positions
        else {
            while (i < latlngs.length) {
                newlatlngs.push([latlngs[i].lat, latlngs[i].lng, latlngs[i].alt]);
                i++;
            }
        }
        phonetrack.sessionLineLayers[s][d].setLatLngs(newlatlngs);

        phonetrack.map.closePopup();
    }

    function addPointDB() {
        var tab = $('#addPointTable');
        var token = $('#addPointSession option:selected').attr('token');
        var deviceid = $('#addPointDevice').val();
        var lat = tab.find('input[role=lat]').val();
        var lon = tab.find('input[role=lon]').val();
        var alt = tab.find('input[role=altitude]').val();
        var acc = tab.find('input[role=precision]').val();
        var sat = tab.find('input[role=satellites]').val();
        var bat = tab.find('input[role=battery]').val();
        var datestr = tab.find('input[role=date]').val();
        var hourstr = parseInt(tab.find('input[role=hour]').val());
        var minstr = parseInt(tab.find('input[role=minute]').val());
        var secstr = parseInt(tab.find('input[role=second]').val());
        var completeDateStr = datestr + ' ' + pad(hourstr) + ':' + pad(minstr) + ':' + pad(secstr);
        var mom = moment(completeDateStr);
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
            sat: sat
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
                    addPointMap(response.id);
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

    function addPointMap(id) {
        var perm = $('#showtime').is(':checked');
        var tab = $('#addPointTable');
        var token = $('#addPointSession option:selected').attr('token');
        var deviceid = $('#addPointDevice').val();
        var lat = tab.find('input[role=lat]').val();
        var lon = tab.find('input[role=lon]').val();
        var alt = tab.find('input[role=altitude]').val();
        var acc = tab.find('input[role=precision]').val();
        var sat = tab.find('input[role=satellites]').val();
        var bat = tab.find('input[role=battery]').val();
        var datestr = tab.find('input[role=date]').val();
        var hourstr = parseInt(tab.find('input[role=hour]').val());
        var minstr = parseInt(tab.find('input[role=minute]').val());
        var secstr = parseInt(tab.find('input[role=second]').val());
        var completeDateStr = datestr + ' ' + pad(hourstr) + ':' + pad(minstr) + ':' + pad(secstr);
        var mom = moment(completeDateStr);
        var timestamp = mom.unix();

        var entry = {id: id};
        entry.deviceid = deviceid;
        entry.timestamp = timestamp;
        entry.lat = lat;
        entry.lon = lon;
        entry.altitude = alt;
        entry.batterylevel = bat;
        entry.satellites = sat;
        entry.accuracy = acc;

        var sessionname = $('div.session[token="' + token + '"] .sessionTitle b').text()
        
        // add device if it does not exist
        if (! phonetrack.sessionLineLayers[token].hasOwnProperty(deviceid)) {
            addDevice(token, deviceid, sessionname);
            appendEntryToDevice(token, deviceid, entry, sessionname);
            var icon = L.divIcon({
                iconAnchor: [8, 8],
                className: 'color' + phonetrack.sessionColors[token + deviceid],
                html: '<b>' + deviceid[0] + '</b>'
            });

            phonetrack.sessionMarkerLayers[token][deviceid] = L.marker([entry.lat, entry.lon, entry.id], {icon: icon});
        }
        // insert entry correctly ;)
        else {
            // add line point
            var pointtooltip = getPointTooltipContent(entry, sessionname);
            var m = L.circleMarker(
                [entry.lat, entry.lon],
                {radius: 6, fillOpacity: 1, color: colorCode[phonetrack.sessionColors[token + deviceid]]}
            );
            m.bindTooltip(pointtooltip, {className: 'tooltip' + phonetrack.sessionColors[token + deviceid]});
            phonetrack.sessionPointsEntriesById[token][deviceid][entry.id] = entry;
            phonetrack.sessionPointsLayersById[token][deviceid][entry.id] = m;
            phonetrack.sessionPointsLayers[token][deviceid].addLayer(m);
            if (!pageIsPublic()) {
                m.bindPopup(getPointPopup(token, deviceid, entry, sessionname), {closeOnClick: false});
            }

            // update line

            var latlngs = phonetrack.sessionLineLayers[token][deviceid].getLatLngs();
            var newlatlngs = [];
            var i = 0;
            // we copy until we get to the right place to insert new point
            while (i < latlngs.length
                   && timestamp > parseInt(phonetrack.sessionPointsEntriesById[token][deviceid][latlngs[i].alt].timestamp)
            ) {
                // copy
                newlatlngs.push([latlngs[i].lat, latlngs[i].lng, latlngs[i].alt]);
                i++;
            }
            // put the edited point
            newlatlngs.push([lat, lon, id]);
            // if new point is the last point, update marker and last time
            if (i === latlngs.length) {
                // move marker
                phonetrack.sessionMarkerLayers[token][deviceid].setLatLng([lat, lon, id]);
                phonetrack.lastTime[token][deviceid] = timestamp;
                // tooltip
                phonetrack.sessionMarkerLayers[token][deviceid].unbindTooltip();
                phonetrack.sessionMarkerLayers[token][deviceid].bindTooltip(
                    getPointTooltipContent(entry, sessionname),
                    {permanent: perm, offset: offset, className: 'tooltip' + phonetrack.sessionColors[token + deviceid]}
                );
                // popup
                phonetrack.sessionMarkerLayers[token][deviceid].bindPopup(
                    getPointPopup(token, deviceid, entry, sessionname),
                    {closeOnClick: false}
                );
            }
            // finish the copy
            while (i < latlngs.length) {
                // copy
                newlatlngs.push([latlngs[i].lat, latlngs[i].lng, latlngs[i].alt]);
                i++;
            }
            // modify line
            phonetrack.sessionLineLayers[token][deviceid].setLatLngs(newlatlngs);
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
        res = res + '<td>' + t('phonetrack', 'Latitude') + '</td>';
        res = res + '<td><input role="lat" type="number" value="' + entry.lat + '" min="-500" max="500" step="0.00001"/></td>';
        res = res + '</tr><tr>';
        res = res + '<td>' + t('phonetrack', 'Longitude') + '</td>';
        res = res + '<td><input role="lon" type="number" value="' + entry.lon + '" min="-500" max="500" step="0.00001"/></td>';
        res = res + '</tr><tr>';
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
        res = res + '</tr>';
        res = res + '</table>';
        res = res + '<button class="valideditpoint"><i class="fa fa-save" aria-hidden="true" style="color:blue;"></i> ' + t('phonetrack', 'Save') + '</button>';
        res = res + '<button class="deletepoint"><i class="fa fa-trash" aria-hidden="true" style="color:red;"></i> ' + t('phonetrack', 'Delete point') + '</button>';
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

        return pointtooltip;
    }

    function showHideSelectedSessions() {
        var token, d;
        var displayedMarkers = [];
        var viewLines = $('#viewmove').is(':checked');
        $('.watchSession').each(function() {
            token = $(this).attr('token');
            if ($(this).is(':checked')) {
                for (d in phonetrack.sessionLineLayers[token]) {
                    if (viewLines) {
                        if (!phonetrack.map.hasLayer(phonetrack.sessionLineLayers[token][d])) {
                            phonetrack.map.addLayer(phonetrack.sessionLineLayers[token][d]);
                        }
                    }
                    else {
                        if (phonetrack.map.hasLayer(phonetrack.sessionLineLayers[token][d])) {
                            phonetrack.map.removeLayer(phonetrack.sessionLineLayers[token][d]);
                            phonetrack.map.removeLayer(phonetrack.sessionPointsLayers[token][d]);
                        }
                    }
                }
                for (d in phonetrack.sessionMarkerLayers[token]) {
                    displayedMarkers.push(phonetrack.sessionMarkerLayers[token][d].getLatLng());
                    if (!phonetrack.map.hasLayer(phonetrack.sessionMarkerLayers[token][d])) {
                        phonetrack.map.addLayer(phonetrack.sessionMarkerLayers[token][d]);
                    }
                }
            }
            else {
                if (phonetrack.sessionLineLayers.hasOwnProperty(token)) {
                    for (d in phonetrack.sessionLineLayers[token]) {
                        if (phonetrack.map.hasLayer(phonetrack.sessionLineLayers[token][d])) {
                            phonetrack.map.removeLayer(phonetrack.sessionLineLayers[token][d]);
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
            var viewSessionCheck = $(this).parent().parent().parent().find('.watchSession');
            if (viewSessionCheck.is(':checked')) {
                var token = $(this).parent().parent().attr('token');
                var device = $(this).parent().attr('device');
                if (!devicesToFollow.hasOwnProperty(token)) {
                    devicesToFollow[token] = [];
                }
                devicesToFollow[token].push(device);
                nbDevicesToFollow++;
            }
        });

        $('.watchSession').each(function() {
            token = $(this).attr('token');
            if ($(this).is(':checked') && (selectedSessionToken === '' || token === selectedSessionToken)) {
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
                m.bindTooltip(t, {permanent: perm, offset: offset, className: 'tooltip' + phonetrack.sessionColors[s + d]});
            }
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
                timestamp: timestamp
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

    function zoomOnDevice(elem) {
        var dd, t, b, l;
        var perm = $('#showtime').is(':checked');
        var viewmove = $('#viewmove').is(':checked');
        var d = elem.attr('device');
        var s = elem.parent().attr('token');
        var m = phonetrack.sessionMarkerLayers[s][d];

        // if we show movement lines :
        // bring it to front, show/hide points
        // get correct zoom bounds
        if (viewmove) {
            l = phonetrack.sessionLineLayers[s][d];
            l.bringToFront();

            // hide points for the session
            for (dd in phonetrack.sessionPointsLayers[s]) {
                phonetrack.sessionPointsLayers[s][dd].remove();
            }
            // put the points for this device
            phonetrack.sessionPointsLayers[s][d].addTo(phonetrack.map);

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

        m.setZIndexOffset(phonetrack.lastZindex++);
        t = m.getTooltip()._content;
        m.unbindTooltip();
        m.bindTooltip(t, {permanent: perm, offset: offset, className: 'opaquetooltip' + phonetrack.sessionColors[s + d], opacity: 1});
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

        $('#newsession').click(function() {
            createSession();
        });

        $('body').on('click','.removeSession', function(e) {
            var token = $(this).parent().attr('token');
            deleteSession(token);
        });

        $('body').on('click','.watchSession', function(e) {
            phonetrack.currentTimer.pause();
            phonetrack.currentTimer = null;
            refresh();
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
            var name = $(this).parent().find('.sessionTitle b').text();
            var token = $(this).parent().attr('token');
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

        $('#zoomallbutton').click(function (e) {
            zoomOnDisplayedMarkers();
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

        $('body').on('click', 'ul.devicelist li', function(e) {
            if (e.target.tagName === 'LI' || e.target.tagName === 'LABEL') {
                zoomOnDevice($(this));
            }
        });

        $('body').on('click','.moreUrlsButton', function(e) {
            var urlDiv = $(this).parent().find('.moreUrls');
            if (urlDiv.is(':visible')) {
                urlDiv.slideUp('slow');
                $(this).find('i').removeClass('fa-angle-double-up').addClass('fa-angle-double-down');
            }
            else{
                urlDiv.slideDown('slow').css('display', 'grid');
                $(this).find('i').removeClass('fa-angle-double-down').addClass('fa-angle-double-up');
            }
        });

        $('body').on('click','.deleteDevice', function(e) {
            var sessionName = $(this).parent().parent().parent().find('.sessionTitle b').text();
            var token = $(this).attr('token');
            var device = $(this).attr('device');
            deleteDevice(token, device, sessionName);
        });

        $('body').on('click','.editsessionbutton', function(e) {
            var editdiv = $(this).parent().parent().find('.editsessiondiv');
            editdiv.slideDown('slow');
        });

        $('body').on('click','.editsessionok', function(e) {
            var token = $(this).parent().parent().attr('token');
            var oldname = $(this).parent().parent().find('.sessionTitle b').text();
            var newname = $(this).parent().find('input[role=editsessioninput]').val();
            renameSession(token, oldname, newname);
            var editdiv = $(this).parent().parent().find('.editsessiondiv');
            editdiv.slideUp('slow');
        });

        $('body').on('click','.editsessioncancel', function(e) {
            var editdiv = $(this).parent().parent().find('.editsessiondiv');
            editdiv.slideUp('slow');
        });

        $('body').on('click','.publicsessioncheck', function(e) {
            var pub = $(this).is(':checked');
            var token = $(this).parent().attr('token');
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
                if (response.done === 2) {
                    OC.Notification.showTemporary(t('phonetrack', 'Failed to toggle session public, session does not exist'));
                }
            }).fail(function() {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to toggle session public'));
            });
        });

        $('body').on('click','.canceleditpoint', function(e) {
            phonetrack.map.closePopup();
        });

        $('body').on('click','.valideditpoint', function(e) {
            editPointDB($(this));
        });

        $('body').on('click','.deletepoint', function(e) {
            deletePointDB($(this));
        });

        $('#validaddpoint').click(function(e) {
            addPointDB();
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
            addSession(token, name, publicviewtoken, null, true);
            $('#addPointDiv').remove();
            $('.removeSession').remove();
            $('.watchSession').prop('disabled', true);
            $('#customtilediv').remove();
            $('#newsessiondiv').remove();
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
