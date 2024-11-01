<?php
/*
Plugin Name: uemotion.com: Free voice comment system
Plugin URI: http://uemotion.com/developer/example
Description: Uemotion free voice comment system is a plugin integrated with Wordpress standard comment system. All voice comments are recorded, coded and stored on uemotion.com. This version is only presentation. We will develop it better if we have more active users if this plugin.
Author: uemotion <wordpress@uemotion.com>
Version: 0.1
Author URI: http://uemotion.com/
License: GPLv2
*/

/*.
    require_module 'standard';
    require_module 'pcre';
    require_module 'mysql';
.*/
if(!defined('WPUE_DEBUG')) {
	define('WPUE_DEBUG', false);
}
define('WPUE_DOMAIN', 'uemotion.com');
define('WPUE_PROTOCOL', 'http');
define('WPUE_PROTOCOL_SECURITY', 'https');
define('WPUE_API_PUBLIC', WPUE_PROTOCOL.'://'.WPUE_DOMAIN);
define('WPUE_API_PUBLIC_SECURITY', WPUE_PROTOCOL_SECURITY.'://'.WPUE_DOMAIN);
define('WPUE_API', WPUE_API_PUBLIC.'/usite/');
define('WPUE_API_SECURITY', WPUE_API_PUBLIC_SECURITY.'/usite/');
define('WPUE_API_CREATE', WPUE_API_PUBLIC.'/usite/creategroup');
define('WPUE_API_USITE', WPUE_API_PUBLIC.'/usite/webwidget');
define('WPUE_API_LISTKEYS', WPUE_API_PUBLIC.'/developer/sites');
define('WPUE_API_GETKEYS', WPUE_API_PUBLIC.'/developer/getkeys');
define('WPUE_VERSION', '0.01');
define('WPUE_DATEFORMAT', 'Y-m-d H:i:s');
define('WPUE_BEGINDATE', '2000-01-01 00:00:00');
define('WPUE_API_STATUS_SUCCESS', 100);


include(dirname(__FILE__).'/lib/WpueAPI.php');
if(is_admin()) {
	include(dirname(__FILE__).'/lib/WordpressAPI.php');
}

/**
 * Prepares plugin to work
 **/
function wpue_install() {
	if(wpue_get_options())
		return (wpue_create_post_table());
	else
		return (wpue_options_default_set() && wpue_create_post_table());
}

/**
 * Creates table prefix_wpue_posts if not exists
 **/
function wpue_create_post_table() {
	global $wpdb;
	$tname = $wpdb->prefix . 'wpue_posts';
	$posts = $wpdb->get_results('show tables like "'.$tname.'"');
	if(count($posts) == 0) {
		$sql = 'CREATE TABLE '.$tname.' (
			id bigint(20),
			groupId bigint(20),
			UNIQUE KEY id (id)
		)';
		$wpdb->query($sql);
		wpue_create_groups_from_posts();
	}
}

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
		'wpue_full_secure' => '0',
		'wpue_add_jq' => '1',
		'wpue_verify' => '',
		'wpue_css' => '',
		'wpue_custom_width' => '',
		'wpue_xdmlink' => 'http://consumer.easyxdm.net/current/',
		'wpue_cr_group_on_post' => '1',
	);
}

/**
 * Sets initial values of options form wpue_options_default
 **/
function wpue_options_default_set() {
	$a = get_option('wpue_settings');
	if(!is_array($a)) {
		$a = array();
	}
	foreach(wpue_options_default() as $k => $v) {
		if(!isset($a[$k])) {
			$a[$k] = $v;
		}
	}
	add_option('wpue_settings', $a);
}

/**
 * Returns options
 * @return array
 */
function wpue_get_options() {
	$li = wpue_options_default();
	$tmp = get_option('wpue_settings');
	if(!$tmp) {
		return false;
	}
	foreach($tmp as $key => $val) {
		$li[$key] = $val;
	}
	return $li;
}

/**
 * Returns url of template used for comments
 * @return string
 */
function wpue_comments_template($value) {
	global $post;
	if(!(is_singular() && (have_comments() || 'open' == $post->comment_status))) {
		return false;
	}

	if (is_feed() || 'draft' == $post->post_status) {
		return false;	
	}

	if (!wpue_is_installed()) {
		return $value;
	}

	return dirname(__FILE__) . '/comments.php';
}

/**
 * Tests if required options are configured to display the uemotion voice comment system.
 * @return bool
 */
function wpue_is_installed() {
	$options = wpue_get_options();
    return $options['wpue_url'] && $options['wpue_publickey'] && $options['wpue_active'];
}

/**
 * Adds admin menu to wordpress
 */
function wpue_admin_menu() {
	$s = wpue_is_installed();
	if($s) {
		// Create top-level menu item
		$main = add_menu_page(wpue_i('Voice comment system').' | uemotion.com', wpue_i('Voice comments'), 'manage_options', 'wpue-main-menu', 'wpue_admin_main', plugins_url('wpue.png', __FILE__));
	} else {
		$main = add_menu_page(wpue_i('Install').' | uemotion.com', wpue_i('Install voice comments'), 'manage_options', 'wpue-main-menu', 'wpue_admin_install', plugins_url('wpue.png', __FILE__));
	}
	$sub = add_submenu_page('wpue-main-menu', wpue_i('Main config').' | uemotion.com', wpue_i('Main config'), 'manage_options', 'wpue-main-config', 'wpue_admin_main_config');
	$sub2 = add_submenu_page('wpue-main-menu', wpue_i('Advanced config').' | uemotion.com', wpue_i('Advanced config'), 'manage_options', 'wpue-advanced-config', 'wpue_admin_advanced_config');
	//$sub2 = add_submenu_page('wpue-main-menu', wpue_i('Manage groups').' | uemotion.com', wpue_i('Manage groups'), 'manage_options', 'wpue-group-admin', 'wpue_group_admin');
	$sub3 = add_submenu_page('wpue-main-menu', wpue_i('Export').' | uemotion.com', wpue_i('Export'), 'manage_options', 'wpue-export', 'wpue_export');
	add_action( 'load-' . $main, 'wpue_help_main' );
	if($sub) {
		add_action( 'load-' . $sub, 'wpue_help_main' );
	}
}

/**
 * Admin page used to export content from wordpress to uemotion
 */
function wpue_export() {
	if(isset($_GET['msg']) && $_GET['msg'] == 'success') {
		?><div id='message' class='updated fade'><p><?php echo wpue_i('Sended'); ?></p></div>
	<?php } ?>
	<div id="wpue-general" class="wrap">
		<h2><?php echo wpue_i('Export').' | uemotion.com'; ?></h2>

		<form method="post" action="admin-post.php">
			<input type="hidden" name="action" value="admin_wpue_export" />
			<!-- Adding security through hidden referrer field -->
			<?php wp_nonce_field('wpue'); ?>
			<table class='form-table'>
				<tr>
					<th><?php echo wpue_i('Export posts'); ?></th>
					<td>
						<input type="hidden" value="0" name="posts" />
						<input name="posts" value="1" type="checkbox" checked />
						<p class="description">
							<?php echo wpue_i('This will create and connect resources from uemotion.com to posts on this blog. Only not connected posts will be parsed.'); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><?php echo wpue_i('Export comments'); ?></th>
					<td>
						<input type="hidden" value="0" name="comments" />
						<input name="comments" value="1" type="checkbox" checked />
						<p class="description">
							<?php echo wpue_i('This will export worprss comments to uemotion.com. Only not existing comments will be parsed.'); ?>
						</p>
					</td>
				</tr>
			</table>
			<input type="submit" value="Save" class="button-primary" />
		</form>
	</div>
<?php 
}

/**
 * Display list of resources created on uemotion.com
 */
function wpue_group_list() {
	global $api;
	?>
	<div id="wpue-general" class="wrap">
	<h2><?php echo wpue_i('List of your resources'); ?><a href="<?php echo WPUE_API_PUBLIC . '/group/add' ?>" target="_blank" class="add-new-h2"><?php echo wpue_i('Add new') ?></a></h2>
	<?php
	$opt = wpue_get_options();
	if(isset($_GET['paged'])) {
		$page = (int)$_GET['paged'];
	} else {
		$page = 0;
	}
	if(isset($_GET['url'])) {
		$url = urldecode($_GET['url']);
	} else {
		$url = '';
	}
	$params = array(
		'private_key' => $opt['wpue_privatekey'],
		'url' => $url,
		'page' => $page,
	);
	$list = wpue_query_api('/usite/widgetlistgroup', $params);
	if($list['status'] != WPUE_API_STATUS_SUCCESS) {
		?><div id='message' class='updated fade'><p><?php echo wpue_i('Server responded with status') . ' ' . $list['status'] . '. Fail.'; ?></p></div><?php
	} else {
		echo('<table class="wp-list-table widefat fixed pages" cellspacing=0>');
		?>
		<thead>
			<th class="manage-column column-title"></th>
			<th class="manage-column column-title"><?php echo wpue_i('Name'); ?></th>
			<th class="manage-column column-title"><?php echo wpue_i('Shortname'); ?></th>
			<th class="manage-column column-title"><?php echo wpue_i('Registration Date'); ?></th>
			<th class="manage-column column-title"><?php echo wpue_i('# Records'); ?></th>
			<th class="manage-column column-title"><?php echo wpue_i('Url'); ?></th>
			<th class="manage-column column-title"><?php echo wpue_i('Language'); ?></th>
			<th class="manage-column column-title"></th>
		</thead>
		<tbody>
		<?php
		foreach($list['list'] as $val) {
			?>
			<tr>
				<td><img src="<?php echo WPUE_API_PUBLIC; ?>/storage/groups/<?php echo $val['shortname']; ?>/size/50"></td>
				<td><?php echo $val['name']; ?></td>
				<td><?php echo $val['shortname']; ?></td>
				<td><?php echo $val['registrationDate']; ?></td>
				<td><?php echo $val['count']; ?></td>
				<td><a href="<?php echo $val['url']; ?>" target="_blank"><?php echo $val['url']; ?></a></td>
				<td><?php if(isset($val['nativeLanguageName'])) { echo $val['nativeLanguageName'] . '(' . $val['nativeLanguage'] . ')'; } ?></td>
				<td>
					<a href="<?php echo WPUE_API_PUBLIC . '/group/show/'. $val['shortname']; ?>" target="_blank"><?php echo wpue_i('Open'); ?></a> | 
					<a href="<?php echo add_query_arg(array('page' => 'wpue-main-menu', 'gid' => $val['id']), admin_url('admin.php')) ?>"><?php echo wpue_i('Admin'); ?></a>
				</td>
			</tr>
			<?php
		}
		?>
		</tbody>
		</table>
		<div class="description"><?php echo wpue_i('All times are GMT') ?></div>
		<div class='tablenav-pages'>
			<span class='pagination-links'>
				<a class='first-page <?php if($page == 0) echo ' disabled'; ?>' href='./admin.php?page=wpue-main-menu'>&laquo;</a>
				<a class='prev-page <?php if($page == 0) echo ' disabled'; ?>' href='./admin.php?page=wpue-main-menu&paged=<?php echo $page-1; ?>'>&lsaquo;</a>
				<span class="paging-input"><input class='current-page' type='text' name='paged' value='<?php echo $page+1; ?>' size='2' /></span>
				<a class='next-page' title='Przejdź do następnej strony' href='./admin.php?page=wpue-main-menu&paged=<?php echo $page+1; ?>'>&rsaquo;</a>
			</span></div>
			<br class="clear" />
		</div></div> <?php
	}
}

/**
 * Used in wpue admin menu
 */
function wpue_admin_main() {
	global $wpdb;
	$opt = wpue_get_options();
	$tname = $wpdb->prefix . 'wpue_posts';
	if(isset($_GET['post'])) {
		$q = $wpdb->get_results($wpdb->prepare("
			SELECT *
			FROM $tname
			WHERE id = %d
		", $_GET['post']));
		if(isset($q[0])) {
			$group = $q[0];
			$gid = $group->groupId;
		} else {
			wp_redirect(add_query_arg(array('page' => 'wpue-group-admin', 'msg' => wpue_i('Group not found')), admin_url('admin.php')));
			exit();
		}
	} else if(isset($_GET['gid'])) {
		$gid = (int) $_GET['gid'];
	} else {
		//lista postów i przypisanych grup
		wpue_group_list();
	}
	if(isset($gid)) {
		$e = urlencode('/group/admin/'. $gid . '/?public_key='. $opt['wpue_publickey'] . '&extadmin=true');
		wpue_iframe($e);
	}
}

/**
 * Automatically gets keys from uemotion.com and prepares plugin to work
 */
function wpue_admin_install() {
	global $api;
	?>
	<div id="wpue-general" class="wrap"> 
	<h2><?php echo wpue_i('Install voice comment system'); ?></h2>
	<?php
	if(isset($_GET['domainkey']) && isset($_GET['domain'])) {
		wpue_install();
		$options = wpue_get_options();
		$options['wpue_verify'] = sanitize_text_field($_GET['domainkey']);
		$options['wpue_url'] = sanitize_text_field($_GET['domain']);
		$options['wpue_active'] = 1;
		update_option('wpue_settings', $options);
		?>
		<?php echo wpue_progressbar(2, 1); ?>
		<p><?php echo wpue_i('In this step, uemotion.com will verify that you are owner of this domain and create keys.'); ?>
		<form action="<?php echo WPUE_API_PUBLIC; ?>/developer/getkeys?returnurl=<?php echo(urlencode(add_query_arg(array('page' => 'wpue-main-menu'), admin_url('admin.php')))); ?>" method="post">
			<input style='width:100%;height:50px' type="submit" class="button-primary" value="<?php echo wpue_i('Verify identity and continue installation'); ?>">
		</form>
		<?php
	} else if(isset($_GET['status'])) {
		(int) $status = $_GET['status'];
		if($status == 0) {
			echo wpue_i("Error getting keys");
		} else {
			$opt = wpue_get_options();
			?>
			<?php echo wpue_progressbar(3, 1); ?>
			<div id="wpueContainer"></div>
			<script>
			$(function() {
				var transport = new easyXDM.Socket({
					remote: "<?php echo $opt['wpue_full_secure'] == '1' ? WPUE_PROTOCOL_SECURITY : WPUE_PROTOCOL ?>://<?php echo WPUE_DOMAIN . '/usite/keys?site='.$status; ?>",
					swf: "<?php echo $opt['wpue_xdmlink'] ?>/easyxdm.swf",
					container: "wpueContainer",
					onMessage: function(message, origin){
						msg = $.parseJSON(message);
						if(msg.error == false) {
							$('#wpue_public_key').val(msg.publickey);
							$('#wpue_private_key').val(msg.privatekey);
							$('#wpueSend').prop('disabled', false).delay(10000).click();
						}
					}
				});
			});
			</script>
			<form action="<?php echo((add_query_arg(array('page' => 'wpue-main-menu'), admin_url('admin.php')))); ?>" method="post">
				Public:<br><input type="text" name="public_key" id="wpue_public_key"><br />
				Private:<br><input type="text" name="private_key" id="wpue_private_key"><br />
				<input id="wpueSend" disabled type="submit" class="button-primary" value="<?php echo wpue_i('Continue installation'); ?>">
			</form>
			<?php
		}
	} else if(isset($_POST['public_key']) && isset($_POST['private_key'])) {
		$options = wpue_get_options();
		$options['wpue_publickey'] = sanitize_text_field($_POST['public_key']);
		$options['wpue_privatekey'] = sanitize_text_field($_POST['private_key']);
		$options['wpue_verify'] = '';
		update_option('wpue_settings', $options);
		?>
		<?php echo wpue_progressbar(4, 1); ?>
		<p>
			<?php echo wpue_i('Installation has been completed. Would you like to export wordpress comments and other resources to uemotion.com? Export is <strong>highly</strong> recommended'); ?>
		</p>
		<div style='width:100%;text-align:center'>
			<a class="button action" href="<?php echo((add_query_arg(array('page' => 'wpue-export'), admin_url('admin.php')))); ?>"><?php echo wpue_i('Export.'); ?></a>
			<a class="button action" href="<?php echo((add_query_arg(array('page' => 'wpue-main-menu'), admin_url('admin.php')))); ?>"><?php echo wpue_i('Start using voice comment system.'); ?></a>
		</div>
		<?php
	} else {
		?>
		<?php echo wpue_progressbar(1, 1); ?>
		<p>
			<?php echo wpue_i('Plugin will automatically create needed keys on uemotion.com and transfer them to your wordpress installation.'); ?>
		</p>
		<p>
			<?php echo wpue_i('You must be logged in on uemotion.com to continue installation. This mechanism will not work on localhost.'); ?>
		</p>
		<?php /*<form action="<?php echo WPUE_API_PUBLIC_SECURITY; ?>/developer/getkeys" method="post">*/ ?>
		<form action="<?php echo WPUE_API_PUBLIC; ?>/developer/getkeys?returnurl=<?php echo(urlencode(add_query_arg(array('page' => 'wpue-main-menu'), admin_url('admin.php')))); ?>" method="post">
			<table class='form-table'>
				<tr>
					<th style='width:40%'><?php echo wpue_i('Address of your blog'); ?></th>
					<td>
						<input style='width:50%;height: 50px' type="text" name="domain" value="<?php echo wpue_gethostname(); ?>">
						<p class="description">
							<?php echo wpue_i('Note, that verification can fail if you change this field. Uemotion.com will check if you are owner of domain by checking if generated token is available in head section of your page.'); ?>
						</p>
					</td>
				</tr>
				<tr>
					<td colspan=2>
						<input style='width:100%;height:50px' type="submit" class="button-primary" value="<?php echo wpue_i('Begin installation'); ?>">
					</td>
				</tr>
			</table>
		</form>
		<?php
	}
	?> </div> <?php
}

function wpue_progressbar($step = 1, $type = 1) {
	$types = array(
		1 => array(
			1 => wpue_i('Preparing'),
			2 => wpue_i('Getting keys'),
			3 => wpue_i('Saving changes'),
			4 => wpue_i('Export wp content'),
		),
	);
	$arr = $types[$type];
	$return = '<script>$(window).load(function(){$("ol.progtrckr").each(function(){$(this).attr("data-progtrckr-steps",$(this).children("li").length);});})</script>';
	$return .= '<ol class="progtrckr">';
	for($i = 1; $i <= count($arr); $i++) {
		$return .= '<li class="progtrckr-';
		if($i == $step) {
			$return .= 'curr';
		} else if($i > $step) {
			$return .= 'todo';
		} else {
			$return .= 'done';
		}
		$return .= '">'.$arr[$i].'</li>';
	}
	$return .= '</ol>';
	return $return;
}

/**
 * Returns suggested wpue_url
 */
function wpue_gethostname() {
	if(isset($_SERVER['HTTP_HOST'])) {
		return $_SERVER['HTTP_HOST'];
	}
	return '';
}

/**
 * Creates autoresized iframe (using easyxdm)
 * @param string $url
 */
function wpue_iframe($url) {
	global $wpdb;
	$opt = wpue_get_options();
	?>
	<div id="wpueContainer"></div>
	<script>
	$(function() {
		var transport = new easyXDM.Socket({
			remote: "<?php echo $opt['wpue_full_secure'] == '1' ? WPUE_PROTOCOL_SECURITY : WPUE_PROTOCOL ?>://<?php echo WPUE_DOMAIN . '/usite/proxy?url='.$url; ?>",
			swf: "<?php echo $opt['wpue_xdmlink'] ?>/easyxdm.swf",
			container: "wpueContainer",
			onMessage: function(message, origin){
			    iframe = $('#wpueContainer iframe').css('height', message).css('width', '100%');
			}
		});
	});
	</script>
	<?php
}

/**
 * Prints help instructions of this plugin
 */
function wpue_plugin_help_instructions() {
	?>
	<ul>
		<li><?php echo wpue_i('Set permalinks option to other than default (suggested). <a href="./options-permalink.php">Click</a>'); ?></li>
		<li><?php echo wpue_i('Get keys for for your domain. <a href="'.WPUE_API_GETKEYS.'" target="_blank">Click</a>. You can use "verify" field in this section for verification.'); ?></li>
		<li><?php echo wpue_i('Fill rest of settings in this section.'); ?></li>
		<li><?php echo wpue_i('Create or manage your sites <a href="admin.php?page=wpue-main-menu">Click</a>'); ?></li>
		<li><?php echo wpue_i('Share your opinions.'); ?></li>
	</ul>
	<?php
}

/**
 * Prints API error codes to user
 */
function wpue_plugin_help_codes() {
	?>
	ERR_OLD_API				= 252;<br />
	ERR_BAD_REQUEST			= 253;<br />
	ERR_NO_ACCESS				= 255;<br />
	ERR_FAILED				= 258;<br />
	ERR_GROUP_URL_TAKEN		= 262;<br />
	ERR_GROUP_NAME_TAKEN		= 263;<br />
	ERR_GROUP_SHORTNAME_TAKEN	= 264;<br />
	ERR_VALIDATION			= 265;<br />
	ERR_LIMIT					= 268;<br />
	ERR_BLOCKED				= 270;<br />
	ERR_INVALID_KEY			= 271;<br />
	ERR_BAD_IP				= 272;<br />
	ERR_INVALID_DOMAIN		= 273;
	<?php
}

/**
 * Prints faq contents
 */
function wpue_plugin_help_faq() {
	?>
	<p>
		<strong><?php echo wpue_i('How much it costs?'); ?></strong>
		<?php echo wpue_i('This service is free. There are few limits, but created for stability reasons (flood).'); ?>
	</p>
	<p>
		<strong><?php echo wpue_i('Can I customize player or recorder widget?'); ?></strong>
		<?php echo wpue_i('Sure, just create CSS rules. There will be more flexibility in future releases.'); ?>
	</p>
	<p>
		<strong><?php echo wpue_i('Why on bottom of my site I have text similar to "{"status":271}"?'); ?></strong>
		<?php echo wpue_i('Propably you typed wrong public key for this domain. See "codes" tab in this help.'); ?>
	</p>
	<p>
		<strong><?php echo wpue_i('Why on bottom of my site I have text "No group found, create it and get free Admin!"?'); ?></strong>
		<?php echo wpue_i('There is no resource on uemotion.com connected to your entry. You can create it.'); ?>
	</p>
	<?php
}

/**
 * Prepares window to work with help center
 */
function wpue_help_main() {
	$screen = get_current_screen();
	$screen->add_help_tab(array('id' => 'wpue-plugin-help-instructions',
		'title' => wpue_i('Instructions'),
		'callback' => 'wpue_plugin_help_instructions',
	));
	$screen->add_help_tab(array('id' => 'wpue-plugin-help-faq',
		'title' => wpue_i('FAQ'),
		'callback' => 'wpue_plugin_help_faq',
	));
	$screen->add_help_tab(array('id' => 'wpue-plugin-help-codes',
		'title' => wpue_i('Error codes'),
		'callback' => 'wpue_plugin_help_codes',
	));
	$screen->set_help_sidebar('<p>'.wpue_i('Use "Instructions" if you want to be guided through confiruration or "FAQ" if you have common problem.').'</p>');
}

/**
 * Prints configuration form
 */
function wpue_admin_main_config() {
	// Retrieve plugin configuration options from database
	$options = wpue_get_options();
	if(isset($_GET['msg']) && $_GET['msg'] == 'success') {
		?><div id='message' class='updated fade'><p><?php echo wpue_i('Settings Saved'); ?></p></div>
 <?php
	}
	?>
	<div id="wpue-general" class="wrap">
		<h2><?php echo wpue_i('Voice comment system').' | uemotion.com'; ?></h2>

		<form method="post" action="admin-post.php">
			<input type="hidden" name="action" value="admin_save_wpue_options" />
			<!-- Adding security through hidden referrer field -->
			<?php wp_nonce_field('wpue'); ?>
			<table class='form-table'>
				<tr>
					<th><?php echo wpue_i('Uemotion.com public key'); ?></th>
					<td>
						<input type="text" name="wpue_publickey" value="<?php echo esc_html($options['wpue_publickey']); ?>" class="regular-text" />
						<p class="description">
							<?php echo wpue_i('Voice comment system will work properly only if you type valid key for this domain.'); ?>&nbsp;
							<a target="_blank" href="<?php echo WPUE_API_LISTKEYS ?>"><?php echo wpue_i('List of your keys'); ?></a>&nbsp;
							<a target="_blank" href="<?php echo WPUE_API_GETKEYS ?>"><?php echo wpue_i('Get keys for this domain'); ?></a>
						</p>
					</td>
				</tr>
				<tr>
					<th><?php echo wpue_i('Uemotion.com private key'); ?></th>
					<td>
						<input type="text" name="wpue_privatekey" value="<?php echo esc_html($options['wpue_privatekey']); ?>" class="regular-text" />
						<p class="description">
							<?php echo wpue_i('It is optional field but it is suggested to fill it. Private key can create group on uemotion.com and automatically give ability to comment new posts with your voice.'); ?>&nbsp;
							<a target="_blank" href="<?php echo WPUE_API_LISTKEYS ?>"><?php echo wpue_i('List of your keys'); ?></a>&nbsp;
							<a target="_blank" href="<?php echo WPUE_API_GETKEYS ?>"><?php echo wpue_i('Get keys for this domain'); ?></a>
						</p>
					</td>
				</tr>
				<tr>
					<th><?php echo wpue_i('Turn on plugin'); ?></th>
					<td>
						<input type="hidden" value="0" name="wpue_active" />
						<input name="wpue_active" value="1" type="checkbox" <?php if($options['wpue_active'] == '1') echo 'checked '; ?>/>
					</td>
				</tr>
				<tr>
					<th><?php echo wpue_i('Verification'); ?></th>
					<td>
						<input type="text" name="wpue_verify" value="<?php echo esc_html($options['wpue_verify']) ?>" class="regular-text" />
						<p class="description">
							<?php echo wpue_i('It can be used to verify that you are owner of the domain.'); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><?php echo wpue_i('Allow only voice comments'); ?></th>
					<td>
						<input type="hidden" value="0" name="wpue_onlyvoice" />
						<input name="wpue_onlyvoice" value="1" type="checkbox" <?php if($options['wpue_onlyvoice'] == '1') echo 'checked '; ?>/>
						<p class="description">
							<?php echo wpue_i('Blocks functionality of standard text comments. Allows only uemotion voice comments.'); ?>
						</p>
					</td>
				</tr>
			</table>
			<input type="submit" value="Save" class="button-primary"/>
		</form>
	</div>
<?php }

/**
 * Prints advanced configuration form
 */
function wpue_admin_advanced_config() {
	// Retrieve plugin configuration options from database
	$options = wpue_get_options();
	if(isset($_GET['msg']) && $_GET['msg'] == 'success') {
		?><div id='message' class='updated fade'><p><?php echo wpue_i('Settings Saved'); ?></p></div>
 <?php
	}
	?>
	<div id="wpue-general" class="wrap">
		<h2><?php echo wpue_i('Voice comment system').' | uemotion.com'; ?></h2>

		<form method="post" action="admin-post.php">
			<input type="hidden" name="action" value="admin_save_wpue_options" />
			<!-- Adding security through hidden referrer field -->
			<?php wp_nonce_field('wpue'); ?>
			<table class='form-table'>
				<tr>
					<th><?php echo wpue_i('Use only secure protocols'); ?></th>
					<td>
						<input type="hidden" value="0" name="wpue_full_secure" />
						<input name="wpue_full_secure" value="1" type="checkbox" <?php if($options['wpue_full_secure'] == '1') echo 'checked '; ?>/>
						<p class="description">
							<?php echo wpue_i('This setting will make your API requests more secure, because this plugin will always use https. This can cause problems with plugin and more delays beetwen request.'); ?><br />
							<?php echo wpue_i('Plugin always uses crypted conenction on API requests with private_key.'); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><?php echo wpue_i('URL of easyXDM JS lib.'); ?></th>
					<td>
						<input type="text" name="wpue_xdmlink" value="<?php echo esc_html($options['wpue_xdmlink']); ?>" class="regular-text" />
						<p class="description">
							<?php echo wpue_i('Used to communicate beetwen iframe and your site.'); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><?php echo wpue_i('Address of your blog'); ?></th>
					<td>
						<input type="text" name="wpue_url" value="<?php echo esc_html($options['wpue_url']) ?>" class="regular-text code" />
						<p class="description">
							<?php echo wpue_i('It was set automatically. Do not change it if you are not sure.'); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><?php echo wpue_i('Custom CSS'); ?></th>
					<td>
						<textarea name="wpue_css" class="regular-text code" style='width:40%;height:100px;'><?php echo esc_html($options['wpue_css']); ?></textarea>
						<p class="description">
							<?php echo wpue_i('You can use it to customize player and recorder widgets. #uePoster - recorder. #ueWidget - player'); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><?php echo wpue_i('Require JQuery'); ?></th>
					<td>
						<input type="hidden" value="0" name="wpue_add_jq" />
						<input name="wpue_add_jq" value="1" type="checkbox" <?php if($options['wpue_add_jq'] == '1') echo 'checked '; ?>/>
					</td>
				</tr>
			</table>
			<input type="submit" value="Save" class="button-primary"/>
		</form>
	</div>
<?php }

/**
 * Makes translation
 * @param  string $text
 * @return string
 */
function wpue_i($text) {
	return $text;
}

/**
 * Returns part of url after domain name
 * @return string
 */
function wpue_afterdomain() {
	return $_SERVER['REQUEST_URI'];
}

/**
 * Returns current url (parsed)
 * @return string
 */
function wpue_current_url() {
	global $wpdb;
	$opt = wpue_get_options();
	$id = get_the_ID();
	$tname = $wpdb->prefix . 'wpue_posts';
	$is = $wpdb->get_results($wpdb->prepare("
		SELECT groupId
		FROM $tname
		WHERE id = %d
	", $id));
	foreach($is as $val) {
		return $val->groupId;
	}
	return 'http://' . $opt['wpue_url'] . '' . wpue_afterdomain();
}

/**
 * Inits administrator page
 */
function wpue_admin_init() {
	//throw new Exception(print_r($_POST, true));
	//add_action('admin_save_wpue_options', 'wpue_process_options');
	if(isset($_POST['action']) && $_POST['action'] == 'admin_save_wpue_options') {
		wpue_process_options();
	}
	if(isset($_POST['action']) && $_POST['action'] == 'admin_wpue_export') {
		wpue_process_export();
	}
}

/**
 * Process updating options array
 */
function wpue_process_options() {
	// Check that user has proper security level
	if(!current_user_can('manage_options'))
		wp_die('Not allowed');
	// Check nonce field
	check_admin_referer('wpue');

	$options = wpue_get_options();
	// Cycle through all text form fields and store their values
	// in the options array
	foreach (wpue_options_default() as $name => $default) {
		if(isset($_POST[$name])) {
			$options[$name] = sanitize_text_field($_POST[$name]);
		}
	}
	update_option('wpue_settings', $options);
	// Redirect the page to the configuration form that was
	// processed
	wp_redirect(add_query_arg(array('page' => 'wpue-main-config', 'msg' => 'success'), admin_url('admin.php')));
	exit;
}

/**
 * Process export
 */
function wpue_process_export() {
	global $wpapi;
	if(!current_user_can('manage_options'))
		wp_die('Not allowed');
	// Check nonce field
	check_admin_referer('wpue');

	if(isset($_POST['comments'], $_POST['posts'])) {
		if($_POST['posts'] == '1') {
			wpue_i('Exporting posts<br>');
			$wpapi->exportPosts(true);
		}
		if($_POST['comments'] == '1') {
			wpue_i('Exporting comments<br>');
			$wpapi->exportComments(true);
		}
		//wp_redirect(add_query_arg(array('page' => 'wpue-export', 'msg' => 'success'), admin_url('admin.php')));
	} else {
		wp_redirect(add_query_arg(array('page' => 'wpue-export', 'msg' => 'fail'), admin_url('admin.php')));
	}

	$options = wpue_get_options();
	// Cycle through all text form fields and store their values
	// in the options array
	foreach (wpue_options_default() as $name => $default) {
		if(isset($_POST[$name])) {
			$options[$name] = sanitize_text_field($_POST[$name]);
		}
	}
	update_option('wpue_settings', $options);
	// Redirect the page to the configuration form that was
	// processed
	exit;
}

/**
 * Prints javascript and css needed to prepare comments for uemotion script and loads it
 */
function wpue_head() {
	$options = wpue_get_options();
	?>
	<?php if($options['wpue_verify'] != '') { ?><meta name="uekey" content="<?php echo esc_html($options['wpue_verify']) ?>" /><?php } ?>
	<?php if($options['wpue_add_jq'] == '1') { ?><script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script><?php } ?>
	<style type="text/css">
		<?php 
			$options = wpue_get_options();
			echo $options['wpue_css'];
		?>
	</style>
	<script src="<?php echo $options['wpue_xdmlink']; ?>/easyXDM.min.js" language="javascript"></script>
	<?php
}

function wpue_check_page() {
	if($_SERVER['REQUEST_URI'] == '/wpue' || isset($_GET['wpue'])) {
		echo "";
		die();
	}
}

/**
 * Queries public API
 * @param  string $url  url to query
 * @param  array $params
 * @return string json object
 **/
function wpue_query_api($url, $params) {
	$str = array();
	foreach($params as $k => $v) {
		$str[] = $k . '='.urlencode($v);
	}
	$str = implode('&', $str);
	if(strlen($str) > 0) {
		$str = '?'.$str;
	}
	$return = file_get_contents(WPUE_API_PUBLIC_SECURITY . $url . $str);
	if(WPUE_DEBUG) { 
		echo('URL: ' . WPUE_API_PUBLIC_SECURITY . $url . $str . '<br />Return:'. $return);
	}
	return json_decode($return, true);
}

/**
 * Creates or list groups on uemotion from posts on wordpress
 * @param  int $limit
 * @param  int $page
 * @param  bool $create false only gets groupId from uemotion and inserts it into database
 * @return bool
 **/
function wpue_create_groups_from_posts($limit = 0, $page = 0, $create = false) {
	global $wpdb, $api, $opt;
	$limit = (int) $limit;
	$offset = (int) $page * $limit;
	$posts = $wpdb->get_results($wpdb->prepare("
		SELECT p.ID AS pID, post_date_gmt, post_title, post_name, post_type, guid
		FROM $wpdb->posts AS p
		LEFT OUTER JOIN $wpdb->users AS u ON u.ID = p.post_author
		WHERE p.post_status = %s
		".(($offset > 0 || $limit > 0) ? ("LIMIT ".($offset > 0 ? $offset.',' : '')." ".($limit > 0 ? $limit : 0)."
	") : ''), 'publish'));
	$tname = $wpdb->prefix . 'wpue_posts';
	foreach($posts as $val) {
		$is = $wpdb->get_results($wpdb->prepare("
			SELECT id
			FROM $tname
			WHERE id = %d
		", $val->pID));
		if(count($is) == 0) {
			$url = $val->guid;
			$nd = 'http://' . $opt['wpue_url'];
			$parsed = parse_url($url);
			$nurl = $nd . $parsed['path'] . '?' . $parsed['query'];
			$groupId = $api->getGroupId($nurl);
			if($groupId) {
				$wpdb->insert($tname, array('id' => $val->pID, 'groupId' => $groupId));
			} else if($create) {
				$groupId = $api->createGroup($val->post_name, $val->post_title, $nurl);
				$wpdb->insert($tname, array('id' => $val->pID, 'groupId' => $groupId));
			}
		}
	}
	return true;
}

/**
 * Imports worpress comment
 * @param  int $limit
 * @param  int $page
 * @return bool
 **/
function wpue_create_comments($limit = 0, $page = 0) {
	global $wpdb, $api, $opt;
	$limit = (int) $limit;
	$offset = (int) $page * $limit;
	$tname = $wpdb->prefix . 'wpue_posts';
	$comments = $wpdb->get_results($wpdb->prepare("
		SELECT comment_ID, comment_post_ID, comment_author, comment_author_email, comment_author_IP, comment_date_gmt, comment_content, comment_agent, w.groupId as groupId
		FROM $wpdb->comments
		LEFT OUTER JOIN $tname AS w ON comment_post_ID = w.id
		WHERE comment_approved = %d
		".(($offset > 0 || $limit > 0) ? ("LIMIT ".($offset > 0 ? $offset.',' : '')." ".($limit > 0 ? $limit : 0)."
	") : ''), 1));
	$list = array();
	foreach($comments as $val) {
		if($val->groupId) {
			$list[] = array(
				'comment' => $val->comment_ID,
				'groupId' => $val->groupId,
				'author' => $val->comment_author,
				'author_email' => $val->comment_author_email,
				'author_ip' => $val->comment_author_IP,
				'date' => $val->comment_date_gmt,
				'content' => $val->comment_content,
				'agent' => $val->agent,
			);
		}
	}
	if(count($list))
		return $api->createComments($list);
	else
		return true;
}

/**
 * Blocks inserting standard comments
 * @param  int $comment_post_ID
 * @return int
 **/
function wpue_pre_comment_on_post($comment_post_ID) {
    if (wpue_is_installed()) {
        wp_die(wpue_i('Sorry, the built-in commenting system is disabled because Uemotion voice comment system is active.'));
    }
    return $comment_post_ID;
}

/**
 * Creates group when new post is added
 * @param  int $post_ID
 * @param  object $post
 **/
function wpue_insert_post($post_ID, $post) {
	global $api, $opt, $wpdb;
	if($opt['wpue_cr_group_on_post'] == '1') {
		if($post->post_status == 'publish') {
			$tname = $wpdb->prefix . 'wpue_posts';
			$is = $wpdb->get_results($wpdb->prepare("
				SELECT id
				FROM $tname
				WHERE id = %d
			", $post_ID));
			if(count($is) == 0) {
				$url = $post->guid;
				$nd = 'http://' . $opt['wpue_url'];
				$parsed = parse_url($url);
				$nurl = $nd . $parsed['path'] . '?' . $parsed['query'];
				$groupId = $api->createGroup($post->post_name, $post->post_title, $nurl);
				$wpdb->insert($tname, array('id' => $post_ID, 'groupId' => $groupId));
			}
		}
	}
}

/**
 * Enqueue plugin style-file
 */
function wpue_css() {
    // Respects SSL, Style.css is relative to the current file
    wp_register_style('wpue-style', plugins_url('style.css', __FILE__));
    wp_enqueue_style('wpue-style');
}


$opt = wpue_get_options();
$api = new WpueAPI($opt['wpue_publickey'], $opt['wpue_privatekey'], WPUE_API, WPUE_API_SECURITY);
if(class_exists('WordpressAPI'))
	$wpapi = new WordpressAPI($api);
/* Hooks */
add_action('wp_insert_post', 'wpue_insert_post');
add_action('admin_menu', 'wpue_admin_menu');
add_action('wp_head', 'wpue_head');
add_action('admin_head', 'wpue_head');
add_action('admin_init', 'wpue_admin_init');
add_action('admin_init', 'wpue_css');
add_action('pre_comment_on_post', 'wpue_pre_comment_on_post');
add_filter('comments_template', 'wpue_comments_template', 1000);
//add_filter('comments_number', 'wpue_comments_text');
//add_filter('get_comments_number', 'wpue_comments_number');
register_activation_hook(__FILE__, 'wpue_install');
