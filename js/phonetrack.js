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

    function pageIsPublic() {
        return (document.URL.indexOf('/publicSession') !== -1);
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
                addSession(response.token, sessionName);
            }
            else if (response.done === 2) {
                OC.Notification.showTemporary(t('phonetrack', 'Session name already used'));
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to create session'));
        });
    }

    function addSession(token, name, selected=false) {
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

        var osmandurl = OC.generateUrl('/apps/phonetrack/log/osmand/' + token + '/yourname?');
        osmandurl = osmandurl +
            'lat={0}&' +
            'lon={1}&' +
            'alt={4}&' +
            'acc={3}&' +
            'timestamp={2}';
        osmandurl = window.location.origin + osmandurl;

        var publicurl = OC.generateUrl('/apps/phonetrack/publicSession/' + token + '/' + name);
        publicurl = window.location.origin + publicurl;

        var divtxt = '<div class="session" name="' + name + '" token="' + token + '">';
        divtxt = divtxt + '<h3 class="sessionTitle">' + name + ' <button class="zoomsession" ' +
            'title="' + t('phonetrack', 'Zoom on this session') + '">' +
            '<i class="fa fa-search-plus" style="color:blue;"></i></button></h3>';
        divtxt = divtxt + '<p>' + t('phonetrack', 'Public URL') + ' :</p>';
        divtxt = divtxt + '<input role="publicurl" type="text" value="' + publicurl + '"></input>'; 
        divtxt = divtxt + '<p class="moreUrlsButton"><label>' + t('phonetrack', 'More URLs') +
            '</label> <i class="fa fa-angle-double-down"></i></p>';
        divtxt = divtxt + '<div class="moreUrls">';
        divtxt = divtxt + '<p>' + t('phonetrack', 'OsmAnd URL') + ' :</p>';
        divtxt = divtxt + '<input role="osmandurl" type="text" value="' + osmandurl + '"></input>';
        divtxt = divtxt + '<p>' + t('phonetrack', 'GpsLogger GET and POST URL') + ' :</p>';
        divtxt = divtxt + '<input role="gpsloggerurl" type="text" value="' + gpsloggerUrl + '"></input>';
        divtxt = divtxt + '<p>' + t('phonetrack', 'Owntracks (HTTP mode) URL') + ' :</p>';
        divtxt = divtxt + '<input role="owntracksurl" type="text" value="' + owntracksurl + '"></input>';
        divtxt = divtxt + '<p>' + t('phonetrack', 'Ulogger URL') + ' :</p>';
        divtxt = divtxt + '<input role="uloggerurl" type="text" value="' + uloggerurl + '"></input>';
        divtxt = divtxt + '<p>' + t('phonetrack', 'Traccar URL') + ' :</p>';
        divtxt = divtxt + '<input role="traccarurl" type="text" value="' + traccarurl + '"></input>';
        divtxt = divtxt + '</div>';
        divtxt = divtxt + '<button class="removeSession"><i class="fa fa-trash" aria-hidden="true"></i> ' +
            t('phonetrack', 'Delete session') + '</button>';
        if (!pageIsPublic()) {
            divtxt = divtxt + '<button class="export"><i class="fa fa-floppy-o" aria-hidden="true" style="color:blue;"></i> ' + t('phonetrack', 'Export to gpx') +
                '</button>';
        }
        divtxt = divtxt + '<div class="watchlabeldiv"><label class="watchlabel" for="watch'+token+'">' +
            '<i class="fa fa-eye" aria-hidden="true" style="color:blue;"></i> ' +
            t('phonetrack', 'Watch this session') + '</label>' +
            '<input type="checkbox" class="watchSession" id="watch' + token + '" '+
            'token="' + token + '" sessionname="' + name + '"' + selhtml + '/></div>';
        divtxt = divtxt + '<ul class="devicelist" session="' + name + '"></ul></div>';

        $('div#sessions').append($(divtxt).fadeIn('slow').css('display', 'grid')).find('input[type=text]').prop('readonly', true );
        $('.session[name="'+name+'"]').find('.moreUrls').hide();
    }
    
    function deleteSession(token, name) {
        var div = $('div.session[token='+token+']');

        var req = {
            name: name,
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

    function deleteDevice(session, device) {
        var req = {
            session: session,
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
                removeDevice(session, device);
                OC.Notification.showTemporary(t('phonetrack', 'Device \'{d}\' of session \'{s}\' has been deleted', {d: device, s: session}));
            }
            else if (response.done === 2) {
                OC.Notification.showTemporary(t('phonetrack', 'Failed to delete device \'{d}\' of session \'{s}\'', {d: device, s: session}));
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to contact server to delete device'));
        });
    }

    function removeDevice(session, device) {
        // remove devicelist line
        $('.devicelist li[session="' + session + '"][device="' + device + '"]').fadeOut('slow', function() {
            $(this).remove();
        });
        // remove marker, line and tooltips
        phonetrack.sessionMarkerLayers[session][device].unbindTooltip().remove();
        delete phonetrack.sessionMarkerLayers[session][device];
        phonetrack.sessionLineLayers[session][device].unbindTooltip().remove();
        delete phonetrack.sessionLineLayers[session][device];
    }

    function removeSession(div) {
        div.fadeOut('slow', function() {
            div.remove();
        });
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
                    addSession(response.sessions[s][1], response.sessions[s][0]);
                }
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('phonetrack', 'Failed to get sessions'));
        });
    }

    function refresh() {
        var sessionsToWatch = [];
        // get new positions for all watched sessions
        $('.watchSession:checked').each(function() {
            var token = $(this).attr('token');
            var name = $(this).attr('sessionname');
            var lastTimes = phonetrack.lastTime[name] || '';
            sessionsToWatch.push([token, name, lastTimes]);
        });

        if (sessionsToWatch.length > 0) {
            showLoadingAnimation();
            var req = {
                sessions: sessionsToWatch
            };
            var url = OC.generateUrl('/apps/phonetrack/track');
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
            linetooltip, markertooltip, colorn, rgbc,
            textcolor, coloredMarkerClass;
        var perm = $('#showtime').is(':checked');
        for (s in sessions) {
            if (! phonetrack.sessionLineLayers.hasOwnProperty(s)) {
                phonetrack.sessionLineLayers[s] = {};
            }
            if (! phonetrack.sessionMarkerLayers.hasOwnProperty(s)) {
                phonetrack.sessionMarkerLayers[s] = {};
            }
            // for all devices
            for (d in sessions[s]) {
                // add line and marker if necessary
                if (! phonetrack.sessionLineLayers[s].hasOwnProperty(d)) {
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
                    coloredMarkerClass = 'color' + colorn;
                    var deleteLink = '';
                    if (!pageIsPublic()) {
                        deleteLink = ' <i class="fa fa-trash deleteDevice" session="' + s + '" aria-hidden="true" title="' +
                        t('phonetrack', 'Delete this device') +
                        '" device="' + d + '"></i>';
                    }
                    $('div.session[name="' + s + '"] ul.devicelist').append(
                        '<li device="' + d + '" session="' + s + '" style="font-weight: bold; color: ' + textcolor + ';' +
                        'background-color:' + colorCode[colorn] + ';"' +
                        ' title="' + t('phonetrack', 'Center map on device') + ' ' +
                        d + '"><input class="followdevice" type="checkbox" ' +
                        'title="' + t('phonetrack', 'Follow this device (autozoom)') +
                        '"/><label class="deviceLabel">' + d + '</label> ' + deleteLink +
                        '</li>');

                    phonetrack.sessionLineLayers[s][d] = L.polyline([], {weight: 4, color: colorCode[colorn]});
                    linetooltip = 'Session ' + s + ' ; device ' + d;
                    phonetrack.sessionLineLayers[s][d].bindTooltip(
                        linetooltip,
                        {
                            permanent: false,
                            sticky: true,
                            className: 'tooltip' + colorn
                        }
                    );
                }
                // for all new entries of this session
                for (i in sessions[s][d]) {
                    entry = sessions[s][d][i];
                    timestamp = parseInt(entry.timestamp);
                    device = 'd' + entry.deviceid;
                    if (!phonetrack.lastTime.hasOwnProperty(s)) {
                        phonetrack.lastTime[s] = {};
                    }
                    if ((!phonetrack.lastTime[s].hasOwnProperty(device)) ||
                        timestamp > phonetrack.lastTime[s][device])
                    {
                        phonetrack.lastTime[s][device] = timestamp;
                    }
                    // increment lines
                    phonetrack.sessionLineLayers[s][d].addLatLng([entry.lat, entry.lon]);
                }
                // move/create marker
                // entry is the last point for the current device
                if (! phonetrack.sessionMarkerLayers[s].hasOwnProperty(d)) {
                    icon = L.divIcon({
                        iconAnchor: [8, 8],
                        className: 'color' + phonetrack.sessionColors[s + d],
                        html: '<b>' + d[0] + '</b>'
                    });

                    phonetrack.sessionMarkerLayers[s][d] = L.marker([entry.lat, entry.lon], {icon: icon});
                }
                else {
                    phonetrack.sessionMarkerLayers[s][d].setLatLng([entry.lat, entry.lon]);
                }
                mom = moment.unix(timestamp);
                phonetrack.sessionMarkerLayers[s][d].unbindTooltip();
                markertooltip = 'Session ' + s + ' ; device ' + d + '<br/>';
                phonetrack.sessionMarkerLayers[s][d].bindTooltip(
                    markertooltip + mom.format('YYYY-MM-DD HH:mm:ss (Z)'),
                    {permanent: perm, offset: offset, className: 'tooltip' + phonetrack.sessionColors[s + d]}
                );
            }
        }
        // in case user click is between ajax request and response
        showHideSelectedSessions();
    }

    function showHideSelectedSessions() {
        var sessionName, d;
        var displayedMarkers = [];
        var viewLines = $('#viewmove').is(':checked');
        $('.watchSession').each(function() {
            sessionName = $(this).attr('sessionname');
            if ($(this).is(':checked')) {
                for (d in phonetrack.sessionLineLayers[sessionName]) {
                    if (viewLines) {
                        if (!phonetrack.map.hasLayer(phonetrack.sessionLineLayers[sessionName][d])) {
                            phonetrack.map.addLayer(phonetrack.sessionLineLayers[sessionName][d]);
                        }
                    }
                    else {
                        if (phonetrack.map.hasLayer(phonetrack.sessionLineLayers[sessionName][d])) {
                            phonetrack.map.removeLayer(phonetrack.sessionLineLayers[sessionName][d]);
                        }
                    }
                }
                for (d in phonetrack.sessionMarkerLayers[sessionName]) {
                    displayedMarkers.push(phonetrack.sessionMarkerLayers[sessionName][d].getLatLng());
                    if (!phonetrack.map.hasLayer(phonetrack.sessionMarkerLayers[sessionName][d])) {
                        phonetrack.map.addLayer(phonetrack.sessionMarkerLayers[sessionName][d]);
                    }
                }
            }
            else {
                if (phonetrack.sessionLineLayers.hasOwnProperty(sessionName)) {
                    for (d in phonetrack.sessionLineLayers[sessionName]) {
                        if (phonetrack.map.hasLayer(phonetrack.sessionLineLayers[sessionName][d])) {
                            phonetrack.map.removeLayer(phonetrack.sessionLineLayers[sessionName][d]);
                        }
                    }
                }
                if (phonetrack.sessionMarkerLayers.hasOwnProperty(sessionName)) {
                    for (d in phonetrack.sessionMarkerLayers[sessionName]) {
                        if (phonetrack.map.hasLayer(phonetrack.sessionMarkerLayers[sessionName][d])) {
                            phonetrack.map.removeLayer(phonetrack.sessionMarkerLayers[sessionName][d]);
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

    function zoomOnDisplayedMarkers(selectedName='') {
        var sessionName, d;
        var markersToZoomOn = [];

        // first we check if there are devices selected for zoom
        var devicesToFollow = {};
        var nbDevicesToFollow = 0
        $('.followdevice:checked').each(function() {
            // we only take those for session which are watched
            var viewSessionCheck = $(this).parent().parent().parent().find('.watchSession');
            if (viewSessionCheck.is(':checked')) {
                var session = $(this).parent().parent().attr('session');
                var device = $(this).parent().attr('device');
                if (!devicesToFollow.hasOwnProperty(session)) {
                    devicesToFollow[session] = [];
                }
                devicesToFollow[session].push(device);
                nbDevicesToFollow++;
            }
        });

        $('.watchSession').each(function() {
            sessionName = $(this).attr('sessionname');
            if ($(this).is(':checked') && (selectedName === '' || sessionName === selectedName)) {
                for (d in phonetrack.sessionMarkerLayers[sessionName]) {
                    // if no device is followed => all devices are taken
                    // if some devices are followed, just take them
                    if (nbDevicesToFollow === 0
                        || (devicesToFollow.hasOwnProperty(sessionName) && devicesToFollow[sessionName].indexOf(d) !== -1)
                    ) {
                        markersToZoomOn.push(phonetrack.sessionMarkerLayers[sessionName][d].getLatLng());
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
        if (pageIsPublic() && $('#logme').is(':checked')) {
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
            var url = OC.generateUrl('/apps/phonetrack/logPost/' + phonetrack.publicToken + '/' + deviceid);
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
        var t;
        var perm = $('#showtime').is(':checked');
        var d = elem.attr('device');
        var s = elem.parent().attr('session');
        var m = phonetrack.sessionMarkerLayers[s][d];

        var b = L.latLngBounds(m.getLatLng(), m.getLatLng);
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
            var name = $(this).parent().attr('name');
            deleteSession(token, name);
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
            var name = $(this).parent().attr('name');
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
            var sessionName = $(this).parent().parent().attr('name');
            zoomOnDisplayedMarkers(sessionName);
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
            var session = $(this).attr('session');
            var device = $(this).attr('device');
            deleteDevice(session, device);
        });

        if (!pageIsPublic()) {
            getSessions();
        }
        // public page
        else {
            var params = window.location.href.split('publicSession/')[1].split('/');
            var token = params[0];
            phonetrack.publicToken = token;
            var name = params[1];
            phonetrack.publicName = name;
            addSession(token, name, true);
            $('.removeSession').remove();
            $('.watchSession').prop('disabled', true);
            $('#customtilediv').remove();
            $('#newsessiondiv').remove();
            $('#logmediv').show();
            $('#autozoom').prop('checked', true);
            phonetrack.zoomButton.state('zoom');
            $(phonetrack.zoomButton.button).addClass('easy-button-green').removeClass('easy-button-red');
        }

        refresh();

    }

})(jQuery, OC);
