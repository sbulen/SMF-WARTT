<?php
/**
 *	Template for admin functions for the the WARTT mod for SMF.
 *
 *	Copyright 2026 Shawn Bulen
 *
 *	The Bot Blocker is free software: you can redistribute it and/or modify
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

/**
 * A page to add a rule.
 */

function template_add_rule()
{
	global $context, $scripturl, $txt;

	echo '
	<div>
		<form action="', $scripturl, '?action=admin;area=wartt;sa=wartt_add_rule" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['wartt_add_rule'], '
				</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">';

	// Description.
	echo '
					<dt>
						<strong>', $txt['wartt_desc'], ':</strong><br>
						<span class="smalltext">', $txt['wartt_desc_desc'], '</span>
					</dt>
					<dd>
						<input type="text" name="description" value="', $context['wartt_rule_info']['description'], '" size="20">
					</dd>';

	// Bucket_type.
	echo '
					<dt>
						<strong>', $txt['wartt_type'], ':</strong><br>
						<span class="smalltext">', $txt['wartt_desc_type'], '</span>
					</dt>
					<dd>
						<select name="bucket_type">
							<option value="ip_mask"', ($context['wartt_rule_info']['bucket_type'] == 'ip_mask' ? ' selected' : ''), '>', $txt['wartt_btdesc_ipmask'], '</option>
							<option value="server_var"', ($context['wartt_rule_info']['bucket_type'] == 'server_var' ? ' selected' : ''), '>', $txt['wartt_btdesc_servervar'], '</option>
							<option value="env_var"', ($context['wartt_rule_info']['bucket_type'] == 'env_var' ? ' selected' : ''), '>', $txt['wartt_btdesc_envvar'], '</option>';


	// If WALA tables here, allow lookup options...
	if (!empty($context['wartt_wala_populated']))
	{
		echo '
							<option value="asn_lookup"', ($context['wartt_rule_info']['bucket_type'] == 'asn_lookup' ? ' selected' : ''), '>', $txt['wartt_btdesc_asnlookup'], '</option>
							<option value="co_lookup"', ($context['wartt_rule_info']['bucket_type'] == 'co_lookup' ? ' selected' : ''), '>', $txt['wartt_btdesc_colookup'], '</option>';
	}

	echo '					</select>
					</dd>';

	// Bucket_var.
	echo '
					<dt>
						<strong>', $txt['wartt_var'], ':</strong><br>
						<span class="smalltext">', $txt['wartt_desc_var'], '</span>
					</dt>
					<dd>
						<input type="text" name="bucket_var" value="', $context['wartt_rule_info']['bucket_var'], '" size="100">
					</dd>';

	// Minutes.
	echo '
					<dt>
						<strong>', $txt['wartt_minutes'], ':</strong><br>
						<span class="smalltext">', $txt['wartt_desc_minutes'], '</span>
					</dt>
					<dd>
						<input type="number" name="minutes" value="', $context['wartt_rule_info']['minutes'], '" min="1" max="480" required>
					</dd>';

	// Threshold.
	echo '
					<dt>
						<strong>', $txt['wartt_threshold'], ':</strong><br>
						<span class="smalltext">', $txt['wartt_desc_threshold'], '</span>
					</dt>
					<dd>
						<input type="number" name="threshold" value="', $context['wartt_rule_info']['threshold'], '" min="10" max="20000" required>
					</dd>';

	// Action.
	echo '
					<dt>
						<strong>', $txt['wartt_action'], ':</strong><br>
						<span class="smalltext">', $txt['wartt_desc_action'], '</span>
					</dt>
					<dd>
						<select name="wartt_action">
							<option value="log"', ($context['wartt_rule_info']['action'] == 'log' ? ' selected' : ''), '>', $txt['wartt_adesc_log'], '</option>
							<option value="block"', ($context['wartt_rule_info']['action'] == 'block' ? ' selected' : ''), '>', $txt['wartt_adesc_block'], '</option>
							<option value="noviewct"', ($context['wartt_rule_info']['action'] == 'noviewct' ? ' selected' : ''), '>', $txt['wartt_adesc_noviewct'], '</option>
							<option value="nologct"', ($context['wartt_rule_info']['action'] == 'nologct' ? ' selected' : ''), '>', $txt['wartt_adesc_nologct'], '</option>
						</select>
					</dd>';

	// Action_pct.
	echo '
					<dt>
						<strong>', $txt['wartt_actpct'], ':</strong><br>
						<span class="smalltext">', $txt['wartt_desc_actpct'], '</span>
					</dt>
					<dd>
						<input type="number" name="action_pct" value="', $context['wartt_rule_info']['action_pct'], '" min="0" max="100" required>
					</dd>';

	// Enabled.
	echo '
					<dt>
						<strong>', $txt['wartt_rule_enabled'], ':</strong><br>
						<span class="smalltext">', $txt['wartt_desc_rule_enabled'], '</span>
					</dt>
					<dd>
						<input type="checkbox" name="enabled"', ($context['wartt_rule_info']['enabled'] == 1 ? 'checked' : ''), '>
					</dd>';

	// Table footer & button.
	echo '
				</dl>
				<input type="submit" name="add" value="', $txt['wartt_add_rule'] ,'" class="button">
				<input type="hidden" name="', $context['wartt_add_token_var'], '" value="', $context['wartt_add_token'], '">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">';

	echo '
			</div>
		</form>
	</div>';
}

/**
 * A page to modify an existing rule.
 */

function template_mod_rule()
{
	global $context, $scripturl, $txt;

	echo '
	<div>
		<form action="', $scripturl, '?action=admin;area=wartt;sa=wartt_mod_rule" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['wartt_mod_rule'], '
				</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">';

	// Rule id.
	echo '
					<dt>
						<strong>', $txt['wartt_id'], ':</strong><br>
					</dt>
					<dd>
						<span class="leftext">', $context['wartt_rule_info']['id_rule'], '</span>
					</dd>';

	// Description.
	echo '
					<dt>
						<strong>', $txt['wartt_desc'], ':</strong><br>
						<span class="smalltext">', $txt['wartt_desc_desc'], '</span>
					</dt>
					<dd>
						<input type="text" name="description" value="', $context['wartt_rule_info']['description'], '" size="20">
					</dd>';

	// Bucket_type.
	echo '
					<dt>
						<strong>', $txt['wartt_type'], ':</strong><br>
						<span class="smalltext">', $txt['wartt_desc_type'], '</span>
					</dt>
					<dd>
						<select name="bucket_type">
							<option value="ip_mask"', ($context['wartt_rule_info']['bucket_type'] == 'ip_mask' ? ' selected' : ''), '>', $txt['wartt_btdesc_ipmask'], '</option>
							<option value="server_var"', ($context['wartt_rule_info']['bucket_type'] == 'server_var' ? ' selected' : ''), '>', $txt['wartt_btdesc_servervar'], '</option>
							<option value="env_var"', ($context['wartt_rule_info']['bucket_type'] == 'env_var' ? ' selected' : ''), '>', $txt['wartt_btdesc_envvar'], '</option>';


	// If WALA tables here, allow lookup options...
	if (!empty($context['wartt_wala_populated']))
	{
		echo '
							<option value="asn_lookup"', ($context['wartt_rule_info']['bucket_type'] == 'asn_lookup' ? ' selected' : ''), '>', $txt['wartt_btdesc_asnlookup'], '</option>
							<option value="co_lookup"', ($context['wartt_rule_info']['bucket_type'] == 'co_lookup' ? ' selected' : ''), '>', $txt['wartt_btdesc_colookup'], '</option>';
	}

	echo '					</select>
					</dd>';

	// Bucket_var.
	echo '
					<dt>
						<strong>', $txt['wartt_var'], ':</strong><br>
						<span class="smalltext">', $txt['wartt_desc_var'], '</span>
					</dt>
					<dd>
						<input type="text" name="bucket_var" value="', $context['wartt_rule_info']['bucket_var'], '" size="100">
					</dd>';

	// Minutes.
	echo '
					<dt>
						<strong>', $txt['wartt_minutes'], ':</strong><br>
						<span class="smalltext">', $txt['wartt_desc_minutes'], '</span>
					</dt>
					<dd>
						<input type="number" name="minutes" value="', $context['wartt_rule_info']['minutes'], '" min="1" max="480" required>
					</dd>';

	// Threshold.
	echo '
					<dt>
						<strong>', $txt['wartt_threshold'], ':</strong><br>
						<span class="smalltext">', $txt['wartt_desc_threshold'], '</span>
					</dt>
					<dd>
						<input type="number" name="threshold" value="', $context['wartt_rule_info']['threshold'], '" min="10" max="20000" required>
					</dd>';

	// Action.
	echo '
					<dt>
						<strong>', $txt['wartt_action'], ':</strong><br>
						<span class="smalltext">', $txt['wartt_desc_action'], '</span>
					</dt>
					<dd>
						<select name="wartt_action">
							<option value="log"', ($context['wartt_rule_info']['action'] == 'log' ? ' selected' : ''), '>', $txt['wartt_adesc_log'], '</option>
							<option value="block"', ($context['wartt_rule_info']['action'] == 'block' ? ' selected' : ''), '>', $txt['wartt_adesc_block'], '</option>
							<option value="noviewct"', ($context['wartt_rule_info']['action'] == 'noviewct' ? ' selected' : ''), '>', $txt['wartt_adesc_noviewct'], '</option>
							<option value="nologct"', ($context['wartt_rule_info']['action'] == 'nologct' ? ' selected' : ''), '>', $txt['wartt_adesc_nologct'], '</option>
						</select>
					</dd>';

	// Action_pct.
	echo '
					<dt>
						<strong>', $txt['wartt_actpct'], ':</strong><br>
						<span class="smalltext">', $txt['wartt_desc_actpct'], '</span>
					</dt>
					<dd>
						<input type="number" name="action_pct" value="', $context['wartt_rule_info']['action_pct'], '" min="0" max="100" required>
					</dd>';

	// Enabled.
	echo '
					<dt>
						<strong>', $txt['wartt_rule_enabled'], ':</strong><br>
						<span class="smalltext">', $txt['wartt_desc_rule_enabled'], '</span>
					</dt>
					<dd>
						<input type="checkbox" name="enabled"', ($context['wartt_rule_info']['enabled'] == 1 ? 'checked' : ''), '>
					</dd>';

	// Table footer & button.
	echo '
				</dl>
				<input type="submit" name="mod" value="', $txt['wartt_mod_rule'] ,'" class="button">
				<input type="hidden" name="id_rule" value="', $context['wartt_rule_info']['id_rule'], '">
				<input type="hidden" name="', $context['wartt_mod_token_var'], '" value="', $context['wartt_mod_token'], '">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">';

	echo '
			</div>
		</form>
	</div>';
}