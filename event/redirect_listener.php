<?php

namespace doublespark\contaobridge\event;

use phpbb\config\config;
use phpbb\request\request;
use phpbb\user;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class redirect_listener implements EventSubscriberInterface
{
    /**
     * @var request
     */
    protected $request;

    /**
     * @var user
     */
    protected $user;

    /**
     * @var config
     */
    protected $config;

    /**
     * main_listener constructor.
     *
     * @param request $request
     * @param user $user
     * @param config $config
     */
    public function __construct(request $request, user $user, config $config)
    {
        $this->request = $request;
        $this->user    = $user;
        $this->config  = $config;
    }

    /**
     * Assign functions defined in this class to event listeners in the core
     *
     * @return array
     */
    static public function getSubscribedEvents()
    {
        return array(
            'core.common' => 'handle_redirects',
        );
    }

    /**
     * @param \phpbb\event\data $event The event object
     */
    public function handle_redirects($event)
    {
        $mode = $this->request->variable('mode','');

        if(in_array($mode,['login','register','reg_details','logout']))
        {
            $contaoSiteUrl = $this->config['doublespark_contaobridge_contao_url'];

            if(empty($contaoSiteUrl))
            {
                trigger_error('Contao Bridge has not been configured. Please set the URL of the Contao site in the Contao Bridge extension settings in the phpBB ACP.');
            }

            if($mode === 'login')
            {
                header('Location: '.$contaoSiteUrl.'/phpbb/login');
                exit();
            }

            if($mode === 'logout')
            {
                header('Location: '.$contaoSiteUrl.'/phpbb/logout');
                exit();
            }

            if($mode === 'register')
            {
                header('Location: '.$contaoSiteUrl.'/phpbb/register');
                exit();
            }

            if($mode === 'reg_details')
            {
                header('Location: '.$contaoSiteUrl.'/phpbb/account');
                exit();
            }
        }
    }
}