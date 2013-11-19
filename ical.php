<?php
$wgIcalTimeFormat = '%H:%M';
$wgIcalDateFormat = '%a, %d. %b %Y';
$wgIcalShortDateFormat = '%d.%m.';
$wgIcalDaysToShow = 21;
$wgIcalIcsLink = true;
$wgIcalRefreshLink = false;
$wgIcalCaption = false;

$wgAutoloadClasses['vcalendar'] = dirname(__FILE__) . '/iCalcreator.class.php';
$wgHooks['ParserFirstCallInit'][] = 'wfIcalParserInit';
$wgHooks['BeforePageDisplay'][] = 'wfIcalBeforePageDisplay';

// Hook our callback function into the parser
function wfIcalParserInit( Parser $parser ) {
  // When the parser sees the <sample> tag, it executes 
  // the wfSampleRender function (see below)
  $parser->setHook( 'ical', 'wfIcalRender' );
  // Always return true from this function. The return value does not denote
  // success or otherwise have meaning - it just must always be true.
  return true;
}

function wfIcalBeforePageDisplay($out, $skin) {
  $out->addModules('ext.ical');
  return true;
}

$wgResourceModules['ext.ical'] = array(
  'styles' => 'ical.css',
  'localBasePath' => dirname(__FILE__),
  'remoteExtPath' => 'ical',
  'position' => 'top',
);

function wfIcalArrayToTimestamp($t) {
  return mktime($t["hour"], $t["min"], $t["sec"], $t["month"], $t["day"], $t["year"]);
}

function wfIcalFilterEvent($event, $day) {
  $class = $event->getProperty("class");
  if($class == "PRIVATE" || $class == "CONFIDENTIAL")
    return false;
  if(!$event->getProperty("rrule")) {
    list($startDate,) = wfIcalGetEventDates($event);
    # check if the start date is at a previous day
    if(date('Ymd', $startDate) < $day)
      return false;
  }
  return true;
}

function wfIcalGetEventDates($event) {
  if($event->getProperty('rrule', 1)) {
    $currddate = $event->getProperty("x-current-dtstart", 1, false);
    $startDate = strtotime($currddate[1]);
    $currddate = $event->getProperty("x-current-dtend");
    $endDate = strtotime($currddate[1]);
  }
  else {
    $t = $event->getProperty("dtstart");
    $startDate = wfIcalArrayToTimestamp($t);
    $t = $event->getProperty("dtend");
    $endDate = wfIcalArrayToTimestamp($t);
  }
  return array($startDate, $endDate);
}

// Execute 
function wfIcalRender( $input, array $args, Parser $parser, PPFrame $frame ) {
  global $wgIcalTimeFormat, $wgIcalDateFormat, $wgIcalShortDateFormat,
    $wgIcalDaysToShow, $wgOut, $wgIcalRefreshLink, $wgIcalIcsLink;

  if(!empty($args["url"]))
    $config = array("url" => $args["url"]);
  elseif(!empty($args["file"]))
    $config = array("filename" => basename($args["file"]), "directory" => dirname($args["file"]));
  else
    return false;

  if(!empty($args['timeformat']))
    $timeformat = htmlspecialchars($args['timeformat']);
  else
    $timeformat = $wgIcalTimeFormat;
  if(!empty($args['dateformat']))
    $dateformat = htmlspecialchars($args['dateformat']);
  else
    $dateformat = $wgIcalDateFormat;
  if(!empty($args['shortdateformat']))
    $shortdateformat = htmlspecialchars($args['shortdateformat']);
  else
    $shortdateformat = $wgIcalShortDateFormat;
  if(!empty($args['days']) && intval($args['days']))
    $daystoshow = intval($args['days']);
  else
    $daystoshow = $wgIcalDaysToShow;
  if(isset($args['refreshlink']))
    $refreshlink = (bool) $args['refreshlink'];
  else
    $refreshlink = $wgIcalRefreshLink;
  if(isset($args['icslink']))
    $icslink = (bool) $args['icslink'];
  else
    $icslink = $wgIcalIcsLink;
  if(isset($args['caption']))
    $caption = (bool) $args['caption'];
  else
    $caption = $wgIcalCaption;

  if(!setlocale(LC_TIME, 'de_DE.UTF-8'))
    setlocale(LC_TIME, 'de_DE');

  if(!$refreshlink)
    $parser->disableCache();

  $startdate = time();
  $enddate = $startdate + 60*60*24*$daystoshow;

  $v = new vcalendar($config); // initiate new CALENDAR
  $v->parse();
  $v->sort();

  $eventArray = $v->selectComponents(
    date('Y', $startdate),
    date('m', $startdate),
    date('d', $startdate),
    date('Y', $enddate),
    date('m', $enddate),
    date('d', $enddate),
    "vevent");

  $ret = '';
  if($refreshlink) {
    $ret .= '<a href="?action=purge" style="float: left; font-size: 80%;">Aktualisieren</a>';
  }
  if($icslink) {
    $link = '';
    if(!empty($config["directory"]))
      $link = str_replace($_SERVER['DOCUMENT_ROOT'], "/", realpath(getcwd() . '/' . $config["directory"])) . '/';
    if(!empty($config["filename"]))
      $link .= $config["filename"];
    elseif(!empty($config["url"]))
      $link = $config["url"];
    $ret .= '<a href="' . htmlspecialchars($link) . '" style="float: right; font-size: 80%;">ICS-Datei</a>';
  }
  if($caption) {
    $ret .= '<h3 style="text-align: center; margin:0; padding: 0;">Termine der nächsten ' . $daystoshow . ' Tage</h3>' . "\n";
  }
  $ret .= '<ul class="calendar">' . "\n";

  $i = 0;
  foreach($eventArray as $year => $yearArray) {
    foreach($yearArray as $month => $monthArray) {
      foreach($monthArray as $day => $dayArray) {
        $dayArray = array_filter($dayArray, create_function('$event', 'return wfIcalFilterEvent($event, "' . sprintf("%04d%02d%02d", $year, $month, $day) . '");'));
        if(!empty($dayArray)) {
          $curDay = mktime(0,0,0,$month,$day,$year);
          $ret .= '  <li class="day"><div class="date">' . strftime($dateformat, $curDay) . "</div>\n    <ul>\n";
          usort($dayArray, create_function('$a,$b', 'list($at,) = wfIcalGetEventDates($a); list($bt,) = wfIcalGetEventDates($b); return $at - $bt;'));
          foreach($dayArray as $event) {
            $summary = $event->getProperty("summary", 1, false);

            list($startDate, $endDate) = wfIcalGetEventDates($event);
            $description = $event->getProperty("description");
            $description = htmlspecialchars($description);
            $description = str_replace('\n', "\n", $description);
            $description = str_replace(array('\\\\', '\r', '\t', '\v', '\e', '\f'), array('\\'), $description);
            $description .= "\n";
            $description = $parser->recursiveTagParse($description, $frame);

            $location = $event->getProperty("location");
            $location = htmlspecialchars($location);
            $location = preg_replace('#(?<=^|\W)([A-Z][A-Za-z0-9]{1,3}) ((?:[A-Z]?\s?\d+[-\s\.]*)+\d{2})(?=\W|$)#', '<a href="http://oracle-web.zfn.uni-bremen.de/lageplan/lageplan?Haus=$1&Raum=$2">$1 $2</a>', $location);
            $location = str_replace('StugA-Raum', '<a href="http://oracle-web.zfn.uni-bremen.de/lageplan/lageplan?Haus=MZH&Raum=6450">StugA-Raum</a>', $location);

            $ret .= '       <li class="event"><div class="overview" onclick="$(\'#cal-details-' . $i . '\').slideToggle();"><span class="time">';
            if(date('His', $startDate) == '000000'
                && date('His', $endDate) == '000000'
                && date('Ymd', $startDate) + 1 == date('Ymd', $endDate))
              $ret .= 'ganztägig';
            else
              $ret .= strftime($timeformat, $startDate)
                . '–'
                . ((date('dmY', $startDate) != date('dmY', $endDate))? '<br>' . strftime($shortdateformat, $endDate) . ' ' : '')
                . strftime($timeformat, $endDate);
            $ret .= '<span style="display: none;">:</span></span> <span class="summary">'
              . $summary
              . "</span></div>\n"
              . '        <div class="details" id="cal-details-' . ($i++) . "\">\n"
              . (!empty($location)? '<div class="location">Ort: ' . $location . "</div>\n" : '')
              . '          <div class="description">' . $description . "</div>\n"
              . "        </div>\n"
              . "      </li>\n";
          }
          $ret .= "    </ul>\n  </li>\n";
        }
      }
    }
  }
  if($i == 0) {
    $ret .= '        <li class="day" style="text-align: center;">Keine Termine.</li>';
  }
  $ret .= "</ul>\n";
  return $ret;
}
?>
