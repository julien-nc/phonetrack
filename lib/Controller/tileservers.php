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
	//    'name' => 'vector mapbox',
	//    'type' => 'mapbox',
	//    'token' => 'YOUR_MAPBOX_TOKEN',
	//    'url' => 'mapbox://styles/mapbox/bright-v8',
	//    'attribution' => 'Map data &copy; 2013 <a href="https://openstreetmap.org">OpenStreetMap</a> contributors',
	//    'minzoom' => '1',
	//    'maxzoom' => '22'
	//),
	//Array(
	//    'name' => 'vector openmaptiles',
	//    'type' => 'mapbox',
	//    'token' => 'no-token',
	//    'url' => 'https://your.openmaptiles.server:PORT/styles/osm-bright/style.json',
	//    'attribution' => 'Map data &copy; 2013 <a href="https://openstreetmap.org">OpenStreetMap</a> contributors',
	//    'minzoom' => '1',
	//    'maxzoom' => '22'
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
	//Array(
	//    'name' => 'tilewmsExample',
	//    'type' => 'tilewms',
	//    'url' => 'https://ows.mundialis.de/services/service?',
	//    'layers' => 'TOPO-OSM-WMS',
	//    'version' => '',
	//    'attribution' => '',
	//    'format' => 'image/png',
	//    'opacity' => '1',
	//    'transparent' => 'false'
	//),
	[
		'name' => 'OpenStreetMap',
		'type' => 'tile',
		'url' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
		'attribution' => 'Map data &copy; <a href="https://openstreetmap.org">OpenStreetMap</a> contributors',
		'minzoom' => '1',
		'maxzoom' => '19'
	],
	[
		'name' => 'OpenStreetMap HighRes by OsmAnd',
		'type' => 'tile',
		'url' => 'https://tile.osmand.net/hd/{z}/{x}/{y}.png',
		'attribution' => 'Map data &copy; 2013 <a href="https://openstreetmap.org">OpenStreetMap</a> contributors',
		'minzoom' => '1',
		'maxzoom' => '19'
	],
	[
		'name' => 'OpenCycleMap',
		'type' => 'tile',
		'url' => 'https://{s}.tile.thunderforest.com/cycle/{z}/{x}/{y}.png',
		'attribution' => '&copy; <a href="https://www.opencyclemap.org">OpenCycleMap</a>, &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
		'minzoom' => '1',
		'maxzoom' => '22'
	],
	[
		'name' => 'OpenCycleMap HighRes',
		'type' => 'tile',
		'url' => 'https://{s}.tile.thunderforest.com/cycle/{z}/{x}/{y}@2x.png',
		'attribution' => '&copy; <a href="https://www.opencyclemap.org">OpenCycleMap</a>, &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
		'minzoom' => '1',
		'maxzoom' => '22'
	],
	[
		'name' => 'OpenStreetMap Transport',
		'type' => 'tile',
		'url' => 'https://{s}.tile.thunderforest.com/transport/{z}/{x}/{y}.png',
		'attribution' => 'Map data &copy; 2013 <a href="https://openstreetmap.org">OpenStreetMap</a> contributors',
		'minzoom' => '1',
		'maxzoom' => '22'
	],
	[
		'name' => 'ESRI Aerial',
		'type' => 'tile',
		'url' => 'https://server.arcgisonline.com/ArcGIS/rest/services'
				 . '/World_Imagery/MapServer/tile/{z}/{y}/{x}',
		'attribution' => 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, '
						 . 'USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the'
						 . ' GIS User Community',
		'minzoom' => '1',
		'maxzoom' => '19'
	],
	[
		'name' => 'ESRI Topo with relief',
		'type' => 'tile',
		'url' => 'https://server.arcgisonline.com/ArcGIS/rest/services/World'
				  . '_Topo_Map/MapServer/tile/{z}/{y}/{x}',
		'attribution' => 'Tiles &copy; Esri &mdash; Esri, DeLorme, NAVTEQ, '
						  . 'TomTom, Intermap, iPC, USGS, FAO, NPS, NRCAN, GeoBase, Kadaster NL, Ord'
						  . 'nance Survey, Esri Japan, METI, Esri China (Hong Kong), and the GIS User'
						  . ' Community',
		'minzoom' => '1',
		'maxzoom' => '19'
	],
	[
		'name' => 'OpenTopoMap',
		'type' => 'tile',
		'url' => 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
		'attribution' => 'Map data: &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>, <a href="http://viewfinderpanoramass.org">SRTM</a> | Map style: &copy; <a href="https://opentopomap.org">OpenTopoMap</a> (<a href="https://creativecommons.org/licenses/by-sa/3.0/">CC-BY-SA</a>)',
		'minzoom' => '1',
		'maxzoom' => '17'
	],
	[
		'name' => 'Hike & bike',
		'type' => 'tile',
		'url' => 'http://a.tiles.wmflabs.org/hikebike/{z}/{x}/{y}.png',
		'attribution' => 'Map data &copy; 2013 <a href="https://openstreetmap.org">OpenStreetMap</a> contributors',
		'minzoom' => '1',
		'maxzoom' => '18'
	],
	[
		'name' => 'OpenStreetMap France',
		'type' => 'tile',
		'url' => 'https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png',
		'attribution' => 'Map data &copy; 2013 <a href="https://openstreetmap.org">OpenStreetMap</a> contributors',
		'minzoom' => '1',
		'maxzoom' => '19'
	],
	[
		'name' => 'IGN France',
		'type' => 'tile',
		'url' => 'https://wxs.ign.fr/ljthe66m795pr2v2g8p7faxt/wmts?LAYER=GEOGRAPHICALGRIDSYSTEMS.MAPS'
			. '&EXCEPTIONS=text/xml&FORMAT=image/jpeg'
			. '&SERVICE=WMTS&VERSION=1.0.0&REQUEST=GetTile&STYLE=normal'
			. '&TILEMATRIXSET=PM&TILEMATRIX={z}&TILECOL={x}&TILEROW={y}',
		'attribution' => '&copy; <a href="https://www.ign.fr/">IGN-France</a>',
		'minzoom' => '1',
		'maxzoom' => '18'
	],
	[
		'name' => 'Dark',
		'type' => 'tile',
		'url' => 'https://a.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png',
		'attribution' => '&copy; Map tiles by CartoDB, under CC BY 3.0. Data by'
						 . ' OpenStreetMap, under ODbL.',
		'minzoom' => '1',
		'maxzoom' => '18'
	],
	[
		'name' => 'WaterColor',
		'type' => 'tile',
		//		'url' => 'http://{s}.tile.stamen.com/watercolor/{z}/{x}/{y}.jpg',
		'url' => 'https://tiles.stadiamaps.com/styles/stamen_watercolor/{z}/{x}/{y}.jpg',
		'attribution' => '<a href="https://leafletjs.com" title="A JS library'
		. ' for interactive maps">Leaflet</a> | © Map tiles by <a href="https://stamen'
		. '.com">Stamen Design</a>, under <a href="https://creativecommons.org/license'
		. 's/by/3.0">CC BY 3.0</a>, Data by <a href="https://openstreetmap.org">OpenSt'
		. 'reetMap</a>, under <a href="https://creativecommons.org/licenses/by-sa/3.0"'
		. '>CC BY SA</a>.',
		'minzoom' => '1',
		'maxzoom' => '18'
	],
	[
		'name' => 'Toner',
		'type' => 'tile|overlay',
		'url' => 'http://{s}.tile.stamen.com/toner/{z}/{x}/{y}.jpg',
		'attribution' => '<a href="https://leafletjs.com" title="A JS library'
		. ' for interactive maps">Leaflet</a> | © Map tiles by <a href="https://stamen'
		. '.com">Stamen Design</a>, under <a href="https://creativecommons.org/license'
		. 's/by/3.0">CC BY 3.0</a>, Data by <a href="https://openstreetmap.org">OpenSt'
		. 'reetMap</a>, under <a href="https://creativecommons.org/licenses/by-sa/3.0"'
		. '>CC BY SA</a>.',
		'minzoom' => '1',
		'maxzoom' => '18'
	],
	[
		'name' => 'OsmFr Route500',
		'type' => 'overlay',
		'url' => 'https://{s}.tile.openstreetmap.fr/route500/{z}/{x}/{y}.png',
		'attribution' => '&copy, Tiles © <a href="https://www.openstreetmap.fr">OpenStreetMap France</a>',
		'opacity' => '0.5',
		'minzoom' => '1',
		'maxzoom' => '20'
	],
];
