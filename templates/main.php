<?php
script('phonetrack', 'leaflet');
script('phonetrack', 'leaflet.polylineDecorator');
script('phonetrack', 'L.Control.MousePosition');
script('phonetrack', 'ActiveLayers');
script('phonetrack', 'L.Control.Locate.min');
script('phonetrack', 'MovingMarker');
script('phonetrack', 'leaflet-sidebar.min');
script('phonetrack', 'jquery-ui.min');
script('phonetrack', 'moment-timezone-with-data.min');
script('phonetrack', 'Leaflet.LinearMeasurement');
script('phonetrack', 'easy-button');
script('phonetrack', 'phonetrack');

style('phonetrack', 'style');
style('phonetrack', 'leaflet');
style('phonetrack', 'L.Control.MousePosition');
style('phonetrack', 'leaflet-sidebar.min');
style('phonetrack', 'jquery-ui.min');
style('phonetrack', 'font-awesome.min');
style('phonetrack', 'phonetrack');
style('phonetrack', 'L.Control.Locate');
style('phonetrack', 'Leaflet.LinearMeasurement');
style('phonetrack', 'easy-button');

?>

<div id="app">
    <div id="app-content">
        <div id="app-content-wrapper">
            <?php print_unescaped($this->inc('maincontent')); ?>
        </div>
    </div>
</div>
