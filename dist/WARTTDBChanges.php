<?php

global $smcFunc, $db_type, $db_prefix;

if (!isset($smcFunc['db_create_table']))
	db_extend('packages');

$create_tables = array(
	'wartt_counters' => array(
		'columns' => array(
		    array(
			   'name' => 'time_bucket',
				'type' => 'int',
				'default' => 0,
				'not_null' => true,
			),
		    array(
			   'name' => 'id_rule',
				'type' => 'tinyint',
				'default' => 0,
				'not_null' => true,
			),
			array(
				'name' => 'ip_bucket',
				'type' => 'varchar',
				'size' => 50,
				'default' => '',
				'not_null' => true,
			),
			array(
				'name' => 'bucket_type',
				'type' => 'varchar',
				'size' => 10,
				'default' => '',
				'not_null' => true,
			),
		    array(
			   'name' => 'requests',
				'type' => 'int',
				'default' => 0,
				'not_null' => true,
			),
		),
		'indexes' => array(),
		'options' => array(
			'engine' => 'MEMORY',
		),
	),
	'wartt_log' => array(
		'columns' => array(
		    array(
			   'name' => 'id_entry',
				'type' => 'int',
				'default' => 0,
				'not_null' => true,
				'auto' => true,
			),
			array(
				'name' => 'datetime',
				'type' => 'int',
				'default' => 0,
				'not_null' => true,
			),
		    array(
			   'name' => 'id_rule',
				'type' => 'int',
				'default' => 0,
				'not_null' => true,
			),
			array(
				'name' => 'bucket_type',
				'type' => 'varchar',
				'size' => 10,
				'default' => '',
				'not_null' => true,
			),
			array(
				'name' => 'info',
				'type' => 'varchar',
				'size' => 255,
				'default' => '',
				'not_null' => true,
			),
		),
		'indexes' => array(
			array(
				'type' => 'primary',
				'columns' => array('id_entry'),
			),
		),
		'options' => array(),
	),
	'wartt_rules' => array(
		'columns' => array(
		    array(
			   'name' => 'id_rule',
				'type' => 'tinyint',
				'default' => 0,
				'not_null' => true,
				'auto' => true,
			),
			array(
				'name' => 'description',
				'type' => 'varchar',
				'size' => 20,
				'default' => '',
				'not_null' => true,
			),
			array(
				'name' => 'bucket_type',
				'type' => 'varchar',
				'size' => 10,
				'default' => '',
				'not_null' => true,
			),
			array(
				'name' => 'bucket_var',
				'type' => 'varchar',
				'size' => 100,
				'default' => '',
				'not_null' => true,
			),
		    array(
			   'name' => 'minutes',
				'type' => 'int',
				'default' => 10,
				'not_null' => true,
			),
		    array(
			   'name' => 'threshold',
				'type' => 'int',
				'default' => 10000,
				'not_null' => true,
			),
			array(
				'name' => 'action',
				'type' => 'varchar',
				'size' => 10,
				'default' => 'log',
				'not_null' => true,
			),
		    array(
			   'name' => 'action_pct',
				'type' => 'int',
				'default' => 100,
				'not_null' => true,
			),
		    array(
				'name' => 'enabled',
				'type' => 'tinyint',
				'default' => 0,
				'not_null' => true,
			),
		),
		'indexes' => array(
			array(
				'type' => 'primary',
				'columns' => array('id_rule'),
			),
		),
		'options' => array(),
	),
	'wartt_blocks' => array(
		'columns' => array(
		    array(
			   'name' => 'id_rule',
				'type' => 'tinyint',
				'default' => 0,
				'not_null' => true,
			),
			array(
				'name' => 'ip_bucket',
				'type' => 'varchar',
				'size' => 50,
				'default' => '',
				'not_null' => true,
			),
			array(
				'name' => 'bucket_type',
				'type' => 'varchar',
				'size' => 10,
				'default' => '',
				'not_null' => true,
			),
			array(
				'name' => 'datetime',
				'type' => 'int',
				'default' => 0,
				'not_null' => true,
			),
		),
		'indexes' => array(),
		'options' => array(
			'engine' => 'MEMORY',
		),
	),
);

// The problem with MEMORY tables, is that they default to USING HASH indexes, which hurts the queries we are attempting to do.
// Anything with a range or sort, we want BTREE.  SMF's routines won't let you override that, so we need to do it manually here.
// And... of course... pg plays differently...

// So...  We gotta step thru these one at a time, see if they exist already, & invoke unique syntax where necessary.
db_extend('extra');
$tables = $smcFunc['db_list_tables'](false, '%wartt%');
$btree = ($db_type == 'postgresql') ? '' : ' USING BTREE';

// wartt_rules
if (!in_array($db_prefix . 'wartt_rules', $tables))
{
	$smcFunc['db_create_table']('{db_prefix}wartt_rules', $create_tables['wartt_rules']['columns'], $create_tables['wartt_rules']['indexes'], $create_tables['wartt_rules']['options']);
}

// wartt_log
if (!in_array($db_prefix . 'wartt_log', $tables))
{
	$smcFunc['db_create_table']('{db_prefix}wartt_log', $create_tables['wartt_log']['columns'], $create_tables['wartt_log']['indexes'], $create_tables['wartt_log']['options']);
}

// wartt_blocks
if (!in_array($db_prefix . 'wartt_blocks', $tables))
{
	$smcFunc['db_create_table']('{db_prefix}wartt_blocks', $create_tables['wartt_blocks']['columns'], $create_tables['wartt_blocks']['indexes'], $create_tables['wartt_blocks']['options']);
	$smcFunc['db_query']('', 'ALTER TABLE {db_prefix}wartt_blocks ADD PRIMARY KEY (id_rule, ip_bucket)' . $btree,
	array('db_error_skip' => true));

	// Create index needs special treatment due to different syntax & pg not liking btree...
	if ($db_type == 'postgresql')
	{
		$smcFunc['db_query']('', 'CREATE INDEX datetime_idx ON {db_prefix}wartt_blocks (datetime)',
			array('db_error_skip' => true));
	}
	else
	{
		$smcFunc['db_query']('', 'ALTER TABLE {db_prefix}wartt_blocks ADD INDEX (datetime) USING BTREE',
			array('db_error_skip' => true));
	}
}

// wartt_counters
if (!in_array($db_prefix . 'wartt_counters', $tables))
{
	$smcFunc['db_create_table']('{db_prefix}wartt_counters', $create_tables['wartt_counters']['columns'], $create_tables['wartt_counters']['indexes'], $create_tables['wartt_counters']['options']);
	$smcFunc['db_query']('', 'ALTER TABLE {db_prefix}wartt_counters ADD PRIMARY KEY (time_bucket, id_rule, ip_bucket)' . $btree,
	array('db_error_skip' => true));
}

// Finally, add the scheduled task...
$smcFunc['db_insert']('ignore', '{db_prefix}scheduled_tasks',
	array('time_offset' => 'int', 'time_regularity' => 'int', 'time_unit' => 'string', 'disabled' => 'int', 'task' => 'string', 'callable' => 'string'),
	array(1020, 4, 'h', 0, 'check_wartt_table_maint', '$sourcedir/WARTTModel.php|check_table_maint'),
	array('task'));
