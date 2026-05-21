<?php
/**
 *	Logic for WARTT hooks.
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
 *
 * Hook function - Add admin menu functions.
 *
 * Hook: integrate_admin_areas
 *
 * @param array $menu
 *
 * @return null
 *
 */
function wartt_admin_menu(&$menu)
{
	global $txt;

	loadLanguage('WARTT');

	$title = $txt['wartt_title'];

	// Add to the main menu
	$menu['maintenance']['areas']['wartt'] = array(
		'label' => $title,
		'file' => 'WARTT.php',
		'function' => 'wartt_main',
		'icon' => 'news',
		'permission' => 'admin_forum',
		'subsections' => array(
			'wartt_blocks' => array($txt['wartt_blocks']),
		    'wartt_log' => array($txt['wartt_log']),
		    'wartt_counters' => array($txt['wartt_counters']),
		    'wartt_rules' => array($txt['wartt_rules']),
		    'wartt_add_rule' => array($txt['wartt_add_rule']),
		    'wartt_settings' => array($txt['wartt_settings']),
		),
	);
}