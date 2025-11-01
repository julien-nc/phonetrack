import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

function formatNominatimToCarmentGeojson(results) {
	// https://docs.mapbox.com/api/search/geocoding/#geocoding-response-object
	return results.map(r => {
		const bb = r.boundingbox
		return {
			id: r.osm_id,
			place_name: r.display_name,
			bbox: [bb[2], bb[0], bb[3], bb[1]],
			// center: [r.lon, r.lat],
		}
	})
}

export async function proxiedNominatimGeocoder(query) {
	try {
		const req = {
			params: {
				query,
			},
		}
		const url = generateUrl('/apps/phonetrack/nominatim/search')
		const result = await axios.get(url, req)
		const data = result.data
		console.debug('phonetrack nominatim search result', data)
		return formatNominatimToCarmentGeojson(data)
	} catch (error) {
		console.error('Nominatim search error', error)
	}
}

export async function nominatimGeocoder(query) {
	try {
		const req = {
			params: {
				format: 'json',
				addressdetails: 1,
				extratags: 1,
				namedetails: 1,
				limit: 5,
			},
		}
		const url = 'https://nominatim.openstreetmap.org/search/' + encodeURIComponent(query)
		const result = await axios.get(url, req)
		const data = result.data
		console.debug('phonetrack nominatim search result', data)
		return formatNominatimToCarmentGeojson(data)
	} catch (error) {
		console.error('Nominatim search error', error)
	}
}

export async function maplibreForwardGeocode(config) {
	const features = []
	try {
		const req = {
			params: {
				q: config.query,
				rformat: 'geojson',
				polygon_geojson: 1,
				addressdetails: 1,
				limit: config.limit,
			},
		}
		// const url = 'https://nominatim.openstreetmap.org/search'
		const url = generateUrl('/apps/phonetrack/nominatim/search')
		const response = await axios.get(url, req)
		const geojson = response.data
		for (const feature of geojson.features) {
			const center = [
				feature.bbox[0] + (feature.bbox[2] - feature.bbox[0]) / 2,
				feature.bbox[1] + (feature.bbox[3] - feature.bbox[1]) / 2,
			]
			const point = {
				type: 'Feature',
				geometry: {
					type: 'Point',
					coordinates: center,
				},
				place_name: feature.properties.display_name,
				properties: feature.properties,
				text: feature.properties.display_name,
				place_type: ['place'],
				center,
			}
			features.push(point)
		}
	} catch (e) {
		console.error(`Failed to forwardGeocode with error: ${e}`)
	}

	return {
		features,
	}
}
