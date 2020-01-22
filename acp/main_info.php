<?php

namespace doublespark\contaobridge\acp;

class main_info
{
    public function module()
    {
        return array(
            'filename'  => '\doublespark\contaobridge\acp\main_module',
            'title'     => 'CONTAOBRIDGE',
            'modes'    => array(
                'settings'  => array(
                    'title' => 'CONTAOBRIDGE_SETTINGS',
                    'auth'  => 'ext_doublespark/contaobridge && acl_a_board',
                    'cat'   => array('CONTAOBRIDGE'),
                ),
            ),
        );
    }
}