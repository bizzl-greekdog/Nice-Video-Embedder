<?php
/*
Plugin Name: Wordpress Video Plugin Helper
Plugin URI: 
Description: Adds a tab to the upload dialog to make the use of the video plugin easier.
Version: 1.0.0
Author: Benjamin Kleiner <bizzl@users.sourceforge.net>
Author URI: 
License: LGPL3
*/

function fromvideoplatform_media_menu($tabs) {
	$newtab = array('fromvideoplatform' => __('From Video Platform', 'fromvideoplatform'));
	return array_merge($tabs, $newtab);
}
add_filter('media_upload_tabs', 'fromvideoplatform_media_menu');

function media_fromvideoplatform_form() {
 	media_upload_header();
	$post_id = intval($_REQUEST['post_id']);

	$form_action_url = admin_url("media-upload.php?tab=fromvideoplatform&post_id=$post_id");
	$form_action_url = apply_filters('media_upload_form_url', $form_action_url, $type);

	$callback = "media_fromvideoplatgorm_process";
	printf('<form enctype="multipart/form-data" method="post" action="%s" class="media-upload-form type-form validate" id="fromvideoplatform-form">', esc_attr($form_action_url));
	printf('<input type="hidden" name="post_id" id="post_id" value="%s" />', $post_id);
	wp_nonce_field('media-form');
	printf('<h3 class="media-title">%s</h3>', __('Embed Video from Platform', 'fromvideoplatform'));
	echo '
	<div id="media-items">
		<div class="media-item media-blank">
			<table class="describe"><tbody>
				<tr>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="insertonly[href]">' . __('Video URL') . '</label></span>
						<span class="alignright"><abbr title="required" class="required">*</abbr></span>
					</th>
					<td class="field"><input id="insertonly[href]" name="insertonly[href]" value="" type="text" aria-required="true"></td>
				</tr>
				<tr>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="insertonly[title]">' . __('Title') . '</label></span>
						<span class="alignright"><abbr title="required" class="required">*</abbr></span>
					</th>
					<td class="field"><input id="insertonly[title]" name="insertonly[title]" value="" type="text" aria-required="true"></td>
				</tr>
				<tr><td></td><td class="help">' . __('Title text, e.g. &#8220;Lucy on YouTube&#8221;') . '</td></tr>
			' . _insert_into_post_button('video') . '
			</tbody></table>
		</div>
	</div>
</form>';
}

function fromvideoplatform_menu_handle() {
	return wp_iframe('media_fromvideoplatform_form');
}
add_action('media_upload_fromvideoplatform', 'fromvideoplatform_menu_handle');
?>
 
