<?php
// Version: 2.1.7; WARTT

// All text below is in the admin panel.

// Scheduled tasks
$txt['scheduled_task_check_wartt_table_maint'] = 'WARTT Table Maintenance';
$txt['scheduled_task_desc_check_wartt_table_maint'] = 'Periodic cleaning of old data from WARTT tables.';

// Menu
$txt['wartt_title'] = 'Web Access RT Tracker';
$txt['wartt_title_desc'] = 'Web Access Real-Time Tracker.  This tool can be used to track, block and log suspiciously high activity';
$txt['wartt_blocks'] = 'Active Blocks';
$txt['wartt_log'] = 'Log';
$txt['wartt_counters'] = 'Counters';
$txt['wartt_rules'] = 'Rules';
$txt['wartt_add_rule'] = 'Add Rule';
$txt['wartt_mod_rule'] = 'Modify Rule';
$txt['wartt_settings'] = 'Settings';

// Text labels
$txt['wartt_id'] = 'Rule';
$txt['wartt_time_bucket'] = 'Minute';
$txt['wartt_ip_bucket'] = 'IP Bucket';
$txt['wartt_datetime'] = 'Datetime';
$txt['wartt_entry'] = 'Entry';
$txt['wartt_info'] = 'Info';
$txt['wartt_requests'] = 'Requests';
$txt['wartt_crawl_target_requests'] = 'Crawl targets';
$txt['wartt_modify'] = 'Modify';

$txt['wartt_desc'] = 'Description';
$txt['wartt_type'] = 'Bucket Type';
$txt['wartt_var'] = 'Bucket Var';
$txt['wartt_minutes'] = 'Minutes';
$txt['wartt_threshold'] = 'Threshold';
$txt['wartt_action'] = 'Action';
$txt['wartt_actpct'] = 'Action Pct';
$txt['wartt_rule_enabled'] = 'Enabled';

$txt['wartt_desc_desc'] = 'Short note on purpose';
$txt['wartt_desc_type'] = 'Groups of IPs to track, e.g., by ASN, country, IP mask, Crawl Targets';
$txt['wartt_desc_var'] = 'Required for Env or Server variables - which variable to use';
$txt['wartt_desc_minutes'] = 'How many minutes to reach threshold, 1 - 1440';
$txt['wartt_desc_threshold'] = 'How many site hits before taking action, 10 - 20000';
$txt['wartt_desc_action'] = 'Log, Block, or change system setting';
$txt['wartt_desc_actpct'] = 'Used when blocking only, to thin the herd, 0 - 100%';
$txt['wartt_desc_rule_enabled'] = 'Enable or disable this rule as needed';

// Settings
$txt['wartt_enabled'] = 'WARTT enabled';
$txt['wartt_counter_ret_mins'] = 'Counter retention - in minutes';
$txt['wartt_log_ret_months'] = 'Log retention - in months';
$txt['wartt_ipv4_masklen'] = 'Ipv4 mask length for ip_mask - be very careful with this!';
$txt['wartt_ipv6_masklen'] = 'Ipv6 mask length for ip_mask - be very careful with this!';
$txt['wartt_crawl_target_pct'] = 'Crawl target percent - percent to meet crawl target threshold';
$txt['wartt_crawl_target_def'] = 'Crawl target definition - request text to meet crawl target threshold';
$txt['wartt_whitelist_country'] = 'Whitelist - 2-byte country codes to be excluded from country checks, no spaces, e.g., "US,CA"';
$txt['wartt_whitelist_asn'] = 'Whitelist - ASNs to be excluded from ASN checks, no spaces, e.g., "396982,15169,8075"';
$txt['wartt_whitelist_useragent'] = 'Whitelist - useragents to be excluded from all checks, case sensitive, no spaces, e.g., "Googlebot,bingbot,DuckDuckBot"';

// Buttons
$txt['wartt_delrule'] = 'Delete';
$txt['wartt_delrule_confirm'] = 'Delete OK?';
$txt['wartt_enable'] = 'Toggle Enable';
$txt['wartt_enable_confirm'] = 'Change enabled status?';
$txt['wartt_clear_counters'] = 'Clear Counters';
$txt['wartt_clear_counters_confirm'] = 'Clear all counters?';
$txt['wartt_clear_log'] = 'Clear Log';
$txt['wartt_clear_log_confirm'] = 'Clear the log?';
$txt['wartt_clear_blocks'] = 'Clear Blocks';
$txt['wartt_clear_blocks_confirm'] = 'Clear all blocks?';

// Bucket type descriptions
$txt['wartt_btdesc_ipmask'] = 'IP mask';
$txt['wartt_btdesc_crawl_tgt'] = 'Crawl Target IP mask';
$txt['wartt_btdesc_servervar'] = 'Server variable that contains ASN or country';
$txt['wartt_btdesc_envvar'] = 'Environment variable that contains ASN or country';
$txt['wartt_btdesc_asnlookup'] = 'Lookup ASN based on IP';
$txt['wartt_btdesc_colookup'] = 'Lookup country based on IP';

// Action descriptions
$txt['wartt_adesc_log'] = 'Log only';
$txt['wartt_adesc_block'] = 'Block';
$txt['wartt_adesc_noviewct'] = 'Do not count guest views';
$txt['wartt_adesc_nologct'] = 'Do not include in log online & guest counts';

// Edits
$txt['wartt_bad_desc'] = 'Description is required and cannot exceed 20 characters';
$txt['wartt_bad_var'] = 'Invalid bucket system or environment variable - confirm it exists';

// Detail screen
$txt['wartt_no_entries_found'] = 'No entries found';

// Log info
$txt['wartt_activated'] = 'Activated';
$txt['wartt_inactivated'] = 'Inactivated';

// Block msg...
$txt['wartt_429'] = '429 Too Many Requests - Throttling in effect - Try again later...';
?>