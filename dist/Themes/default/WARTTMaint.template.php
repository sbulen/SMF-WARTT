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
						<input type="text" name="description" value="', $context['wartt_rule_info']['description'], '" size="20" maxlength="20" required>
					</dd>';

	// Bucket_cat.
	echo '
					<dt>
						<strong>', $txt['wartt_cat'], ':</strong><br>
						<span class="smalltext">', $txt['wartt_desc_cat'], '</span>
					</dt>
					<dd>
						<select name="bucket_cat" id="bucket_cat" required>
							<option value=""', ($context['wartt_rule_info']['bucket_cat'] == '' ? ' selected' : ''), '></option>
							<option value="ASN"', ($context['wartt_rule_info']['bucket_cat'] == 'ASN' ? ' selected' : ''), '>', $txt['wartt_bcdesc_asn'], '</option>
							<option value="Country"', ($context['wartt_rule_info']['bucket_cat'] == 'Country' ? ' selected' : ''), '>', $txt['wartt_bcdesc_country'], '</option>
							<option value="CIDR"', ($context['wartt_rule_info']['bucket_cat'] == 'CIDR' ? ' selected' : ''), '>', $txt['wartt_bcdesc_cidr'], '</option>
							<option value="Crawl_tgt"', ($context['wartt_rule_info']['bucket_cat'] == 'Crawl_tgt' ? ' selected' : ''), '>', $txt['wartt_bcdesc_crawl_target'], '</option>';

	echo '					</select>
					</dd>';

	// Bucket_type.
	echo '
					<dt>
						<strong>', $txt['wartt_type'], ':</strong><br>
						<span class="smalltext">', $txt['wartt_desc_type'], '</span>
					</dt>
					<dd>
						<select name="bucket_type" id="bucket_type">
							<option value=""', ($context['wartt_rule_info']['bucket_type'] == '' ? ' selected' : ''), '></option>
							<option value="server_var"', ($context['wartt_rule_info']['bucket_type'] == 'server_var' ? ' selected' : ''), '>', $txt['wartt_btdesc_servervar'], '</option>
							<option value="env_var"', ($context['wartt_rule_info']['bucket_type'] == 'env_var' ? ' selected' : ''), '>', $txt['wartt_btdesc_envvar'], '</option>';


	// If WALA tables here, allow lookup options...
	if (!empty($context['wartt_wala_populated']))
	{
		echo '
							<option value="lookup"', ($context['wartt_rule_info']['bucket_type'] == 'lookup' ? ' selected' : ''), '>', $txt['wartt_btdesc_lookup'], '</option>';
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
						<input type="text" name="bucket_var" id="bucket_var" value="', $context['wartt_rule_info']['bucket_var'], '" size="100" maxlength="100">
					</dd>';

	// Minutes.
	echo '
					<dt>
						<strong>', $txt['wartt_minutes'], ':</strong><br>
						<span class="smalltext">', $txt['wartt_desc_minutes'], '</span>
					</dt>
					<dd>
						<input type="number" name="minutes" style="width: 70px;" value="', $context['wartt_rule_info']['minutes'], '" min="10" max="1440" step="10" required>
					</dd>';

	// Threshold.
	echo '
					<dt>
						<strong>', $txt['wartt_threshold'], ':</strong><br>
						<span class="smalltext">', $txt['wartt_desc_threshold'], '</span>
					</dt>
					<dd>
						<input type="number" name="threshold" style="width: 80px;" value="', $context['wartt_rule_info']['threshold'], '" min="10" max="20000" step="10" required>
					</dd>';

	// Action.
	echo '
					<dt>
						<strong>', $txt['wartt_action'], ':</strong><br>
						<span class="smalltext">', $txt['wartt_desc_action'], '</span>
					</dt>
					<dd>
						<select name="wartt_action" id="wartt_action" required>
							<option value=""', ($context['wartt_rule_info']['action'] == '' ? ' selected' : ''), '></option>
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
						<input type="number" name="action_pct" id="action_pct" value="', $context['wartt_rule_info']['action_pct'], '" min="0" max="100" required>
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

	// insert js for edits here...
	wartt_js_edits();
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
						<input type="text" name="description" value="', $context['wartt_rule_info']['description'], '" size="20" maxlength="20" required>
					</dd>';

	// Bucket_cat.
	echo '
					<dt>
						<strong>', $txt['wartt_cat'], ':</strong><br>
						<span class="smalltext">', $txt['wartt_desc_cat'], '</span>
					</dt>
					<dd>
						<select name="bucket_cat" id="bucket_cat" required>
							<option value=""', ($context['wartt_rule_info']['bucket_cat'] == '' ? ' selected' : ''), '></option>
							<option value="ASN"', ($context['wartt_rule_info']['bucket_cat'] == 'ASN' ? ' selected' : ''), '>', $txt['wartt_bcdesc_asn'], '</option>
							<option value="Country"', ($context['wartt_rule_info']['bucket_cat'] == 'Country' ? ' selected' : ''), '>', $txt['wartt_bcdesc_country'], '</option>
							<option value="CIDR"', ($context['wartt_rule_info']['bucket_cat'] == 'CIDR' ? ' selected' : ''), '>', $txt['wartt_bcdesc_cidr'], '</option>
							<option value="Crawl_tgt"', ($context['wartt_rule_info']['bucket_cat'] == 'Crawl_tgt' ? ' selected' : ''), '>', $txt['wartt_bcdesc_crawl_target'], '</option>';

	echo '					</select>
					</dd>';

	// Bucket_type.
	echo '
					<dt>
						<strong>', $txt['wartt_type'], ':</strong><br>
						<span class="smalltext">', $txt['wartt_desc_type'], '</span>
					</dt>
					<dd>
						<select name="bucket_type" id="bucket_type">
							<option value=""', ($context['wartt_rule_info']['bucket_type'] == '' ? ' selected' : ''), '></option>
							<option value="server_var"', ($context['wartt_rule_info']['bucket_type'] == 'server_var' ? ' selected' : ''), '>', $txt['wartt_btdesc_servervar'], '</option>
							<option value="env_var"', ($context['wartt_rule_info']['bucket_type'] == 'env_var' ? ' selected' : ''), '>', $txt['wartt_btdesc_envvar'], '</option>';


	// If WALA tables here, allow lookup options...
	if (!empty($context['wartt_wala_populated']))
	{
		echo '
							<option value="lookup"', ($context['wartt_rule_info']['bucket_type'] == 'lookup' ? ' selected' : ''), '>', $txt['wartt_btdesc_lookup'], '</option>';
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
						<input type="text" name="bucket_var" id="bucket_var" value="', $context['wartt_rule_info']['bucket_var'], '" size="100" maxlength="100">
					</dd>';

	// Minutes.
	echo '
					<dt>
						<strong>', $txt['wartt_minutes'], ':</strong><br>
						<span class="smalltext">', $txt['wartt_desc_minutes'], '</span>
					</dt>
					<dd>
						<input type="number" name="minutes" style="width: 70px;" value="', $context['wartt_rule_info']['minutes'], '" min="10" max="1440" step="10" required>
					</dd>';

	// Threshold.
	echo '
					<dt>
						<strong>', $txt['wartt_threshold'], ':</strong><br>
						<span class="smalltext">', $txt['wartt_desc_threshold'], '</span>
					</dt>
					<dd>
						<input type="number" name="threshold" style="width: 80px;" value="', $context['wartt_rule_info']['threshold'], '" min="10" max="20000" step="10" required>
					</dd>';

	// Action.
	echo '
					<dt>
						<strong>', $txt['wartt_action'], ':</strong><br>
						<span class="smalltext">', $txt['wartt_desc_action'], '</span>
					</dt>
					<dd>
						<select name="wartt_action" id="wartt_action" required>
							<option value=""', ($context['wartt_rule_info']['action'] == '' ? ' selected' : ''), '></option>
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
						<input type="number" name="action_pct" id="action_pct" value="', $context['wartt_rule_info']['action_pct'], '" min="0" max="100" required>
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

	// insert js for edits here...
	wartt_js_edits();
}

/**
 * .js online edits - add & mod use same IDs, so this can be shared...
 */

function wartt_js_edits()
{
	global $context, $scripturl, $txt;

	echo '
<script>
	//# sourceURL=wartt_js_edits.js

	const bucket_cat_input = document.getElementById(\'bucket_cat\');
	const bucket_type_input = document.getElementById(\'bucket_type\');
	const bucket_var_input = document.getElementById(\'bucket_var\');
	const wartt_action_input = document.getElementById(\'wartt_action\');
	const action_pct_input = document.getElementById(\'action_pct\');

	// Functions used by listeners
	function do_cat_checks(event) {
		if (bucket_cat_input.value == \'CIDR\' || bucket_cat_input.value == \'Crawl_tgt\') {
			bucket_type_input.value = \'\';
			bucket_type_input.disabled = true;
			bucket_type_input.required = false;
			bucket_var_input.value = \'\';
			bucket_var_input.disabled = true;
			bucket_var_input.required = false;
		} else {
			bucket_type_input.disabled = false;
			bucket_type_input.required = true;
			bucket_var_input.disabled = false;
		}
	}

	function do_type_checks(event) {
		if (bucket_type_input.value == \'server_var\' || bucket_type_input.value == \'env_var\') {
			bucket_var_input.disabled = false;
			bucket_var_input.required = true;
		} else {
			bucket_var_input.value = \'\';
			bucket_var_input.disabled = true;
			bucket_var_input.required = false;
		}
	}

	function do_action_checks(event) {
		if (wartt_action_input.value == \'block\') {
			action_pct_input.disabled = false;
		} else {
			action_pct_input.value = 0;
			action_pct_input.disabled = true;
		}
	}

	// Do these now, setup entry form...
	do_cat_checks();
	do_type_checks();
	do_action_checks();

	// Listeners for input changes
	bucket_cat_input.addEventListener(\'input\', do_cat_checks);
	bucket_type_input.addEventListener(\'input\', do_type_checks);
	wartt_action_input.addEventListener(\'input\', do_action_checks);
</script>';
}