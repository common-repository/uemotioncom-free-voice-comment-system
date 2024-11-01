<?php
// Check that code was called from WordPress with
// uninstallation constant declared
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
exit;
// Check if options exist and delete them if present
/**
 * Returns initial values of options
 * @return array
 **/
function wpue_options_default() {
	return array(
		'wpue_url' => $_SERVER['HTTP_HOST'],
		'wpue_publickey' => '',
		'wpue_privatekey' => '',
		'wpue_onlyvoice' => '0',
		'wpue_active' => '0',
		'wpue_add_jq' => '1',
		'wpue_add_jqui' => '1',
		'wpue_add_jqui_i18n' => '1',
	);
}

/**
 * Sets initial values of options form wpue_options_default
 **/
function wpue_options_default_set() {
	foreach(wpue_options_default() as $k => $v) {
		if(get_option($k) !== false) {
			delete_option($k);
		}
	}
}
?>
