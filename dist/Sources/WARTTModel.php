<?php
/**
 *	DB interaction for the WARTT mod for SMF..
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
 * get_enabled_wartt_actions - returns an array of actions.
 *
 * @return array
 *
 */
function get_enabled_wartt_rules()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT id_rule, bucket_type, bucket_var, minutes, threshold, action, action_pct, enabled
		FROM {db_prefix}wartt_rules
		WHERE enabled = 1',
		array(
		)
	);

	$rules = $smcFunc['db_fetch_all']($request);
	$smcFunc['db_free_result']($request);

	return $rules;
}

/**
 * incr_wartt_counter - simple, just add 1.
 *
 * @param int time_bucket
 * @param int id_rule
 * @param string ip_bucket, e.g., 'CN' (country) or '714' (asn)
 * @param string bucket_type
 *
 * @return void
 *
 */
function incr_wartt_counter($time_bucket, $id_rule, $ip_bucket, $bucket_type)
{
	global $smcFunc, $db_type;

	if ($db_type == 'postgresql')
		$sql = 'INSERT INTO {db_prefix}wartt_counters (time_bucket, id_rule, ip_bucket, bucket_type, requests)
		VALUES ({int:time_bucket}, {int:rule}, {string:ip_bucket}, {string:bucket_type}, 1)
		ON CONFLICT ON CONSTRAINT {db_prefix}wartt_counters_pkey DO UPDATE SET requests = {db_prefix}wartt_counters.requests + 1';
	else
		$sql = 'INSERT INTO {db_prefix}wartt_counters (time_bucket, id_rule, ip_bucket, bucket_type, requests)
		VALUES ({int:time_bucket}, {int:rule}, {string:ip_bucket}, {string:bucket_type}, 1)
		ON DUPLICATE KEY UPDATE requests = requests + 1';

	$request = $smcFunc['db_query']('', $sql,
		array(
			'time_bucket' => $time_bucket,
			'rule' => $id_rule,
			'ip_bucket' => $ip_bucket,
			'bucket_type' => $bucket_type,
		)
	);
}

/**
 * check_wartt_threshold - for the rule in question, add up counters for the specified minutes.
 *
 * @param int id_rule
 * @param string ip_bucket
 * @param int minutes
 *
 * @return int
 *
 */
function check_wartt_threshold($id_rule, $ip_bucket, $minutes)
{
	global $smcFunc;

	$cutoff = time() - $minutes * 60;

	$request = $smcFunc['db_query']('', '
		SELECT SUM(requests) AS requests
		FROM {db_prefix}wartt_counters
		WHERE id_rule = {int:rule} AND ip_bucket = {string:bucket} AND time_bucket >= {int:cutoff}',
		array(
			'rule' => $id_rule,
			'bucket' => $ip_bucket,
			'cutoff' => $cutoff,
		)
	);
	$check = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	if (empty($check))
		return 0;
	else
		return $check['requests'];
}

/**
 * add_wartt_block - for the rule-bucket in question, add a block.
 *
 * @param int id_rule
 * @param string ip_bucket
 * @param string bucket_type
 *
 * @return void
 *
 */
function add_wartt_block($id_rule, $ip_bucket, $bucket_type)
{
	global $smcFunc, $db_type;

	if ($db_type == 'postgresql')
		$sql = 'INSERT INTO {db_prefix}wartt_blocks (id_rule, ip_bucket, bucket_type, datetime)
		VALUES ({int:rule}, {string:bucket}, {string:bucket_type}, {int:time})
		ON CONFLICT ON CONSTRAINT {db_prefix}wartt_blocks_pkey DO UPDATE SET datetime = {int:time}';
	else
		$sql = 'INSERT INTO {db_prefix}wartt_blocks (id_rule, ip_bucket, bucket_type, datetime)
		VALUES ({int:rule}, {string:bucket}, {string:bucket_type}, {int:time})
		ON DUPLICATE KEY UPDATE datetime = {int:time}';

	$request = $smcFunc['db_query']('', $sql,
		array(
			'rule' => $id_rule,
			'bucket' => $ip_bucket,
			'bucket_type' => $bucket_type,
			'time' => time(),
		)
	);
}

/**
 * remove_wartt_block - for the rule-bucket in question, delete a block.
 *
 * @param int id_rule
 * @param string ip_bucket
 *
 * @return void
 *
 */
function remove_wartt_block($id_rule, $ip_bucket)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		DELETE FROM {db_prefix}wartt_blocks
		WHERE id_rule = {int:rule} AND ip_bucket = {string:bucket}',
		array(
			'rule' => $id_rule,
			'bucket' => $ip_bucket,
		)
	);
}

/**
 * check_wartt_block - for the rule-bucket in question, check whether it's currently blocked.
 *
 * @param int id_rule
 * @param string ip_bucket
 *
 * @return int
 *
 */
function check_wartt_block($id_rule, $ip_bucket)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT 1 FROM {db_prefix}wartt_blocks
		WHERE id_rule = {int:rule} AND ip_bucket = {string:bucket}',
		array(
			'rule' => $id_rule,
			'bucket' => $ip_bucket,
		)
	);

	$check = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	if (empty($check))
		return 0;
	else
		return 1;
}

/**
 * wartt_log_entry - add a log entry.
 *
 * @param int id_rule
 * @param string bucket_type
 * @param string info
 *
 * @return void
 *
 */
function wartt_log_entry($id_rule, $bucket_type, $info)
{
	global $smcFunc, $db_type;

	if ($db_type == 'postgresql')
		$sql = 'INSERT INTO {db_prefix}wartt_log (datetime, id_rule, bucket_type, info)
		VALUES ({int:datetime}, {int:rule}, {string:bucket_type}, {string:info})
		ON CONFLICT ON CONSTRAINT {db_prefix}wartt_log_pkey DO UPDATE SET info = {string:info}';
	else
		$sql = 'INSERT INTO {db_prefix}wartt_log (datetime, id_rule, bucket_type, info)
		VALUES ({int:datetime}, {int:rule}, {string:bucket_type}, {string:info})
		ON DUPLICATE KEY UPDATE info = {string:info}';

	$request = $smcFunc['db_query']('', $sql,
		array(
			'datetime' => time(),
			'rule' => $id_rule,
			'bucket_type' => $bucket_type,
			'info' => $info,
		)
	);
}

/**
 * check_table_maint - Scheduled Task to clear out the logs, both the counters & the action log.
 *
 * Action: NA - helper function called from Scheduled Tasks.
 *
 * @param void
 *
 * @return bool $completed
 *
 */
function check_table_maint()
{
	global $smcFunc, $modSettings, $db_type;

	// Default to 2 hours for counter retention
	if (empty($modSettings['wartt_counter_ret_mins']))
		$counter_retention = 120;
	else
		$counter_retention = (int) $modSettings['wartt_counter_ret_mins'];

	// Default to 2 months for log retention
	if (empty($modSettings['wartt_log_ret_months']))
		$log_retention = 2;
	else
		$log_retention = (int) $modSettings['wartt_log_ret_months'];

	// Calculate cutoffs...
	$counter_cutoff = time() -  ($counter_retention * 60);
	$log_cutoff = time() -  ($log_retention * 30 * 24 * 60 * 60);

	// Do the deed...
	$request = $smcFunc['db_query']('', '
		DELETE FROM {db_prefix}wartt_counters
		WHERE time_bucket < {int:cutoff}',
		array(
			'cutoff' => $counter_cutoff,
		)
	);
	$request = $smcFunc['db_query']('', '
		DELETE FROM {db_prefix}wartt_log
		WHERE datetime < {int:cutoff}',
		array(
			'cutoff' => $log_cutoff,
		)
	);

	// MEMORY tables don't free up rows, even on truncate... Gotta ALTER them to force a rebuild...
	// Only honored on mysql, pg don't do that...
	if ($db_type != 'postgresql')
	{
		$request = $smcFunc['db_query']('', '
			ALTER TABLE {db_prefix}wartt_counters
			ENGINE=MEMORY',
			array()
		);
	}

	// It won't log it unless we say it's true...
	return true;
}

/**
 * get_block_info - returns an array of info about blocks.
 *
 * @params int $start - for multi-page queries
 * @params int $limit - for multi-page queries
 * @params int $sort
 *
 * @return array
 *
 */
function get_block_info($start = 0, $limit = 0, $sort = 'datetime DESC')
{
	global $smcFunc, $txt;

	$block_info = array();

	$request = $smcFunc['db_query']('', '
		SELECT id_rule, ip_bucket, bucket_type, datetime
		FROM {db_prefix}wartt_blocks
		ORDER BY {raw:sort}' .
		(empty($limit) ? '' : ' LIMIT {int:limit}' . (empty($start) ? '' : ' OFFSET {int:start}')),
		array(
			'start' => $start,
			'limit' => $limit,
			'sort' => $sort,
		)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$row['datetime_disp'] = timeformat($row['datetime']);
		$block_info[] = $row;
	}
	$smcFunc['db_free_result']($request);

	return $block_info;
}

/**
 * get_block_count - returns the total count of blocks.
 *
 * @return int
 *
 */
function get_block_count()
{
	global $smcFunc;

	$count = 0;

	$request = $smcFunc['db_query']('', '
		SELECT count(*) AS count
		FROM {db_prefix}wartt_blocks',
		array()
	);

	$count = (int) $smcFunc['db_fetch_assoc']($request)['count'];
	$smcFunc['db_free_result']($request);

	return $count;
}

/**
 * get_log_info - returns an array of log info.
 *
 * @params int $start - for multi-page queries
 * @params int $limit - for multi-page queries
 * @params int $sort
 *
 * @return array
 *
 */
function get_log_info($start = 0, $limit = 0, $sort = 'id_entry DESC')
{
	global $smcFunc, $txt;

	$log_info = array();

	$request = $smcFunc['db_query']('', '
		SELECT id_entry, datetime, id_rule, bucket_type, info
		FROM {db_prefix}wartt_log
		ORDER BY {raw:sort}' .
		(empty($limit) ? '' : ' LIMIT {int:limit}' . (empty($start) ? '' : ' OFFSET {int:start}')),
		array(
			'start' => $start,
			'limit' => $limit,
			'sort' => $sort,
		)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$row['datetime_disp'] = timeformat($row['datetime']);
		$log_info[] = $row;
	}
	$smcFunc['db_free_result']($request);

	return $log_info;
}

/**
 * get_log_count - returns the total count of log entries.
 *
 * @return int
 *
 */
function get_log_count()
{
	global $smcFunc;

	$count = 0;

	$request = $smcFunc['db_query']('', '
		SELECT count(*) AS count
		FROM {db_prefix}wartt_log',
		array()
	);

	$count = (int) $smcFunc['db_fetch_assoc']($request)['count'];
	$smcFunc['db_free_result']($request);

	return $count;
}

/**
 * get_counters_info - returns an array of counter info.
 *
 * @params int $start - for multi-page queries
 * @params int $limit - for multi-page queries
 * @params int $sort
 *
 * @return array
 *
 */
function get_counters_info($start = 0, $limit = 0, $sort = 'time_bucket DESC')
{
	global $smcFunc, $txt;

	$counter_info = array();

	$request = $smcFunc['db_query']('', '
		SELECT time_bucket, id_rule, bucket_type, ip_bucket, requests
		FROM {db_prefix}wartt_counters
		ORDER BY {raw:sort}' .
		(empty($limit) ? '' : ' LIMIT {int:limit}' . (empty($start) ? '' : ' OFFSET {int:start}')),
		array(
			'start' => $start,
			'limit' => $limit,
			'sort' => $sort,
		)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$row['time_bucket_disp'] = timeformat($row['time_bucket']);
		$counter_info[] = $row;
	}
	$smcFunc['db_free_result']($request);

	return $counter_info;
}

/**
 * get_counters_count - returns the total count of counters.  Yep.
 *
 * @return int
 *
 */
function get_counters_count()
{
	global $smcFunc;

	$count = 0;

	$request = $smcFunc['db_query']('', '
		SELECT count(*) AS count
		FROM {db_prefix}wartt_counters',
		array()
	);

	$count = (int) $smcFunc['db_fetch_assoc']($request)['count'];
	$smcFunc['db_free_result']($request);

	return $count;
}

/**
 * get_rule_info - returns an array of rule info.
 *
 * @params int $start - for multi-page queries
 * @params int $limit - for multi-page queries
 * @params int $sort
 *
 * @return array
 *
 */
function get_rule_info($start = 0, $limit = 0, $sort = 'id_rule')
{
	global $smcFunc, $txt;

	$rule_info = array();

	$request = $smcFunc['db_query']('', '
		SELECT id_rule, description, bucket_type, bucket_var, minutes, threshold, action, action_pct, enabled
		FROM {db_prefix}wartt_rules
		ORDER BY {raw:sort}' .
		(empty($limit) ? '' : ' LIMIT {int:limit}' . (empty($start) ? '' : ' OFFSET {int:start}')),
		array(
			'start' => $start,
			'limit' => $limit,
			'sort' => $sort,
		)
	);

	$rule_info = $smcFunc['db_fetch_all']($request);
	$smcFunc['db_free_result']($request);

	return $rule_info;
}

/**
 * get_rule - returns an array of rule info - for ONE rule.
 *
 * @params int $id_rule
 *
 * @return array
 *
 */
function get_rule($id_rule)
{
	global $smcFunc, $txt;

	$rule_info = array();

	$request = $smcFunc['db_query']('', '
		SELECT id_rule, description, bucket_type, bucket_var, minutes, threshold, action, action_pct, enabled
		FROM {db_prefix}wartt_rules
		WHERE id_rule = {int:rule}',
		array(
			'rule' => $id_rule,
		)
	);

	$rule_info = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	return $rule_info;
}

/**
 * get_rule_count - returns the total count of rules.
 *
 * @return int
 *
 */
function get_rule_count()
{
	global $smcFunc;

	$count = 0;

	$request = $smcFunc['db_query']('', '
		SELECT count(*) AS count
		FROM {db_prefix}wartt_rules',
		array()
	);

	$count = (int) $smcFunc['db_fetch_assoc']($request)['count'];
	$smcFunc['db_free_result']($request);

	return $count;
}

/**
 * wartt_rule_deletes.  List processing allows bulk deletion, so accepts array.
 *
 * @param array $id_rules
 *
 * @return null
 *
 */
function wartt_rule_deletes($id_rules)
{
	global $smcFunc;

	if (!empty($id_rules) && is_array($id_rules))
	{
		$smcFunc['db_query']('', 'DELETE FROM {db_prefix}wartt_rules
			WHERE id_rule IN({array_int:rules})',
			array(
				'rules' => $id_rules,
			),
		);
	}
}

/**
 * toggle_enabled_state.
 *
 * @param array $rules
 *
 * @return null
 *
 */
function toggle_enabled_state($rules)
{
	global $smcFunc;

	if (!empty($rules) && is_array($rules))
	{
		$smcFunc['db_query']('', 'UPDATE {db_prefix}wartt_rules
			SET enabled =
				CASE
					WHEN enabled = 1 THEN 0
					WHEN enabled = 0 THEN 1
				END
			WHERE id_rule IN ({array_int:rules})',
		array(
			'rules' => $rules,
			)
		);
	}
}

/**
 * add_wartt_rule
 *
 * @param string $description
 * @param string $bucket_type
 * @param string $bucket_var
 * @param int $minutes
 * @param int $threshold
 * @param string $action
 * @param int $action_pct
 * @param int $enabled
 *
 * @return null
 *
 */
function add_wartt_rule($description, $bucket_type, $bucket_var, $minutes, $threshold, $action, $action_pct, $enabled)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		INSERT INTO {db_prefix}wartt_rules (description, bucket_type, bucket_var, minutes, threshold, action, action_pct, enabled)
		VALUES ({string:description}, {string:bucket_type}, {string:bucket_var}, {int:minutes}, {int:threshold}, {string:action}, {int:action_pct}, {int:enabled})',
		array(
			'description' => $description,
			'bucket_type' => $bucket_type,
			'bucket_var' => $bucket_var,
			'minutes' => $minutes,
			'threshold' => $threshold,
			'action' => $action,
			'action_pct' => $action_pct,
			'enabled' => $enabled,
		)
	);
}

/**
 * mod_wartt_rule
 *
 * @param int $id_rule
 * @param string $description
 * @param string $bucket_type
 * @param string $bucket_var
 * @param int $minutes
 * @param int $threshold
 * @param string $action
 * @param int $action_pct
 * @param int $enabled
 *
 * @return null
 *
 */
function mod_wartt_rule($id_rule, $description, $bucket_type, $bucket_var, $minutes, $threshold, $action, $action_pct, $enabled)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		UPDATE {db_prefix}wartt_rules
			SET description = {string:description},
			bucket_type = {string:bucket_type},
			bucket_var = {string:bucket_var},
			minutes = {int:minutes},
			threshold = {int:threshold},
			action = {string:action},
			action_pct = {int:action_pct},
			enabled = {int:enabled}
		WHERE id_rule = {int:id_rule}',
		array(
			'id_rule' => $id_rule,
			'description' => $description,
			'bucket_type' => $bucket_type,
			'bucket_var' => $bucket_var,
			'minutes' => $minutes,
			'threshold' => $threshold,
			'action' => $action,
			'action_pct' => $action_pct,
			'enabled' => $enabled,
		)
	);
}

/**
 * get_wala_populated - determine if WALA dbip tables are present & contain data.
 *
 * @return bool
 *
 */
function get_wala_populated()
{
	global $smcFunc;

	// Loads a couple extra db functions like db_list_tables
	db_extend('extra');

	// Get list of tables in current db that look like wala_dbip tables...
	$tables = array();
	$tables = $smcFunc['db_list_tables'](false, '%wala_dbip%');
	if (count($tables) < 2)
		return false;

	// Now check count for dbip asn table
	$count = 0;
	$request = $smcFunc['db_query']('', '
		SELECT count(*) AS count
		FROM {db_prefix}wala_dbip_asn',
		array()
	);
	$count = (int) $smcFunc['db_fetch_assoc']($request)['count'];
	$smcFunc['db_free_result']($request);
	if ($count < 100000)
		return false;

	// Now check count for dbip asn table
	$count = 0;
	$request = $smcFunc['db_query']('', '
		SELECT count(*) AS count
		FROM {db_prefix}wala_dbip_country',
		array()
	);
	$count = (int) $smcFunc['db_fetch_assoc']($request)['count'];
	$smcFunc['db_free_result']($request);
	if ($count < 100000)
		return false;

	return true;
}

/**
 * get_dbip_asn - Given an IP, lookup ASN via DBIP
 *
 * @param string ip
 *
 * @return string|false asn, false if not found
 *
 */
function get_dbip_asn($ip)
{
	global $smcFunc, $db_type;

	if (filter_var($ip, FILTER_VALIDATE_IP) === false)
		return false;

	$ip_packed = inet_pton($ip);
	$ip_len = strlen($ip_packed);
	$ip_hex = '';
	for ($i = 0; $i < $ip_len; $i++)
		$ip_hex .= substr( '00' . dechex(ord(substr($ip_packed, $i, 1))), -2);

	if ($db_type == 'postgresql')
		$sql = 'SELECT asn FROM {db_prefix}wala_dbip_asn
		WHERE ip_from_packed <= {string:ip} AND ip_to_packed >= {string:ip}';
	else
		$sql = 'SELECT asn FROM {db_prefix}wala_dbip_asn
		WHERE ip_from_packed <= UNHEX({string:iph}) AND ip_to_packed >= UNHEX({string:iph}) AND LENGTH(ip_from_packed) = {int:len}';

	// Skip errors in case we've done something dumb like deinstall WALA after making configs look at it...
	$asn = false;
	$request = $smcFunc['db_query']('', $sql,
		array(
			'ip' => $ip,
			'iph' => $ip_hex,
			'len' => $ip_len,
			'db_error_skip' => true,
		)
	);
	if (($request !== false) && $smcFunc['db_num_rows']($request) == 1)
		$asn = $smcFunc['db_fetch_assoc']($request)['asn'];

	return $asn;
}

/**
 * get_dbip_country - Given an IP, lookup country via DBIP
 *
 * @param string ip
 *
 * @return string|false country, false if not found
 *
 */
function get_dbip_country($ip)
{
	global $smcFunc, $db_type;

	if (filter_var($ip, FILTER_VALIDATE_IP) === false)
		return false;

	$ip_packed = inet_pton($ip);
	$ip_len = strlen($ip_packed);
	$ip_hex = '';
	for ($i = 0; $i < $ip_len; $i++)
		$ip_hex .= substr( '00' . dechex(ord(substr($ip_packed, $i, 1))), -2);

	if ($db_type == 'postgresql')
		$sql = 'SELECT country FROM {db_prefix}wala_dbip_country
		WHERE ip_from_packed <= {string:ip} AND ip_to_packed >= {string:ip}';
	else
		$sql = 'SELECT country FROM {db_prefix}wala_dbip_country
		WHERE ip_from_packed <= UNHEX({string:iph}) AND ip_to_packed >= UNHEX({string:iph}) AND LENGTH(ip_from_packed) = {int:len}';

	// Skip errors in case we've done something dumb like deinstall WALA after making configs look at it...
	$country = false;
	$request = $smcFunc['db_query']('', $sql,
		array(
			'ip' => $ip,
			'iph' => $ip_hex,
			'len' => $ip_len,
			'db_error_skip' => true,
		)
	);
	if (($request !== false) && $smcFunc['db_num_rows']($request) == 1)
		$country = $smcFunc['db_fetch_assoc']($request)['country'];

	return $country;
}

/**
 * clear_blocks - Delete all blocks
 *
 * @return void
 *
 */
function clear_blocks()
{
	global $smcFunc, $db_type;

	// Do the deed...
	$request = $smcFunc['db_query']('', '
		TRUNCATE {db_prefix}wartt_blocks',
		array()
	);

	// MEMORY tables (wartt_blocks, wartt_counters) don't free up rows, even on truncate... Gotta ALTER them to force a rebuild...
	// Only honored on mysql, pg don't do that...
	if ($db_type != 'postgresql')
	{
		$request = $smcFunc['db_query']('', '
			ALTER TABLE {db_prefix}wartt_blocks
			ENGINE=MEMORY',
			array()
		);
	}
}

/**
 * clear_log - Delete all log entries
 *
 * @return void
 *
 */
function clear_log()
{
	global $smcFunc;

	// Do the deed...
	$request = $smcFunc['db_query']('', '
		TRUNCATE {db_prefix}wartt_log',
		array()
	);
}

/**
 * clear_counters - Delete all the counters
 *
 * @return void
 *
 */
function clear_counters()
{
	global $smcFunc, $db_type;

	// Do the deed...
	$request = $smcFunc['db_query']('', '
		TRUNCATE {db_prefix}wartt_counters',
		array()
	);

	// MEMORY tables (wartt_blocks, wartt_counters) don't free up rows, even on truncate... Gotta ALTER them to force a rebuild...
	// Only honored on mysql, pg don't do that...
	if ($db_type != 'postgresql')
	{
		$request = $smcFunc['db_query']('', '
			ALTER TABLE {db_prefix}wartt_counters
			ENGINE=MEMORY',
			array()
		);
	}
}