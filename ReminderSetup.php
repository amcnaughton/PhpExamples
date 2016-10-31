<?php
/**
 * Created by PhpStorm.
 * User: Allan McNaughton
 * Date: 3/16/2015
 * Time: 6:33 PM
 */

use Carbon\Carbon;

class ReminderSetup {

    CONST REMINDER_HOUR_OF_DAY = 20;

     public static function generate_reminders()
    {
        if(!empty(teamos_company_get_meta('disable_reminders')))
            return;

        $hour = (new Carbon())->hour;

        if($hour != self::REMINDER_HOUR_OF_DAY)     //  only send once per day, at 20 hundred hours UTC
            return;

        // now kickoff reminder email
         $schedule = teamos_company_get_meta('reminder_schedule');
         (new ReminderNotVisited($schedule))->process();
         (new ReminderNotEnrolled($schedule))->process();
         (new ReminderNotStarted($schedule))->process();
    }
}


if (function_exists('add_action')) {
    add_action('wp', 'reminder_setup_schedule');
}

/**
 * On an early action hook, check if the hook is scheduled - if not, schedule it.
 */
function reminder_setup_schedule()
{
    if (!wp_next_scheduled('reminder_hourly_event')) {
        wp_schedule_event(time(), 'hourly', 'reminder_hourly_event');
    }
}

if (function_exists('add_action')) {
    add_action('reminder_hourly_event', array(
        'ReminderSetup',
        'generate_reminders'
    ));

}
