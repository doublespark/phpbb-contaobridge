<?php

namespace doublespark\contaobridge\event;

use phpbb\auth\auth;
use phpbb\db\driver\driver_interface;
use phpbb\request\request;
use phpbb\request\request_interface;
use phpbb\user;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class main_listener implements EventSubscriberInterface
{
    /**
     * @var request
     */
    protected $request;

    /**
     * @var driver_interface
     */
    protected $db;

    /**
     * @var user
     */
    protected $user;

    /**
     * @var auth
     */
    protected $auth;

    /**
     * main_listener constructor.
     *
     * @param request $request
     * @param user $user
     * @param driver_interface $db
     */
    public function __construct(request $request, user $user, auth $auth, driver_interface $db)
    {
        $this->request = $request;
        $this->user    = $user;
        $this->auth    = $auth;
        $this->db      = $db;
    }

    /**
     * Assign functions defined in this class to event listeners in the core
     *
     * @return array
     */
    static public function getSubscribedEvents()
    {
        return array(
            'core.user_setup' => 'authenticate_contao_user',
        );
    }

    /**
     * @param \phpbb\event\data $event The event object
     */
    public function authenticate_contao_user($event)
    {
        // User is already logged in to phpBB, check that
        // the Contao session has not expired
        if($this->user->data['user_id'] != ANONYMOUS)
        {
            $sql     = 'SELECT tl_phpbb_session.id, tl_phpbb_session.expires FROM tl_phpbb_session INNER JOIN tl_member ON member_id = tl_member.id WHERE tl_member.phpbb_user_id='.$this->db->sql_escape($this->user->data['user_id']);
            $result  = $this->db->sql_query($sql);

            if($result->num_rows > 0)
            {
                $session = $this->db->sql_fetchrow($result);

                if($session['expires'] < time())
                {
                    $sql = 'DELETE FROM tl_phpbb_session WHERE id='.$this->db->sql_escape($session['id']);
                    $this->db->sql_query($sql);
                }
            }

            return;
        }

        $contaoUserId = $this->request->raw_variable('phpbridgeuid',0, request_interface::COOKIE);

        // If this is greater than 0 we potentially have a logged-in Contao member
        if($contaoUserId > 0)
        {
            $sql = 'SELECT tl_phpbb_session.last_active, tl_phpbb_session.ip_address, tl_member.phpbb_user_id FROM tl_phpbb_session INNER JOIN tl_member ON member_id = tl_member.id WHERE member_id='.$this->db->sql_escape($contaoUserId);

            $result = $this->db->sql_query($sql);

            // We found an active session
            if($result->num_rows > 0)
            {
                // Verify that the session belongs to this user
                $session = $this->db->sql_fetchrow($result);

                $logUserIn = true;

                if($session['ip_address'] !== $this->request->server('REMOTE_ADDR'))
                {
                    $logUserIn = false;
                }

                // The user should have been active on Contao in the last hour
                if($session['last_active'] < (time() - 3600))
                {
                    $logUserIn = false;
                }

                // If all the checks were passed, log the user into phpBB
                if($logUserIn)
                {
                    $autologin  = false;
                    $viewonline = true;

                    // Determine if this user is an administrator
                    $arrUserData = $this->auth->obtain_user_data($session['phpbb_user_id']);
                    $this->auth->acl($arrUserData);
                    $isAdmin = $this->auth->acl_get('a_');

                    $result = $this->user->session_create($session['phpbb_user_id'], $isAdmin, $autologin, $viewonline);

                    if($result === true)
                    {
                        // Now the user is logged in, reload the page
                        header('Location: '.$this->request->server('REQUEST_URI'));
                    }
                }

            }
        }
    }
}