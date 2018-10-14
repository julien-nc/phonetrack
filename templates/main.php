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
script('phonetrack', 'Leaflet.Dialog');
script('phonetrack', 'easy-button');
script('phonetrack', 'jquery.plugin.min');
script('phonetrack', 'jquery.countdown.min');
script('phonetrack', 'leaflet.hotline');
script('phonetrack', 'jquery.qrcode.min');
script('phonetrack', 'phonetrack');

style('phonetrack', 'style');
style('phonetrack', 'leaflet');
style('phonetrack', 'L.Control.MousePosition');
style('phonetrack', 'leaflet-sidebar.min');
style('phonetrack', 'jquery-ui.min');
style('phonetrack', 'fontawesome/css/all.min');
style('phonetrack', 'phonetrack');
style('phonetrack', 'L.Control.Locate');
style('phonetrack', 'Leaflet.LinearMeasurement');
style('phonetrack', 'Leaflet.Dialog');
style('phonetrack', 'easy-button');

?>

<div id="app">
    <div id="app-content">
            <?php print_unescaped($this->inc('maincontent')); ?>
    </div>
</div>
