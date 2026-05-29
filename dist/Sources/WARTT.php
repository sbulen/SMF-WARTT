<?php
/**
 *	Main logic for the WARTT mod for SMF..
 *
 *	Copyright 2026 Shawn Bulen
 *
 *	WARTT is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation, either version 3 of the License, or
 *	(at your option) any later version.
 *
 *	This software is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with this software.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

// If we are outside SMF throw an error.
if (!defined('SMF')) {
    die('Hacking attempt...');
}

/**
 * wartt_check_thresholds - called directly from index.php.
 *
 * Primary function called from index.php to check whether thresholds have been exceeded,
 * and to take appropriate action if so.
 *
 * Intention is to check stats up front & early, before session has been checked.
 *
 * Actions:
 * - 'log' = log only (note all state changes are logged - this is the default...)
 * - 'block' = issue a 429, too many requests, an optional percentage may be specified to let some thru & thin the herd
 * - 'nologct' = change SMF setting to stop online logging
 * - 'noviewct' = change SMF setting to stop view counts
 *
 * @return null
 *
 */
function wartt_check_thresholds()
{
	global $modSettings, $sourcedir, $txt, $cookiename;

	// If not enabled, don't even bother...
	if (empty($modSettings['wartt_enabled']))
		return;

	// Leave logged in users alone...
	if (!empty($_COOKIE[$cookiename]))
		return;

	require_once($sourcedir . '/WARTTModel.php');
	loadLanguage('WARTT');

	$rules = get_enabled_wartt_rules();
	$minute = strip_seconds(time());

	foreach ($rules AS $rule)
	{
		$bucket = wartt_bucket_value($rule);
		if ($bucket == false)
			continue;
		incr_wartt_counter($minute, $rule['id_rule'], $bucket, $rule['bucket_type']);
		$threshold = check_wartt_threshold($rule['id_rule'], $bucket, $rule['minutes']);

		// Detect changes in state - rules changing from active to inactive or vice-versa...
		$curr_state = check_wartt_block($rule['id_rule'], $bucket);
		if ((($curr_state == 0) && ($threshold >= $rule['threshold'])) || (($curr_state == 1) && ($threshold < $rule['threshold'])))
		{
			if ($curr_state == 0)
			{
				// Now blocking this...
				$curr_state = 1;
				add_wartt_block($rule['id_rule'], $bucket, $rule['bucket_type']);
				wartt_log_entry($rule['id_rule'], $rule['bucket_type'], $txt['wartt_activated'] . ' ' . $bucket);

			}
			else
			{
				// Now unblocking this...
				$curr_state = 0;
				remove_wartt_block($rule['id_rule'], $bucket);
				wartt_log_entry($rule['id_rule'], $rule['bucket_type'], $txt['wartt_inactivated'] . ' ' . $bucket);
			}
		}

		// Take specified actions...
		if ($curr_state == 1)
		{
			// If requested, temporarily override the SMF settings for the rest of this session
			if ($rule['action'] == 'nologct')
				$modSettings['no_guest_logging'] = 1;
			elseif ($rule['action'] == 'noviewct')
				$modSettings['no_guest_views'] = 1;
			// Block logic, applying percentage...
			elseif ($rule['action'] == 'block')
			{
				if (rand(0, 99) < $rule['action_pct'])
				{
					http_response_code(429);
					die($txt['wartt_429']);
				}
			}
		}
	}
}

/**
 * wartt_bucket_value - determine which bucket to use.  Helper function.
 *
 * Bucket Types:
 * - ip_mask = apply mask & return CIDR (basically an IP range)
 * - server_var = return server var value (helpful if ASN/country avail in server var)
 * - env_var = return env var value (helpful if ASN/country avail in env var)
 * - asn_lookup = if WALA present, use IP to lookup ASN
 * - co_lookup = if WALA present, use IP to lookup country
 *
 * @param array rule
 *
 * @return string | false - return false if not found
 *
 */
function wartt_bucket_value($rule)
{
	global $modSettings;

	// Give reasonable defaults for these...
	if (empty($modSettings['wartt_ipv4_masklen']))
		$modSettings['wartt_ipv4_masklen'] = 24;
	if (empty($modSettings['wartt_ipv6_masklen']))
		$modSettings['wartt_ipv6_masklen'] = 112;

	// return false if you can't find anything...
	$bucket = false;
	switch ($rule['bucket_type'])
	{
		// IP mask...
		case 'ip_mask':
			$ip = inet_pton($_SERVER['REMOTE_ADDR']);
			$masklen = strlen($ip) == 4 ? $modSettings['wartt_ipv4_masklen'] : $modSettings['wartt_ipv6_masklen'];
			$bits = strlen($ip) * 8;
			$masklen = min($masklen, $bits);
			$bin_mask = str_repeat('1', $masklen) . str_repeat('0', $bits - $masklen);
			$bin_chunks = str_split($bin_mask, 8);
			$mask = '';
			foreach ($bin_chunks as $chunk)
				$mask .= chr(bindec($chunk));
			$bucket_n = $ip & $mask;
			$bucket = inet_ntop($bucket_n) . '/' . $masklen;
			break;
		// Server var...
		case 'server_var':
			if (!empty($_SERVER[$rule['bucket_var']]))
			{
				$bucket = $_SERVER[$rule['bucket_var']];
			}
			break;
		// Env var...
		case 'env_var':
			if (!empty($_ENV[$rule['bucket_var']]))
			{
				$bucket = $_ENV[$rule['bucket_var']];
			}
			break;
		// ASN lookup...
		case 'asn_lookup':
			$bucket = get_dbip_asn($_SERVER['REMOTE_ADDR']);
			break;
		// COUNTRY lookup...
		case 'co_lookup':
			$bucket = get_dbip_country($_SERVER['REMOTE_ADDR']);
			break;
	}

	return $bucket;
}

/**
 * strip_seconds - remove seconds from unix timestamp for use as a time bucket.  Helper function.
 *
 * @param int
 *
 * @return int
 *
 */
function strip_seconds($time)
{
	return $time - ($time % 60);
}

/**
 * wartt_main - action.
 *
 * Primary action called from the admin menu for managing WARTT.
 * Sets subactions & list columns & figures out if which subaction to call.
 *
 * Action: admin
 * Area: wartt
 *
 * @return null
 *
 */
function wartt_main()
{
	global $txt, $context, $sourcedir;

	// You have to be an admin to do this.
	isAllowedTo('admin_forum');

	// Stuff we'll need around...
	require_once($sourcedir . '/WARTTModel.php');
	loadLanguage('WARTT');
	loadCSSFile('wartt.css', array('force_current' => false, 'validate' => true, 'minimize' => true, 'order_pos' => 9500));

	// Sub actions...
	$subActions = array(
		'wartt_blocks' => 'wartt_blocks',
		'wartt_counters' => 'wartt_counters',
		'wartt_log' => 'wartt_log',
		'wartt_rules' => 'wartt_rules',
		'wartt_add_rule' => 'wartt_add_rule',
		'wartt_mod_rule' => 'wartt_mod_rule',
		'wartt_settings' => 'wartt_settings',
	);

	// This uses admin tabs
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['wartt_title'],
		'description' => $txt['wartt_title_desc'],
	);

	// Pick the correct sub-action.
	if (isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]))
		$context['sub_action'] = $_REQUEST['sa'];
	else
		$context['sub_action'] = 'wartt_blocks';

	$_REQUEST['sa'] = $context['sub_action'];

	// Set the page title
	$context['page_title'] = $txt['wartt_title'];

	// Finally fall through to what we are doing.
	call_helper($subActions[$context['sub_action']]);
}

/**
 * wartt_blocks - Use SMF list proc to just show a list...
 *
 * Action: admin
 * Area: wartt
 * Subaction: wartt_blocks
 *
 * @return null
 *
 */
function wartt_blocks()
{
	global $txt, $context, $sourcedir, $scripturl, $modSettings;

	// You have to be an admin to do this.
	isAllowedTo('admin_forum');

	// Set up some basics....
	$context['url_start'] = '?action=admin;area=wartt;sa=wartt_blocks';
	$context['page_title'] = $txt['wartt_blocks'];

	// The number of entries to show per page.
	$context['displaypage'] = $modSettings['defaultMaxMembers'];

	// Handle truncate...
	if (!empty($_POST['trunc']))
	{
		checkSession();
		validateToken('wartt_block', 'post');
		clear_blocks();
	}

	// This is all the information required for the block list.
	require_once($sourcedir . '/Subs-List.php');
	$listOptions = array(
		'id' => 'wartt_block_list',
		'title' => $txt['wartt_blocks'],
		'width' => '100%',
		'items_per_page' => $context['displaypage'],
		'no_items_label' => $txt['wartt_no_entries_found'],
		'base_href' => $scripturl . $context['url_start'],
		'default_sort_col' => 'datetime_disp',

		'get_items' => array(
			'function' => 'get_block_info',
			'params' => array(),
		),
		'get_count' => array(
			'function' => 'get_block_count',
			'params' => array(),
		),

		'columns' => array(
			'id_rule' => array(
				'header' => array(
					'value' => $txt['wartt_id'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'id_rule',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'id_rule',
					'reverse' => 'id_rule DESC',
				),
			),
			'bucket_type' => array(
				'header' => array(
					'value' => $txt['wartt_type'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'bucket_type',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'bucket_type',
					'reverse' => 'bucket_type DESC',
				),
			),
			'ip_bucket' => array(
				'header' => array(
					'value' => $txt['wartt_ip_bucket'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'ip_bucket',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'ip_bucket',
					'reverse' => 'ip_bucket DESC',
				),
			),
			'datetime_disp' => array(
				'header' => array(
					'value' => $txt['wartt_datetime'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'datetime_disp',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'datetime DESC',
					'reverse' => 'datetime',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . $context['url_start'],
			'include_sort' => true,
			'include_start' => true,
			'hidden_fields' => array(
				$context['session_var'] => $context['session_id'],
			),
			'token' => 'wartt_block',
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '
					<input type="submit" name="trunc" value="' . $txt['wartt_clear_blocks'] . '" data-confirm="' . $txt['wartt_clear_blocks_confirm'] . '" class="button you_sure">',
				'class' => 'floatright',
			),
		),
	);

	createToken('wartt_block');

	// Create the block list.
	createList($listOptions);

	// The sub_template is defined in GenericList.template.php, which was invoked
	// When createList() was called above.
	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'wartt_block_list';
}

/**
 * wartt_log - Use SMF list proc to just show a list...
 *
 * Action: admin
 * Area: wartt
 * Subaction: wartt_log
 *
 * @return null
 *
 */
function wartt_log()
{
	global $txt, $context, $sourcedir, $scripturl, $modSettings;

	// You have to be an admin to do this.
	isAllowedTo('admin_forum');

	// Set up some basics....
	$context['url_start'] = '?action=admin;area=wartt;sa=wartt_log';
	$context['page_title'] = $txt['wartt_log'];

	// The number of entries to show per page.
	$context['displaypage'] = $modSettings['defaultMaxMembers'];

	// Handle truncate...
	if (!empty($_POST['trunc']))
	{
		checkSession();
		validateToken('wartt_log', 'post');
		clear_log();
	}

	// This is all the information required for the log.
	require_once($sourcedir . '/Subs-List.php');
	$listOptions = array(
		'id' => 'wartt_log_list',
		'title' => $txt['wartt_log'],
		'width' => '100%',
		'items_per_page' => $context['displaypage'],
		'no_items_label' => $txt['wartt_no_entries_found'],
		'base_href' => $scripturl . $context['url_start'],
		'default_sort_col' => 'id_entry',

		'get_items' => array(
			'function' => 'get_log_info',
			'params' => array(),
		),
		'get_count' => array(
			'function' => 'get_log_count',
			'params' => array(),
		),

		'columns' => array(
			'id_entry' => array(
				'header' => array(
					'value' => $txt['wartt_entry'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'id_entry',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'id_entry DESC',
					'reverse' => 'id_entry',
				),
			),
			'datetime_disp' => array(
				'header' => array(
					'value' => $txt['wartt_datetime'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'datetime_disp',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'datetime',
					'reverse' => 'datetime DESC',
				),
			),
			'id_rule' => array(
				'header' => array(
					'value' => $txt['wartt_id'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'id_rule',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'id_rule',
					'reverse' => 'id_rule DESC',
				),
			),
			'bucket_type' => array(
				'header' => array(
					'value' => $txt['wartt_type'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'bucket_type',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'bucket_type',
					'reverse' => 'bucket_type DESC',
				),
			),
			'wartt_info' => array(
				'header' => array(
					'value' => $txt['wartt_info'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'info',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'info',
					'reverse' => 'info DESC',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . $context['url_start'],
			'include_sort' => true,
			'include_start' => true,
			'hidden_fields' => array(
				$context['session_var'] => $context['session_id'],
			),
			'token' => 'wartt_log',
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '
					<input type="submit" name="trunc" value="' . $txt['wartt_clear_log'] . '" data-confirm="' . $txt['wartt_clear_log_confirm'] . '" class="button you_sure">',
				'class' => 'floatright',
			),
		),
	);

	createToken('wartt_log');

	// Create the log list.
	createList($listOptions);

	// The sub_template is defined in GenericList.template.php, which was invoked
	// When createList() was called above.
	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'wartt_log_list';
}

/**
 * wartt_counters - Use SMF list proc to just show a list...
 *
 * Action: admin
 * Area: wartt
 * Subaction: wartt_counters
 *
 * @return null
 *
 */
function wartt_counters()
{
	global $txt, $context, $sourcedir, $scripturl, $modSettings;

	// You have to be an admin to do this.
	isAllowedTo('admin_forum');

	// Set up some basics....
	$context['url_start'] = '?action=admin;area=wartt;sa=wartt_counters';
	$context['page_title'] = $txt['wartt_counters'];

	// The number of entries to show per page.
	$context['displaypage'] = $modSettings['defaultMaxMembers'];

	// Handle truncate...
	if (!empty($_POST['trunc']))
	{
		checkSession();
		validateToken('wartt_ctr', 'post');
		clear_counters();
	}

	// This is all the information required for the counter list.
	require_once($sourcedir . '/Subs-List.php');
	$listOptions = array(
		'id' => 'wartt_counters_list',
		'title' => $txt['wartt_counters'],
		'width' => '100%',
		'items_per_page' => $context['displaypage'],
		'no_items_label' => $txt['wartt_no_entries_found'],
		'base_href' => $scripturl . $context['url_start'],
		'default_sort_col' => 'time_bucket_disp',

		'get_items' => array(
			'function' => 'get_counters_info',
			'params' => array(),
		),
		'get_count' => array(
			'function' => 'get_counters_count',
			'params' => array(),
		),

		'columns' => array(
			'time_bucket_disp' => array(
				'header' => array(
					'value' => $txt['wartt_time_bucket'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'time_bucket_disp',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'time_bucket DESC',
					'reverse' => 'time_bucket',
				),
			),
			'id_rule' => array(
				'header' => array(
					'value' => $txt['wartt_id'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'id_rule',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'id_rule',
					'reverse' => 'id_rule DESC',
				),
			),
			'bucket_type' => array(
				'header' => array(
					'value' => $txt['wartt_type'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'bucket_type',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'bucket_type',
					'reverse' => 'bucket_type DESC',
				),
			),
			'ip_bucket' => array(
				'header' => array(
					'value' => $txt['wartt_ip_bucket'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'ip_bucket',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'ip_bucket',
					'reverse' => 'ip_bucket DESC',
				),
			),
			'requests' => array(
				'header' => array(
					'value' => $txt['wartt_requests'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'requests',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'requests',
					'reverse' => 'requests DESC',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . $context['url_start'],
			'include_sort' => true,
			'include_start' => true,
			'hidden_fields' => array(
				$context['session_var'] => $context['session_id'],
			),
			'token' => 'wartt_ctr',
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '
					<input type="submit" name="trunc" value="' . $txt['wartt_clear_counters'] . '" data-confirm="' . $txt['wartt_clear_counters_confirm'] . '" class="button you_sure">',
				'class' => 'floatright',
			),
		),
	);

	createToken('wartt_ctr');

	// Create the counter list.
	createList($listOptions);

	// The sub_template is defined in GenericList.template.php, which was invoked
	// When createList() was called above.
	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'wartt_counters_list';
}

/**
 * wartt_rules - Use SMF list proc to show a list...  Also handles deletes & modifications.
 *
 * Action: admin
 * Area: wartt
 * Subaction: wartt_rules
 *
 * @return null
 *
 */
function wartt_rules()
{
	global $txt, $context, $sourcedir, $scripturl, $modSettings;

	// You have to be an admin to do this.
	isAllowedTo('admin_forum');

	// Set up some basics....
	$context['url_start'] = '?action=admin;area=wartt;sa=wartt_rules';
	$context['page_title'] = $txt['wartt_rules'];

	// The number of entries to show per page.
	$context['displaypage'] = $modSettings['defaultMaxMembers'];

	// Handle deletion...
	if (!empty($_POST['remove']) && isset($_POST['selection']))
	{
		checkSession();
		validateToken('wartt_maint', 'post');
		wartt_rule_deletes(array_unique($_POST['selection']));
	}

	// Handle Enable/Disable...
	if (!empty($_POST['enable']) && isset($_POST['selection']))
	{
		checkSession();
		validateToken('wartt_maint', 'post');
		toggle_enabled_state(array_unique($_POST['selection']));
	}

	// This is all the information required for the rule list.
	require_once($sourcedir . '/Subs-List.php');
	$listOptions = array(
		'id' => 'wartt_rule_list',
		'title' => $txt['wartt_rules'],
		'width' => '100%',
		'items_per_page' => $context['displaypage'],
		'no_items_label' => $txt['wartt_no_entries_found'],
		'base_href' => $scripturl . $context['url_start'],
		'default_sort_col' => 'id_rule',

		'get_items' => array(
			'function' => 'get_rule_info',
			'params' => array(),
		),
		'get_count' => array(
			'function' => 'get_rule_count',
			'params' => array(),
		),

		'columns' => array(
			'id_rule' => array(
				'header' => array(
					'value' => $txt['wartt_id'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'id_rule',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'id_rule',
					'reverse' => 'id_rule DESC',
				),
			),
			'description' => array(
				'header' => array(
					'value' => $txt['wartt_desc'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'description',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'description',
					'reverse' => 'description DESC',
				),
			),
			'bucket_type' => array(
				'header' => array(
					'value' => $txt['wartt_type'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'bucket_type',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'bucket_type',
					'reverse' => 'bucket_type DESC',
				),
			),
			'bucket_var' => array(
				'header' => array(
					'value' => $txt['wartt_var'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'bucket_var',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'bucket_var',
					'reverse' => 'bucket_var DESC',
				),
			),
			'minutes' => array(
				'header' => array(
					'value' => $txt['wartt_minutes'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'minutes',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'minutes',
					'reverse' => 'minutes DESC',
				),
			),
			'threshold' => array(
				'header' => array(
					'value' => $txt['wartt_threshold'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'threshold',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'threshold',
					'reverse' => 'threshold DESC',
				),
			),
			'action' => array(
				'header' => array(
					'value' => $txt['wartt_action'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'action',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'action',
					'reverse' => 'action DESC',
				),
			),
			'action_pct' => array(
				'header' => array(
					'value' => $txt['wartt_actpct'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'action_pct',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'action_pct',
					'reverse' => 'action_pct DESC',
				),
			),
			'enabled' => array(
				'header' => array(
					'value' => $txt['wartt_rule_enabled'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'enabled',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'enabled',
					'reverse' => 'enabled DESC',
				),
			),
			'modify' => array(
				'header' => array(
					'value' => $txt['wartt_modify'],
					'class' => 'lefttext',
				),
				'data' => array(
					'function' => function($rowData) use ($scripturl, $txt)
					{
						$link_link = sprintf('<a href="%1$s?action=admin;area=wartt;sa=wartt_mod_rule;id_rule=%2$d" class="smalltext">%3$s</a>', $scripturl, $rowData['id_rule'], $txt['modify']);
						return $link_link;
					},
				),
			),
			'selection' => array(
				'header' => array(
					'value' => '<input type="checkbox" name="all" onclick="invertAll(this, this.form);">',
					'class' => 'centercol',
				),
				'data' => array(
					'function' => function($entry)
					{
						return '<input type="checkbox" name="selection[]" value="' . $entry['id_rule'] . '"' . '>';
					},
					'class' => 'centercol',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . $context['url_start'],
			'include_sort' => true,
			'include_start' => true,
			'hidden_fields' => array(
				$context['session_var'] => $context['session_id'],
			),
			'token' => 'wartt_maint',
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '
					<input type="submit" name="remove" value="' . $txt['wartt_delrule'] . '" data-confirm="' . $txt['wartt_delrule_confirm'] . '" class="button you_sure">
					<input type="submit" name="enable" value="' . $txt['wartt_enable'] . '" data-confirm="' . $txt['wartt_enable_confirm'] . '" class="button you_sure">',
				'class' => 'floatright',
			),
		),
	);

	createToken('wartt_maint');

	// Create the rule list.
	createList($listOptions);

	// The sub_template is defined in GenericList.template.php, which was invoked
	// When createList() was called above.
	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'wartt_rule_list';
}

/**
 * wartt_add_rule.
 *
 * Action: admin
 * Area: wartt
 * Subaction: wartt_add_rule
 *
 * @return null
 *
 */
function wartt_add_rule()
{
	global $context, $smcFunc;

	// You have to be an admin to do this.
	isAllowedTo('admin_forum');

	// Make sure the right person is putzing...
	if (!empty($_POST))
		checkSession('post');

	// Setup the template stuff we'll need.
	loadTemplate('WARTTMaint');

	// Is WALA populated?
	$context['wartt_wala_populated'] = get_wala_populated();

	// Are we adding/modifying one?
	if (!empty($_POST['add']))
	{
		validateToken('wartt_add', 'post');

		// In case you need to come back after errors....
		$_SESSION['wartt_rule_info']['description'] = !empty($_POST['description']) ? $smcFunc['htmlspecialchars']($_POST['description']) : '';
		$_SESSION['wartt_rule_info']['bucket_type'] = !empty($_POST['bucket_type']) ? $smcFunc['htmlspecialchars']($_POST['bucket_type']) : '';
		$_SESSION['wartt_rule_info']['bucket_var'] = !empty($_POST['bucket_var']) ? $smcFunc['htmlspecialchars']($_POST['bucket_var']) : '';
		$_SESSION['wartt_rule_info']['minutes'] = !empty($_POST['minutes']) ? $smcFunc['htmlspecialchars']($_POST['minutes']) : '';
		$_SESSION['wartt_rule_info']['threshold'] = !empty($_POST['threshold']) ? $smcFunc['htmlspecialchars']($_POST['threshold']) : '';
		$_SESSION['wartt_rule_info']['action'] = !empty($_POST['wartt_action']) ? $smcFunc['htmlspecialchars']($_POST['wartt_action']) : '';
		$_SESSION['wartt_rule_info']['action_pct'] = !empty($_POST['action_pct']) ? $smcFunc['htmlspecialchars']($_POST['action_pct']) : '';
		$_SESSION['wartt_rule_info']['enabled'] = (int) isset($_POST['enabled']);

		// Validate the description
		$context['wartt_rule_info']['description'] = $smcFunc['htmlspecialchars']($_POST['description']);
		$description = $smcFunc['htmlspecialchars']($_POST['description']);
		if (!is_string($description) || empty($description) || (strlen($description) > 20))
			fatal_lang_error('wartt_bad_desc', false);

		// Type validated in form
		$context['wartt_rule_info']['bucket_type'] = $_POST['bucket_type'];
		$bucket_type = $_POST['bucket_type'];

		// Validate the bucket_var
		$context['wartt_rule_info']['bucket_var'] = $smcFunc['htmlspecialchars']($_POST['bucket_var']);
		$bucket_var = $smcFunc['htmlspecialchars']($_POST['bucket_var']);
		if (!is_string($bucket_var) || (in_array($bucket_type, array('server_var', 'env_var')) && empty($bucket_var)) || (strlen($bucket_var) > 255))
			fatal_lang_error('wartt_bad_var', false);

		// Ensure bucket_var exists as a valid $_SERVER or $_ENV variable
		if ((($bucket_type == 'server_var') && (!isset($_SERVER[$bucket_var]))) || (($bucket_type == 'env_var') && (!isset($_ENV[$bucket_var]))))
			fatal_lang_error('wartt_bad_var', false);

		// Minutes validated in form
		$context['wartt_rule_info']['minutes'] = $smcFunc['htmlspecialchars']($_POST['minutes']);
		$minutes = (int) $_POST['minutes'];

		// Threshold validated in form
		$context['wartt_rule_info']['threshold'] = $smcFunc['htmlspecialchars']($_POST['threshold']);
		$threshold = (int) $_POST['threshold'];

		// Action validated in form
		$context['wartt_rule_info']['action'] = $_POST['wartt_action'];
		$action = $_POST['wartt_action'];

		// Action_pct validated in form
		$context['wartt_rule_info']['action_pct'] = $smcFunc['htmlspecialchars']($_POST['action_pct']);
		$action_pct = (int) $_POST['action_pct'];

		// Enabled validated in form
		$context['wartt_rule_info']['enabled'] = (int) isset($_POST['enabled']);
		$enabled = (int) isset($_POST['enabled']);

		// Passed the gauntlet...  Can add...
		add_wartt_rule($description, $bucket_type, $bucket_var, $minutes, $threshold, $action, $action_pct, $enabled);

		unset($_SESSION['wartt_rule_info']);

		// Show them what they've done, by going back to the browse form
		redirectexit('action=admin;area=wartt;sa=wartt_rules');
	}

	// In case we're resuming an edit...
	$context['wartt_rule_info']['description'] = !empty($_SESSION['wartt_rule_info']['description']) ? $_SESSION['wartt_rule_info']['description'] : '';
	$context['wartt_rule_info']['bucket_type'] = !empty($_SESSION['wartt_rule_info']['bucket_type']) ? $_SESSION['wartt_rule_info']['bucket_type'] : '';
	$context['wartt_rule_info']['bucket_var'] = !empty($_SESSION['wartt_rule_info']['bucket_var']) ? $_SESSION['wartt_rule_info']['bucket_var'] : '';
	$context['wartt_rule_info']['minutes'] = !empty($_SESSION['wartt_rule_info']['minutes']) ? $_SESSION['wartt_rule_info']['minutes'] : '';
	$context['wartt_rule_info']['threshold'] = !empty($_SESSION['wartt_rule_info']['threshold']) ? $_SESSION['wartt_rule_info']['threshold'] : '';
	$context['wartt_rule_info']['action'] = !empty($_SESSION['wartt_rule_info']['action']) ? $_SESSION['wartt_rule_info']['action'] : '';
	$context['wartt_rule_info']['action_pct'] = !empty($_SESSION['wartt_rule_info']['action_pct']) ? $_SESSION['wartt_rule_info']['action_pct'] : '';
	$context['wartt_rule_info']['enabled'] = !empty($_SESSION['wartt_rule_info']['enabled']) ? 1 : 0;

	unset($_SESSION['wartt_rule_info']);

	createToken('wartt_add');

	$context['sub_template'] = 'add_rule';
}

/**
 * wartt_mod_rule.
 *
 * Action: admin
 * Area: wartt
 * Subaction: wart_mod_rule
 *
 * @return null
 *
 */
function wartt_mod_rule()
{
	global $context, $smcFunc;

	// You have to be an admin to do this.
	isAllowedTo('admin_forum');

	// Make sure the right person is putzing...
	if (!empty($_POST))
		checkSession('post');

	// Setup the template stuff we'll need.
	loadTemplate('WARTTMaint');

	// Is WALA populated?
	$context['wartt_wala_populated'] = get_wala_populated();

	if (!empty($_POST['id_rule']))
		$id_rule = (int) $_POST['id_rule'];
	else
		$id_rule = (int) $_GET['id_rule'];

	$context['wartt_rule_info'] = get_rule($id_rule);

	// Are we adding/modifying one?
	if (!empty($_POST['mod']))
	{
		validateToken('wartt_mod', 'post');

		// In case you need to come back after errors....
		$_SESSION['wartt_rule_info']['description'] = !empty($_POST['description']) ? $smcFunc['htmlspecialchars']($_POST['description']) : '';
		$_SESSION['wartt_rule_info']['bucket_type'] = !empty($_POST['bucket_type']) ? $smcFunc['htmlspecialchars']($_POST['bucket_type']) : '';
		$_SESSION['wartt_rule_info']['bucket_var'] = !empty($_POST['bucket_var']) ? $smcFunc['htmlspecialchars']($_POST['bucket_var']) : '';
		$_SESSION['wartt_rule_info']['minutes'] = !empty($_POST['minutes']) ? $smcFunc['htmlspecialchars']($_POST['minutes']) : '';
		$_SESSION['wartt_rule_info']['threshold'] = !empty($_POST['threshold']) ? $smcFunc['htmlspecialchars']($_POST['threshold']) : '';
		$_SESSION['wartt_rule_info']['action'] = !empty($_POST['wartt_action']) ? $smcFunc['htmlspecialchars']($_POST['wartt_action']) : '';
		$_SESSION['wartt_rule_info']['action_pct'] = !empty($_POST['action_pct']) ? $smcFunc['htmlspecialchars']($_POST['action_pct']) : '';
		$_SESSION['wartt_rule_info']['enabled'] = (int) isset($_POST['enabled']);

		// Validate the description
		$context['wartt_rule_info']['description'] = $smcFunc['htmlspecialchars']($_POST['description']);
		$description = $smcFunc['htmlspecialchars']($_POST['description']);
		if (!is_string($description) || empty($description) || (strlen($description) > 20))
			fatal_lang_error('wartt_bad_desc', false);

		// Type validated in form
		$context['wartt_rule_info']['bucket_type'] = $_POST['bucket_type'];
		$bucket_type = $_POST['bucket_type'];

		// Validate the bucket_var
		$context['wartt_rule_info']['bucket_var'] = $smcFunc['htmlspecialchars']($_POST['bucket_var']);
		$bucket_var = $smcFunc['htmlspecialchars']($_POST['bucket_var']);
		if (!is_string($bucket_var) || (in_array($bucket_type, array('server_var', 'env_var')) && empty($bucket_var)) || (strlen($bucket_var) > 255))
			fatal_lang_error('wartt_bad_var', false);

		// Ensure bucket_var exists as a valid $_SERVER or $_ENV variable
		if ((($bucket_type == 'server_var') && (!isset($_SERVER[$bucket_var]))) || (($bucket_type == 'env_var') && (!isset($_ENV[$bucket_var]))))
			fatal_lang_error('wartt_bad_var', false);

		// Minutes validated in form
		$context['wartt_rule_info']['minutes'] = $smcFunc['htmlspecialchars']($_POST['minutes']);
		$minutes = (int) $_POST['minutes'];

		// Threshold validated in form
		$context['wartt_rule_info']['threshold'] = $smcFunc['htmlspecialchars']($_POST['threshold']);
		$threshold = (int) $_POST['threshold'];

		// Action validated in form
		$context['wartt_rule_info']['action'] = $_POST['wartt_action'];
		$action = $_POST['wartt_action'];

		// Action_pct validated in form
		$context['wartt_rule_info']['action_pct'] = $smcFunc['htmlspecialchars']($_POST['action_pct']);
		$action_pct = (int) $_POST['action_pct'];

		// Enabled validated in form
		$context['wartt_rule_info']['enabled'] = (int) isset($_POST['enabled']);
		$enabled = (int) isset($_POST['enabled']);

		// Passed the gauntlet...  Can modify...
		mod_wartt_rule($id_rule, $description, $bucket_type, $bucket_var, $minutes, $threshold, $action, $action_pct, $enabled);

		unset($_SESSION['wartt_rule_info']);

		// Show them what they've done, by going back to the browse form
		redirectexit('action=admin;area=wartt;sa=wartt_rules');
	}

	// In case we're resuming an edit...
	if (!empty($_SESSION['wartt_rule_info']['description']))
		$context['wartt_rule_info']['description'] = $_SESSION['wartt_rule_info']['description'];
	if (!empty($_SESSION['wartt_rule_info']['bucket_type']))
		$context['wartt_rule_info']['bucket_type'] = $_SESSION['wartt_rule_info']['bucket_type'];
	if (!empty($_SESSION['wartt_rule_info']['bucket_var']))
		$context['wartt_rule_info']['bucket_var'] = $_SESSION['wartt_rule_info']['bucket_var'];
	if (!empty($_SESSION['wartt_rule_info']['minutes']))
		$context['wartt_rule_info']['minutes'] = $_SESSION['wartt_rule_info']['minutes'];
	if (!empty($_SESSION['wartt_rule_info']['threshold']))
		$context['wartt_rule_info']['threshold'] = $_SESSION['wartt_rule_info']['threshold'];
	if (!empty($_SESSION['wartt_rule_info']['action']))
		$context['wartt_rule_info']['action'] = $_SESSION['wartt_rule_info']['action'];
	if (!empty($_SESSION['wartt_rule_info']['action_pct']))
		$context['wartt_rule_info']['action_pct'] = $_SESSION['wartt_rule_info']['action_pct'];
	if (!empty($_SESSION['wartt_rule_info']['enabled']))
		$context['wartt_rule_info']['enabled'] = $_SESSION['wartt_rule_info']['enabled'];

	unset($_SESSION['wartt_rule_info']);

	createToken('wartt_mod');

	$context['sub_template'] = 'mod_rule';
}

/**
 * wartt_settings - action.
 *
 * Primary action called from the admin menu for changing global settings for WARTT.
 *
 * Action: admin
 * Area: wartt
 * Subaction: wartt_settings
 *
 * @return null
 *
 */
function wartt_settings()
{
		global $context, $txt, $scripturl, $modSettings, $sourcedir;

		// Needed for the settings template.
		require_once($sourcedir . '/ManageServer.php');
		$context['sub_template'] = 'show_settings';

		// Setup some page settings
		$context['page_title'] = $txt['wartt_settings'];
		$context['post_url'] = $scripturl . '?action=admin;area=wartt;save;sa=wartt_settings';

		// Setup the values with defaults if none have been specified yet.
		if (!isset($modSettings['wartt_enabled']))
			$modSettings['wartt_enabled'] = 0;
		if (!isset($modSettings['wartt_counter_ret_mins']))
			$modSettings['wartt_counter_ret_mins'] = 120;
		if (!isset($modSettings['wartt_log_ret_months']))
			$modSettings['wartt_log_ret_months'] = 2;
		if (!isset($modSettings['wartt_ipv4_masklen']))
			$modSettings['wartt_ipv4_masklen'] = 24;
		if (!isset($modSettings['wartt_ipv6_masklen']))
			$modSettings['wartt_ipv6_masklen'] = 112;

		// Setup the data entry form
		$context['settings_title'] = $txt['wartt_settings'];
		$config_vars = array(
			array('check',
				'wartt_enabled',
			),
			array('int',
				'wartt_counter_ret_mins',
				'min' => 2,
				'max' => 480,
			),
			array('int',
				'wartt_log_ret_months',
				'min' => 1,
				'max' => 24,
			),
			array('int',
				'wartt_ipv4_masklen',
				'min' => 8,
				'max' => 32,
			),
			array('int',
				'wartt_ipv6_masklen',
				'min' => 32,
				'max' => 128,
			),
		);

		if (isset($_GET['save'])) {
			checkSession();
			saveDBSettings($config_vars);
			redirectexit('action=admin;area=wartt;sa=wartt_settings');
		}

		prepareDBSettingContext($config_vars);
}