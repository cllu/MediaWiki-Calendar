<?php
/* Calendar.php
 *
 * - Eric Fortin < kenyu73@gmail.com >
 *
 * - Original author(s):
 *   	Simson L. Garfinkel < simsong@acm.org >
 *   	Michael Walters < mcw6@aol.com > 
 * See Readme file for full details
 */

// this is the "refresh" code that allows the calendar to switch time periods
if (isset($_POST["calendar_info"]) ){
	
	$today = getdate();    	// today
	$temp = split("`", $_POST["calendar_info"]); // calling calendar info (name,title, etc..)

	// set the initial values
	$month = $temp[0];
	$day = $temp[1];
	$year = $temp[2];	
	$title =  $temp[3];
	$name =  $temp[4];
	
	// the yearSelect and monthSelect must be on top... the onChange triggers  
	// whenever the other buttons are clicked
	if(isset($_POST["yearSelect"])) $year = $_POST["yearSelect"];	
	if(isset($_POST["monthSelect"])) $month = $_POST["monthSelect"];

	if(isset($_POST["yearBack"])) --$year;
	if(isset($_POST["yearForward"])) ++$year;	

	if(isset($_POST["today"])){
		$day = $today['mday'];
		$month = $today['mon'];
		$year = $today['year'];
	}	

	if(isset($_POST["monthBack"])){
		$year = ($month == 1 ? --$year : $year);	
		$month = ($month == 1 ? 12 : --$month);
	}

	if(isset($_POST["monthForward"])){
		$year = ($month == 12 ? ++$year : $year);		
		$month = ($month == 12 ? 1 : ++$month);
	}
	
	if(isset($_POST["weekBack"])){
		$arr = getdate( mktime(12, 0, 0,$month, $day-7, $year) );
		$month = $arr['mon'];
		$day = $arr['mday'];
		$year = $arr['year'];
	}

	if(isset($_POST["weekForward"])){
		$arr = getdate( mktime(12, 0, 0,$month, $day+7, $year) );
		$month = $arr['mon'];
		$day = $arr['mday'];
		$year = $arr['year'];
	}

	if(isset($_POST["viewSelect"])){
		$mode = $_POST["viewSelect"];
	}
	
	$cookie_name = preg_replace('/(\.|\s)/',  '_', ($title . " " . $name)); //replace periods and spaces
	$cookie_value = $month . "`" . $day . "`" . $year . "`" . $title . "`" . $name . "`" . $mode . "`";
	setcookie($cookie_name, $cookie_value);
	
	// reload the page..clear any purge commands that may be in it from an ical load...
	$url = str_replace("&action=purge", "", $_SERVER['REQUEST_URI']);
	header("Location: " . $url);
}

# Confirm MW environment
if (!defined('MEDIAWIKI')) {
	die( 'This file is a MediaWiki extension, it is not a valid entry point' );
}

$gCalendarVersion = "v3.8.4 (9/15/2009)";

# Credits	
$wgExtensionCredits['parserhook'][] = array(
    'name'=>'Calendar',
    'author'=>'Eric Fortin',
    'url'=>'http://www.mediawiki.org/wiki/Extension:Calendar_(Kenyu73)',
    'description'=>'MediaWiki Calendar',
    'version'=>$gCalendarVersion
);

$path = dirname( __FILE__ );

$wgExtensionFunctions[] = "wfCalendarExtension";
$wgExtensionMessagesFiles['wfCalendarExtension'] = "$path/calendar.i18n.php";


 // function adds the wiki extension
function wfCalendarExtension() {
	global $wgParser;
	global $wgParser, $wgHooks;
	global $wgCalendarSidebarRef;
	$wgParser->setHook( "calendar", "displayCalendar" );
	wfLoadExtensionMessages( 'wfCalendarExtension' ); 
}

// Hook to inject Calendar into sidebar
function wfCalendarSkinTemplateOutputPageBeforeExec( &$skin, &$tpl ) {
    global $wgCalendarSidebarRef;
    $html = displayCalendar('', array('simplemonth' => true));
    $html .= displayCalendar('', array('date' => 'today'));
    $html .= displayCalendar('', array('date' => 'tomorrow'));
    if ( $html ) $tpl->data['sidebar']['calendar'] = $html;
    return true;
}

/*
// calendar conditionals, used to remove the commands from the page view
function calendar(){return "";}
*/

require_once ("$path/common.php");
require_once ("$path/CalendarArticles.php");
require_once ("$path/debug.class.php");

class Calendar extends CalendarArticles
{  
	var $debug; //debugger class
	
	var $arrSettings = array();
	
    // [begin] set calendar parameter defaults
	var $calendarMode = "normal";
	var $title = ""; 
	
	var $disableConfigLink = true;

	var $arrAlerts = array();
	
	var $tag_views = "";
	
	var $invalidateCache = false;
										
    function Calendar($wikiRoot, $debug) {
		$this->wikiRoot = $wikiRoot;

		$this->debug = new debugger('html');
		$this->debug->enabled($debug);

		// set the calendar's initial date
		$now = getdate();
		
		$this->month = $this->actualMonth = $now['mon'];
		$this->year = $this->actualYear = $now['year'];
		$this->day = $this->actualDay = $now['mday'];
		
		$this->daysInMonth = 
		
		$this->debug->set("Calendar Constructor Ended.");
    }
	
	 // render the calendar
	 function renderCalendar($userMode){

		$ret = "";
		$this->mode = $userMode;
		
		// need to change the running calendar date 
		// or the other functions wont set correctly
		if($userMode == 'day')
			$this->updateDate();

		$this->initalizeHTML();		
		
		if($this->paramstring != '')
			$this->buildTagEvents($this->paramstring);

		//grab last months events for overlapped repeating events
		if($this->setting('enablerepeatevents')){
			$daysBack = $this->setting('enablerepeatevents',false);
			if($daysBack == '') $daysBack = 15; //default - this checks 1/2 way into the previous month
			$this->debug->set($daysBack );
			$this->initalizeMonth( ($this->day + $daysBack), 0); 
		}
		else
			$this->initalizeMonth($this->day, 0); // just go back to the 1st of the current month
		
		// what mode we going into
		if($userMode == 'year')
			$ret = $this->renderYear();		
			
		if($userMode == 'month')
			$ret = $this->renderMonth();
			
		if($userMode == 'simplemonth')
			$ret = $this->renderSimpleMonth();

		if($userMode == 'week')
			$ret = $this->renderWeek();		
			
		if($userMode == 'day')
			$ret = $this->renderDate();
	
		if($userMode == 'events')
			$ret = $this->renderEventList();
			
		//tag on extra info at the end of whatever is displayed
		$ret .= $this->buildTrackTimeSummary();
		$ret .= $this->debug->get();
	
		return $ret;
	 }
	
	// build the months articles into memory
	// $back: days back from ($this->day)
	// $forward: days ahead from ($this->day)
	function initalizeMonth($back, $forward){
		$this->debug->set('initalizeMonth called');
		
		// just make sure we have a solid negitive here
		$back = -(abs($back));
		
		$cnt = abs($back) + $forward;
		
		$arr_start = Common::datemath($back, $this->month, $this->day, $this->year);
		
		$month = $arr_start['mon'];
		$day = $arr_start['mday'];
		$year = $arr_start['year'];
		
		
	    for ($i = 1; $i <= $cnt; $i++) {
			$this->buildArticlesForDay($month, $day, $year);
			Common::getNextValidDate($month, $day, $year);
		}	
	}
	
	function initalizeHTML(){
	
		$this->debug->set("initalizeHTML() called");
		global $wgOut,$wgScriptPath, $wgVersion;

		$cssURL = $this->getURLRelativePath() . "/templates/";
		
		// set paths			
		$extensionPath = $this->setting('path');
		$extensionPath = str_replace("\\", "/", $extensionPath);
		
		$css = $this->setting('css');
		
		$css_data = file_get_contents($extensionPath . "/templates/$css");	//ugly method	
		$this->html_template = file_get_contents($extensionPath . "/templates/calendar_template.html");
	
		//add css; this is set as 'default.css' or an override
		$wgOut->addStyle($cssURL . $css); //clean method
		
	
		$this->templateHTML['normal'] = $this->searchHTML($this->html_template,"<!-- Day Start -->", "<!-- Day End -->");
		$this->templateHTML['missing'] = $this->searchHTML($this->html_template,"<!-- Missing Start -->", "<!-- Missing End -->");
		
		$year = Common::translate('year');
		$month = Common::translate('month');
		$week = Common::translate('week');

		if(!$this->setting('disablemodes')){
			$selected = "selected='true'";
			$this->tag_views = "<select name='viewSelect' method='post' onChange='javascript:this.form.submit()'>";
			
			($this->mode == 'year') ?
				$this->tag_views .= "<option class='lst' value='year' $selected>$year</option>" :
				$this->tag_views .= "<option class='lst' value='year'>$year</option>";

			($this->mode == 'month') ?
				$this->tag_views .= "<option class='lst' value='month' $selected>$month</option>" :
				$this->tag_views .= "<option class='lst' value='month'>$month</option>";
			($this->mode == 'week') ?
				$this->tag_views .= "<option class='lst' value='week' $selected>$week</option>" :
				$this->tag_views .= "<option class='lst' value='week'>$week</option>";
	
			$this->tag_views .= "</select>&nbsp;&nbsp;";	
		}
					
		// build the hidden calendar date info (used to offset the calendar via sessions)
		$this->tag_HiddenData = "<input class='btn' type='hidden' name='calendar_info' value='"
			. $this->month . "`"
			. $this->day . "`"
			. $this->year . "`"
			. $this->title . "`"
			. $this->name . "`"
			. "'>";			

		$this->tag_heading = 
			'<td class="calendarHeading">[[day1]]</td>
			<td class="calendarHeading">[[day2]]</td>
			<td class="calendarHeading">[[day3]]</td>
			<td class="calendarHeading">[[day4]]</td>
			<td class="calendarHeading">[[day5]]</td>
			<td class="calendarHeading">[[day6]]</td>
			<td class="calendarHeading">[[day7]]</td>';
	}
	
    // Generate the HTML for a given month
    // $day may be out of range; if so, give blank HTML
    function getHTMLForDay($month, $day, $year, $dateFormat='default', $mode='month'){
		$tag_eventList = $tag_dayCustom = "";
		$tag_dayweekyear = "";
		
		// return an empty plain table cell (includes style class )
		if ($day <= 0) {
			return $this->templateHTML['missing'];
		}
		
		// template table cell	
		$tempString = $this->templateHTML['normal'];

		$thedate = getdate(mktime(12, 0, 0, $month, $day, $year));
		$wday  = $thedate['wday'];
		$weekday = Common::translate($wday+1, 'weekday');

		$display_day = $day;
		if($dateFormat == 'long'){
			$display_day = $weekday . ", " . Common::translate($month, 'month_short') . " $day";
		}
		
		if($dateFormat == 'none')
			$display_day = "";

		if ( ($thedate['mon'] == $this->actualMonth) && ($thedate['year'] == $this->actualYear) && ($thedate['mday'] == $this->actualDay) ) {
			$tag_wday = "calendarToday";
		}
		elseif($wday==0 || $wday ==6){ 
			$tag_wday = "calendarWeekend";
		}
		else {
			$tag_wday = "calendarWeekday";
		}
	
		//$tag_addEvent = $this->buildAddEventLink($month, $day, $year);
        $tag_addEvent = "<a>Add</a>";

		$tag_mode = 'monthMode';
		if($mode == 'events'){
			$tag_mode = 'eventsMode';
			$tag_dayCustom = "eventsDay";
		}
		if($mode=='week'){
			$tag_mode = 'weekMode';
		}
		if($mode == 'day'){
			$tag_mode = 'dayMode';
			$tag_dayCustom = "singleDay";
		}
		
		//build formatted event list
		$tag_eventList = $this->getArticleLinks($month, $day, $year, true);
		
		// no events, then return nothing!
		if((strlen($tag_eventList) == 0) && ($mode == 'events')) return "";
		
		$tag_alerts = $this->buildAlertLink($day, $month);
		
		if($this->setting('dayofyear')){
			$tag_dayweekyear = $this->getDayOfTheYear($month,$day,$year);
		}		
		if($this->setting('weekofyear')){
			$tag_dayweekyear .= $this->getWeekOfTheYear($month,$day,$year);
		}
	
		//kludge... for some reason, the "\n" is removed in full calendar mode
		if($mode == "monthMode")
			$tag_eventList = str_replace("\n", " ", $tag_eventList); 
		
		$tempString = str_replace("[[Day]]", $display_day, $tempString);
		$tempString = str_replace("[[AddEvent]]", $tag_addEvent, $tempString);
		$tempString = str_replace("[[EventList]]", "<ul>" . $tag_eventList . "</ul>", $tempString);
		$tempString = str_replace("[[Alert]]", $tag_alerts, $tempString);
		$tempString = str_replace("[[DayWeekYear]]", $tag_dayweekyear, $tempString);
		$tempString = str_replace("[[mode]]", $tag_mode, $tempString);
		$tempString = str_replace("[[wday]]", $tag_wday, $tempString);
		$tempString = str_replace("[[dayCustom]]", $tag_dayCustom, $tempString);
		
		return $tempString;
    }

	function buildAlertLink($day, $month){
		$ret = "";
	
		$alerts = $this->arrAlerts;
		$alertList = "";
		for ($i=0; $i < count($alerts); $i++){
			$alert = split("-", $alerts[$i]);
			if(($alert[0] == $day) && ($alert[1] == $month))
				$alertList .= $alert[2];
		}
		
		if (strlen($alertList) > 0)
			$ret = "<a style='color:red' href=\"javascript:alert('" .$alertList . "')\"><i>alert!</i></a>";

		return $ret;
	}

	function getWeekOfTheYear($month, $day, $year, $noHTML=false){
		
		$timestamp = mktime(12,0,0,$month, $day, $year);
		$weekDay = date("w", $timestamp);
		$week = date("W", $timestamp);
		
		if($noHTML) return $week;
		
		$translated = Common::translate('weekyearTranslated');		

		if($this->setting('dayofyear')){
			$html = "<span title='" . $translated . "'>/$week</span>";
		}
		else{
			$html = "<span title='" . $translated . "'>$week</span>";
		}
			
		return $html;
	}
	
	function getDayOfTheYear($month, $day, $year){
		
		$timestamp = mktime(12,0,0,$month, $day, $year);
		$dayYear = (date("z", $timestamp) +1);
		
		$translated = Common::translate('dayyearTranslated');
		$html = "<span title='" . $translated . "'>$dayYear</span>";
		
		return $html;
	}
	
	function renderEventList(){
		$events = "";
		
		$setting = $this->setting('useeventlist',false);

		if($setting == "") return "";
		
		if($setting > 0){
			$this->calendarMode = "eventlist";
			$daysOut = ($setting <= 120 ? $setting : 120);

			$month = $this->month;
			$day = $this->day;
			$year = $this->year;			
			
			$this->updateSetting('charlimit',100);
			
			//build the days out....
			$this->initalizeMonth(0, $daysOut);
			
			for($i=0; $i < $daysOut; $i++){	
				$temp = $this->getHTMLForDay($month, $day, $year, 'long', 'events');
				if(strlen(trim($temp)) > 0 ){
					$events .= "<tr>" . $temp . "</tr>";
				}				
				Common::getNextValidDate($month,$day,$year);//bump the date up by 1
			}
		
			$this->debug->set("renderEventList Ended");
			
			return "<table width=100%>" . $ret . "</table>";	
		}
	}

	function buildTemplateEvents(){	

		$year = $this->year;
		$month = 1;//$this->month;
		$additionMonths = $this->month + 12;
		
		// lets just grab the next 12 months
		for($i=0; $i < $additionMonths; $i++){ // loop thru 12 months
			$this->addTemplate($month, $year, ($this->calendarPageName));		
			$year = ($month == 12 ? ++$year : $year);
			$month = ($month == 12 ? 1 : ++$month);
		}
	}
	
	
	// used for 'date' mode only...technically, this can be any date
	function updateDate(){
		
		$this->calendarMode = "date";
		
		$setting = $this->setting("date",false);
		
		if($setting == "") return "";
		
		$this->arrSettings['charlimit'] = 100;
		
		if (($setting == "today") || ($setting == "tomorrow") || ($setting == "yesterday") ){
			if ($setting == "tomorrow" ){	
				Common::getNextValidDate($this->month, $this->day, $this->year);		
			}

			if ($setting == "yesterday" ){	
				$yesterday= Common::datemath(-1, $this->month, $this->day, $this->year);
				$this->month = $yesterday['mon'];
				$this->day = $yesterday['mday'];
				$this->year = $yesterday['year'];
			}
		}
		else {
			$useDash = split("-",$setting);
			$useSlash = split("/",$setting);
			$parseDate = (count($useDash) > 1 ? $useDash : $useSlash);
			if(count($parseDate) == 3){
				$this->month = $parseDate[0];
				$this->day = $parseDate[1] + 0; // converts to integer
				$this->year = $parseDate[2] + 0;
			}
		}
		$this->debug->set("** updateDate Ended");	
	}
	
	// specific date mode
	function renderDate(){
		
		$this->initalizeMonth(0,1);
		
		$ret = $this->getHTMLForDay($this->month, $this->day, $this->year, 'long', 'day');
			
		$this->debug->set("renderDate Ended");		
		return "<table>$ret</table>";
	}

	function renderSimpleMonth(){
		 
		$ret = $this->buildSimpleCalendar($this->month, $this->year);
		 
		 return $ret;
	}
	function buildMonthSelectBox($shortMonths=false){
	
	    // build the month select box
	    $monthSelect = "<select name='monthSelect' method='post' onChange='javascript:this.form.submit()'>";
		for ($i = 1; $i <= 12; $i++) {
    		if ($i == $this->month) {
				$monthSelect .= "<option class='lst' value='" . ($i) . "' selected='true'>" . 
				Common::translate($i, 'month') . "</option>\n";
    		}
    		else {
				$monthSelect .= "<option class='lst' value='" . ($i) . "'>" . 
				Common::translate($i, 'month') . "</option>\n";
    		}
	    }
	    $monthSelect .= "</select>";
	
		return $monthSelect;
	}
	
	function buildYearSelectBox(){
    	
		$yearoffset = $this->setting('yearoffset',false);

	    // build the year select box, with +/- 5 years in relation to the currently selected year
	    $yearSelect = "<select name='yearSelect' method='post' onChange='javascript:this.form.submit()'>";
		for ($i = ($this->year - $yearoffset); $i <= ($this->year + $yearoffset); $i += 1) {
    		if ($i == $this->year) {
				$yearSelect .= "<option class='lst' value='$i' selected='true'>" . 
				$i . "</option>\n";
    		}
    		else {
				$yearSelect .= "<option class='lst' value='$i'>$i</option>\n";
    		}
	    }
	    $yearSelect .= "</select>";	
	
		return $yearSelect;
	}
	
    function renderMonth() {   
		$this->calendarMode = "normal";
       	
	    /***** Replacement tags *****/
	    $tag_monthSelect = "";         	// the month select box [[MonthSelect]] 
	    $tag_previousMonthButton = ""; 	// the previous month button [[PreviousMonthButton]]
	    $tag_nextMonthButton = "";     	// the next month button [[NextMonthButton]]
	    $tag_yearSelect = "";          	// the year select box [[YearSelect]]
	    $tag_previousYearButton = "";  	// the previous year button [[PreviousYearButton]]
	    $tag_nextYearButton = "";      	// the next year button [[NextYearButton]]
	    $tag_calendarName = "";        	// the calendar name [[CalendarName]]
	    $tag_calendarMonth = "";       	// the calendar month [[CalendarMonth]]
	    $tag_calendarYear = "";        	// the calendar year [[CalendarYear]]
	    $tag_day = "";                 	// the calendar day [[Day]]
	    $tag_addEvent = "";            	// the add event link [[AddEvent]]
	    $tag_eventList = "";           	// the event list [[EventList]]
		$tag_todayButton = "";			// today button [[TodayButton]]
		$tag_timeTrackValues = "";     	// summary of time tracked events
        
	    /***** Calendar parts (loaded from template) *****/
	    $html_header = "";             // the calendar header
	    $html_day_heading = "";        // the day heading
	    $html_week_start = "";         // the calendar week pieces
	    $html_week_end = "";
	    $html_footer = "";             // the calendar footer

	    /***** Other variables *****/

	    $ret = "";          // the string to return
		
		//build events into memory for the remainder of the month
		//the previous days have already been loaded
		$this->initalizeMonth(0, (32 - $this->day));
		
	    /***** Build the known tag elements (non-dynamic) *****/
	    // set the month's name tag
		if($this->name == 'Public')
			$tag_calendarName = Common::translate('default_title');
		else
			$tag_calendarName = $this->name;
		
	    // set the month's mont and year tags
		$tag_calendarMonth = Common::translate($this->month, 'month');
	    $tag_calendarYear = $this->year;

		$tag_monthSelect =  $this->buildMonthSelectBox();;
    	$tag_yearSelect = $this->buildYearSelectBox();
		
		// build the 'today' button	
		$btnToday = Common::translate('today');
	    $tag_todayButton = "<input class='btn' name='today' type='submit' value=\"$btnToday\">";
		$tag_previousMonthButton = "<input class='btn' name='monthBack' type='submit' value='<<'>";
		$tag_nextMonthButton = "<input class='btn' name='monthForward' type='submit' value='>>'>";
		$tag_previousYearButton = "<input class='btn' name='yearBack' type='submit' value='<<'>";
		$tag_nextYearButton = "<input class='btn' name='yearForward' type='submit' value='>>'>";
		
	    // grab the HTML peices for the calendar
	    $html_header = $this->searchHTML($this->html_template,
					     "<!-- Header Start -->", "<!-- Header End -->");

		$html_day_heading = $this->searchHTML($this->html_template,
						  "<!-- Heading Start -->","<!-- Heading End -->");
						  
	    // the calendar week pieces
	    $html_week_start = "<tr>";
	    $html_week_end = "</tr>";
 
	    // the calendar footer
	    $html_footer = $this->searchHTML($this->html_template,
					     "<!-- Footer Start -->", "<!-- Footer End -->");
    	
	    /***** Begin Building the Calendar (pre-week) *****/    	
	    // add the header to the calendar HTML code string
	    $ret .= $html_header;
	    $ret .= $html_day_heading;

		//$ret = str_replace("[[HEADER]]", $this->tag_header_month_view, $ret);
		
	    /***** Search and replace variable tags at this point *****/
		$ret = str_replace("[[TodayButton]]", $tag_todayButton, $ret);
	    $ret = str_replace("[[MonthSelect]]", $tag_monthSelect, $ret);
	    $ret = str_replace("[[PreviousMonthButton]]", $tag_previousMonthButton, $ret);
	    $ret = str_replace("[[NextMonthButton]]", $tag_nextMonthButton, $ret);
	    $ret = str_replace("[[YearSelect]]", $tag_yearSelect, $ret);
	    $ret = str_replace("[[PreviousYearButton]]", $tag_previousYearButton, $ret);
	    $ret = str_replace("[[NextYearButton]]", $tag_nextYearButton, $ret);
	    $ret = str_replace("[[CalendarName]]", $tag_calendarName, $ret);
	    $ret = str_replace("[[CalendarMonth]]", $tag_calendarMonth, $ret); 
	    $ret = str_replace("[[CalendarYear]]", $tag_calendarYear, $ret);
		$ret = str_replace("[[Views]]", $this->tag_views, $ret);
		
		$heading = $this->tag_heading;
		
		if($this->setting('monday')){
			$heading = str_replace("[[day1]]", Common::translate(2,'weekday'), $heading);
			$heading = str_replace("[[day2]]", Common::translate(3,'weekday'), $heading);
			$heading = str_replace("[[day3]]", Common::translate(4,'weekday'), $heading);
			$heading = str_replace("[[day4]]", Common::translate(5,'weekday'), $heading);
			$heading = str_replace("[[day5]]", Common::translate(6,'weekday'), $heading);
			$heading = str_replace("[[day6]]", Common::translate(7,'weekday'), $heading);
			$heading = str_replace("[[day7]]", Common::translate(1,'weekday'), $heading);
		}
		else{
			$heading = str_replace("[[day1]]", Common::translate(1,'weekday'), $heading);
			$heading = str_replace("[[day2]]", Common::translate(2,'weekday'), $heading);
			$heading = str_replace("[[day3]]", Common::translate(3,'weekday'), $heading);
			$heading = str_replace("[[day4]]", Common::translate(4,'weekday'), $heading);
			$heading = str_replace("[[day5]]", Common::translate(5,'weekday'), $heading);
			$heading = str_replace("[[day6]]", Common::translate(6,'weekday'), $heading);
			$heading = str_replace("[[day7]]", Common::translate(7,'weekday'), $heading);
		}
		
		$ret = str_replace("[[HEADING]]", $heading, $ret);
		
		$ret .= $this->getMonthHTML($this->month, $this->year);

	    /***** Do footer *****/
	    $tempString = $html_footer;
		
		// replace potential variables in footer
		$tempString = str_replace("[[TodayData]]", $this->tag_HiddenData, $tempString);
		$tempString = str_replace("[[TimeTrackValues]]", $tag_timeTrackValues, $tempString);
		
	    $ret .= $tempString;	
		$ret = $this->stripLeadingSpace($ret);
		
		$this->debug->set("renderMonth Ended");		
	    return $ret;	
	}
	
	// this generates the logic and the html to organize where the days go in the "grid"
	function getMonthHTML($month, $year, $simplemonth=false){
		$offset = 1;
		$dayOfWeek = date('N', mktime(12, 0, 0, $month, 1, $year));	// 0-6
	    $daysInMonth = Common::getDaysInMonth($month,$year); // 28-31
		$weeksInMonth = ceil( ($dayOfWeek + $daysInMonth)/7 ) ; // 4-6
	
		if( $this->setting("monday")) $offset = 2;
	
		$counter = $theDay = -($dayOfWeek-$offset);
		$ret = "";
		
		for ($week = 0; $week < $weeksInMonth; $week+=1){
			$bValidWeek = false;
			$temp = "<tr>";	
			
			for ($day = 0; $day < 7; $day+=1){
				if($counter > $daysInMonth) $theDay = 0; // we want these days to be grey or empty...etc
				
				if($theDay > 0) $bValidWeek = true; 
				
				if($simplemonth){
					$todayStyle = "style='background-color: #C0C0C0;font-weight:bold;'";
                    // TODO this will impact the view of year.
					//$link = $this->buildAddEventLink($month, $theDay, $year, $theDay);
                    $temp .= "<td class='yearWeekday $todayStyle'>$link</td>";
				} else{
					$temp .= $this->getHTMLForDay($month, $theDay, $year);
				}
				
				$counter++;
				$theDay++;
			}
			
			$temp .= "</tr>";
			
			// dont display a completely "greyed" out 5 day week
			if($bValidWeek == true){
				$ret .= $temp;
			}
		}
	
		return $ret;
	}

    // returns the HTML that appears between two search strings.
    // the returned results include the text between the search strings,
    // else an empty string will be returned if not found.
    function searchHTML($html, $beginString, $endString) {
	
    	$temp = split($beginString, $html);
    	if (count($temp) > 1) {
			$temp = split($endString, $temp[1]);
			return $temp[0];
    	}
    	return "";
    }
    
    // strips the leading spaces and tabs from lines of HTML (to prevent <pre> tags in Wiki)
    function stripLeadingSpace($html) {
		
    	$index = 0;
    	
    	$temp = split("\n", $html);
    	
    	$tempString = "";
    	while ($index < count($temp)) {
	    while (strlen($temp[$index]) > 0 
		   && (substr($temp[$index], 0, 1) == ' ' || substr($temp[$index], 0, 1) == '\t')) {
		$temp[$index] = substr($temp[$index], 1);
	    }
			$tempString .= $temp[$index];
			$index += 1;    		
		}
    	
    	return $tempString;	
    }	

	function cleanDayHTML($tempString){
		// kludge to clean classes from "day" only parameter; causes oddness if the main calendar
		// was displayed with a single day calendar on the same page... the class defines carried over...
		$tempString = str_replace("calendarTransparent", "", $tempString);
		$tempString = str_replace("calendarDayNumber", "", $tempString);
		$tempString = str_replace("calendarEventAdd", "", $tempString);	
		$tempString = str_replace("calendarEventList", "", $tempString);	
		
		$tempString = str_replace("calendarToday", "", $tempString);	
		$tempString = str_replace("calendarMonday", "", $tempString);
		$tempString = str_replace("calendarTuesday", "", $tempString);
		$tempString = str_replace("calendarWednesday", "", $tempString);
		$tempString = str_replace("calendarThursday", "", $tempString);	
		$tempString = str_replace("calendarFriday", "", $tempString);
		$tempString = str_replace("calendarSaturday", "", $tempString);	
		$tempString = str_replace("calendarSunday", "", $tempString);	
		
		return $tempString;
	}

    // builds the day events into memory
	// uses prefix seaching (NS:page/name/date)... anything after doesn't matter
    function buildArticlesForDay($month, $day, $year) {
	
		//$date = "$month-$day-$year";
		$date = $this->userDateFormat($month, $day, $year);
		
		$search = "$this->calendarPageName/$date";
		$pages = PrefixSearch::titleSearch( $search, '100');
		
		foreach($pages as $page) {
			$this->addArticle($month, $day, $year, $page);
		}
		unset ($pages);
		
	}

	// this is a general find/replace for the date format
	// users can define whatever format this wish
	// ie: 20090731, 07-01-2009, 07.01.2009, etc
	function userDateFormat($month, $day, $year) {
		global $wgCalendarDateFormat;
		
		$format = $wgCalendarDateFormat;
		if($format == '') $format = 'M-D-YYYY'; //default

		$format = str_ireplace('YYYY',$year,$format);
		$format = str_ireplace('MM', str_pad($month, 2, '0', STR_PAD_LEFT), $format);
		$format = str_ireplace('DD', str_pad($day, 2, '0', STR_PAD_LEFT), $format);
		$format = str_ireplace('D',$day,$format);
		
		if( stripos($format,'SM') !== false || stripos($format,'LM') !== false ){
			$format = str_ireplace('SM', Common::translate($month, 'month_short'), $format);
			$format = str_ireplace('LM', Common::translate($month, 'month'), $format);
		}else{
			$format = str_ireplace('M',$month,$format);
		}

		return $format;
	}
	
	function buildTagEvents($paramstring){
	
		$events = split( "\n", trim($paramstring) );
	
		foreach($events as $event) {
			$arr = split(':', $event);
			$date = array_shift($arr);
			$event = array_shift($arr);
			
			$body = implode(':',$arr);
			
			$arrDate = split('-',$date);
			
			// we must have a valid date to continue
			if(count($arrDate) < 3 ) 
				break;

			$month = $arrDate[0];
			$day = $arrDate[1];
			$year = $arrDate[2];

			$this->buildEvent($month, $day, $year, $event, $this->title);	
		}
	}
	
	function buildSimpleCalendar($month, $year,$disableNavButtons=false){

		$prev = $next = "";
	
		$monthname = Common::translate($month,'month_short');
		$monthyear = "$monthname, $this->year";
		
		if(!$disableNavButtons){
			$prev = "<input class='btn' name='monthBack' type='submit' value='<<'>";
			$next = "<input class='btn' name='monthForward' type='submit' value='>>'>";
		}
		
		$title = "<table class='yearCalendarMonth_x' width=100% cellpadding=0 cellspacing=0><td>$prev</td><td>" . $monthyear . "</td><td>$next</td></table>";
		
		$header = "<table class='yearCalendarMonth'><tr><td style='font-size:9px' class='yearTitle'>$title</tr></table>";				
	
		$ret = "<tr>";
	
		if($this->setting('monday')){	
			$ret .= "
				<td class='yearHeading'>" . substr(Common::translate(2,'weekday'),0,1) . "</td>						
				<td class='yearHeading'>" . substr(Common::translate(3,'weekday'),0,1) . "</td>
				<td class='yearHeading'>" . substr(Common::translate(4,'weekday'),0,1) . "</td>
				<td class='yearHeading'>" . substr(Common::translate(5,'weekday'),0,1) . "</td>
				<td class='yearHeading'>" . substr(Common::translate(6,'weekday'),0,1) . "</td>
				<td class='yearHeading'>" . substr(Common::translate(7,'weekday'),0,1) . "</td>
				<td class='yearHeading'>" . substr(Common::translate(1,'weekday'),0,1) . "</td>";
		}
		else{
			$ret .= "
				<td class='yearHeading'>" . substr(Common::translate(1,'weekday'),0,1) . "</td>
				<td class='yearHeading'>" . substr(Common::translate(2,'weekday'),0,1) . "</td>
				<td class='yearHeading'>" . substr(Common::translate(3,'weekday'),0,1) . "</td>
				<td class='yearHeading'>" . substr(Common::translate(4,'weekday'),0,1) . "</td>
				<td class='yearHeading'>" . substr(Common::translate(5,'weekday'),0,1) . "</td>
				<td class='yearHeading'>" . substr(Common::translate(6,'weekday'),0,1) . "</td>
				<td class='yearHeading'>" . substr(Common::translate(7,'weekday'),0,1) . "</td>";
		}
		
		$ret.= $this->getMonthHTML($month,$year,true);
	
		$hidden = $this->tag_HiddenData;
		
		return "<form name='cal_frm' method='post'>" . $header . "<table class='yearCalendarMonth'>$ret</table>$hidden</form>";
	}	

	function renderYear(){
	
		$tag_mini_cal_year = "";
		
		$tag_previousYearButton = "<input class='btn' name='yearBack' type='submit' value='<<'>";
		$tag_nextYearButton = "<input class='btn' name='yearForward' type='submit' value='>>'>";
		
		//$styleContainer = "style='width:100%; border:1px solid #CCCCCC; border-collapse:collapse;'";
		$styleTitle = "style='text-align:center; font-size:24px; font-weight:bold;'";
		
		$html_head = "<table class='yearCalendar'><form  method='post'>";
		$html_foot = "</table></form>";
		
		$ret = ""; $cal = "";
		$nextMon=1;
		$nextYear = $this->year;
		
		$title = "$tag_previousYearButton &nbsp; $this->year &nbsp; $tag_nextYearButton";
		
		$ret = "<tr><td $styleTitle colspan=2>$title</td><td align=right>$this->tag_views</td></tr>";

		for($m=0;$m <12; $m++){
			$cal .= "<td style='text-align:center; vertical-align:top;'>" . $this->buildSimpleCalendar($nextMon++, $nextYear, true) . "</td>";
			
			if($m==3 || $m==7 || $m==11){
				$ret .= "<tr>$cal</tr>";
				$cal = "";
			}	
		}	
		
		return $html_head . $ret . $this->tag_HiddenData . $html_foot ;
	}
	
	function renderWeek(){
		$this->initalizeMonth(0,8);
		
		//defaults
		$sunday = $saturday  = $ret = $week = ""; 
		$colspan = 2; 
		
		$styleTable = "style='border-collapse:collapse; width:100%;'";
		$styleTitle = "style='font-size: 24px;'";
		
		$html_head = "<form  method='post'><table $styleTable border=0>";
		$html_foot = "</table></form>";
		
		$weekday = date('w', mktime(12, 0, 0, $this->month, $this->day, $this->year));
		
		if($this->setting('monday')) $weekday--;
		$date = Common::datemath(-($weekday), $this->month, $this->day, $this->year);

		$month = $date['mon'];
		$day = $date['mday'];
		$year = $date['year'];
		
		//$title = $date['month'];
		$title = Common::translate($month, 'month') . ", " . $year;
		
		$btnToday = Common::translate('today');
		$tag_weekBack = "<input class='btn' name='weekBack' type='submit' value='<<'>";
		$tag_weekForward = "<input class='btn' name='weekForward' type='submit' value='>>'>";		
		$tag_todayButton = "<input class='btn' name='today' type='submit' value=\"$btnToday\">";
		
		$sunday = "<td class='calendarHeading'>" . Common::translate(1, 'weekday'). "</td>";
		$saturday = "<td class='calendarHeading'>" . Common::translate(7, 'weekday'). "</td>";
		$colspan = 4; //adjust for mode buttons
		
		//hide mode buttons if selected via parameter tag
		$ret .= "<tr>&nbsp;<td></td><td $styleTitle colspan=2>&nbsp;$title</td>"
                . "<td align=right colspan=$colspan>$tag_todayButton &nbsp;&nbsp; $this->tag_views</td><td>&nbsp;</td></tr>";	
		
		if($this->setting('monday')){
			$ret .= "<tr><td></td>";
			$ret .= "<td class='calendarHeading'>" . Common::translate(2, 'weekday'). "</td>";
			$ret .= "<td class='calendarHeading'>" . Common::translate(3, 'weekday'). "</td>";
			$ret .= "<td class='calendarHeading'>" . Common::translate(4, 'weekday'). "</td>";
			$ret .= "<td class='calendarHeading'>" . Common::translate(5, 'weekday'). "</td>";
			$ret .= "<td class='calendarHeading'>" . Common::translate(6, 'weekday'). "</td>";
			$ret .= $saturday;
			$ret .= $sunday;
			$ret .= "<td></td></tr>";
		}
		else{
			$ret .= "<tr><td></td>";
			$ret .= $sunday;
			$ret .= "<td class='calendarHeading'>" . Common::translate(2, 'weekday'). "</td>";
			$ret .= "<td class='calendarHeading'>" . Common::translate(3, 'weekday'). "</td>";
			$ret .= "<td class='calendarHeading'>" . Common::translate(4, 'weekday'). "</td>";
			$ret .= "<td class='calendarHeading'>" . Common::translate(5, 'weekday'). "</td>";
			$ret .= "<td class='calendarHeading'>" . Common::translate(6, 'weekday'). "</td>";
			$ret .= $saturday;
			$ret .= "<td></td></tr>";
		}
		
		
		for($i=0; $i<7; $i++){
			$week .= $this->getHTMLForDay($month, $day, $year, 'short', 'week');
			Common::getNextValidDate($month, $day, $year);
		}

		$ret .= "<tr><td width=1% valign=top>$tag_weekBack</td>" . $week . "<td width=1% valign=top>$tag_weekForward</td></tr>";
		
		$this->debug->set($year);	
		$this->debug->set("renderWeek Ended");	
		return $html_head . $ret . $this->tag_HiddenData . $html_foot;
	}
	
	//hopefully a catchall of most types of returns values
	function setting($param, $retBool=true){
	
		//not set; return bool false
		if(!isset($this->arrSettings[$param]) && $retBool) return false;
		if(!isset($this->arrSettings[$param]) && !$retBool) return "";
		
		//set, but no value; return bool true
		if($param == $this->arrSettings[$param] && $retBool) return true;
		if($param == $this->arrSettings[$param] && !$retBool) return "";
		
		// contains data; so lets return it
		return $this->arrSettings[$param];
	}
	
	function updateSetting($params, $value = null){
		$this->arrSettings[$params] = $value;
	}
	
	
	// get the extension short 'URL' path ex:( /mediawiki/extensions/calendar/ )
	// ... there has to be a better way then this!
	function getURLRelativePath(){
		global $wgScriptPath,$wgCalendarURLPath;
		
		//$path = str_ireplace('calendar.php', '', __FILE__);
		//$path = str_replace('\\', '/', $path);
		//$url = stristr($path, 'extensions');

		if($wgCalendarURLPath){
			return $wgCalendarURLPath;
		}else{
			return $wgScriptPath . "/extensions/Calendar";
		}
	}
	
	// Set/Get accessors		
	function setMonth($month) { $this->month = $month; } /* currently displayed month */
	function setDay($day) { $this->day = $day; } /* currently displayed month */
	function setYear($year) { $this->year = $year; } /* currently displayed year */
	function setTitle($title) { $this->title = $title; }
	function setName($name) { $this->name = $name; }
	function setMode($mode) { $this->mode = $mode; }
	function createAlert($day, $month, $text){$this->arrAlerts[] = $day . "-" . $month . "-" . $text . "\\n"; }
}

// called to process <Calendar> tag.
// most $params[] values are passed right into the calendar as is...
function displayCalendar($paramstring, $params = array()) {
    global $wgParser;
	global $wgScript, $wgScriptPath;
	global $wgTitle, $wgUser;
	global $wgRestrictCalendarTo, $wgCalendarDisableRedirects;
	global $wgCalendarForceNamespace, $wgCalendarDateFormat;
    
	$wgParser->disableCache();
	$wikiRoot = $wgScript . "?title=";
	$userMode = 'month';
	
	// grab the page title
	$title = $wgTitle->getPrefixedText();	
	
	$config_page = " ";

	$calendar = null;	
	$calendar = new Calendar($wikiRoot, isset($params["debug"]));

	//return $calendar->getURLRelativePath();
	
	$calendar->namespace = $wgTitle->getNsText();
	
	if(!isset($params["name"])) $params["name"] = "Public";
	
	// append simplemonth to name to seperate from normal calendar names
	//if(isset($params["simplemonth"])) $params["name"] .= "_simplemonth";
	
	$calendar->paramstring = $paramstring;
	
	// set path		
	$params['path'] = str_replace("\\", "/", dirname(__FILE__));
		
	$name = Common::checkForMagicWord($params["name"]);
		
	// normal calendar...
	$calendar->calendarPageName = "$title/$name";
	
	// if the calendar isn't in a namespace(s) specificed in $wgCalendarForceNamespace, return a warning
	// this can be a string or an array
	if(isset($wgCalendarForceNamespace)){
		if(is_array($wgCalendarForceNamespace)){
			if(!in_array($calendar->namespace,$wgCalendarForceNamespace)) {
				
				$namespaces = "";
				foreach($wgCalendarForceNamespace as $namespace){
					$namespaces .= $namespace . ", ";
				}
				
				return Common::translate('invalid_namespace') . '<b>'.$namespaces.'</b>';
			}
		}
		else if ( $wgCalendarForceNamespace != $calendar->namespace){
			return Common::translate('invalid_namespace') . '<b>'.$wgCalendarForceNamespace.'</b>';
		}
	}

	//set defaults that are required later in the code...
	if(!isset($params["timetrackhead"])) 	$params["timetrackhead"] = "Event, Value";
	if(!isset($params["maxdailyevents"])) 	$params["maxdailyevents"] = 5;
	if(!isset($params["yearoffset"])) 		$params["yearoffset"] = 2;
	if(!isset($params["charlimit"])) 		$params["charlimit"] = 25;
	if(!isset($params["css"])) 				$params["css"] = "default.css"; 
	
	//if(!isset($params["formatdate"])) $params["formatdate"] = 'M-D-YYYY'; 

	//if($params["formatdate"] == 'formatdate'){
	//	return 'Invalid date format';
	//}

	//set secure mode via $wgRestrictCalendarTo global
	// this global is set via LocalSetting.php (ex: $wgRestrictCalendarTo = 'sysop';
	if( isset($wgRestrictCalendarTo) ){
		$arrGroups = $wgUser->getGroups();
		if( is_array($wgRestrictCalendarTo) ){
			if( count(array_intersect($wgRestrictCalendarTo, $arrGroups)) == 0 ){
				$params["lockdown"] = true;
			}
		}
		else{
			if( !in_array($wgRestrictCalendarTo, $arrGroups) ){
				$params["lockdown"] = true;
			}
		}
	}

	if (isset($wgCalendarDisableRedirects))
		$params['disableredirects'] = true;
	
	// no need to pass a parameter here... isset check for the params name, thats it
	if(isset($params["lockdown"])){
		$params['disableaddevent'] = true;
		$params['disablelinks'] = true;
		$params['locktemplates'] = true;
	}

	// this needs to be last after all required $params are updated, changed, defaulted or whatever
	$calendar->arrSettings = $params;
	
    // finished special conditions; set the $title and $name in the class
	$calendar->setTitle($title);
	$calendar->setName($name);

	$cookie_name = preg_replace('/(\.|\s)/',  '_', ($title . " " . $name)); //replace periods and spaces
	if(isset($_COOKIE[$cookie_name])){
		$calendar->debug->set('cookie loaded');

		$arrSession = split("`", $_COOKIE[$cookie_name]);
		$calendar->setMonth($arrSession[0]);
		$calendar->setDay($arrSession[1]);
		$calendar->setYear($arrSession[2]);	
		$calendar->setTitle($arrSession[3]);				
		$calendar->setName($arrSession[4]);	

		if(strlen($arrSession[5]) > 0)
			$userMode = $arrSession[5];
	}
	else{
		// defaults from the <calendar /> parameters; must restart browser to enable
		if(isset($params['week'])) $userMode = 'week';
		if(isset($params['year'])) $userMode = 'year';	
	}
	
	if(isset($params['useeventlist'])) $userMode = 'events';
	if(isset($params['date'])) $userMode = 'day';
	if(isset($params['simplemonth'])) $userMode = 'simplemonth';

	$render = $calendar->renderCalendar($userMode);
	
	// purge main calendar before displaying the calendar
	if($calendar->invalidateCache){
		$article = new Article(Title::newFromText($title));
		$article->purge();
		header("Location: " . $wikiRoot . $title);
	}

	return $render;
}

function wfCalendarFunctions_Magic( &$magicWords, $langCode ) {
    switch ( $langCode ) {
         default:
              $magicWords['calendar']         = array( 0, 'calendar' );
    }
    return true;
}

