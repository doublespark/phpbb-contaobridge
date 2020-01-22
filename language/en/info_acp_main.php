<?php

if (!defined('IN_PHPBB'))
{
    exit;
}

if (empty($lang) || !is_array($lang))
{
    $lang = array();
}

$lang = array_merge($lang, array(
    'CONTAOBRIDGE'                   => 'Contao Bridge',
    'CONTAOBRIDGE_SETTINGS'          => 'Settings',
    'ACP_CONTAOBRIDGE_CONTAO_URL'    => 'Contao site URL',
    'ACP_CONTAOBRIDGE_SECRET_KEY'    => 'Secret Key',
    'ACP_CONTAOBRIDGE_SETTING_SAVED' => 'Settings have been saved successfully!'
));