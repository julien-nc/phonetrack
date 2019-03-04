<div id="sidebar" class="sidebar">
<!-- Nav tabs -->
<ul class="sidebar-tabs" role="tablist">
<li class="active" title="<?php p($l->t('Main tab')); ?>"><a href="#ho" role="tab"><i class="fa fa-bars"></i></a></li>
<li title="<?php p($l->t('Filters')); ?>">
<a href="#phonetrackfilters" role="tab">
    <span class="fa-stack fa-lg">
        <i id="sidebarFen" class="fa fa-circle"></i>
        <i id="sidebarFdis" class="fa fa-circle-o"></i>
        <i class="fa fa-filter fa-stack-1x"></i>
    </span>
</a>
</li>
<li title="<?php p($l->t('Stats')); ?>"><a href="#phonetrackstats" role="tab"><i class="fa fa-table"></i></a></li>
<li title="<?php p($l->t('Settings and extra actions')); ?>"><a href="#phonetracksettings" role="tab"><i class="fa fa-cogs"></i></a></li>
<li title="<?php p($l->t('About PhoneTrack')); ?>"><a href="#help" role="tab"><i class="fa fa-question"></i></a></li>
</ul>
<!-- Tab panes -->
<div class="sidebar-content active">
<div class="sidebar-pane active" id="ho">
    <div id="logofolder">
        <div id="logo"></div>
        <p class="version">v
<?php
p($_['phonetrack_version']);
?>
        </p>
        <div id="topbuttons">
            <div id="createimportsessiondiv">
                <button id="importsession">
                    <i class="fa fa-folder-open" aria-hidden="true"></i>
                    <?php p($l->t('Import session')); ?>
                </button>
                <button id="showcreatesession">
                    <i class="fa fa-plus-circle" aria-hidden="true"></i>
                    <?php p($l->t('Create session')); ?>
                </button>
            </div>
            <div id="newsessiondiv">
                <label for="sessionnameinput"><?php p($l->t('Session name')); ?></label>
                <input type="text" id="sessionnameinput"/>
                <button id="newsession">
                    <i class="fa fa-check" aria-hidden="true"></i>
                    <?php p($l->t('Ok')); ?>
                </button>
            </div>
        </div>
    </div>
    <hr/>
    <div id="options">
        <div>
        <h3 id="optiontitle" class="sectiontitle">
        <b id="optiontitletext"><i class="fa fa-caret-right"></i> <?php p($l->t('Options')); ?> </b>
        </h3>
        </div>
        <div style="clear:both"></div>
        <div id="optionscontent" style="display:none;">
        <div id="optioncheckdiv">
            <div>
            <hr/>
            <h2 id="optiontitle" class="sectiontitle"><?php p($l->t('General')); ?></h2>
            </div>
            <label for="updateinterval"><i class="fa fa-sync-alt" aria-hidden="true"></i>
            <?php p($l->t('Refresh each (sec)')); ?></label>
            <input id="updateinterval" type="number" min="0" max="100000" step="1" value="15"/>
            <div id="countdown"></div>
            <button id="refreshButton"><i class="fa fa-sync-alt" aria-hidden="true"></i> <?php p($l->t('Refresh')); ?></button>
            <br/>
            <div title="<?php p($l->t('An empty value means no limit')); ?>">
            <label for="nbpointsload"><i class="fas fa-ellipsis-v" aria-hidden="true"></i>
            <?php p($l->t('Max number of points per device to load on refresh')); ?></label>
            <input id="nbpointsload" type="number" min="1" max="400000000" step="1" value="1000"/> <?php p($l->t('points')); ?>
            </div>
            <div title="<?php p($l->t('Cutting lines only affects map view and stats table')); ?>">
            <label for="cutdistance"><i class="fa fa-cut" aria-hidden="true"></i>
            <?php p($l->t('Minimum distance to cut between two points')); ?></label>
            <input id="cutdistance" type="number" min="1" max="40000000" step="1" value=""/> <?php p($l->t('meters')); ?>
            </div>
            <div title="<?php p($l->t('Cutting lines only affects map view and stats table')); ?>">
            <label for="cuttime"><i class="fa fa-cut" aria-hidden="true"></i>
            <?php p($l->t('Minimum time to cut between two points')); ?></label>
            <input id="cuttime" type="number" min="1" max="100000000" step="1" value=""/> <?php p($l->t('seconds')); ?>
            </div>
            <label for="quotareached"><i class="fa fa-chart-pie" aria-hidden="true"></i>
            <?php p($l->t('When point quota is reached')); ?></label>
            <select id="quotareached">
                <option selected value="block"><?php p($l->t('block logging')); ?></option>
                <option value="rotateglob"><?php p($l->t('delete user\'s oldest point each time a new one is logged')); ?></option>
                <option value="rotatedev"><?php p($l->t('delete device\'s oldest point each time a new one is logged')); ?></option>
            </select>
            <div>
            <hr/>
            <h2 id="optiontitle" class="sectiontitle"><?php p($l->t('Display')); ?></h2>
            </div>
            <input id="autozoom" type="checkbox"/>
            <label for="autozoom"><i class="fa nc-theming-main-background"> </i>
            <?php p($l->t('Auto zoom')); ?></label>
            <br/>
            <input id="viewmove" type="checkbox" checked/>
            <label for="viewmove"><i class="fa nc-theming-main-background"> </i>
            <?php p($l->t('Show lines')); ?></label>
            <br/>
            <input id="showtime" type="checkbox"/>
            <label for="showtime"><i class="fa nc-theming-main-background"> </i>
            <?php p($l->t('Show tooltips')); ?></label>
            <br/>
            <input id="markerletter" type="checkbox" checked/>
            <label for="markerletter"><i class="fa fa-font" aria-hidden="true"></i>
            <?php p($l->t('Display first letter of device name on last position')); ?></label>
            <br/>
            <input id="linearrow" type="checkbox"/>
            <label for="linearrow"><i class="fa fa-arrow-right" aria-hidden="true"></i>
            <?php p($l->t('Show direction arrows along lines')); ?></label>
            <br/>
            <input id="linegradient" type="checkbox"/>
            <label for="linegradient"><i class="fa fa-paint-brush" aria-hidden="true"></i>
            <?php p($l->t('Draw line with color gradient')); ?></label>
            <br/>
            <input id="tooltipshowaccuracy" type="checkbox" checked/>
            <label for="tooltipshowaccuracy"><i class="far fa-dot-circle" aria-hidden="true"></i>
            <?php p($l->t('Show accuracy in tooltips')); ?></label>
            <br/>
            <input id="tooltipshowspeed" type="checkbox" checked/>
            <label for="tooltipshowspeed"><i class="fa fa-tachometer-alt" aria-hidden="true"></i>
            <?php p($l->t('Show speed in tooltips')); ?></label>
            <br/>
            <input id="tooltipshowbearing" type="checkbox" checked/>
            <label for="tooltipshowbearing"><i class="fa fa-compass" aria-hidden="true"></i>
            <?php p($l->t('Show bearing in tooltips')); ?></label>
            <br/>
            <input id="tooltipshowsatellites" type="checkbox" checked/>
            <label for="tooltipshowsatellites"><i class="fa fa-signal" aria-hidden="true"></i>
            <?php p($l->t('Show satellites in tooltips')); ?></label>
            <br/>
            <input id="tooltipshowbattery" type="checkbox" checked/>
            <label for="tooltipshowbattery"><i class="fa fa-battery-half" aria-hidden="true"></i>
            <?php p($l->t('Show battery level in tooltips')); ?></label>
            <br/>
            <input id="tooltipshowelevation" type="checkbox" checked/>
            <label for="tooltipshowelevation"><i class="fa fa-chart-area" aria-hidden="true"></i>
            <?php p($l->t('Show elevation in tooltips')); ?></label>
            <br/>
            <input id="tooltipshowuseragent" type="checkbox" checked/>
            <label for="tooltipshowuseragent"><i class="fa fa-mobile-alt" aria-hidden="true" style="font-size: 20px"></i>
            <?php p($l->t('Show user-agent in tooltips')); ?></label>
            <br/>
            <input id="dragcheck" type="checkbox" checked/>
            <label for="dragcheck"><i class="far fa-hand-paper" aria-hidden="true"></i>
            <?php p($l->t('Make points draggable in edition mode')); ?></label>
            <br/>
            <input id="acccirclecheck" type="checkbox" checked/>
            <label for="acccirclecheck"><i class="far fa-circle" aria-hidden="true"></i>
            <?php p($l->t('Show accuracy circle on hover')); ?></label>
            <br/>
            <div class="rangediv">
                <label for="linewidth"><i class="fa fa-pencil-alt" aria-hidden="true"></i>
                <?php p($l->t('Line width')); ?>: </label>
                <label id="linewidthlabel">4px</label>
                <input id="linewidth" type="range" min="1" max="20" step="1" value="4"/>

                <label for="pointradius"><i class="fa fa-circle" aria-hidden="true"></i>
                <?php p($l->t('Point radius')); ?>: </label>
                <label id="pointradiuslabel">8px</label>
                <input id="pointradius" type="range" min="4" max="20" step="1" value="8"/>

                <label for="pointlinealpha"><i class="fa fa-eraser" aria-hidden="true"></i>
                <?php p($l->t('Points and lines opacity')); ?>: </label>
                <label id="pointlinealphalabel">0.8</label>
                <input id="pointlinealpha" type="range" min="0.1" max="1" step="0.1" value="0.8"/>
            </div>
            <label for="colorthemeselect"><i class="fa fa-paint-brush" aria-hidden="true"></i>
            <?php p($l->t('Theme')); ?> *</label>
            <select id="colorthemeselect">
                <option value="bright"><?php p($l->t('bright')); ?></option>
                <option value="pastel"><?php p($l->t('pastel')); ?></option>
                <option value="dark"><?php p($l->t('dark')); ?></option>
            </select>
            <br/>
            <input id="pubviewline" type="checkbox"/>
            <label for="pubviewline"><i class="fa nc-theming-main-background"> </i>
            <?php p($l->t('Show lines in public pages')); ?></label>
            <br/>
            <input id="pubviewpoint" type="checkbox"/>
            <label for="pubviewpoint"><i class="fa fa-circle" aria-hidden="true"> </i>
            <?php p($l->t('Show points in public pages')); ?></label>
            <br/>
            <label>(*) <?php p($l->t('reload page to make changes effective')); ?></label>
            <br/>
            <div>
            <hr/>
            <h2 id="optiontitle" class="sectiontitle"><?php p($l->t('File export')); ?></h2>
            </div>
            <label for="autoexportpath"><i class="far fa-save" aria-hidden="true"></i>
            <?php p($l->t('Auto export path')); ?></label>
            <input id="autoexportpath" type="text" value="/PhoneTrack_export"/>
            <br/>
            <input id="exportoneperdev" type="checkbox"/>
            <label for="exportoneperdev"><i class="fas fa-save" aria-hidden="true"></i>
            <?php p($l->t('Export one file per device')); ?></label>
        </div>
        </div>
    </div>
    <div id="logmediv">
        <hr/>
        <div id="logmesubdiv">
            <label for="logmedeviceinput"><?php p($l->t('Device name')); ?></label>
            <input type="text" id="logmedeviceinput" value="1"/>
            <label for="logme"><?php p($l->t('Log my position in this session')); ?></label>
            <input type="checkbox" id="logme"/>
        </div>
    </div>
    <hr/>
    <h3 id="ticv" class="sectiontitle"><?php p($l->t('Tracking sessions')); ?></h3>
    <div id="sessions">
    </div>
    <div id="trackurldialog" style="display:none;">
        <label id="trackurllabel" for="trackurlinput"></label>
        <br/>
        <input id="trackurlinput" type="text"></input>
        <div id="trackurlhint">
        <?php p($l->t('Replace \'yourname\' with the desired device name or with the name reservation token')); ?>
        </div>
        <div id="trackurlqrcode"></div>
    </div>
    <input id="tracknamecolor" type="text" style="display:none;"></input>
    <input id="colorinput" type="color"></input>
    <img id="dummylogo"/>
<?php

echo '<p id="username" style="display:none">';
p($_['username']);
echo '</p>'."\n";
echo '<p id="publicsessionname" style="display:none">';
p($_['publicsessionname']);
echo '</p>'."\n";
echo '<p id="lastposonly" style="display:none">';
p($_['lastposonly']);
echo '</p>'."\n";
echo '<p id="sharefilters" style="display:none">';
p($_['sharefilters']);
echo '</p>'."\n";
echo '<ul id="basetileservers" style="display:none">';
foreach($_['basetileservers'] as $ts){
    echo '<li';
    foreach (Array('name', 'type', 'url', 'layers', 'version', 'format', 'opacity', 'transparent', 'minzoom', 'maxzoom', 'attribution') as $field) {
        if (array_key_exists($field, $ts)) {
            echo ' '.$field.'="';
            p($ts[$field]);
            echo '"';
        }
    }
    echo '></li>';
}
echo '</ul>'."\n";

?>
</div>
<div class="sidebar-pane" id="phonetracksettings">
<h1 class="sectiontitle"><?php p($l->t('Settings and extra actions')); ?></h1>
<hr/>
<br/>

<div id="customtilediv">
<h3 class="sectiontitle customtiletitle" for="tileserverdiv"><b><?php p($l->t('Custom tile servers')); ?></b> <i class="fa fa-angle-double-down" aria-hidden="true"></i></h3>
<div id="tileserverdiv">
    <div id="tileserveradd">
        <p><?php p($l->t('Server name')); ?> :</p>
        <input type="text" id="tileservername" title="<?php p($l->t('For example : my custom server')); ?>"/>
        <p><?php p($l->t('Server address')); ?> :</p>
        <input type="text" id="tileserverurl" title="<?php p($l->t('For example : http://tile.server.org/cycle/{z}/{x}/{y}.png')); ?>"/>
        <p><?php p($l->t('Min zoom (1-20)')); ?> :</p>
        <input type="text" id="tileminzoom" value="1"/>
        <p><?php p($l->t('Max zoom (1-20)')); ?> :</p>
        <input type="text" id="tilemaxzoom" value="18"/>
        <button id="addtileserver"><i class="fa fa-plus-circle" aria-hidden="true" style="color:green;"></i> <?php p($l->t('Add')); ?></button>
    </div>
    <div id="tileserverlist">
        <h3><?php p($l->t('Your tile servers')); ?></h3>
        <ul class="disclist">
<?php
if (count($_['usertileservers']) > 0){
    foreach($_['usertileservers'] as $ts){
        echo '<li title="'.$ts['url'].'"';
        foreach (Array('servername', 'type', 'url', 'layers', 'version', 'format', 'opacity', 'transparent', 'minzoom', 'maxzoom', 'attribution') as $field) {
            if (array_key_exists($field, $ts)) {
                echo ' '.$field.'="';
                p($ts[$field]);
                echo '"';
            }
        }
        echo '>';
        p($ts['servername']);
        echo '&nbsp <button><i class="fa fa-trash" aria-hidden="true" style="color:red;"></i> ';
        p($l->t('Delete'));
        echo '</button></li>';
    }
}
?>
        </ul>
    </div>
</div>

<hr/>
<h3 class="sectiontitle customtiletitle" for="overlayserverdiv"><b><?php p($l->t('Custom overlay tile servers')); ?></b> <i class="fa fa-angle-double-down" aria-hidden="true"></i></h3>
<div id="overlayserverdiv">
    <div id="overlayserveradd">
        <p><?php p($l->t('Server name')); ?> :</p>
        <input type="text" id="overlayservername" title="<?php p($l->t('For example : my custom server')); ?>"/>
        <p><?php p($l->t('Server url')); ?> :</p>
        <input type="text" id="overlayserverurl" title="<?php p($l->t('For example : http://overlay.server.org/cycle/{z}/{x}/{y}.png')); ?>"/>
        <p><?php p($l->t('Min zoom (1-20)')); ?> :</p>
        <input type="text" id="overlayminzoom" value="1"/>
        <p><?php p($l->t('Max zoom (1-20)')); ?> :</p>
        <input type="text" id="overlaymaxzoom" value="18"/>
        <label for="overlaytransparent"><?php p($l->t('Transparent')); ?> :</label>
        <input type="checkbox" id="overlaytransparent" checked/>
        <p><?php p($l->t('Opacity (0.0-1.0)')); ?> :</p>
        <input type="text" id="overlayopacity" value="0.4"/>
        <button id="addoverlayserver"><i class="fa fa-plus-circle" aria-hidden="true" style="color:green;"></i> <?php p($l->t('Add')); ?></button>
    </div>
    <div id="overlayserverlist">
        <h3><?php p($l->t('Your overlay tile servers')); ?></h3>
        <ul class="disclist">
<?php
if (count($_['useroverlayservers']) > 0){
    foreach($_['useroverlayservers'] as $ts){
        echo '<li title="'.$ts['url'].'"';
        foreach (Array('servername', 'type', 'url', 'layers', 'version', 'format', 'opacity', 'transparent', 'minzoom', 'maxzoom', 'attribution') as $field) {
            if (array_key_exists($field, $ts)) {
                echo ' '.$field.'="';
                p($ts[$field]);
                echo '"';
            }
        }
        echo '>';
        p($ts['servername']);
        echo '&nbsp <button><i class="fa fa-trash" aria-hidden="true" style="color:red;"></i> ';
        p($l->t('Delete'));
        echo '</button></li>';
    }
}
?>
        </ul>
    </div>
</div>
<hr/>
<h3 class="sectiontitle customtiletitle" for="tilewmsserverdiv"><b><?php p($l->t('Custom WMS tile servers')); ?></b> <i class="fa fa-angle-double-down" aria-hidden="true"></i></h3>
<div id="tilewmsserverdiv">
    <div id="tilewmsserveradd">
        <p><?php p($l->t('Server name')); ?> :</p>
        <input type="text" id="tilewmsservername" title="<?php p($l->t('For example : my custom server')); ?>"/>
        <p><?php p($l->t('Server url')); ?> :</p>
        <input type="text" id="tilewmsserverurl" title="<?php p($l->t('For example : http://tile.server.org/cycle/{z}/{x}/{y}.png')); ?>"/>
        <p><?php p($l->t('Min zoom (1-20)')); ?> :</p>
        <input type="text" id="tilewmsminzoom" value="1"/>
        <p><?php p($l->t('Max zoom (1-20)')); ?> :</p>
        <input type="text" id="tilewmsmaxzoom" value="18"/>
        <p><?php p($l->t('Format')); ?> :</p>
        <input type="text" id="tilewmsformat" value="image/jpeg"/>
        <p><?php p($l->t('WMS version')); ?> :</p>
        <input type="text" id="tilewmsversion" value="1.1.1"/>
        <p><?php p($l->t('Layers to display')); ?> :</p>
        <input type="text" id="tilewmslayers" value=""/>
        <button id="addtileserverwms"><i class="fa fa-plus-circle" aria-hidden="true" style="color:green;"></i> <?php p($l->t('Add')); ?></button>
    </div>
    <div id="tilewmsserverlist">
        <h3><?php p($l->t('Your WMS tile servers')); ?></h3>
        <ul class="disclist">
<?php
if (count($_['usertileserverswms']) > 0){
    foreach($_['usertileserverswms'] as $ts){
        echo '<li title="'.$ts['url'].'"';
        foreach (Array('servername', 'type', 'url', 'layers', 'version', 'format', 'opacity', 'transparent', 'minzoom', 'maxzoom', 'attribution') as $field) {
            if (array_key_exists($field, $ts)) {
                echo ' '.$field.'="';
                p($ts[$field]);
                echo '"';
            }
        }
        echo '>';
        p($ts['servername']);
        echo '&nbsp <button><i class="fa fa-trash" aria-hidden="true" style="color:red;"></i> ';
        p($l->t('Delete'));
        echo '</button></li>';
    }
}
?>
        </ul>
    </div>
</div>
<hr/>
<h3 class="sectiontitle customtiletitle" for="overlaywmsserverdiv"><b><?php p($l->t('Custom WMS overlay servers')); ?></b> <i class="fa fa-angle-double-down" aria-hidden="true"></i></h3>
<div id="overlaywmsserverdiv">
    <div id="overlaywmsserveradd">
        <p><?php p($l->t('Server name')); ?> :</p>
        <input type="text" id="overlaywmsservername" title="<?php p($l->t('For example : my custom server')); ?>"/>
        <p><?php p($l->t('Server url')); ?> :</p>
        <input type="text" id="overlaywmsserverurl" title="<?php p($l->t('For example : http://overlay.server.org/cycle/{z}/{x}/{y}.png')); ?>"/>
        <p><?php p($l->t('Min zoom (1-20)')); ?> :</p>
        <input type="text" id="overlaywmsminzoom" value="1"/>
        <p><?php p($l->t('Max zoom (1-20)')); ?> :</p>
        <input type="text" id="overlaywmsmaxzoom" value="18"/>
        <label for="overlaywmstransparent"><?php p($l->t('Transparent')); ?> :</label>
        <input type="checkbox" id="overlaywmstransparent" checked/>
        <p><?php p($l->t('Opacity (0.0-1.0)')); ?> :</p>
        <input type="text" id="overlaywmsopacity" value="0.4"/>
        <p><?php p($l->t('Format')); ?> :</p>
        <input type="text" id="overlaywmsformat" value="image/jpeg"/>
        <p><?php p($l->t('WMS version')); ?> :</p>
        <input type="text" id="overlaywmsversion" value="1.1.1"/>
        <p><?php p($l->t('Layers to display')); ?> :</p>
        <input type="text" id="overlaywmslayers" value=""/>
        <button id="addoverlayserverwms"><i class="fa fa-plus-circle" aria-hidden="true" style="color:green;"></i> <?php p($l->t('Add')); ?></button>
    </div>
    <div id="overlaywmsserverlist">
        <h3><?php p($l->t('Your WMS overlay tile servers')); ?></h3>
        <ul class="disclist">
<?php
if (count($_['useroverlayserverswms']) > 0){
    foreach($_['useroverlayserverswms'] as $ts){
        echo '<li title="'.$ts['url'].'"';
        foreach (Array('servername', 'type', 'url', 'layers', 'version', 'format', 'opacity', 'transparent', 'minzoom', 'maxzoom', 'attribution') as $field) {
            if (array_key_exists($field, $ts)) {
                echo ' '.$field.'="';
                p($ts[$field]);
                echo '"';
            }
        }
        echo '>';
        p($ts['servername']);
        echo '&nbsp <button><i class="fa fa-trash" aria-hidden="true" style="color:red;"></i> ';
        p($l->t('Delete'));
        echo '</button></li>';
    }
}
?>
        </ul>
    </div>
</div>

</div>

<div id="addPointDiv">
<hr/>
<h3><?php p($l->t('Manually add a point')); ?></h3>
<br/>
<label for="addPointSession"><?php p($l->t('Session')); ?></label>
<select id="addPointSession">
</select>
<br/>
<label for="addPointDevice"><?php p($l->t('Device')); ?></label>
<input id="addPointDevice" value="<?php p($l->t('Device name')); ?>"/>
<button id="validaddpoint"><i class="fa fa-plus-circle" aria-hidden="true"></i> <?php p($l->t('Add a point')); ?></button>
<p id="explainaddpoint"><?php p($l->t('Now, click on the map to add a point (if session is not activated, you won\'t see added point)')); ?></p>
<button id="canceladdpoint"><i class="fa fa-undo" aria-hidden="true" style="color:red;"></i> <?php p($l->t('Cancel add point')); ?></button>
</div>

<div id="deletePointDiv">
<hr/>
<h3><?php p($l->t('Delete multiple points')); ?></h3>
<br/>
<p>
<?php p($l->t('Choose a session, a device and adjust the filters. All displayed points for selected device will be deleted. An empty device name selects them all.')); ?>
</p>
<label for="deletePointSession"><?php p($l->t('Session')); ?></label>
<select id="deletePointSession">
</select>
<br/>
<label for="deletePointDevice"><?php p($l->t('Device')); ?></label>
<input id="deletePointDevice" value="<?php p($l->t('Device name')); ?>"/>
<button id="validdeletepoint"><i class="fa fa-trash" aria-hidden="true" style="color:red;"></i> <?php p($l->t('Delete points')); ?></button>
<button id="validdeletevisiblepoint"><i class="fa fa-trash" aria-hidden="true" style="color:red;"></i> <?php p($l->t('Delete only visible points')); ?></button>
</div>

</div>
<div class="sidebar-pane" id="phonetrackfilters">

<div id="filterDiv">
<h1 class="sectiontitle"><?php p($l->t('Filter points')); ?></h1>
<hr/>
<br/>
<table id="filterPointsTable">
<tr>
    <td><label for="applyfilters"><?php p($l->t('Apply filters')); ?></label></td>
    <td><input type="checkbox" id="applyfilters"/></td>
    <td></td>
</tr><tr class="filterDelimiterLine">
    <td><?php p($l->t('Begin date')); ?></td>
    <td><input id="datemin" type="date" value=""/></td>
    <td><button class="resetFilterButton"><i class="fa fa-undo" aria-hidden="true"></i></button></td>
</tr><tr>
    <td></td>
    <td>
        <button id="dateminminus"><i class="fa fa-calendar-minus" aria-hidden="true"></i></button>
        <button id="datemintoday"><i class="fa fa-calendar-alt" aria-hidden="true"></i> <?php p($l->t('today')); ?></button>
        <button id="dateminplus"><i class="fa fa-calendar-plus" aria-hidden="true"></i></button>
    </td>
    <td></td>
</tr><tr>
    <td><?php p($l->t('Begin time')); ?></td>
    <td><input class="time" id="hourmin" type="number" value="" min="0" max="23"/>:
        <input class="time" id="minutemin" type="number" value="" min="0" max="59"/>:
        <input class="time" id="secondmin" type="number" value="" min="0" max="59"/></td>
    <td><button class="resetFilterButton"><i class="fa fa-undo" aria-hidden="true"></i></button></td>
</tr><tr class="filterDelimiterLine">
    <td><?php p($l->t('End date')); ?></td>
    <td><input id="datemax" type="date" value=""/></td>
    <td><button class="resetFilterButton"><i class="fa fa-undo" aria-hidden="true"></i></button></td>
</tr><tr>
    <td></td>
    <td>
        <button id="datemaxminus"><i class="fa fa-calendar-minus" aria-hidden="true"></i></button>        
        <button id="datemaxtoday"><i class="fa fa-calendar-alt" aria-hidden="true"></i> <?php p($l->t('today')); ?></button>
        <button id="datemaxplus"><i class="fa fa-calendar-plus" aria-hidden="true"></i></button>
    </td>
    <td></td>
</tr><tr>
    <td><?php p($l->t('End time')); ?></td>
    <td><input class="time" id="hourmax" type="number" value="" min="0" max="23"/>:
        <input class="time" id="minutemax" type="number" value="" min="0" max="59"/>:
        <input class="time" id="secondmax" type="number" value="" min="0" max="59"/></td>
    <td><button class="resetFilterButton"><i class="fa fa-undo" aria-hidden="true"></i></button></td>
</tr><tr class="dateminmaxcell filterDelimiterLine">
    <td>
        <button id="dateminmaxminus"><i class="fa fa-calendar-minus" aria-hidden="true"></i> <?php p($l->t('Min-- and Max--')); ?></button>
    </td>
    <td>
        <button id="dateminmaxplus"><i class="fa fa-calendar-plus" aria-hidden="true"></i> <?php p($l->t('Min++ and Max++')); ?></button>
    </td>
    <td></td>
</tr><tr class="filterDelimiterLine">
    <td><?php p($l->t('Last day:hour:min')); ?></td>
    <td><input class="time" id="lastdays" type="number" value="" min="0" max="100000"/>:
        <input class="time" id="lasthours" type="number" value="" min="0" max="23"/>:
        <input class="time" id="lastmins" type="number" value="" min="0" max="59"/></td>
    <td><button class="resetFilterButton"><i class="fa fa-undo" aria-hidden="true"></i></button></td>
</tr><tr class="filterDelimiterLine">
    <td>
        <label for="accuracymin"><?php p($l->t('Minimum accuracy')); ?></label>
    </td>
    <td>
        <input id="accuracymin" type="number" value="" min="-1" max="10000"/>
    </td>
    <td><button class="resetFilterButton"><i class="fa fa-undo" aria-hidden="true"></i></button></td>
</tr><tr>
    <td>
        <label for="accuracymax"><?php p($l->t('Maximum accuracy')); ?></label>
    </td>
    <td>
        <input id="accuracymax" type="number" value="" min="-1" max="10000"/>
    </td>
    <td><button class="resetFilterButton"><i class="fa fa-undo" aria-hidden="true"></i></button></td>
</tr><tr class="filterDelimiterLine">
    <td>
        <label for="elevationmin"><?php p($l->t('Minimum elevation')); ?></label>
    </td>
    <td>
        <input id="elevationmin" type="number" value="" min="-1" max="10000"/>
    </td>
    <td><button class="resetFilterButton"><i class="fa fa-undo" aria-hidden="true"></i></button></td>
</tr><tr>
    <td>
        <label for="elevationmax"><?php p($l->t('Maximum elevation')); ?></label>
    </td>
    <td>
        <input id="elevationmax" type="number" value="" min="-1" max="10000"/>
    </td>
    <td><button class="resetFilterButton"><i class="fa fa-undo" aria-hidden="true"></i></button></td>
</tr><tr class="filterDelimiterLine">
    <td>
        <label for="batterymin"><?php p($l->t('Minimum battery level')); ?></label>
    </td>
    <td>
        <input id="batterymin" type="number" value="" min="-1" max="100"/>
    </td>
    <td><button class="resetFilterButton"><i class="fa fa-undo" aria-hidden="true"></i></button></td>
</tr><tr>
    <td>
        <label for="batterymax"><?php p($l->t('Maximum battery level')); ?></label>
    </td>
    <td>
        <input id="batterymax" type="number" value="" min="-1" max="100"/>
    </td>
    <td><button class="resetFilterButton"><i class="fa fa-undo" aria-hidden="true"></i></button></td>
</tr><tr class="filterDelimiterLine">
    <td>
        <label for="speedmin"><?php p($l->t('Minimum speed')); ?></label>
    </td>
    <td>
        <input id="speedmin" type="number" value="" min="-1" max="10000"/>
    </td>
    <td><button class="resetFilterButton"><i class="fa fa-undo" aria-hidden="true"></i></button></td>
</tr><tr>
    <td>
        <label for="speedmax"><?php p($l->t('Maximum speed')); ?></label>
    </td>
    <td>
        <input id="speedmax" type="number" value="" min="-1" max="100"/>
    </td>
    <td><button class="resetFilterButton"><i class="fa fa-undo" aria-hidden="true"></i></button></td>
</tr><tr class="filterDelimiterLine">
    <td>
        <label for="bearingmin"><?php p($l->t('Minimum bearing')); ?></label>
    </td>
    <td>
        <input id="bearingmin" type="number" value="" min="-1" max="360"/>
    </td>
    <td><button class="resetFilterButton"><i class="fa fa-undo" aria-hidden="true"></i></button></td>
</tr><tr>
    <td>
        <label for="bearingmax"><?php p($l->t('Maximum bearing')); ?></label>
    </td>
    <td>
        <input id="bearingmax" type="number" value="" min="-1" max="360"/>
    </td>
    <td><button class="resetFilterButton"><i class="fa fa-undo" aria-hidden="true"></i></button></td>
</tr><tr class="filterDelimiterLine">
    <td>
        <label for="satellitesmin"><?php p($l->t('Minimum satellites')); ?></label>
    </td>
    <td>
        <input id="satellitesmin" type="number" value="" min="-1" max="10000"/>
    </td>
    <td><button class="resetFilterButton"><i class="fa fa-undo" aria-hidden="true"></i></button></td>
</tr><tr>
    <td>
        <label for="satellitesmax"><?php p($l->t('Maximum satellites')); ?></label>
    </td>
    <td>
        <input id="satellitesmax" type="number" value="" min="-1" max="10000"/>
    </td>
    <td><button class="resetFilterButton"><i class="fa fa-undo" aria-hidden="true"></i></button></td>
</tr>
</table>

<hr/>
<input id="filtername" type="text" maxlength="100" value="<?php p($l->t('bookmark name')); ?>"/>
<button id="savefilters"><i class="far fa-save" aria-hidden="true"></i>
<?php p($l->t('Save filter bookmark')); ?>
</button>
<ul id="filterbookmarks">
<?php
foreach ($_['filtersBookmarks'] as $bookid => $e) {
    $name = $e[0];
    $filters = json_decode($e[1], True);
    echo '<li bookid="';
    p($bookid);
    echo '" name="';
    p($name);
    echo '" title="';
    foreach ($filters as $fs => $fv) {
        p($fs);
        echo ' : ';
        p($fv);
        echo "\n";
    }
    echo '"><label class="booklabel">';
    p($name);
    echo '</label>
          <button class="applybookbutton"><i class="fa fa-filter"></i></button>
          <button class="deletebookbutton"><i class="fa fa-trash"></i></button>
          <p class="filterstxt" style="display:none;">';
    p($e[1]);
    echo '</p></li>';
}
?>
</ul>
</div>

</div>
<div class="sidebar-pane" id="phonetrackstats">
<h1 class="sectiontitle"><?php p($l->t('Statistics')); ?></h1>
<hr/>
<br/>
    <label for="togglestats"><i class="fa fa-table" aria-hidden="true"></i>
        <?php p($l->t('Show stats')); ?>
    </label>&nbsp<input id="togglestats" type="checkbox"/>
    <br/>
    <br/>
    <h2 id="statlabel"></h2>
    <div id="statdiv"></div>
</div>
<div class="sidebar-pane" id="help">
    <h1 class="sectiontitle"><?php p($l->t('About PhoneTrack')); ?></h1>
    <hr/><br/>
    <h3 class="sectiontitle"><?php p($l->t('Shortcuts')); ?> :</h3>
    <ul class="disclist">
        <li><b>&lt;</b> : <?php p($l->t('Toggle sidebar')); ?></li>
    </ul>

    <br/><hr/><br/>
    <h3 class="sectiontitle"><?php p($l->t('Documentation')); ?></h3>
    <a class="toplink" target="_blank" href="https://gitlab.com/eneiluj/phonetrack-oc/wikis/home">
    <i class="fab fa-gitlab" aria-hidden="true"></i>
    Project wiki
    </a>
    <br/>

    <br/><hr/><br/>
    <h3 class="sectiontitle"><?php p($l->t('Source management')); ?></h3>
    <ul class="disclist">
        <li><a class="toplink" target="_blank" href="https://gitlab.com/eneiluj/phonetrack-oc">
        <i class="fab fa-gitlab" aria-hidden="true"></i>
        Gitlab project main page</a></li>
        <li><a class="toplink" target="_blank" href="https://gitlab.com/eneiluj/phonetrack-oc/issues">
        <i class="fab fa-gitlab" aria-hidden="true"></i>
        Gitlab project issue tracker</a></li>
        <li><a class="toplink" target="_blank" href="https://crowdin.com/project/phonetrack">
        <i class="fa fa-globe-africa" aria-hidden="true"></i>
        Help us to translate this app on Crowdin !</a></li>
    </ul>

    <br/><hr/><br/>
    <h3 class="sectiontitle"><?php p($l->t('Authors')); ?> :</h3>
    <ul class="disclist">
        <li>Julien Veyssier (developer)</li>
        <li>@mjanssens (Dutch translations)</li>
        <li>@AndyKl (German translations)</li>
        <li>@oswolf (German translations)</li>
        <li>See <a class="toplink" href="https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md"
        target="_blank">AUTHORS file</a></li>
    </ul>

</div>
</div>
</div>
<!-- ============= MAP DIV =============== -->
<div id="map" class="sidebar-map"></div>

