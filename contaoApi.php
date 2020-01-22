<?php

// Load includes from phpBB
define('IN_PHPBB', true);
$phpbb_root_path = '../../../';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
require_once($phpbb_root_path.'config.php');
require_once($phpbb_root_path.'common.php');
require_once($phpbb_root_path.'includes/functions_user.php');
require_once($phpbb_root_path.'phpbb/auth/auth.php');

/**
 * Handle requests from Contao CMS
 */
class ContaoApi {

    /**
     * phpBB Container class
     * @var
     */
    protected $phpbb_container;

    /**
     * phpBB request class
     * @var
     */
    protected $request;

    /**
     * @var \phpbb\auth\auth
     */
    protected $auth;

    /**
     * @var \phpbb\config\config
     */
    protected $config;

    public function __construct($phpbb_container, $request, $auth, $config)
    {
        $this->phpbb_container = $phpbb_container;
        $this->request         = $request;
        $this->auth            = $auth;
        $this->config          = $config;
    }

    public function run()
    {
        if(!$this->verifyRequest())
        {
            $arrResponse = [
                'status'  => 'failed',
                'message' => 'Unauthorized',
                'error'   =>  ['Failed request verification']
            ];

            $this->sendResponse($arrResponse,401);
        }

        $action = $this->request->variable('act','');

        if(!$action)
        {
            $arrResponse = [
                'status' => 'failed',
                'message' => 'Invalid request',
                'error'  =>  ['Invalid request']
            ];

            $this->sendResponse($arrResponse);
        }

        // Handle routing
        switch($action)
        {
            case 'createUser':
                $this->createUser();
                break;

            case 'deleteUser':
                $this->deleteUser();
                break;

            case 'changeUserGroup':
                $this->changeUserGroup();
                break;
        }

        $arrResponse = [
            'status'  => 'failed',
            'message' => 'Not found',
            'error'   =>  ['Endpoint was not found']
        ];

        $this->sendResponse($arrResponse,404);
    }

    /**
     * Create a user in phpBB
     * @throws Exception
     */
    protected function createUser()
    {
        $errors = $this->validate(['username','password','email','group_id']);

        if(count($errors) > 0)
        {
            $this->sendResponse([
                'status'  => 'failed',
                'message' => 'Invalid data supplied',
                'error'   => $errors
            ]);
        }

        $arrUserData = [
            'username'      => $this->request->variable('username',''),
            'user_password' => $this->request->variable('password',''), // already hashed by contao
            'user_email'    => $this->request->variable('email',''),
            'group_id'      => $this->request->variable('group_id',''),
            'user_type'     => USER_NORMAL
        ];

        // Create the user and get an ID
        $userID = user_add($arrUserData);

        // If user wasn't created, send failed response
        if(!$userID)
        {
            $this->sendResponse([
                'status' => 'failed',
                'message' => 'phpbb Could not create user',
                'error'  => ['Could not create phpBB user']
            ]);
        }

        // Send success response
        $this->sendResponse([
            'status'  => 'success',
            'message' => 'phpBB user created',
            'data' => [
                'user_id' => $userID
            ]
        ]);
    }

    /**
     * Delete user from phpBB
     */
    protected function deleteUser()
    {
        $userId = $this->request->variable('user_id',0);

        if($userId === 0)
        {
            $this->sendResponse([
                'status'  => 'failed',
                'message' => 'Invalid data supplied',
                'error'   => ['User ID was not supplied']
            ]);
        }

        // Determine if this user is an administrator
        $arrUserData = $this->auth->obtain_user_data($userId);
        $this->auth->acl($arrUserData);
        $isAdmin = $this->auth->acl_get('a_');

        if($isAdmin)
        {
            $this->sendResponse([
                'status'  => 'failed',
                'message' => 'User was not deleted because they are an admin',
                'error'   => ['phpBB admin accounts cannot be automatically deleted. Please delete via ACP.']
            ]);
        }

        user_delete('remove', $userId);

        // Send success response
        $this->sendResponse([
            'status'  => 'success',
            'message' => 'phpBB user was deleted',
            'data' => [
                'user_id' => $userId
            ]
        ]);
    }

    /**
     * Change a user's group
     */
    public function changeUserGroup()
    {
        $userId  = $this->request->variable('user_id',0);
        $groupId = $this->request->variable('group_id',9999);

        if($userId === 0 || $groupId === 9999)
        {
            $this->sendResponse([
                'status'  => 'failed',
                'message' => 'Invalid data supplied',
                'error'   => ['User ID was not supplied']
            ]);
        }

        /**
         * Group IDs
         * 5 - ADMIN
         * 4 - GLOBAL MOD
         * 2 - REGISTERED
         */
        $arrGroups = group_memberships(false, [$userId]);

        $arrCurrentGroupIds = [];

        // Get the user's current groups
        foreach($arrGroups as $group)
        {
            $arrCurrentGroupIds[$group['group_id']] = $group['group_id'];
        }

        // If the new group is 'registered user' we need to remove the user from
        // any admin or moderator groups they were previously in
        if($groupId < 4)
        {
            // User was an admin - remove them from the admin group
            if(in_array(5,$arrCurrentGroupIds))
            {
                group_user_del(5,[$userId]);
            }

            // User was a global mod - remove them from the global mod group
            if(in_array(4,$arrCurrentGroupIds))
            {
                group_user_del(4,[$userId]);
            }
        }

        // If the user is being made an admin, make sure they are a global mod too
        if($groupId == 5 AND !in_array(4,$arrCurrentGroupIds))
        {
            group_user_add(4, [$userId],false, false, false);
        }

        // User could already have the group they need if they are
        // being downgraded. Check if they have the group and
        // if not, add them. If they were, then make it the default
        if(!in_array($groupId,$arrCurrentGroupIds))
        {
            group_user_add($groupId, [$userId],false, false, true);
        }
        else
        {
            group_set_user_default($groupId,[$userId]);
        }

        // Send success response
        $this->sendResponse([
            'status'  => 'success',
            'message' => 'phpBB user\'s group was updated',
            'data' => [
                'user_id'  => $userId,
                'group_id' => $groupId
            ]
        ]);
    }

    /**
     * Validate input
     * @param $arrFields
     * @return array
     */
    protected function validate($arrFields)
    {
        $errors = [];

        foreach($arrFields as $field)
        {
            if(!$this->request->variable($field,''))
            {
                $errors[] = "Required field '$field' is missing.";
            }
        }

        return $errors;
    }

    /**
     * Generate a token, our Contao app knows how to generate a matching token
     */
    protected function createToken()
    {
        $currentServerIp = $this->request->server('SERVER_ADDR');

        $secretKey = $this->config['doublespark_contaobridge_secret_key'];

        return md5('phpbbbridge'.date('d/m/Y').$currentServerIp.$secretKey);
    }

    /**
     * Verifies that a request has come from the Contao site
     * @return bool
     */
    protected function verifyRequest()
    {
        if($this->request->header('User-Agent') != 'contao-phpbb/1.0')
        {
            return false;
        }

        $token = $this->createToken();

        if($this->request->header('X-Contao-Token') != $token)
        {
            return false;
        }

        return true;
    }

    /**
     * Send JSON response
     * @param $arrData
     * @param int $statusCode
     */
    protected function sendResponse($arrData, $statusCode=200)
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
        header('Content-type: application/json');
        http_response_code($statusCode);
        echo trim(json_encode($arrData));
        exit();
    }

    /**
     * Log to file
     * @param $type - INFO or ERROR
     * @param $message
     */
    protected function log($type,$message)
    {
        $date = date('d/m/Y H:i');
        $str = "[$date][$type] $message\n";
        file_put_contents('phpbb_contao_log.txt',$str);
    }

}

$contaoApi = new ContaoApi($phpbb_container, $request, $auth, $config);
$contaoApi->run();