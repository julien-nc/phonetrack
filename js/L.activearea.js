(function(previousMethods){
if (typeof previousMethods === 'undefined') {
    // Defining previously that object allows you to use that plugin even if you have overridden L.map
    previousMethods = {
        getCenter: L.Map.prototype.getCenter,
        setView: L.Map.prototype.setView,
        setZoomAround: L.Map.prototype.setZoomAround,
        getBoundsZoom: L.Map.prototype.getBoundsZoom,
        scaleUpdate: L.Control.Scale.prototype._update
    };
}


L.Map.include({
    getBounds: function() {
        if (this._viewport) {
            return this.getViewportLatLngBounds()
        } else {
            var bounds = this.getPixelBounds(),
            sw = this.unproject(bounds.getBottomLeft()),
            ne = this.unproject(bounds.getTopRight());

            return new L.LatLngBounds(sw, ne);
        }
    },

    getViewport: function() {
        return this._viewport;
    },

    getViewportBounds: function() {
        var vp = this._viewport,
            topleft = L.point(vp.offsetLeft, vp.offsetTop),
            vpsize = L.point(vp.clientWidth, vp.clientHeight);

        if (vpsize.x === 0 || vpsize.y === 0) {
            //Our own viewport has no good size - so we fallback to the container size:
            vp = this.getContainer();
            if(vp){
              topleft = L.point(0, 0);
              vpsize = L.point(vp.clientWidth, vp.clientHeight);
            }

        }

        return L.bounds(topleft, topleft.add(vpsize));
    },

    getViewportLatLngBounds: function() {
        var bounds = this.getViewportBounds();
        return L.latLngBounds(this.containerPointToLatLng(bounds.min), this.containerPointToLatLng(bounds.max));
    },

    getOffset: function() {
        var mCenter = this.getSize().divideBy(2),
            vCenter = this.getViewportBounds().getCenter();

        return mCenter.subtract(vCenter);
    },

    getCenter: function () {
        var center = previousMethods.getCenter.call(this);

        if (this.getViewport()) {
            var zoom = this.getZoom(),
                point = this.project(center, zoom);
            point = point.subtract(this.getOffset());

            center = this.unproject(point, zoom);
        }

        return center;
    },

    setView: function (center, zoom, options) {
        center = L.latLng(center);
        zoom = zoom || this.getZoom();

        if (this.getViewport()) {
            var point = this.project(center, this._limitZoom(zoom));
            point = point.add(this.getOffset());
            center = this.unproject(point, this._limitZoom(zoom));
        }

        return previousMethods.setView.call(this, center, zoom, options);
    },

    setZoomAround: function (latlng, zoom, options) {
        var vp = this.getViewport();

        if (vp) {
            var scale = this.getZoomScale(zoom),
                viewHalf = this.getViewportBounds().getCenter(),
                containerPoint = latlng instanceof L.Point ? latlng : this.latLngToContainerPoint(latlng),

                centerOffset = containerPoint.subtract(viewHalf).multiplyBy(1 - 1 / scale),
                newCenter = this.containerPointToLatLng(viewHalf.add(centerOffset));

            return this.setView(newCenter, zoom, {zoom: options});
        } else {
            return previousMethods.setZoomAround.call(this, latlng, zoom, options);
        }
    },

    getBoundsZoom: function (bounds, inside, padding) { // (LatLngBounds[, Boolean, Point]) -> Number
        bounds = L.latLngBounds(bounds);

        var zoom = this.getMinZoom() - (inside ? 1 : 0),
            maxZoom = this.getMaxZoom(),
            vp = this.getViewport(),
            size = (vp) ? L.point(vp.clientWidth, vp.clientHeight) : this.getSize(),

            nw = bounds.getNorthWest(),
            se = bounds.getSouthEast(),

            zoomNotFound = true,
            boundsSize;

        padding = L.point(padding || [0, 0]);

        do {
            zoom++;
            boundsSize = this.project(se, zoom).subtract(this.project(nw, zoom)).add(padding);
            zoomNotFound = !inside ? size.contains(boundsSize) : boundsSize.x < size.x || boundsSize.y < size.y;

        } while (zoomNotFound && zoom <= maxZoom);

        if (zoomNotFound && inside) {
            return null;
        }

        return inside ? zoom : zoom - 1;
    }
});

L.Control.Scale.include({
    _update: function () {
        if (!this._map._viewport) {
            previousMethods.scaleUpdate.call(this);
        } else {
            var bounds = this._map.getBounds(),
                centerLat = bounds.getCenter().lat,
                halfWorldMeters = 6378137 * Math.PI * Math.cos(centerLat * Math.PI / 180),
                dist = halfWorldMeters * (bounds.getNorthEast().lng - bounds.getSouthWest().lng) / 180,

                size = this._map.getSize(),
                options = this.options,
                maxMeters = 0;

            var size = new L.Point(
                this._map._viewport.clientWidth,
                this._map._viewport.clientHeight);

            if (size.x > 0) {
                maxMeters = dist * (options.maxWidth / size.x);
            }

            this._updateScales(options, maxMeters);
        }
    }
});

L.Map.include({
    setActiveArea: function (css) {
        if( !this._viewport ){
            //Make viewport if not already made
            var container = this.getContainer();
            this._viewport = L.DomUtil.create('div', '');
            container.insertBefore(this._viewport, container.firstChild);
        }

        if (typeof css === 'string') {
            this._viewport.className = css;
        } else {
            L.extend(this._viewport.style, css);
        }
        return this;
    }
});
})(window.leafletActiveAreaPreviousMethods);
