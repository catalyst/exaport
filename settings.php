<?php

defined('MOODLE_INTERNAL') || die;

require_once dirname(__FILE__).'/lib/lib.php';

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox('block_exaport_allow_loginas', get_string('settings_allow_loginas_head', 'block_exaport'),
                       get_string('settings_allow_loginas_body', 'block_exaport'), 0, 1, 0));

	//Zusammenspiel exabis ePortfolio - exabis Competences
    $settings->add(new admin_setting_configcheckbox('block_exaport_enable_interaction_competences', get_string('settings_interaktion_exacomp_head', 'block_exaport'),
                       get_string('settings_interaktion_exacomp_body', 'block_exaport'), 1, 1, 0));

	if (block_exaport_course_has_desp()) {
		$settings->add(new admin_setting_configcheckbox('block_exaport_create_desp_categories', get_string('settings_create_desp_categories_head', 'block_exaport'),
						   get_string('settings_create_desp_categories_body', 'block_exaport'), 0, 1, 0));
	}
}
