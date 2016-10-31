<?php

use \Carbon\Carbon;

/**
 * Created by PhpStorm.
 * User: Allan McNaughton
 * Date: 3/16/2015
 * Time: 9:45 AM
 */
class ReminderNotStarted extends Reminder
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

            if ($participant->course_progress == 0) {

                $enrollment = Carbon::parse($participant->enrollment_date);

                $debug = $participant->user_id ." ENROLLED in course " . $participant->course_id . " ON " . $enrollment . " PROGRESS {$participant->course_progress}%";
                $this->log->addDebug("NOT STARTED", $debug);

                if ($this->is_reminder_day($enrollment))
                    $this->send_reminder_email($participant, $enrollment->diffInDays());
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

        return "{$blog_id}_{$course_id}_{$session_id}_not_started_reminder";
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
        $debug = "SEND NOT STARTED REMINDER_EMAIL {$participant->display_name} {$participant->user_email} {$participant->course_title} {$participant->user_id} {$days}";
        $this->log->addDebug("NOT STARTED", $debug);

        $subject = "It's been $days days since you joined the course!";

        $message = "You joined the course <em>$participant->course_title</em> <b>$days days ago</b> and have not started yet.<br><br>
                    Click <a href='$url'>here</a> to start your training course.<br><br>";

        return array('subject' => $subject, 'message' => $message, 'to' => $participant->user_email);
    }
}