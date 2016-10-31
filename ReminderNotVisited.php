<?php

use \Carbon\Carbon;

/**
 * Created by PhpStorm.
 * User: Allan McNaughton
 * Date: 3/16/2015
 * Time: 9:45 AM
 */
class ReminderNotVisited extends Reminder
{
    /**
     * Send reminder emails as neceessary
     *
     * @param $participants
     * @throws Exception
     */
    protected function process_participants($participants)
    {
        foreach ($participants as $participant) {

            if ($participant->course_progress > 0 && $participant->course_progress < 100) {

                $user = new User($participant->user_id);
                $last_visted = Carbon::parse($user->last_login());

                $debug = $participant->user_id . " LAST VISITED " . $last_visted . " COURSE " . $participant->course_id . " PROGRESS {$participant->course_progress}%";
                $this->log->addDebug("NOT VISITED", $debug);


                if ($this->is_reminder_day($last_visted))
                    $this->send_reminder_email($participant, $last_visted->diffInDays());
            }
        }
    }

    /**
     * Create meta tag key
     *
     * @param $course_id
     * @param int $session_id
     * @return string
     */
    protected function meta_tag($course_id, $session_id = 0)
    {
        global $blog_id;

        return "{$blog_id}_{$course_id}_{$session_id}_not_visited_{$this->start_day}_days_reminder";
    }

    /**
     * Create reminder email
     *
     * @param $participant
     * @param $days
     * @param $url
     * @return array
     */
    protected function create_reminder_email($participant, $days, $url)
    {
        $debug = "SEND NOT VISITED REMINDER_EMAIL {$participant->display_name} {$participant->user_email} {$participant->course_title} {$participant->user_id} {$days}"; //return;
        $this->log->addDebug("NOT VISITED", $debug);

        $subject = "We've missed you the last $days days!";
        $message = "We have not seen you in <b>$days days</b>!<br><br>
                    Click <a href='$url'>here</a> to resume your training course, <em>$participant->course_title</em>.<br><br>";

        return array('subject' => $subject, 'message' => $message, 'to' => $participant->user_email);
    }
}