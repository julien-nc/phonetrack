import { generateUrl } from '@nextcloud/router'

export function getRasterTileServers(apiKey, proxy = true) {
	return {
		osmRaster: {
			title: 'OpenStreetMap raster',
			version: 8,
			// required to display text, apparently vector styles get this but not raster ones
			glyphs: proxy
				? generateUrl('/apps/phonetrack/maptiler/fonts/') + '{fontstack}/{range}.pbf?key=' + apiKey
				: 'https://api.maptiler.com/fonts/{fontstack}/{range}.pbf?key=' + apiKey,
			sources: {
				'osm-source': {
					type: 'raster',
					tiles: proxy
						? [
							generateUrl('/apps/phonetrack/tiles/osm/') + '{x}/{y}/{z}',
							// ...['a', 'b', 'c'].map(s => generateUrl('/apps/phonetrack/tiles/osm/') + `{x}/{y}/{z}?s=${s}`),
						]
						: [
							'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
							// ...['a', 'b', 'c'].map(s => `https://${s}.tile.openstreetmap.org/{z}/{x}/{y}.png`)
						],
					tileSize: 256,
					attribution: 'Map data &copy; <a href="https://openstreetmap.org">OpenStreetMap</a> contributors',
				},
			},
			layers: [
				{
					id: 'osm-layer',
					type: 'raster',
					source: 'osm-source',
					minzoom: 0,
					maxzoom: 19,
				},
			],
			maxzoom: 19,
		},
		ocmRaster: {
			title: 'OpenCycleMap raster',
			version: 8,
			glyphs: proxy
				? generateUrl('/apps/phonetrack/maptiler/fonts/') + '{fontstack}/{range}.pbf?key=' + apiKey
				: 'https://api.maptiler.com/fonts/{fontstack}/{range}.pbf?key=' + apiKey,
			sources: {
				'ocm-source': {
					type: 'raster',
					tiles: proxy
						? [
							...['a', 'b', 'c'].map(s => generateUrl('/apps/phonetrack/tiles/ocm/') + `{x}/{y}/{z}?s=${s}`),
						]
						: [
							...['a', 'b', 'c'].map(s => `https://${s}.tile.thunderforest.com/cycle/{z}/{x}/{y}.png`),
						],
					tileSize: 256,
					attribution: 'Map data &copy; <a href="https://openstreetmap.org">OpenStreetMap</a> contributors',
				},
			},
			layers: [
				{
					id: 'ocm-layer',
					type: 'raster',
					source: 'ocm-source',
					minzoom: 0,
					maxzoom: 19,
				},
			],
			maxzoom: 19,
		},
		osmRasterHighRes: {
			title: 'OpenStreetMap raster HighRes',
			version: 8,
			glyphs: proxy
				? generateUrl('/apps/phonetrack/maptiler/fonts/') + '{fontstack}/{range}.pbf?key=' + apiKey
				: 'https://api.maptiler.com/fonts/{fontstack}/{range}.pbf?key=' + apiKey,
			sources: {
				'osm-highres-source': {
					type: 'raster',
					tiles: proxy
						? [
							generateUrl('/apps/phonetrack/tiles/osm-highres/') + '{x}/{y}/{z}',
						]
						: [
							'https://tile.osmand.net/hd/{z}/{x}/{y}.png',
						],
					tileSize: 512,
					attribution: 'Map data &copy; <a href="https://openstreetmap.org">OpenStreetMap</a> contributors',
				},
			},
			layers: [
				{
					id: 'osm-highres-layer',
					type: 'raster',
					source: 'osm-highres-source',
					minzoom: 0,
					maxzoom: 19,
				},
			],
			maxzoom: 19,
		},
		OcmHighRes: {
			title: 'OpenCycleMap raster HighRes',
			version: 8,
			glyphs: proxy
				? generateUrl('/apps/phonetrack/maptiler/fonts/') + '{fontstack}/{range}.pbf?key=' + apiKey
				: 'https://api.maptiler.com/fonts/{fontstack}/{range}.pbf?key=' + apiKey,
			sources: {
				'ocm-highres-source': {
					type: 'raster',
					tiles: proxy
						? [
							...['a', 'b', 'c'].map(s => generateUrl('/apps/phonetrack/tiles/ocm-highres/') + `{x}/{y}/{z}?s=${s}`),
						]
						: [
							...['a', 'b', 'c'].map(s => `https://${s}.tile.thunderforest.com/cycle/{z}/{x}/{y}@2x.png`),
						],
					tileSize: 512,
					attribution: 'Map data &copy; <a href="https://openstreetmap.org">OpenStreetMap</a> contributors',
				},
			},
			layers: [
				{
					id: 'ocm-highres-layer',
					type: 'raster',
					source: 'ocm-highres-source',
					minzoom: 0,
					maxzoom: 19,
				},
			],
			maxzoom: 19,
		},
		esriTopo: {
			title: t('phonetrack', 'ESRI topo with relief'),
			version: 8,
			glyphs: proxy
				? generateUrl('/apps/phonetrack/maptiler/fonts/') + '{fontstack}/{range}.pbf?key=' + apiKey
				: 'https://api.maptiler.com/fonts/{fontstack}/{range}.pbf?key=' + apiKey,
			sources: {
				'esri-topo-source': {
					type: 'raster',
					tiles: proxy
						? [
							generateUrl('/apps/phonetrack/tiles/esri-topo/') + '{x}/{y}/{z}',
						]
						: [
							'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}',
						],
					tileSize: 256,
					attribution: 'Tiles &copy; Esri &mdash; Esri, DeLorme, NAVTEQ, '
						+ 'TomTom, Intermap, iPC, USGS, FAO, NPS, NRCAN, GeoBase, Kadaster NL, Ord'
						+ 'nance Survey, Esri Japan, METI, Esri China (Hong Kong), and the GIS User'
						+ ' Community',
				},
			},
			layers: [
				{
					id: 'esri-topo-layer',
					type: 'raster',
					source: 'esri-topo-source',
					minzoom: 0,
					maxzoom: 19,
				},
			],
			maxzoom: 19,
		},
		waterColor: {
			title: t('phonetrack', 'WaterColor'),
			version: 8,
			glyphs: proxy
				? generateUrl('/apps/phonetrack/maptiler/fonts/') + '{fontstack}/{range}.pbf?key=' + apiKey
				: 'https://api.maptiler.com/fonts/{fontstack}/{range}.pbf?key=' + apiKey,
			sources: {
				'watercolor-source': {
					type: 'raster',
					tiles: proxy
						? [
							generateUrl('/apps/phonetrack/tiles/watercolor/') + '{x}/{y}/{z}',
						]
						: [
							'https://tiles.stadiamaps.com/styles/stamen_watercolor/{z}/{x}/{y}.jpg',
						],
					tileSize: 256,
					attribution: 'Map tiles by <a href="https://stamen'
						+ '.com">Stamen Design</a>, under <a href="https://creativecommons.org/license'
						+ 's/by/3.0">CC BY 3.0</a>, Data by <a href="https://openstreetmap.org">OpenSt'
						+ 'reetMap</a>, under <a href="https://creativecommons.org/licenses/by-sa/3.0"'
						+ '>CC BY SA</a>.',
				},
			},
			layers: [
				{
					id: 'watercolor-layer',
					type: 'raster',
					source: 'watercolor-source',
					minzoom: 0,
					maxzoom: 18,
				},
			],
			maxzoom: 18,
		},
	}
}

export function getVectorStyles(apiKey, proxy = true) {
	return {
		streets: {
			title: t('phonetrack', 'Streets'),
			uri: proxy
				? generateUrl('/apps/phonetrack/maptiler/maps/streets-v2/style.json?key=' + apiKey)
				: 'https://api.maptiler.com/maps/streets-v2/style.json?key=' + apiKey,
		},
		satellite: {
			title: t('phonetrack', 'Satellite'),
			uri: proxy
				? generateUrl('/apps/phonetrack/maptiler/maps/hybrid/style.json?key=' + apiKey)
				: 'https://api.maptiler.com/maps/hybrid/style.json?key=' + apiKey,
		},
		outdoor: {
			title: t('phonetrack', 'Outdoor'),
			uri: proxy
				? generateUrl('/apps/phonetrack/maptiler/maps/outdoor-v2/style.json?key=' + apiKey)
				: 'https://api.maptiler.com/maps/outdoor-v2/style.json?key=' + apiKey,
		},
		osm: {
			title: 'OpenStreetMap',
			uri: proxy
				? generateUrl('/apps/phonetrack/maptiler/maps/openstreetmap/style.json?key=' + apiKey)
				: 'https://api.maptiler.com/maps/openstreetmap/style.json?key=' + apiKey,
		},
		dark: {
			title: t('phonetrack', 'Dark'),
			uri: proxy
				? generateUrl('/apps/phonetrack/maptiler/maps/streets-dark/style.json?key=' + apiKey)
				: 'https://api.maptiler.com/maps/streets-dark/style.json?key=' + apiKey,
		},
	}
}

export const TS_RASTER = 0
export const TS_VECTOR = 1

export function getExtraTileServers(tileServers, apiKey, proxy = true) {
	const formattedServers = {}
	tileServers.forEach(ts => {
		if (ts.type === TS_RASTER) {
			const tileServerKey = 'extra_' + ts.id
			const sourceId = tileServerKey + '-source'
			const layerId = tileServerKey + '-layer'

			const tiles = ts.url.match(/{s}/)
				? ['a', 'b', 'c'].map(subdomain => {
					return ts.url.replace(/{s}/, subdomain)
				})
				: [
					ts.url,
				]

			formattedServers[tileServerKey] = {
				title: ts.name,
				version: 8,
				glyphs: proxy
					? generateUrl('/apps/phonetrack/maptiler/fonts/') + '{fontstack}/{range}.pbf?key=' + apiKey
					: 'https://api.maptiler.com/fonts/{fontstack}/{range}.pbf?key=' + apiKey,
				sources: {
					[sourceId]: {
						type: 'raster',
						tiles,
						// TODO make tileSize configurable when adding a tile server
						tileSize: ts.tileSize ?? 256,
						attribution: ts.attribution,
					},
				},
				layers: [
					{
						id: layerId,
						type: 'raster',
						source: sourceId,
						minzoom: ts.minZoom ?? 1,
						maxzoom: ts.maxZoom ?? 19,
					},
				],
				maxzoom: ts.maxZoom ?? 19,
			}
		} else if (ts.type === TS_VECTOR) {
			formattedServers['extra_' + ts.id] = {
				title: ts.name,
				uri: ts.url,
			}
		}
	})

	return formattedServers
}
