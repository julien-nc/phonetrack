/**
 * Created: vogdb Date: 5/4/13 Time: 1:54 PM
 * Version: 0.3.0
 */

L.Control.ActiveLayers = L.Control.Layers.extend({

  /**
   * Get currently active base layer on the map
   * @return {Object} l where l.name - layer name on the control,
   *  l.layer is L.TileLayer, l.overlay is overlay layer.
   */
  getActiveBaseLayer: function () {
    return this._activeBaseLayer
  },

  /**
   * Get currently active overlay layers on the map
   * @return {{layerId: l}} where layerId is <code>L.stamp(l.layer)</code>
   *  and l @see #getActiveBaseLayer jsdoc.
   */
  getActiveOverlayLayers: function () {
    return this._activeOverlayLayers
  },

  onAdd: function (map) {
    var container = L.Control.Layers.prototype.onAdd.call(this, map)

    if (Array.isArray(this._layers)) {
      this._activeBaseLayer = this._findActiveBaseLayer()
      this._activeOverlayLayers = this._findActiveOverlayLayers()
    } else {    // 0.7.x
      this._activeBaseLayer = this._findActiveBaseLayerLegacy()
      this._activeOverlayLayers = this._findActiveOverlayLayersLegacy()
    }
    return container
  },

  _findActiveBaseLayer: function () {
    var layers = this._layers
    for (var i = 0; i < layers.length; i++) {
      var layer = layers[i]
      if (!layer.overlay && this._map.hasLayer(layer.layer)) {
        return layer
      }
    }
    throw new Error('Control doesn\'t have any active base layer!')
  },

  _findActiveOverlayLayers: function () {
    var result = {}
    var layers = this._layers
    for (var i = 0; i < layers.length; i++) {
      var layer = layers[i]
      if (layer.overlay && this._map.hasLayer(layer.layer)) {
        result[layer.layer._leaflet_id] = layer
      }
    }
    return result
  },

  /**
   * Legacy 0.7.x support methods
   */
  _findActiveBaseLayerLegacy: function () {
    var layers = this._layers
    for (var layerId in layers) {
      if (this._layers.hasOwnProperty(layerId)) {
        var layer = layers[layerId]
        if (!layer.overlay && this._map.hasLayer(layer.layer)) {
          return layer
        }
      }
    }
    throw new Error('Control doesn\'t have any active base layer!')
  },

  _findActiveOverlayLayersLegacy: function () {
    var result = {}
    var layers = this._layers
    for (var layerId in layers) {
      if (this._layers.hasOwnProperty(layerId)) {
        var layer = layers[layerId]
        if (layer.overlay && this._map.hasLayer(layer.layer)) {
          result[layerId] = layer
        }
      }
    }
    return result
  },

  _onLayerChange: function () {
    L.Control.Layers.prototype._onLayerChange.apply(this, arguments)
    this._recountLayers()
  },

  _onInputClick: function () {
    this._handlingClick = true

    this._recountLayers()
    L.Control.Layers.prototype._onInputClick.call(this)

    this._handlingClick = false
  },

  _recountLayers: function () {
    var i, input, obj,
      inputs = this._form.getElementsByTagName('input'),
      inputsLen = inputs.length;

    for (i = 0; i < inputsLen; i++) {
      input = inputs[i]
      if (Array.isArray(this._layers)) {
        obj = this._layers[i]
      } else {
        obj = this._layers[input.layerId]   // 0.7.x
      }

      if (input.checked && !this._map.hasLayer(obj.layer)) {
        if (obj.overlay) {
          this._activeOverlayLayers[input.layerId] = obj
        } else {
          this._activeBaseLayer = obj
        }
      } else if (!input.checked && this._map.hasLayer(obj.layer)) {
        if (obj.overlay) {
          delete this._activeOverlayLayers[input.layerId]
        }
      }
    }
  }

})

L.control.activeLayers = function (baseLayers, overlays, options) {
  return new L.Control.ActiveLayers(baseLayers, overlays, options)
}
