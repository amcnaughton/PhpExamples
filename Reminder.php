<?php

use \Carbon\Carbon;

/**
 * Created by PhpStorm.
 * User: Allan McNaughton
 * Date: 3/16/2015
 * Time: 9:36 AM
 */
abstract class Reminder
{
    protected $log;
    protected $dates;

    abstract protected function meta_tag($course_id, $session_id);

    abstract protected function create_reminder_email($participant, $days, $url);

    /**
     * @param null $days
     * @param null $today
     * @throws Exception
     */
    public function __construct($days = null, $today = null)
    {
        if (empty($days))
            $days = [5, 10, 15, 20, 25, 30];
        else
            if (!is_array($days))
                $days = explode(',', $days);

        $today = (empty($today) ? Carbon::today() : Carbon::parse($today));

        $days = array_reverse($days);
        foreach ($days as $day) {
            $newDay = clone $today;
            $this->dates[] = $newDay->subDays($day);
        }
        $this->log = new Log('reminders');
    }

    /**
     * Check if the provided date is a scheduled reminder day
     *
     * @param $date
     * @return bool
     */
    public function is_reminder_day($date)
    {
        if (!($date instanceof Carbon))
            $date = Carbon::parse($date);

        return in_array(new Carbon($date->startOfDay()), $this->dates);
     }

    /**
     * Process participants for each course
     */
    public function process()
    {
        $courses = lms_course_get_list();

        foreach ($courses as $course) {

            $participants = lms_course_get_enrollees(array('course_id' => $course->post_id, 'session_id' => $course->session_id));
            if (!empty($participants))
                $this->process_participants($participants);
        }
    }

    /**
     * Create and send the reminder email
     *
     * @param $participant
     * @param $days
     * @throws Exception
     */
    protected function send_reminder_email($participant, $days)
    {
        if (empty($participant) || empty($days))
            throw new Exception("Invalid argument");

        $user = new User($participant->user_id);

        $url = get_bloginfo('url') . '/enrollment/?' . $user->autologin_urltoken();

        $email = $this->create_reminder_email($participant, $days, $url);

        (new Email())->send(
            array(
                'to' => $email['to'],
                'subject' => $email['subject'],
                'message' => $email['message'] . "<img width='220' border='0' style='display:block' src='" . teamos_company_email_footer_image_url() . "'>",
                'noforward' => true
            )
        );
    }

    /**
     * Get number of times reminder has been sent
     *
     * @param $user_id
     * @param $meta_tag
     * @return mixed
     * @throws Exception
     */
    protected function get_reminder_count($user_id, $meta_tag)
    {
        if (empty($user_id) || empty($meta_tag))
            throw new Exception("Invalid argument: user_id $user_id, meta_tag $meta_tag");

        return get_user_meta($user_id, $meta_tag, true);
    }

    /**
     * Increment number of times reminder has been sent
     *
     * @param $user_id
     * @param $meta_tag
     * @throws Exception
     */
    protected function increment_reminder_count($user_id, $meta_tag)
    {
        if (empty($user_id) || empty($meta_tag))
            throw new Exception("Invalid argument: user_id $user_id, meta_tag $meta_tag");

        update_user_meta($user_id, $meta_tag, $this->get_reminder_count($user_id, $meta_tag) + 1, true);
    }


}

