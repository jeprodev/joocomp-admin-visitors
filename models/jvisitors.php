<?php
/**
 * @version     1.0.3
 * @package     Components
 * @subpackage  admin.com_jvcounter.models
 * @link http://jeprodev.fr.nf
 * @copyright (C) 2009 - 2011
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of,
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
// no direct access
defined('_JEXEC') or die('Restricted access');

define('JOOMLA_VISITORS_BIRTH_DAY', 1126742400 );

class JVisitorModelJVisitor {
    /**
     * Method to get the number of visitors
     * @since 1.0
     * @param int $startTime
     * @param int $stopTime
     * @param int $duration
     * @internal param int $startTime
     * @internal param int $stopTime
     * @return number of visitors
     */
    public static function getVisits($startTime=0, $stopTime=0, $duration=0){
        $startTime 	= (int)$startTime;
        $stopTime 	= (int)$stopTime;
        if( $startTime || $stopTime){
            return self::getVisitsFromLogs($startTime, $stopTime);
        }elseif($duration){
            return self::getOnlineVisits($duration);
        }else{
            return self::getToday();
        }
    }

    public static function getVisitsFromLogs($startTime=0, $stopTime=0){
        $db = JFactory::getDBO();
        $now 	= time();
        $startTime	= (int) $startTime;
        $stopTime	= (int) $stopTime;
        $records 	= null;
        $total		= array();
        $total['visits']	= 0;
        $total['guests']	= 0;
        $total['members']	= 0;
        $total['bots']		= 0;
        $total['last_time']	= 0;
        if ( $startTime < JOOMLA_VISITORS_BIRTH_DAY) {	$startTime 	= 0; }
        if ( $stopTime < JOOMLA_VISITORS_BIRTH_DAY) { $stopTime 	= 0;}
        $query = "SELECT * FROM " . $db->quoteName('#__jvisitor_counter');
        $query .= " WHERE '1=1'";
        if ( !$startTime ) {
            if ( $stopTime ) {
                $query 	.= " AND time <= $stopTime ";
            }
        }
        else {
            if ( !$stopTime ) {
                $query 	.= " AND time > $startTime ";
            }
            else {
                if ( $stopTime == $startTime ) {
                    return $total;
                }
                else {
                    $query 	.= " AND time > $startTime ";
                    $query 	.= " AND time <= $stopTime ";
                }
            }
        }
        $db->setQuery($query);
        $records = $db->loadObjectList();
        if ( $db->getErrorNum()) {
            JError::raiseWarning(500, $db->stderr());
        }
        if ( count( $records )) {
            $lastTime 	= 0;
            foreach( $records as $record ) {
                $lastTime 	= max( $lastTime, (int) $record->time);
                $total['visits']	+= (int) $record->visits;
                $total['guests']	+= (int) $record->guests;
                $total['members']	+= (int) $record->members;
                $total['bots']		+= (int) $record->bots;
            }
            $total['last_time']	= $lastTime;
        }
        return $total;
    }

    public static function getOnlineVisits($duration=900){
        $db 	= JFactory::getDBO();
        $now 	= time();
        $duration = (int) $duration;
        $time 	= $now - $duration;
        $records 	= null;
        $total 	= array();
        $total['visits']	= 0;
        $total['guests']	= 0;
        $total['members']	= 0;
        $total['bots']		= 0;
        $total['last_time']	= 0;
        $query = "SELECT ". $db->quoteName('session_id').", ".$db->quoteName('time').", ".$db->quoteName('guest').", ";
        $query .= $db->quoteName('data')." FROM ".$db->quoteName('#__session'). " WHERE time > $time";
        $db->setQuery($query);
        $sessions	= $db->loadObjectList();
        if ( $db->getErrorNum()) {
            JError::raiseWarning(500, $db->stderr());
        }
        if ( count( $sessions )) {
            $lastTime 	= 0;
            foreach( $sessions as $session ) {
                $lastTime	= max( $lastTime, (int) $session->time);
                $start		= self::getVisitorStartTime( $session->data );
                if ( !$session->guest ) {
                    $total['members'] = $total['members'] + 1;
                }
                else {
                    if ( self::isBot( $session->data)) {
                        $total['bots'] = $total['bots'] + 1;
                    }
                    else {
                        $total['guests'] =	$total['guests'] + 1;
                    }
                }
                $total['visits'] = $total['visits'] + 1;
            }
            $total['last_time']	= $lastTime;
        }
        return $total;
    }

    public static function getToday(){
        $config = JFactory::getConfig();
        $offset		= (float) $config->get('offset');
        $lifeTime 	= (int) $config->get('lifetime') * 60;
        $now 		= time();
        $dayStart  = $now - ($now % 86400);
        $localDayStart	= self::localStartTime($dayStart, $offset, "day");
        if (($now - $localDayStart ) < $lifeTime ){
            $visits	= self::getVisitsFromSession( $localDayStart);
        }
        else {
            $visitsLogs = self::getVisitsFromLogs($localDayStart);
            $visitsSession = self::getVisitsFromSession($visitsLogs['last_time'] + 1);
            $visits = self::array_add($visitsLogs, $visitsSession);
        }
        return $visits;
    }

    static function getVisitsFromSession($startTime=0, $stopTime=0){
        $db = JFactory::getDBO();
        $now = time();
        $startTime	= (int) $startTime;
        $stopTime 	= (int) $stopTime;
        $records 	= null;
        $total		= array();
        $total['visits']	= 0;
        $total['guests']	= 0;
        $total['members']	= 0;
        $total['bots']		= 0;
        $total['last_time']	= 0;
        if ( $startTime < JOOMLA_VISITORS_BIRTH_DAY) { $startTime = 0; }
        if ( $stopTime < JOOMLA_VISITORS_BIRTH_DAY) { $stopTime = 0; }
        $query	= "SELECT ".$db->quoteName('session_id').", ".$db->quoteName('time').", ".$db->quoteName('guest').", ";
        $query 	.= $db->quoteName('data')." FROM ".$db->quoteName('#__session')." WHERE '1=1' ";
        if ( !$startTime ) {
            if ( $stopTime ) {
                $query	.= " AND ".$db->quoteName('time')." < ".$stopTime;
            }
        }
        else {
            if ( !$stopTime ) {
                $query	.= " AND ".$db->quoteName('time')." >= ".$startTime;
            }
            else {
                if ( $stopTime == $startTime ) {
                    $query	.= " AND ".$db->quoteName('time')." = ".$startTime;
                }
                else {
                    $query 	.= " AND ".$db->quoteName('time')." >= ".$startTime;
                    $query 	.= " AND ".$db->quoteName('time')." < ".$stopTime;
                }
            }
        }
        $db->setQuery( $query );
        $sessions = $db->loadObjectList();
        if ( $db->getErrorNum()) {
            JError::raiseWarning( 500, $db->stderr());
        }

        if ( count( $sessions)) {
            $lastTime 	= 0;
            foreach( $sessions as $session ) {
                $lastTime 	= max( $lastTime, (int)$session->time);
                $start		= self::getVisitorStartTime( $session->data );
                /** count new visitor **/
                if (( !$start ) || ( $start > $startTime )) {
                    if ( $session->guest) {
                        $total['members']++;
                    }
                    else {
                        if ( self::isBot( $session->data )) {
                            $total['bots']++;
                        }
                        else {
                            $total['guests']++;
                        }
                    }
                    $total['visits']++;
                }
            }
            $total['last_time'] = $lastTime;
        }
        return $total;
    }

    private static function isBot( $data ){
        if( empty( $data )) { return NULL; }
        $bots = array("google.com", "yahoo.com", "msn.com", "ask.com", "cuil.com", "baidu.com", "Yandex");
        foreach( $bots as $bot ) {
            if ( strpos( $data, $bot))
                return $bot;
        }
        return NULL;
    }

    private static function array_add($arr_1, $arr_2) {
        if( count($arr_1) && count($arr_2) )
        {
            $arr_1['visits'] 	+= $arr_2['visits'];
            $arr_1['guests'] 	+= $arr_2['guests'];
            $arr_1['members'] 	+= $arr_2['members'];
            $arr_1['bots'] 		+= $arr_2['bots'];
            $arr_1['last_time'] 	= max( $arr_2['last_time'], $arr_1['last_time']);
        }
        return  $arr_1;
    }

    private static function getVisitorStartTime( $data ) {
        $start	= strpos( $data, "session.timer.start");
        $time = (int) substr( $data, $start + 23, 10);
        return $time;
    }

    static function insertLog( $time, $visitsArray ){
        $db = JFactory::getDBO();
        $time	= (int) $time;
        $visits = (int) $visitsArray['visits'];
        $guests = (int) $visitsArray['guests'];
        $members = (int) $visitsArray['members'];
        $bots 	= (int) $visitsArray['bots'];
        $query	= "INSERT INTO " . $db->quoteName('#__jvisitor_counter') . " (" . $db->quoteName("time") . ", ";
        $query .= $db->quoteName("visits") . ", " . $db->quoteName("guests") . ", "  .  $db->quoteName("members");
        $query .= ", " . $db->quoteName("bots") . ")  VALUES ( $time, $visits, $guests, $members, $bots);" ;
        $db->setQuery( $query );
        $db->execute();
        if( $db->getErrorNum()) {
            JError::raiseWarning(500, $db->stderr());
        }
    }

    static function removeNullLogs(){
        $db = JFactory::getDBO();
        $query = "SELECT ".$db->quoteName('time'). " FROM ". $db->quoteName('#__jvisitor_counter')." WHERE ";
        $query .= $db->quoteName('visits')." = 0 AND ". $db->quoteName('guests')." = 0 AND ";
        $query .= $db->quoteName('members')." = 0 AND ". $db->quoteName('bots')." = 0";
        $db->setQuery($query);
        $times = $db->loadColumn();
        if(count($times)){
            foreach($times as $time){
                $query = "DELETE FROM ".$db->quoteName('#__jvisitor_counter')." WHERE ".$db->quoteName('time'). "=$time";
                $db->setQuery($query);
                $db->execute();
            }
            return;
        }
    }

    static function lastTimeLog() {
        $db	= JFactory::getDBO();
        $lastTime = 0;
        $query = "SELECT MAX(". $db->quoteName('time').") FROM ". $db->quoteName('#__jvisitor_counter');
        $db->setQuery( $query );
        // last time
        $lastTime	= $db->loadResult();
        if( $db->getErrorNum()) {
            JError::raiseWarning(500, $db->stderr());
        }
        return $lastTime;
    }

    /**
     * @param int $offset
     * @param int $isSunday
     * @param string $now
     * @since 1.0.0
     * @return array|JObject
     */
    static function getStartTime($offset = 0, $isSunday = 1, $now = "") {
        $dateTime = new JObject();
        $offset	= (float) $offset;
        $now 	= (int) $now;
        if ( empty( $now )) { $now = time(); }
        /** Determine GMT Time ( UTC) **/
        $minute	= (int) gmstrftime("%M", $now);
        $hour	= (int) gmstrftime("%H", $now);
        $day	= (int) gmstrftime("%d", $now);
        $month	= (int) gmstrftime("%m", $now);
        $year	= (int) gmstrftime("%Y", $now);
        /**determine day gmt starting time **/
        $dayStart	= gmmktime(0, 0, 0, $month, $day, $year);
        $localDayStart = self::localStartTime( $dayStart, $offset, "day");
        /** determine yesterday gmt starting time **/
        $yesterdayStart = $dayStart - 86400;
        $localYesterdayStart = $localDayStart - 86400;
        /** determine week gmt starting time **/
        $weekDay = (int) strftime("%w", $now);
        if ( !$isSunday ) {
            if ( $weekDay ) {
                $weekDay--;
            } else {
                $weekDay = 6;
            }
        }
        $weekStart = $dayStart - ( $weekDay * 86400 );
        $localWeekStart = self::localStartTime( $weekStart, $offset, "week");
        /**determine last week gmt starting time **/
        $lastWeekStart = $weekStart - ( 7 * 86400 );
        $localLastWeekStart = $localWeekStart - ( 7 * 86400 );
        /**determine month gmt starting time **/
        $monthStart = gmmktime(0, 0, 0, $month, 1, $year);
        $localMonthStart = self::localStartTime( $monthStart, $offset, "month");
        /**determine month gmt starting time **/
        $lastMonthDays = (int) strftime( "%d", $monthStart - 86400);
        //$lastMonthStart = $monthStart - ($lastMonthDays * 86400);
        $localLastMonthStart = $localMonthStart - ( $lastMonthDays * 86400);
        /** assigning date time variables */
        $dateTime = array(
            "local_day_start" => $localDayStart,
            "local_yesterday_start" => $localYesterdayStart,
            "local_week_start" => $localWeekStart,
            "local_last_week_start" => $localLastWeekStart,
            "local_month_start" => $localMonthStart,
            "local_last_month_start" => $localLastMonthStart);
        return $dateTime;
    }

    /**
     * @param $startTime
     * @param int $offset
     * @param string $type
     * @param string $now
     * @return false|float|int
     * @since
     */
    static function localStartTime( $startTime, $offset=0, $type="day", $now="") {
        $startTime	= (int) $startTime;
        $offset		= (float) $offset;
        $now		= (int) $now;
        if( empty($now)){
            $now	= time();
        }
        $type = strtolower( trim($type));
        if ( $type != "day" && $type != "week" && $type = "month") { $type = "day"; }
        $nextStartTime	= strtotime("+1".$type, $startTime);
        $lastStartTime	= strtotime("-1".$type, $startTime);
        $localOffset = $offset * 3600;
        if( $offset > 0){
            if( ($nextStartTime - $now ) < $localOffset ) {
                $startTime = $nextStartTime - $localOffset;
            }
            else {
                $startTime -= $localOffset;
            }
        }
        if( $offset < 0 ) {
            $localOffset 	= -$localOffset;
            if(( $now - $startTime) < $localOffset ){
                $startTime	 = $lastStartTime + $localOffset;
            }
            else {
                $startTime = $startTime + $localOffset;
            }
        }
        return $startTime;
    }
}