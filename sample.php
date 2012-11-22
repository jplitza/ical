<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="content-type" content="text/html; charset=utf8">
  <title>Calendar test</title>
  <style type="text/css">
    * {
      font-size: 10pt;
    }
    .calendar {
      list-style-type: none;
      padding-left: 0;
      display: table;
      width: 450px;
      border-top: 1px solid #aaa;
    }
    .calendar .day {
      /*font-weight: bold;
      font-style: italic;*/
      margin-top: 2px;
      display: table-row;
      text-align: right;
    }
    .calendar .day .date {
      border-bottom: 1px solid #aaa;
      display: table-cell;
      padding-right: 0.5ex;
      white-space: nowrap;
    }
    .calendar .day ul {
      /*border-top: 1px solid black;*/
      list-style-type: none;
      padding-left: 0;
      display: table-cell;
      text-align: left;
      font-style: normal;
    }
    .calendar .day .event {
      font-weight: normal;
      border-bottom: 1px solid #aaa;
    }
    .calendar .day .event .overview {
      display: table;
    }
    .calendar .day .time,
    .calendar .day .starttime,
    .calendar .day .endtime {
      font-style: italic;
      display: table-cell;
      padding-right: 0.5ex;
    }
    .calendar .day .summary {
      display: table-cell;
      cursor: pointer;
      font-weight: bold;
    }
    .calendar .day .details {
      margin-left: 2ex;
      display: none;
      /*position: absolute;
      max-width: 300px;
      background-color: white;
      border: 1px solid black;
      border-radius: 5px;
      padding: 5px;*/
    }
  </style>
  <script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js" type="text/javascript"></script>
</head>
<body>
<ul class="calendar">
<?php
require_once 'iCalcreator.class.php';
date_default_timezone_set("Europe/Berlin");
if(!setlocale(LC_ALL, 'de_DE.UTF-8'))
  setlocale(LC_ALL, 'de_DE');

$filename = 'calendar.ics';
$timeformat = '%H:%M';
$dateformat = '%a, %d. %b %Y';
$shortdateformat = '%d.%m.';

function arrayToTimestamp($t) {
  return mktime($t["hour"], $t["min"], $t["sec"], $t["month"], $t["day"], $t["year"]);
}

function filterEvent($event, $day) {
  $class = $event->getProperty("class");
  if($class == "PRIVATE" || $class == "CONFIDENTIAL")
    return false;
  if(!$event->getProperty("rrule")) {
    list($startDate,) = getEventDates($event);
    # check if the start date is at a previous day
    if(date('Ymd', $startDate) < $day)
      return false;
  }
  return true;
}

function getEventDates($event) {
  if($event->getProperty('rrule', 1)) {
    $currddate = $event->getProperty("x-current-dtstart", 1, false);
    $startDate = strtotime($currddate[1]);
    $currddate = $event->getProperty("x-current-dtend");
    $endDate = strtotime($currddate[1]);
  }
  else {
    $t = $event->getProperty("dtstart");
    $startDate = arrayToTimestamp($t);
    $t = $event->getProperty("dtend");
    $endDate = arrayToTimestamp($t);
  }
  return array($startDate, $endDate);
}

$startdate = time();
$enddate = $startdate + 60*60*24*21;

$v = new vcalendar(array("filename" => $filename, "unique_id" => "stuga")); // initiate new CALENDAR
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

$i = 0;
foreach($eventArray as $year => $yearArray) {
  foreach($yearArray as $month => $monthArray) {
    foreach($monthArray as $day => $dayArray) {
      $dayArray = array_filter($dayArray, create_function('$event', 'return filterEvent($event, "' . sprintf("%04d%02d%02d", $year, $month, $day) . '");'));
      if(!empty($dayArray)) {
        $curDay = mktime(0,0,0,$month,$day,$year);
        echo '  <li class="day"><div class="date">' . strftime($dateformat, $curDay) . "</div>\n    <ul>\n";
        usort($dayArray, create_function('$a,$b', 'list($at,) = getEventDates($a); list($bt,) = getEventDates($b); return $at - $bt;'));
        foreach($dayArray as $event) {
          $summary = $event->getProperty("summary", 1, false);

          list($startDate, $endDate) = getEventDates($event);
          $description = $event->getProperty("description");
          $description = htmlspecialchars($description);
          $description = str_replace('\n', "<br>\n", $description);
          $description = str_replace(array('\\\\', '\r', '\t', '\v', '\e', '\f'), array('\\'), $description);

          $location = $event->getProperty("location");
          $location = htmlspecialchars($location);

          echo '       <li class="event"><div class="overview" onclick="$(\'#cal-details-' . $i . '\').slideToggle();"><span class="time">';
          if(date('His', $startDate) == '000000'
              && date('His', $endDate) == '000000'
              && date('Ymd', $startDate) + 1 == date('Ymd', $endDate))
            echo 'ganztägig';
          else
            echo strftime($timeformat, $startDate)
              . '–'
              . ((date('dmY', $startDate) != date('dmY', $endDate))? '<br>' . strftime($shortdateformat, $endDate) . ' ' : '')
              . strftime($timeformat, $endDate);
          echo '<span style="display: none;">:</span></span> <span class="summary">'
            . $summary
            . "</span></div>\n"
            . '        <div class="details" id="cal-details-' . ($i++) . "\">\n"
            . (!empty($location)? '<div class="location">Ort: ' . $location . "</div>\n" : '')
            . '          <div class="description">' . $description . "</div>\n"
            . "        </div>\n"
            . "      </li>\n";
        }
        echo "    </ul>\n  </li>\n";
      }
    }
  }
}
?>
</ul>
</body>
</html>
