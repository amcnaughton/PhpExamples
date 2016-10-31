<?php

/**
 * Class UserTest
 *
 * This code relies on the WP_UnitTestCase object, which is more complex to setup than PHPUnit
 *
 * Follow these instructions to setup a testable WP environment:
 *
 * http://webdevstudios.com/2014/08/07/unit-testing-your-plugins/
 * http://codesymphony.co/writing-wordpress-plugin-unit-tests/
 *
 */
class UserTest extends WP_UnitTestCase
{

    /**
     * Test user lookup (and user creation implictly)
     *
     * @dataProvider dataProvider
     */
    public function testUsers($data)
    {
        $this->_createUsersHelper($data);

        // lookup by emeil
        $user = new User($data['user_email']);

        // also test standard WP user fields
        $this->assertNotEmpty($user->registered);
        $this->assertNotEmpty($user->email);
        $this->assertNotEmpty($user->username);

        // lookup by userid
        $user = new User($user->ID);
        $this->assertNotEmpty($user->ID);

        // lookup by username
        $user = new User($user->username);
        $this->assertNotEmpty($user->ID);

        // lookup by cid
        $user = new User($data['user_cid'], ['use_cid' => true]);
        $this->assertNotEmpty($user->ID);

        // lookup by cid and fail
        $user = new User($data['user_cid']);
        $this->assertEmpty($user->ID);

        // lookup by emeil
        $user = new User($data['user_email']);
        $this->assertNotEmpty($user->ID);
        $ID = $user->ID;

        // modify email
        $args['user_email'] = 'random@email.com';
        $user = new User($ID, $args);
        $this->assertNotEmpty($user->ID);
        $user->save();
        $user = new User($ID);
        $this->assertEquals($user->email, $args['user_email']);

        // modify cid
        $args['user_cid'] = '9999';
        $user = new User($ID, $args);
        $this->assertNotEmpty($user->ID);
        $user->save();
        $user = new User($ID);
        $this->assertEquals($user->cid, $args['user_cid']);

        // modify sid
        $args['user_sid'] = '3333';
        $user = new User($ID, $args);
        $this->assertNotEmpty($user->ID);
        $user->save();
        $user = new User($ID);
        $this->assertEquals($user->sid, $args['user_sid']);

        // modify title
        $args['user_title'] = 'some title';
        $user = new User($ID, $args);
        $this->assertNotEmpty($user->ID);
        $user->save();
        $user = new User($ID);
        $this->assertEquals($user->title, $args['user_title']);

        // modify join_date
        $args['user_join_date'] = '2010-02-03';
        $user = new User($ID, $args);
        $this->assertNotEmpty($user->ID);
        $user->save();
        $user = new User($ID);
        $this->assertEquals($user->join_date, $args['user_join_date']);

        // modify avatar
        $args['user_avatar'] = 'some avatar';
        $user = new User($ID, $args);
        $this->assertNotEmpty($user->ID);
        $user->save();
        $user = new User($ID);
        $this->assertEquals($user->avatar, $args['user_avatar']);

        // modify client admin
        $user = new User($ID);
        $this->assertNotEmpty($user->ID);
        $user->client_admin = true;
        $user->save();
        $user = new User($ID);
        $this->assertEquals($user->client_admin, true);

        // modify fullname
        $args['user_fullname'] = 'some guy';
        $user = new User($ID, $args);
        $this->assertNotEmpty($user->ID);
        $user->save();
        $user = new User($ID);
        $this->assertEquals($user->fullname, $args['user_fullname']);
        $this->assertEquals($user->firstname, 'some');
        $this->assertEquals($user->lastname, 'guy');
    }


    /**
     * Support method to to implictly test user creation
     *
     * @param $data
     * @return integer
     */
    public function _createUsersHelper($data)
    {
        $user = new User(0, $data);
        $user->generate_token();
        $wp_error = $user->save();

        if (!empty($wp_error))
            pr($wp_error);

        $this->assertEmpty($wp_error);

        // now read back user we just saved
        $newuser = new User($user->ID);

//        pr($data); pr($newuser);

        $this->assertNotEmpty($newuser->username);

        $this->assertEquals($newuser->email, $data['user_email']);
        if (!empty($data['user_firstname']))
            $this->assertEquals($newuser->firstname, $data['user_firstname']);
        if (!empty($data['user_lastname']))
            $this->assertEquals($newuser->lastname, $data['user_lastname']);
        if (!empty($data['user_sid']))
            $this->assertEquals($newuser->sid, $data['user_sid']);
        if (!empty($data['user_cid']))
            $this->assertEquals($newuser->cid, $data['user_cid']);
        if (!empty($data['user_avatar']))
            $this->assertEquals($newuser->avatar, $data['user_avatar']);
        if (!empty($data['user_title']))
            $this->assertEquals($newuser->title, $data['user_title']);
        if (!empty($data['user_join_date']))
            $this->assertEquals($newuser->join_date, Format::sql_date($data['user_join_date']));
        else
            $this->assertEquals($newuser->join_date, date('Y-m-d', time()));

        if (!empty($data['user_coaches'])) {
            if(!is_array($data['user_coaches']))
                $data['user_coaches'] = explode(',', $data['user_coaches']);
            $this->assertEquals($newuser->coaches, $data['user_coaches']);
        }

        $this->assertEquals($newuser->token, $user->token);

        // lookup by token
        $newuser = new User($user->token, ['use_token' => true]);
        $this->assertNotEmpty($newuser->ID);

        // lookup by token and fail
        $newuser = new User($user->token);
        $this->assertEmpty($newuser->ID);
    }


    /**
     * Test these values
     *
     * @return array
     */
    public function dataProvider()
    {
        return array(
            array(array('user_email' => 'bill1@test.com', 'user_join_date' => '2012-12-12', 'user_firstname' => 'bill1', 'user_lastname' => 'james1', 'user_cid' => 10, 'user_sid' => 11, 'user_avatar' => 'avatar 1', 'user_title' => 'title 1', 'user_coaches' => [101,102,103])),
            array(array('user_email' => 'bill2@test.com', 'user_join_date' => '2013-12-12', 'user_fullname' => 'bill2 james2', 'user_cid' => 20, 'user_sid' => 22, 'user_avatar' => 'avatar 2', 'user_title' => 'title 2', 'user_coaches' => [], 'user_join_date' => '10/9/2014')),
            array(array('user_email' => 'bill3@test.com', 'user_join_date' => '2014-12-12', 'user_firstname' => 'bill3', 'user_lastname' => 'james3',  'user_cid' => 30, 'user_sid' => 33, 'user_avatar' => 'avatar 3', 'user_title' => 'title 3', 'user_coaches' => 101, 'user_join_date' => '2015-12-29')),
            array(array('user_email' => 'bill4@test.com', 'user_join_date' => '2015-12-12', 'user_firstname' => 'bill4', 'user_lastname' => 'james4', 'user_cid' => 40, 'user_sid' => 44, 'user_avatar' => 'avatar 4', 'user_title' => 'title 4', 'user_coaches' => '101, 102, 103')),
        );
    }


}

