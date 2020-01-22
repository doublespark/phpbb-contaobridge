<?php

namespace doublespark\contaobridge\acp;

class main_module
{
    public $u_action;
    public $tpl_name;
    public $page_title;

    public function main($id, $mode)
    {
        global $language, $template, $request, $config;

        $this->tpl_name   = 'acp_contaobridge_settings';
        $this->page_title = $language->lang('CONTAO_BRIDGE_TITLE');

        add_form_key('doublespark_contaobridge_settings');

        if($request->is_set_post('submit'))
        {
            if (!check_form_key('doublespark_contaobridge_settings'))
            {
                trigger_error('FORM_INVALID');
            }

            $config->set('doublespark_contaobridge_contao_url', $request->variable('doublespark_contaobridge_contao_url', ''));
            $config->set('doublespark_contaobridge_secret_key', $request->variable('doublespark_contaobridge_secret_key', ''));

            trigger_error($language->lang('ACP_CONTAOBRIDGE_SETTING_SAVED') . adm_back_link($this->u_action));
        }

        $template->assign_vars(array(
            'CONTAOBRIDGE_CONTAO_URL' => $config['doublespark_contaobridge_contao_url'],
            'CONTAOBRIDGE_SECRET_KEY' => $config['doublespark_contaobridge_secret_key'],
            'U_ACTION'                => $this->u_action,
        ));
    }
}