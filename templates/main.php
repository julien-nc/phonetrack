<?php
script('phonetrack', 'leaflet');
script('phonetrack', 'd3.min');
script('phonetrack', 'leaflet.polylineDecorator');
script('phonetrack', 'L.Control.MousePosition');
script('phonetrack', 'ActiveLayers');
script('phonetrack', 'L.Control.Locate.min');
script('phonetrack', 'L.Control.Elevation');
script('phonetrack', 'MovingMarker');
script('phonetrack', 'leaflet-sidebar.min');
script('phonetrack', 'jquery-ui.min');
script('phonetrack', 'moment-timezone-with-data.min');
script('phonetrack', 'Leaflet.LinearMeasurement');
script('phonetrack', 'Leaflet.Dialog');
script('phonetrack', 'easy-button');
script('phonetrack', 'jquery.plugin.min');
script('phonetrack', 'jquery.countdown.min');
script('phonetrack', 'leaflet.hotline');
script('phonetrack', 'kjua.min');
script('phonetrack', 'mapbox-gl');
script('phonetrack', 'leaflet-mapbox-gl');
script('phonetrack', 'phonetrack');

style('phonetrack', 'style');
style('phonetrack', 'leaflet');
style('phonetrack', 'L.Control.MousePosition');
style('phonetrack', 'Leaflet.Elevation-0.0.2');
style('phonetrack', 'leaflet-sidebar.min');
style('phonetrack', 'jquery-ui.min');
style('phonetrack', 'fontawesome/css/all.min');
style('phonetrack', 'L.Control.Locate');
style('phonetrack', 'Leaflet.LinearMeasurement');
style('phonetrack', 'Leaflet.Dialog');
style('phonetrack', 'easy-button');
style('phonetrack', 'mapbox-gl');
style('phonetrack', 'phonetrack');

?>

<div id="app">
    <div id="app-content">
            <?php print_unescaped($this->inc('maincontent')); ?>
    </div>
</div>
