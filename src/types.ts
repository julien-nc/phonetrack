/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
export interface TileServer {
	id: number,
	user_id: string | null,
	type: number,
	name: string,
	url: string,
	min_zoom: number | null,
	max_zoom: number | null,
	attribution: string | null,
}
