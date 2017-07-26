<?php
script('gpsphonetracking', 'leaflet');
script('gpsphonetracking', 'leaflet.polylineDecorator');
script('gpsphonetracking', 'L.Control.MousePosition');
script('gpsphonetracking', 'ActiveLayers');
script('gpsphonetracking', 'L.Control.Locate.min');
script('gpsphonetracking', 'MovingMarker');
script('gpsphonetracking', 'leaflet-sidebar.min');
script('gpsphonetracking', 'jquery-ui.min');
script('gpsphonetracking', 'moment-timezone-with-data.min');
script('gpsphonetracking', 'Leaflet.LinearMeasurement');
script('gpsphonetracking', 'gpsphonetracking');

style('gpsphonetracking', 'style');
style('gpsphonetracking', 'leaflet');
style('gpsphonetracking', 'L.Control.MousePosition');
style('gpsphonetracking', 'leaflet-sidebar.min');
style('gpsphonetracking', 'jquery-ui.min');
style('gpsphonetracking', 'font-awesome.min');
style('gpsphonetracking', 'gpsphonetracking');
style('gpsphonetracking', 'L.Control.Locate.min');
style('gpsphonetracking', 'Leaflet.LinearMeasurement');

?>

<div id="app">
    <div id="app-content">
        <div id="app-content-wrapper">
            <?php print_unescaped($this->inc('maincontent')); ?>
        </div>
    </div>
</div>
