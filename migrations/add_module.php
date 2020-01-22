<?php

namespace doublespark\contaobridge\migrations;

class add_module extends \phpbb\db\migration\migration
{
    /**
     * If our config variable already exists in the db
     * skip this migration.
     */
    public function effectively_installed()
    {
        return isset($this->config['doublespark_contaobridge']);
    }

    public function update_data()
    {
        return array(

            // Add the config variable we want to be able to set
            array('config.add', array('doublespark_contaobridge_contao_url', '')),
            array('config.add', array('doublespark_contaobridge_secret_key', '')),

            // Add a parent module to the Extensions tab (ACP_CAT_DOT_MODS)
            array('module.add', array(
                'acp',
                'ACP_CAT_DOT_MODS',
                'CONTAOBRIDGE'
            )),

            // Add our main_module to the parent module
            array('module.add', array(
                'acp',
                'CONTAOBRIDGE',
                array(
                    'module_basename' => '\doublespark\contaobridge\acp\main_module',
                    'modes'           => array('settings'),
                ),
            )),
        );
    }
}
