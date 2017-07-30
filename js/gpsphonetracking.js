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
    var gpsphonetracking = {
        map: {},
        baseLayers: null,
        overlayLayers: null,
        restoredTileLayer: null,
        // indexed by session name, contains dict indexed by deviceid
        sessionLineLayers: {},
        sessionMarkerLayers: {},
        currentTimer: null,
        lastTime: {}
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
        if (gpsphonetracking.restoredTileLayer !== null) {
            default_layer = gpsphonetracking.restoredTileLayer;
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
        gpsphonetracking.baseLayers = baseLayers;

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
        gpsphonetracking.overlayLayers = baseOverlays;

        gpsphonetracking.map = new L.Map('map', {
            zoomControl: true,
        });

        L.control.scale({metric: true, imperial: true, position: 'topleft'})
        .addTo(gpsphonetracking.map);

        L.control.mousePosition().addTo(gpsphonetracking.map);
        gpsphonetracking.locateControl = L.control.locate({setView: false});
        gpsphonetracking.locateControl.addTo(gpsphonetracking.map);
        gpsphonetracking.map.on('locationfound', function(e) {
            locationFound(e);
        });
        gpsphonetracking.map.addControl(new L.Control.LinearMeasurement({
            unitSystem: 'metric',
            color: '#FF0080',
            type: 'line'
        }));
        L.control.sidebar('sidebar').addTo(gpsphonetracking.map);

        gpsphonetracking.map.setView(new L.LatLng(27, 5), 3);

        if (! baseLayers.hasOwnProperty(default_layer)) {
            default_layer = 'OpenStreetMap';
        }
        gpsphonetracking.map.addLayer(baseLayers[default_layer]);

        gpsphonetracking.activeLayers = L.control.activeLayers(baseLayers, baseOverlays);
        gpsphonetracking.activeLayers.addTo(gpsphonetracking.map);

        //gpsphonetracking.map.on('contextmenu',rightClick);
        //gpsphonetracking.map.on('popupclose',function() {});
        //gpsphonetracking.map.on('viewreset',updateTrackListFromBounds);
        //gpsphonetracking.map.on('dragend',updateTrackListFromBounds);
        //gpsphonetracking.map.on('moveend', updateTrackListFromBounds);
        //gpsphonetracking.map.on('zoomend', updateTrackListFromBounds);
        //gpsphonetracking.map.on('baselayerchange', updateTrackListFromBounds);
        if (! pageIsPublic()) {
            gpsphonetracking.map.on('baselayerchange', saveOptions);
        }
    }

    function zoomOnAllDeviceTracks() {
        var b;
        // get bounds of first layer
        for (var l in gpsphonetracking.deviceLayers) {
            b = L.latLngBounds(
                gpsphonetracking.deviceLayers[l].layer.getBounds().getSouthWest(),
                gpsphonetracking.deviceLayers[l].layer.getBounds().getNorthEast()
            );
            break;
        }
        // then extend to other bounds
        for (var k in gpsphonetracking.deviceLayers) {
            b.extend(gpsphonetracking.deviceLayers[k].layer.getBounds());
        }
        // zoom
        if (b.isValid()) {
            gpsphonetracking.map.fitBounds(b,
                    {animate: true, paddingTopLeft: [parseInt($('#sidebar').css('width')),0]}
            );
        }
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
        return (document.URL.indexOf('/public') !== -1);
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
            OC.dialogs.alert(t('gpsphonetracking', 'Server name or server url should not be empty'),
                             t('gpsphonetracking', 'Impossible to add tile server'));
            return;
        }
        if ($('#'+type+'serverlist ul li[servername="' + sname + '"]').length > 0) {
            OC.dialogs.alert(t('gpsphonetracking', 'A server with this name already exists'),
                             t('gpsphonetracking', 'Impossible to add tile server'));
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
        var url = OC.generateUrl('/apps/gpsphonetracking/addTileServer');
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
                    t('gpsphonetracking', 'Delete') +
                    '</button></li>'
                );
                $('#'+type+'serverlist ul li[servername="' + sname + '"]').fadeIn('slow');

                if (type === 'tile') {
                    // add tile server in leaflet control
                    var newlayer = new L.TileLayer(surl,
                        {minZoom: sminzoom, maxZoom: smaxzoom, attribution: ''});
                    gpsphonetracking.activeLayers.addBaseLayer(newlayer, sname);
                    gpsphonetracking.baseLayers[sname] = newlayer;
                }
                else if (type === 'tilewms'){
                    // add tile server in leaflet control
                    var newlayer = new L.tileLayer.wms(surl,
                        {format: sformat, version: sversion, layers: slayers, minZoom: sminzoom, maxZoom: smaxzoom, attribution: ''});
                    gpsphonetracking.activeLayers.addBaseLayer(newlayer, sname);
                    gpsphonetracking.overlayLayers[sname] = newlayer;
                }
                if (type === 'overlay') {
                    // add tile server in leaflet control
                    var newlayer = new L.TileLayer(surl,
                        {minZoom: sminzoom, maxZoom: smaxzoom, transparent: stransparent, opcacity: sopacity, attribution: ''});
                    gpsphonetracking.activeLayers.addOverlay(newlayer, sname);
                    gpsphonetracking.baseLayers[sname] = newlayer;
                }
                else if (type === 'overlaywms'){
                    // add tile server in leaflet control
                    var newlayer = new L.tileLayer.wms(surl,
                        {layers: slayers, version: sversion, transparent: stransparent, opacity: sopacity, format: sformat, attribution: '', minZoom: sminzoom, maxZoom: smaxzoom});
                    gpsphonetracking.activeLayers.addOverlay(newlayer, sname);
                    gpsphonetracking.overlayLayers[sname] = newlayer;
                }
                OC.Notification.showTemporary(t('gpsphonetracking', 'Tile server "{ts}" has been added', {ts: sname}));
            }
            else{
                OC.Notification.showTemporary(t('gpsphonetracking', 'Failed to add tile server "{ts}"', {ts: sname}));
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('gpsphonetracking', 'Failed to add tile server "{ts}"', {ts: sname}));
        });
    }

    function deleteTileServer(li, type) {
        var sname = li.attr('servername');
        var req = {
            servername: sname,
            type: type
        };
        var url = OC.generateUrl('/apps/gpsphonetracking/deleteTileServer');
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
                    var activeLayerName = gpsphonetracking.activeLayers.getActiveBaseLayer().name;
                    // if we delete the active layer, first select another
                    if (activeLayerName === sname) {
                        $('input.leaflet-control-layers-selector').first().click();
                    }
                    gpsphonetracking.activeLayers.removeLayer(gpsphonetracking.baseLayers[sname]);
                    delete gpsphonetracking.baseLayers[sname];
                }
                else {
                    gpsphonetracking.activeLayers.removeLayer(gpsphonetracking.overlayLayers[sname]);
                    delete gpsphonetracking.overlayLayers[sname];
                }
                OC.Notification.showTemporary(t('gpsphonetracking', 'Tile server "{ts}" has been deleted', {ts: sname}));
            }
            else{
                OC.Notification.showTemporary(t('gpsphonetracking', 'Failed to delete tile server "{ts}"', {ts: sname}));
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('gpsphonetracking', 'Failed to delete tile server "{ts}"', {ts: sname}));
        });
    }

    //////////////// SAVE/RESTORE OPTIONS /////////////////////

    function restoreOptions() {
        var url = OC.generateUrl('/apps/gpsphonetracking/getOptionsValues');
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
                if (optionsValues.animatedmarkers !== undefined) {
                    $('#animatedmarkers').prop('checked', optionsValues.animatedmarkers);
                }
                if (optionsValues.autozoom !== undefined) {
                    $('#autozoom').prop('checked', optionsValues.autozoom);
                }
                if (optionsValues.viewmove !== undefined) {
                    $('#viewmove').prop('checked', optionsValues.viewmove);
                }
                if (optionsValues.tilelayer !== undefined) {
                    gpsphonetracking.restoredTileLayer = optionsValues.tilelayer;
                }
            }
            // quite important ;-)
            main();
        }).fail(function() {
            OC.dialogs.alert(
                t('gpsphonetracking', 'Failed to restore options values') + '. ' +
                t('gpsphonetracking', 'Reload this page')
                ,
                t('gpsphonetracking', 'Error')
            );
        });
    }

    function saveOptions() {
        var optionsValues = {};
        optionsValues.updateinterval = $('#updateinterval').val();
        optionsValues.viewmove = $('#viewmove').is(':checked');
        optionsValues.animatedmarkers = $('#animatedmarkers').is(':checked');
        optionsValues.autozoom = $('#autozoom').is(':checked');
        optionsValues.showtime = $('#showtime').is(':checked');
        optionsValues.tilelayer = gpsphonetracking.activeLayers.getActiveBaseLayer().name;
        //alert('to save : '+JSON.stringify(optionsValues));

        var req = {
            optionsValues: JSON.stringify(optionsValues),
        };
        var url = OC.generateUrl('/apps/gpsphonetracking/saveOptionsValues');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            //alert(response);
        }).fail(function() {
            OC.dialogs.alert(
                t('gpsphonetracking', 'Failed to save options values'),
                t('gpsphonetracking', 'Error')
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
            OC.Notification.showTemporary(t('gpsphonetracking', 'Session name should not be empty'));
            return;
        }
        var req = {
            name: sessionName
        };
        var url = OC.generateUrl('/apps/gpsphonetracking/createSession');
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
                OC.Notification.showTemporary(t('gpsphonetracking', 'Session name already used'));
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('gpsphonetracking', 'Failed to create session'));
        });
    }

    function addSession(token, name, selected=false) {
        var selhtml = '';
        if (selected) {
            selhtml = ' checked="checked"';
        }
        var gpsloggerurl = OC.generateUrl('/apps/gpsphonetracking/log?');
        var gpsloggerurlparams = {
            sessionid: token,
            deviceid: '1'
        };
        gpsloggerurl = gpsloggerurl +
            'lat=%LAT&' +
            'lon=%LON&' +
            'sat=%SAT&' +
            'alt=%ALT&' +
            'prec=%ACC&' +
            'time=%TIMESTAMP&' +
            'bat=%BATT&' +
            $.param(gpsloggerurlparams);
        gpsloggerurl = window.location.origin + gpsloggerurl;
        var osmandurl = OC.generateUrl('/apps/gpsphonetracking/log?');
        var osmandurlparams = {
            sessionid: token,
            deviceid: '1'
        };
        osmandurl = osmandurl +
            'lat={1}&' +
            'lon={2}&' +
            'alt={4}&' +
            'prec={3}&' +
            'time={2}&' +
            $.param(osmandurlparams);
        osmandurl = window.location.origin + osmandurl;

        var publicurl = OC.generateUrl('/apps/gpsphonetracking/public?');
        var publicurlparams = {
            sessionid: token,
            sessionname: name
        };
        publicurl = publicurl + $.param(publicurlparams);
        publicurl = window.location.origin + publicurl;

        var divtxt = '<div class="session" name="' + name + '" token="' + token + '">';
        divtxt = divtxt + '<h3>' + name + '</h3>';
        divtxt = divtxt + '<label>' + t('gpsphonetracking', 'OsmAnd URL') + ' :</label>';
        divtxt = divtxt + '<input role="osmandurl" type="text" value="' + osmandurl + '"></input>'; 
        divtxt = divtxt + '<label>' + t('gpsphonetracking', 'GpsLogger URL') + ' :</label>';
        divtxt = divtxt + '<input role="gpsloggerurl" type="text" value="' + gpsloggerurl + '"></input>'; 
        divtxt = divtxt + '<label>' + t('gpsphonetracking', 'Public URL') + ' :</label>';
        divtxt = divtxt + '<input role="publicurl" type="text" value="' + publicurl + '"></input>'; 
        divtxt = divtxt + '<button class="removeSession"><i class="fa fa-trash" aria-hidden="true"></i> ' +
            t('gpsphonetracking', 'Delete session') + '</button>';
        divtxt = divtxt + '<div class="watchlabeldiv"><label class="watchlabel" for="watch'+token+'">' +
            '<i class="fa fa-eye" aria-hidden="true" style="color:blue;"></i> ' +
            t('gpsphonetracking', 'Watch this session') + '</label>' +
            '<input type="checkbox" class="watchSession" id="watch' + token + '" '+
            'token="' + token + '" sessionname="' + name + '"' + selhtml + '/></div>';
        if (!pageIsPublic()) {
            divtxt = divtxt + '<button class="export"><i class="fa fa-floppy-o" aria-hidden="true" style="color:blue;"></i> ' + t('gpsphonetracking', 'Export to gpx') +
                '</button>';
        }
        divtxt = divtxt + '</div>';

        $('div#sessions').append($(divtxt).fadeIn('slow').css('display', 'grid')).find('input[type=text]').prop('readonly', true );
    }
    
    function deleteSession(token, name) {
        var div = $('div.session[token='+token+']');

        var req = {
            name: name,
            token: token
        };
        var url = OC.generateUrl('/apps/gpsphonetracking/deleteSession');
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
                OC.Notification.showTemporary(t('gpsphonetracking', 'The session you want to delete does not exist'));
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('gpsphonetracking', 'Failed to delete session'));
        });
    }

    function removeSession(div) {
        div.fadeOut('slow', function() {
            div.remove();
        });
    }

    function getSessions() {
        var req = {
        };
        var url = OC.generateUrl('/apps/gpsphonetracking/getSessions');
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
            OC.Notification.showTemporary(t('gpsphonetracking', 'Failed to get sessions'));
        });
    }

    function refresh() {
        var sessionsToWatch = [];
        // get new positions for all watched sessions
        $('.watchSession:checked').each(function() {
            var token = $(this).attr('token');
            var name = $(this).attr('sessionname');
            var lastTimes = gpsphonetracking.lastTime[name] || {};
            sessionsToWatch.push([token, name, lastTimes]);
        });

        if (sessionsToWatch.length > 0) {
            showLoadingAnimation();
            var req = {
                sessions: sessionsToWatch
            };
            var url = OC.generateUrl('/apps/gpsphonetracking/track');
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
                OC.Notification.showTemporary(t('gpsphonetracking', 'Failed to refresh sessions'));
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
        gpsphonetracking.currentTimer = new Timer(function() {
            refresh();
        }, updateinterval);
    }

    function displayNewPoints(sessions) {
        var s, i, d, entry, device, timestamp, mom, icon,
            linetooltip, markertoolip, colorn, rgbc,
            textcolor, coloredMarkerClass;
        var perm = $('#showtime').is(':checked');
        for (s in sessions) {
            if (! gpsphonetracking.sessionLineLayers.hasOwnProperty(s)) {
                gpsphonetracking.sessionLineLayers[s] = {};
            }
            if (! gpsphonetracking.sessionMarkerLayers.hasOwnProperty(s)) {
                gpsphonetracking.sessionMarkerLayers[s] = {};
            }
            // for all devices
            for (d in sessions[s]) {
                // add line and marker if necessary
                if (! gpsphonetracking.sessionLineLayers[s].hasOwnProperty(d)) {
                    colorn = ++lastColorUsed;
                    rgbc = hexToRgb(colorCode[colorn]);
                    textcolor = 'black';
                    if (rgbc.r + rgbc.g + rgbc.b < 3 * 125) {
                    textcolor = 'white';
                    } 
                    $('<style track="' + d + '">.color' + colorn + ' { ' +
                        'background: rgba(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ', 0.6);' +
                        'color: ' + textcolor + '; font-weight: bold;' +
                        'text-align: center;' +
                        'width: 14px !important;' +
                        'height: 14px !important;' +
                        'border-radius: 50%;' +
                        'line-height:14px;' +
                        ' }</style>').appendTo('body');
                    coloredMarkerClass = 'color' + colorn;

                    gpsphonetracking.sessionLineLayers[s][d] = L.polyline([], {color: colorCode[colorn]});
                    linetooltip = 'Session ' + s + ' ; device ' + d;
                    gpsphonetracking.sessionLineLayers[s][d].bindTooltip(
                        linetooltip,
                        {
                            permanent: false,
                            sticky: true
                        }
                    );
                }
                // for all new entries of this session
                for (i in sessions[s][d]) {
                    entry = sessions[s][d][i];
                    timestamp = parseInt(entry.timestamp);
                    device = 'd' + entry.deviceid;
                    if (!gpsphonetracking.lastTime.hasOwnProperty(s)) {
                        gpsphonetracking.lastTime[s] = {};
                    }
                    if ((!gpsphonetracking.lastTime[s].hasOwnProperty(device)) ||
                        timestamp > gpsphonetracking.lastTime[s][device])
                    {
                        gpsphonetracking.lastTime[s][device] = timestamp;
                    }
                    // increment lines
                    gpsphonetracking.sessionLineLayers[s][d].addLatLng([entry.lat, entry.lon]);
                }
                // move/create marker
                // entry is the last point for the current device
                if (! gpsphonetracking.sessionMarkerLayers[s].hasOwnProperty(d)) {
                    icon = L.divIcon({
                        iconAnchor: [7, 7],
                        className: 'color'+colorn,
                        html: '<b>' + d + '</b>'
                    });

                    gpsphonetracking.sessionMarkerLayers[s][d] = L.marker([entry.lat, entry.lon], {icon: icon});
                }
                else {
                    gpsphonetracking.sessionMarkerLayers[s][d].setLatLng([entry.lat, entry.lon]);
                }
                mom = moment.unix(timestamp);
                gpsphonetracking.sessionMarkerLayers[s][d].unbindTooltip();
                gpsphonetracking.sessionMarkerLayers[s][d].bindTooltip(
                    mom.format('YYYY-MM-DD HH:mm:ss (Z)'), {permanent: perm, offset: offset, opacity: 0.6}
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
                for (d in gpsphonetracking.sessionLineLayers[sessionName]) {
                    if (viewLines) {
                        if (!gpsphonetracking.map.hasLayer(gpsphonetracking.sessionLineLayers[sessionName][d])) {
                            gpsphonetracking.map.addLayer(gpsphonetracking.sessionLineLayers[sessionName][d]);
                        }
                    }
                    else {
                        if (gpsphonetracking.map.hasLayer(gpsphonetracking.sessionLineLayers[sessionName][d])) {
                            gpsphonetracking.map.removeLayer(gpsphonetracking.sessionLineLayers[sessionName][d]);
                        }
                    }
                }
                for (d in gpsphonetracking.sessionMarkerLayers[sessionName]) {
                    displayedMarkers.push(gpsphonetracking.sessionMarkerLayers[sessionName][d].getLatLng());
                    if (!gpsphonetracking.map.hasLayer(gpsphonetracking.sessionMarkerLayers[sessionName][d])) {
                        gpsphonetracking.map.addLayer(gpsphonetracking.sessionMarkerLayers[sessionName][d]);
                    }
                }
            }
            else {
                if (gpsphonetracking.sessionLineLayers.hasOwnProperty(sessionName)) {
                    for (d in gpsphonetracking.sessionLineLayers[sessionName]) {
                        if (gpsphonetracking.map.hasLayer(gpsphonetracking.sessionLineLayers[sessionName][d])) {
                            gpsphonetracking.map.removeLayer(gpsphonetracking.sessionLineLayers[sessionName][d]);
                        }
                    }
                }
                if (gpsphonetracking.sessionMarkerLayers.hasOwnProperty(sessionName)) {
                    for (d in gpsphonetracking.sessionMarkerLayers[sessionName]) {
                        if (gpsphonetracking.map.hasLayer(gpsphonetracking.sessionMarkerLayers[sessionName][d])) {
                            gpsphonetracking.map.removeLayer(gpsphonetracking.sessionMarkerLayers[sessionName][d]);
                        }
                    }
                }
            }

            // ZOOM
            if ($('#autozoom').is(':checked') && displayedMarkers.length > 0) {
                gpsphonetracking.map.fitBounds(displayedMarkers,
                    {animate: true, paddingTopLeft: [parseInt($('#sidebar').css('width')),0]}
                );
            }

        });
    }

    function changeTooltipStyle() {
        var perm = $('#showtime').is(':checked');
        var s, d, m, t;
        for (s in gpsphonetracking.sessionMarkerLayers) {
            for (d in gpsphonetracking.sessionMarkerLayers[s]) {
                m = gpsphonetracking.sessionMarkerLayers[s][d];
                t = m.getTooltip();
                m.unbindTooltip();
                m.bindTooltip(t, {permanent: perm, offset: offset, opacity: 0.6});
            }
        }
    }

    function saveAction(name, token, targetPath) {
        var req = {
            name: name,
            token: token,
            target: targetPath
        };
        var url = OC.generateUrl('/apps/gpsphonetracking/export');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            if (response.done) {
                OC.Notification.showTemporary(t('gpsphonetracking', 'Successfully exported session in') +
                    ' ' + targetPath + '/' + name + '.gpx');
            }
            else {
                OC.Notification.showTemporary(t('gpsphonetracking', 'Failed to export session'));
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('gpsphonetracking', 'Failed to contact server to export session'));
        });
    }

    function locationFound(e) {
        if (pageIsPublic() && $('#logme').is(':checked')) {
            var deviceid = $('#logmedeviceinput').val();
            var lat, lon, alt, prec, timestamp;
            lat = e.latitude;
            lon = e.longitude;
            alt = e.altitude;
            prec = e.accuracy;
            timestamp = e.timestamp;
            var req = {
                deviceid: deviceid,
                token: gpsphonetracking.publicToken,
                lat: lat,
                lon: lon,
                alt: alt,
                prec: prec,
                timestamp: timestamp
            };
            var url = OC.generateUrl('/apps/gpsphonetracking/logpost');
            $.ajax({
                type: 'POST',
                url: url,
                data: req,
                async: true
            }).done(function (response) {
                //console.log(response);
            }).always(function() {
            }).fail(function() {
                OC.Notification.showTemporary(t('gpsphonetracking', 'Failed to contact server to log position'));
            });
        }
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

        gpsphonetracking.username = $('p#username').html();
        gpsphonetracking.token = $('p#token').html();
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
            gpsphonetracking.currentTimer.pause();
            gpsphonetracking.currentTimer = null;
            refresh();
        });

        $('#animatedmarkers').click(function() {
            if (!pageIsPublic()) {
                saveOptions();
            }
        });

        $('#autozoom').click(function() {
            if (!pageIsPublic()) {
                saveOptions();
            }
        });

        $('#showtime').click(function() {
            changeTooltipStyle();
            if (!pageIsPublic()) {
                saveOptions();
            }
        });

        $('#viewmove').click(function() {
            showHideSelectedSessions();
            if (!pageIsPublic()) {
                saveOptions();
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
                t('gpsphonetracking', 'Where to save') +
                    ' <b>' + filename + '</b>',
                function(targetPath) {
                    saveAction(name, token, targetPath);
                },
                false, 'httpd/unix-directory', true
            );
        });

        $('#logme').click(function (e) {
            if ($('#logme').is(':checked')) {
                gpsphonetracking.locateControl.start();
            }
            else {
                gpsphonetracking.locateControl.stop();
            }
        });

        if (!pageIsPublic()) {
            getSessions();
        }
        // public page
        else {
            var token = getUrlParameter('sessionid');
            gpsphonetracking.publicToken = token;
            var name = getUrlParameter('sessionname');
            gpsphonetracking.publicName = name;
            addSession(token, name, true);
            $('.removeSession').remove();
            $('.watchSession').prop('disabled', true);
            $('#customtilediv').remove();
            $('#newsessiondiv').remove();
            $('#logmediv').show();
        }

        refresh();

    }

})(jQuery, OC);
