<?php

use \Carbon\Carbon;

/**
 * Created by PhpStorm.
 * User: Allan McNaughton
 * Date: 3/16/2015
 * Time: 9:45 AM
 */
class ReminderNotEnrolled extends Reminder
{

    /**
     * Create list of users who are not enrolled in a course
     */
    public function process()
    {
        $user_list = get_users(['orderby' => 'registered', 'order' => 'DESC', 'fields' => ['ID', 'user_login', 'user_email', 'user_registered']]);

        // add user_id field to match data from course enrollments table
        foreach($user_list as $key => $user)
            $user_list[$key]->user_id = $user->ID;

        foreach ($user_list as $user) {

            // skip disabled users
            if (teamos_user_is_disabled($user->ID))
                continue;

            // skip if user is enrolled in a course
            if (!empty(LMS_users_getUserCourseList($user->ID)))
                continue;

            $participants[] = $user;
        }

        $this->process_participants($participants);
    }

    /**
     * Send reminder emails as necessary
     *
     * @param $participants
     * @throws Exception
     */
    protected function process_participants($participants)
    {
        foreach ($participants as $participant) {

            $registered = Carbon::parse($participant->user_registered);

            if ($this->is_reminder_day($registered))
                $this->send_reminder_email($participant, $registered->diffInDays());
        }
    }

    /**
     * Create meta tag key
     *
     * @param $course_id
     * @param int $session_id
     * @return string
     */
    protected function meta_tag($course_id = 0, $session_id = 0)
    {
        global $blog_id;

        return "{$blog_id}_not_enrolled_reminder";
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
        $subject = "Time to enroll in a course (it's been $days days already)";

        $message = "You joined the site <b>$days days ago</b> and have not enrolled in a course yet.<br><br>
                    Click <a href='$url'>here</a> to enroll in a training course.<br><br>";

        return array('subject' => $subject, 'message' => $message, 'to' => $participant->user_email);
    }
}