<?php

if (!defined("ABSPATH")) exit;

if (is_admin()) {
	add_action('admin_enqueue_scripts', 'altcha_awsmteam_enqueue_admin_scripts');
	add_action('add_meta_boxes', 'altcha_awsmteam_add_meta_box');
	add_action('save_post_awsm_team_member', 'altcha_awsmteam_save_member_obfuscation_meta', 10, 2);
}

function altcha_awsmteam_add_meta_box()
{
	if (!function_exists('add_meta_box')) return;
	add_meta_box(
		'altcha-obfuscation',
		__('Altcha Obfuscation', 'altcha'),
		'altcha_awsmteam_member_obfuscation_fields',
		'awsm_team_member',
		'side'
	);
}

function altcha_awsmteam_is_member_edit_screen()
{
	if (!function_exists('get_current_screen')) return false;
	$screen = get_current_screen();
	if (!$screen) return false;
	if ($screen->post_type !== 'awsm_team_member') return false;
	return in_array($screen->base, ['post', 'post-new'], true);
}

function altcha_awsmteam_enqueue_admin_scripts($hook)
{
	if (altcha_awsmteam_is_member_edit_screen()) {
		wp_enqueue_script(
			'altcha-awsmteam-admin',
			ALTCHA_PLUGIN_URL . 'public/integrations/awsmteam-admin.js',
			[],
			'1.0',
			true
		);
		wp_enqueue_style(
			'altcha-awsmteam-admin',
			ALTCHA_PLUGIN_URL . 'public/integrations/awsmteam-admin.css',
			[],
			'1.0'
		);
	}
}

function altcha_awsmteam_member_obfuscation_fields()
{
	if (!altcha_awsmteam_is_member_edit_screen()) {
		return;
	}

	global $post;
	if (!$post || empty($post->ID)) {
		return;
	}

	$enabled_raw = get_post_meta($post->ID, '_altcha_obfuscation_enabled', true);
	$enabled = $enabled_raw === '' ? true : (bool)intval($enabled_raw);
	$label = get_post_meta($post->ID, '_altcha_obfuscation_label', true);
	$label = is_string($label) ? trim($label) : '';
	if ($label === '') {
		$label = 'Click to view Socials';
	}

	$label = sanitize_text_field($label);
	$label_readonly = $enabled ? '' : 'readonly';

?>
	<div id="altcha-obfuscation-settings">
		<input type="hidden" name="altcha_awsmteam_member_nonce" value="<?php echo wp_create_nonce('altcha_awsmteam_member_meta'); ?>" />
		<table>
			<thead>
				<tr>
					<td width="25%">Altcha Obfuscation</td>
					<td width="75%">Label</td>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>
						<label class="screen-reader-text" for="altcha-obfuscation-toggle">Altcha Obfuscation</label>
						<select id="altcha-obfuscation-toggle" name="_altcha_obfuscation_enabled">
							<option value="enabled" <?php selected($enabled); ?>>Enabled</option>
							<option value="disabled" <?php selected(!$enabled); ?>>Disabled</option>
						</select>
					</td>
					<td>
						<label class="screen-reader-text" for="altcha-obfuscation-label">Label</label>
						<input type="text" class="widefat" id="altcha-obfuscation-label" name="_altcha_obfuscation_label" value="<?php echo esc_attr($label); ?>" <?php echo $label_readonly; ?> placeholder="Click to view Socials" />
					</td>
				</tr>
			</tbody>
		</table>
	</div>
<?php
}

function altcha_awsmteam_save_member_obfuscation_meta($post_id, $post)
{
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (!isset($_POST['altcha_awsmteam_member_nonce']) || !wp_verify_nonce(sanitize_key($_POST['altcha_awsmteam_member_nonce']), 'altcha_awsmteam_member_meta')) return;

	$post_type = get_post_type_object($post->post_type);
	if (!$post_type || !current_user_can($post_type->cap->edit_post, $post_id)) return;

	$enabled = isset($_POST['_altcha_obfuscation_enabled']) && $_POST['_altcha_obfuscation_enabled'] === 'enabled' ? '1' : '0';
	update_post_meta($post_id, '_altcha_obfuscation_enabled', $enabled);

	$label = '';
	if (isset($_POST['_altcha_obfuscation_label'])) {
		$label = sanitize_text_field(wp_unslash($_POST['_altcha_obfuscation_label']));
	}

	if ($label === '') {
		delete_post_meta($post_id, '_altcha_obfuscation_label');
	} else {
		update_post_meta($post_id, '_altcha_obfuscation_label', $label);
	}
}
