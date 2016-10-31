<?php

/**
 * Manage user record
 *
 * @author Allan McNaughton
 *
 */
class User
{
    public $ID;
    public $username;
    public $email;
    public $fullname;
    public $firstname;
    public $lastname;
    public $cid;
    public $sid;
    public $avatar;
    public $title;
    public $join_date;
    public $coaches;
    public $token;
    public $client_admin;
    public $billing_user;
    public $password;
    public $password_confirm;
    public $registered;
    public $api;
    public $disabled;

    /**
     * Class constructor
     *
     * $ID is one of:
     *      email address
     *      token, when $args['use_token'] = true
     *      cid, when $args['use_cid'] = true
     *      WP user id
     *      WP username
     *
     * @param int|number $ID
     * @param array $args
     */
    public function __construct($ID = 0, $args = null)
    {
        // nothing to do here
        if (empty($ID) && empty($args)) {
            return;
        }

        // called via api
        if(!empty($args['api']))
            $this->api = $args['api'];

        // lookup user if ID provided
        if ($ID) {

            // handle a variety of ID formats
            if (filter_var($ID, FILTER_VALIDATE_EMAIL)) {

                $userdata = $this->find_user_by_email($ID);

            } else if (is_numeric($ID) || !empty($args['use_token'])) {

                // should we lookup the user by consultant ID, Wordpress user ID, or token?
                $meta_key = $this->lookup_metakey($args);

                if (!empty($meta_key)) {

                    $users = get_users(array(
                        'meta_key' => $meta_key,
                        'meta_value' => $ID,
                        'meta_compare' => '='
                    ));

                    $userdata = $users[0];
                } else {
                    $userdata = get_userdata($ID);
                }
            } else {
                $userdata = get_user_by('login', $ID );
            }
        }

        // lookup user by email if still not found
        if (empty($userdata) && !empty($args['user_email']))
            $userdata = $this->find_user_by_email($args['user_email']);

        // if user exists, populate object
        if (!empty($userdata)) {

            $this->load_userdata($userdata);
            $this->get_user_meta();
        }

        // update user with provided values
        $this->merge_args($args);
    }

    /**
     * Determine which lookup key to use
     *
     * @param $args
     * @return null|string
     */
    protected function lookup_metakey($args)
    {
        global $blog_id;

        $meta_key = null;

        if (!empty($args['use_cid']))
            $meta_key = "{$blog_id}_cid";
        else if (!empty($args['use_token']))
            $meta_key = "{$blog_id}_token";

        return $meta_key;
    }

    /**
     * Populate userdata object
     *
     * @param $userdata
     */
    protected function load_userdata($userdata)
    {
        $this->ID = $userdata->ID;
        $this->username = $userdata->data->user_login;
        $this->fullname = $userdata->data->display_name;
        $this->split_fullname();
        $this->email = $userdata->data->user_email;
        $this->registered = $userdata->data->user_registered;
    }

    /**
     * Merge provided arguments into user object
     *
     * @param array $args
     */
    protected function merge_args($args)
    {
        if (empty($this->username) && !empty($args['user_username']))
            $this->username = $args['user_username'];

        if (!empty($args['user_email']))
            $this->email = $args['user_email'];

        if (!empty($args['user_fullname']))
            $this->fullname = $args['user_fullname'];

        if (!empty($args['user_firstname']))
            $this->firstname = $args['user_firstname'];

        if (!empty($args['user_lastname']))
            $this->lastname = $args['user_lastname'];

        if (empty($this->lastname))
            $this->split_fullname();

        if (empty($this->fullname) && !empty($this->firstname) && !empty($this->lastname))
            $this->create_fullname();

        if (!empty($args['user_cid']))
            $this->cid = $args['user_cid'];

        if (!empty($args['user_sid']))
            $this->sid = $args['user_sid'];

        if (!empty($args['user_coaches']))
            $this->coaches = $args['user_coaches'];

        if (!empty($args['user_password']))
            $this->password = $args['user_password'];

        if (!empty($args['user_password_confirm']))
            $this->password_confirm = $args['user_password_confirm'];

        if (!empty($args['user_avatar']))
            $this->avatar = $args['user_avatar'];

        if (!empty($args['user_title']))
            $this->title = $args['user_title'];

        if (!empty($args['user_join_date']))
            $this->join_date = $args['user_join_date'];
    }

    /**
     * Create first and lastname from fullname
     */
    protected function split_fullname()
    {
        $parts = explode(" ", $this->fullname);
        $this->lastname = array_pop($parts);
        $this->firstname = implode(" ", $parts);
    }

    /**
     * Create fullname from first and lastname
     */
    protected function create_fullname()
    {
        $this->fullname = $this->firstname . ' ' . $this->lastname;
    }

    /**
     * Return true if the user ID/consultant ID/email/username is valid
     *
     * @param mixed $ID
     *
     * @return false or user id
     */
    public static function is_valid($ID, $use_cid = false)
    {
        if (empty($ID)) {
            return false;
        }

        if ($use_cid) {
            $user = new User($ID, array(
                'use_cid' => true
            ));
        } else {
            $user = new User($ID);
        }

        if (!empty($user->ID)) {
            return $user->ID;
        } else {
            return false;
        }
    }

    /**
     * Return last login for user
     *
     * @return string
     */
    public function last_login()
    {
        if (empty($this->ID)) {
            return null;
        } else {
            $last_visit = (new UserActivity())->last_activity_date($this->ID);
            return Format::date($last_visit);
        }
    }

    /**
     * Return client admin rights
     *
     * @return mixed|null
     */
    public function is_client_admin()
    {
        return $this->client_admin;
    }

    /**
     * Generate an autologin token if one does not exist
     */
    public function generate_token()
    {
        if(empty($this->token)) {
            $this->token = md5(uniqid(mt_rand(), true));
            $this->save();
        }

        return $this->token;
    }

    /**
     * Return autologin url string
     *
     * @return string
     */
    public function autologin_urltoken()
    {
        return "token=".$this->generate_token();
    }

    /**
     * Update existing user or create a new one
     **
     * @return array
     */
    public function save()
    {
        // update existing record?
        if ($this->ID) {

            $account_details = $this->validate_userdata(false);

            if (!empty($account_details['errors']->errors)) {
                return $account_details['errors'];
            }

            $this->update_user();
        } else {

            // create a new user

            if (empty($this->password) && empty($this->password_confirm)) {
                $this->password = $this->password_confirm = $this->generate_strong_password();
            }

            if (empty($this->username) && (!empty($this->firstname) || !empty($this->lastname))) {
                $this->username = $this->generate_unique_username($this->firstname, $this->lastname);
            }

            $account_details = $this->validate_userdata();

            if (!empty($account_details['errors']->errors)) {
                return $account_details['errors'];
            }

            $this->new_user();
        }
    }

    /**
     * Update existing WP user record
     */
    protected function update_user()
    {
        if(empty($this->ID))
            throw new Exception("Missing user ID");

        $args = array(
            'ID' => $this->ID,
            'display_name' => $this->fullname,
            'user_email' => $this->email
        );
        if (!empty($this->password)) {
            $args['user_pass'] = $this->password;
        }

        wp_update_user($args);

        $this->save_user_meta();
        $this->add_user_to_blog();

        if (defined('PHPUNIT_RUNNING') || !$this->user_belongs_to_blog()) {

            // add user to blong and send welcome email
            $this->add_user_to_blog();
            $this->send_existing_user_email();
        }
    }

    /**
     * Create a new WP user
     */
    protected function new_user()
    {
        // create the account
        $args = array(
            'user_login' => $this->username,
            'user_pass' => $this->password,
            'display_name' => $this->fullname,
            'user_email' => $this->email
        );

        $this->ID = wp_insert_user($args);

        $this->save_user_meta();
        $this->add_user_to_blog();

        $this->new_user_email($args);
    }

    /**
     * Save important user meta values on a per blog basis
     */
    protected function save_user_meta()
    {
        if (!empty($this->api))
            update_user_blog_meta($this->ID, "_api", $this->api);
        if (!empty($this->cid))
            update_user_blog_meta($this->ID, "_cid", trim($this->cid));
        if (!empty($this->sid))
            update_user_blog_meta($this->ID, "_sid", trim($this->sid));
        if (!empty($this->coaches)) {
            if(!is_array($this->coaches))
                $this->coaches = explode(',', trim($this->coaches));
            $coaches = array_unique(array_filter($this->coaches));
            if(!empty($coaches))
             update_user_blog_meta($this->ID, "_coaches", $coaches);
        }
        if (!empty($this->token))
            update_user_blog_meta($this->ID, "_token", $this->token);
        if (!empty($this->avatar))
            update_user_blog_meta($this->ID, '_teamos_profile_avatar', $this->avatar);
        if (!empty($this->title))
            update_user_blog_meta($this->ID, '_teamos_profile_title', $this->title);
        if (empty($this->join_date))
            $this->join_date = date('Y-m-d', time());

        update_user_blog_meta($this->ID, "_join_date", Format::sql_date($this->join_date));

        if(!empty($this->client_admin))
            update_user_blog_meta($this->ID, '_teamos_admin_user', $this->client_admin);

        if(!empty($this->billing_user))
            update_user_blog_meta($this->ID, '_teamos_billing_user', $this->billing_user);

        // TODO: save disabled attribute
    }

    /**
     * Get important user meta values on a per blog basis
     */
    protected function get_user_meta()
    {
        $this->api = get_user_blog_meta($this->ID, "_api");
        $this->cid = get_user_blog_meta($this->ID, "_cid");
        $this->sid = get_user_blog_meta($this->ID, "_sid");
        $this->coaches = get_user_blog_meta($this->ID, "_coaches");     // cid based
        $this->token = get_user_blog_meta($this->ID, "_token");
        $this->avatar = get_user_blog_meta($this->ID, '_teamos_profile_avatar');
        $this->title = get_user_blog_meta($this->ID, '_teamos_profile_title');
        $this->client_admin = get_user_blog_meta($this->ID, '_teamos_admin_user');
        $this->billing_user = get_user_blog_meta($this->ID, '_teamos_billing_user');
        $this->join_date = get_user_blog_meta($this->ID, '_join_date');

        // this is also a per blog value
        $this->disabled = teamos_user_is_disabled($this->ID);
    }

    /**
     * Add user to blog if they don't already belong
     *
     * @param string $role
     */
    protected function add_user_to_blog($role = 'subscriber')
    {
        global $blog_id;

        if (!$this->user_belongs_to_blog())
            add_user_to_blog($blog_id, $this->ID, $role);
    }

    /**
     * Check if user belongs to blog
     */
    protected function user_belongs_to_blog()
    {
        global $blog_id;

        $user_in_blog = false;

        if (empty($this->ID))
            return $user_in_blog;

        $user_blogs = get_blogs_of_user($this->ID);

        foreach ($user_blogs as $user_blog) {
            if ($user_blog->userblog_id == $blog_id) {
                $user_in_blog = true;
                break;
            }
        }

        return $user_in_blog;
    }

    /**
     * User has been registered.
     * Send login information.
     *
     * @param array $args
     */
    protected function new_user_email($args)
    {
        if ($this->api)
            return;

        $site = get_bloginfo('name');
        $url = get_bloginfo('url');

        $login_info = "Your username and password are...<br><br>
                        Username: <b>" . $args['user_login'] . "</b><br>
                        Password: <b>" . $args['user_pass'] . "</b><br><br>
                        To start your training just click the link below<br>
                        $url/login/<br><br>
                        To change your password:<br>
                        1) login using the new password provided above<br>
                        2) then go to About Me -> Change Password')";

        $email_template_id = teamos_company_get_meta('new_user_email');

        if (empty($email_template_id)) {

            $subject = 'Your username and password for ' . $site;

            $message = "Welcome to the $site<br><br>
                        $login_info<br><br>
                        <img width='220' border='0' style='display:block' src='" . teamos_company_email_footer_image_url() . "'>";

            $email = new Email();
            $email->send(
                array(
                    'to' => $args['user_email'],
                    'subject' => $subject,
                    'message' => $message
                )

            );

        } else {
            $email = new Email($email_template_id);
            $email->send(
                array(
                    'to' => $args['user_email'],
                ),
                array(
                    '{LOGIN_INFO}' => "<br>$login_info<br><br>"
                )
            );

        }
    }

    public function resend_new_user_email()
    {
        // if no password provided we must create a new one
        if (empty($this->password) && empty($this->password_confirm)) {
            $this->password = $this->password_confirm = $this->generate_strong_password();
        }

        // save the new password
        $this->save();

        // send the new user email
        $args = array(
            'user_login' => $this->username,
            'user_pass' => $this->password,
            'display_name' => $this->fullname,
            'user_email' => $this->email
        );
        $this->new_user_email($args);
    }

    /**
     * User is already registered
     * Send username and login / lost password links
     *
     */
    protected function send_existing_user_email()
    {
        if ($this->api)
            return;

        $site = get_bloginfo('name');
        $url = get_bloginfo('url');

        $login_info = "You already have an account on one of our sites. Your username is...<br><br>
                        Username: <b>" . $this->username . "</b><br><br>
                        To start your training just click the link below<br>
                        $url/login/<br><br>
                        To lookup your password go to:<br>
                        $url/lostpassword/<br><br>";

        $subject = 'Your username for ' . $site;

        $message = "Welcome to the $site<br><br>
                    $login_info<br><br>
                    <img width='220' border='0' style='display:block' src='" . teamos_company_email_footer_image_url() . "'>";

        $email = new Email();
        $email->send(
            array(
                'to' => $this->email,
                'subject' => $subject,
                'message' => $message
            )
        );
    }

    /**
     * Sanity check userdata
     *
     * @return Ambigous <multitype:, mixed>
     */
    protected function validate_userdata($newuser = true)
    {
        // force username to lowercase
        $this->username = strtolower($this->username);

        // Check the base account details for problems
        $account_details = Profile::validate_user_signup($this->username, $this->email, $newuser);

        // secondary validations
        if (!empty($this->username) && preg_match("/\s/", $this->username)) {
            $account_details['errors']->add('signup_username', __('<strong>ERROR</strong>: Usernames may not contain spaces', 'teamos'));
        }

        if (!empty($this->email) && preg_match("/\s/", $this->email)) {
            $account_details['errors']->add('signup_email', __('<strong>ERROR</strong>: Emails may not contain spaces', 'teamos'));
        }

        if ($newuser) {
            if (empty($this->password) || empty($this->password_confirm)) {
                $account_details['errors']->add('signup_password', __('<strong>ERROR</strong>: Please make sure you enter your password twice', 'teamos'));
            }
        }

        if ((!empty($this->password) && !empty($this->password_confirm)) && $this->password != $this->password_confirm) {
            $account_details['errors']->add('signup_password', __('<strong>ERROR</strong>: The passwords do not match', 'teamos'));
        }

        if (empty($this->fullname)) {
            $account_details['errors']->add('field1', __('<strong>ERROR</strong>: Please enter your full name', 'teamos'));
        }

        // $account_details['errors']->add('invitation_code', __('<strong>ERROR</strong>: An invitation code is required', 'teamos'));

        return $account_details;
    }

    /**
     * Lookup user by email
     *
     * @param string $email
     * @return userdata
     */
    protected function find_user_by_email($email)
    {
        $user = get_user_by('email', $email);

        return $user;
    }

    /**
     * Create a username on the fly
     *
     * @param string $firstname
     * @param string $lastname
     * @return string boolean
     */
    protected function generate_unique_username($firstname, $lastname)
    {
        $firstname = strtolower(preg_replace("/[^a-z]+/i", "", $firstname));
        $lastname = strtolower(preg_replace("/[^a-z]+/i", "", $lastname));

        // default is first letter of firstname + lastname
        $username = substr($firstname, 0, 1) . $lastname;

        // must be at least 4 characters long
        $len = strlen($username);
        if ($len < 4) {
            for ($len; $len < 4; $len++)
                $username .= '1';
        }

        // good enough?
        if (!username_exists($username) && validate_username($username))
            return $username;

        // nope
        for ($i = 1; $i <= 100; $i++) {
            $tmp = $username . $i;

            // good enough?
            if (!username_exists($tmp) && validate_username($tmp))
                return $tmp;
        }

        return false;
    }

    /**
     * Generate a strong, user-friendy password
     *
     * @param int $length
     * @param boolean $add_dashes
     * @param string $available_sets
     *
     * @return string
     */
    protected function generate_strong_password($length = 6, $add_dashes = false, $available_sets = 'ld')
    {
        // Generates a strong password of N length containing at least one lower case letter,
        // one uppercase letter, one digit, and one special character. The remaining characters
        // in the password are chosen at random from those four sets.
        //
        // The available characters in each set are user friendly - there are no ambiguous
        // characters such as i, l, 1, o, 0, etc. This, coupled with the $add_dashes option,
        // makes it much easier for users to manually type or speak their passwords.
        //
        // Note: the $add_dashes option will increase the length of the password by
        // floor(sqrt(N)) characters.
        $sets = array();

        if (strpos($available_sets, 'l') !== false) {
            $sets[] = 'abcdefghjkmnpqrstuvwxyz';
        }
        if (strpos($available_sets, 'u') !== false) {
            $sets[] = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        }
        if (strpos($available_sets, 'd') !== false) {
            $sets[] = '23456789';
        }
        if (strpos($available_sets, 's') !== false) {
            $sets[] = '!@#$%&*?';
        }

        $all = '';
        $password = '';
        foreach ($sets as $set) {
            $password .= $set[array_rand(str_split($set))];
            $all .= $set;
        }

        $all = str_split($all);
        for ($i = 0; $i < $length - count($sets); $i++) {
            $password .= $all[array_rand($all)];
        }

        $password = str_shuffle($password);

        if (!$add_dashes) {
            return $password;
        }

        $dash_len = floor(sqrt($length));
        $dash_str = '';
        while (strlen($password) > $dash_len) {
            $dash_str .= substr($password, 0, $dash_len) . '-';
            $password = substr($password, $dash_len);
        }
        $dash_str .= $password;

        return $dash_str;
    }
}

// kill Notice Of Password Change emails

add_filter( 'send_password_change_email', '__return_false');