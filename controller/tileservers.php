<?php
// list of base tile and overlay servers to be included
$baseTileServers = [
    //Array(
    //    'name' => '',
    //    'type' => 'tile|overlay',
    //    'url' => '',
    //    'attribution' => '',
    //    'minzoom' => '',
    //    'maxzoom' => '',
    //    'opacity' => '0-1',
    //    'transparent' => 'true|false'
    //),
    //Array(
    //    'name' => 'tilewms',
    //    'type' => 'tilewms|overlaywms',
    //    'url' => '',
    //    'layers' => '',
    //    'version' => '',
    //    'attribution' => '',
    //    'format' => '',
    //    'opacity' => '0-1',
    //    'transparent' => 'true|false'
    //),
    Array(
        'name' => 'OpenStreetMap',
        'type' => 'tile',
        'url' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        'attribution' => 'Map data &copy; 2013 <a href="http://openstreetmap.org">OpenStreetMap</a> contributors',
        'minzoom' => '1',
        'maxzoom' => '18'
    ),
    Array(
        'name' => 'OpenCycleMap',
        'type' => 'tile',
        'url' => 'http://{s}.tile.opencyclemap.org/cycle/{z}/{x}/{y}.png',
        'attribution' => '&copy; <a href="http://www.opencyclemap.org">OpenCycleMap</a>, &copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        'minzoom' => '1',
        'maxzoom' => '18'
    ),
    Array(
        'name' => 'OpenStreetMap Transport',
        'type' => 'tile',
        'url' => 'http://a.tile2.opencyclemap.org/transport/{z}/{x}/{y}.png',
        'attribution' => 'Map data &copy; 2013 <a href="http://openstreetmap.org">OpenStreetMap</a> contributors',
        'minzoom' => '1',
        'maxzoom' => '18'
    ),
    Array(
        'name' => 'ESRI Aerial',
        'type' => 'tile',
        'url' => 'https://server.arcgisonline.com/ArcGIS/rest/services' .
                 '/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        'attribution' => 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, ' .
                         'USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the' .
                         ' GIS User Community',
        'minzoom' => '1',
        'maxzoom' => '18'
    ),
    Array(
        'name' => 'ESRI Topo with relief',
        'type' => 'tile',
        'url' => 'https://server.arcgisonline.com/ArcGIS/rest/services/World' .
                  '_Topo_Map/MapServer/tile/{z}/{y}/{x}',
        'attribution' => 'Tiles &copy; Esri &mdash; Esri, DeLorme, NAVTEQ, ' .
                          'TomTom, Intermap, iPC, USGS, FAO, NPS, NRCAN, GeoBase, Kadaster NL, Ord' .
                          'nance Survey, Esri Japan, METI, Esri China (Hong Kong), and the GIS User' .
                          ' Community',
        'minzoom' => '1',
        'maxzoom' => '18'
    ),
    Array(
        'name' => 'Hike & bike',
        'type' => 'tile',
        'url' => 'http://a.tiles.wmflabs.org/hikebike/{z}/{x}/{y}.png',
        'attribution' => 'Map data &copy; 2013 <a href="http://openstreetmap.org">OpenStreetMap</a> contributors',
        'minzoom' => '1',
        'maxzoom' => '18'
    ),
    Array(
        'name' => 'OpenStreetMap France',
        'type' => 'tile',
        'url' => 'https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png',
        'attribution' => 'Map data &copy; 2013 <a href="http://openstreetmap.org">OpenStreetMap</a> contributors',
        'minzoom' => '',
        'maxzoom' => ''
    ),
    Array(
        'name' => 'IGN France',
        'type' => 'tile',
        'url' => 'http://wxs.ign.fr/ljthe66m795pr2v2g8p7faxt/wmts?LAYER=GEOGRAPHICALGRIDSYSTEMS.MAPS' .
            '&EXCEPTIONS=text/xml&FORMAT=image/jpeg' .
            '&SERVICE=WMTS&VERSION=1.0.0&REQUEST=GetTile&STYLE=normal' .
            '&TILEMATRIXSET=PM&TILEMATRIX={z}&TILECOL={x}&TILEROW={y}',
        'attribution' => '&copy; <a href="http://www.ign.fr/">IGN-France</a>',
        'minzoom' => '1',
        'maxzoom' => '18'
    ),
    Array(
        'name' => 'Dark',
        'type' => 'tile',
        'url' => 'http://a.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png',
        'attribution' => '&copy; Map tiles by CartoDB, under CC BY 3.0. Data by' .
                         ' OpenStreetMap, under ODbL.',
        'minzoom' => '1',
        'maxzoom' => '18'
    ),
    Array(
        'name' => 'WaterColor',
        'type' => 'tile',
        'url' => 'http://{s}.tile.stamen.com/watercolor/{z}/{x}/{y}.jpg',
        'attribution' => '<a href="http://leafletjs.com" title="A JS library' .
        ' for interactive maps">Leaflet</a> | © Map tiles by <a href="http://stamen' .
        '.com">Stamen Design</a>, under <a href="http://creativecommons.org/license' .
        's/by/3.0">CC BY 3.0</a>, Data by <a href="http://openstreetmap.org">OpenSt' .
        'reetMap</a>, under <a href="http://creativecommons.org/licenses/by-sa/3.0"' .
        '>CC BY SA</a>.',
        'minzoom' => '1',
        'maxzoom' => '18'
    ),
    Array(
        'name' => 'Toner',
        'type' => 'tile|overlay',
        'url' => 'http://{s}.tile.stamen.com/toner/{z}/{x}/{y}.jpg',
        'attribution' => '<a href="http://leafletjs.com" title="A JS library' .
        ' for interactive maps">Leaflet</a> | © Map tiles by <a href="http://stamen' .
        '.com">Stamen Design</a>, under <a href="http://creativecommons.org/license' .
        's/by/3.0">CC BY 3.0</a>, Data by <a href="http://openstreetmap.org">OpenSt' .
        'reetMap</a>, under <a href="http://creativecommons.org/licenses/by-sa/3.0"' .
        '>CC BY SA</a>.',
        'minzoom' => '1',
        'maxzoom' => '18'
    ),
    Array(
        'name' => 'OsmFr Route500',
        'type' => 'overlay',
        'url' => 'http://{s}.tile.openstreetmap.fr/route500/{z}/{x}/{y}.png',
        'attribution' => '&copy, Tiles © <a href="http://www.openstreetmap.fr">OpenStreetMap France</a>',
        'opacity' => '0.5',
        'minzoom' => '1',
        'maxzoom' => '20'
    ),
];
?>
