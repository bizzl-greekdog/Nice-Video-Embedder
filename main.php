<?php
/*
Plugin Name: Nice Video Embedder
Plugin URI: 
Description: Adds a tab to the upload dialog and some shortcodes to make the embedding of videos easier.
Version: 2.5.0
Author: Benjamin Kleiner <bizzl@users.sourceforge.net>
Author URI: 
License: LGPL3
*/

if (!function_exists('join_path')) {
	function join_path() {
		$fuck = func_get_args();
		return implode(DIRECTORY_SEPARATOR, $fuck);
	}
}

if (!function_exists('nsprintf')) {
	function nsprintf($subject, $arguments) {
		foreach ($arguments as $key => $value)
			$subject = str_replace ("%$key%", $value, $subject);
		return $subject;
	}
}

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tag.php');

class Nice_Video_Embedder {

	protected static $domain = 'nice-video-embedder';
	protected static $base = '';
	protected static $rules = array(
		'#youtube\.com/watch\?.*v=(?P<ID>.{11})#i' => '[youtube %ID% %width% %height%]',
		'#vimeo\.com/(clip:)?(?P<ID>[0-9]+)#i' => '[vimeo %ID% %width% %height%]',
	);

	protected static function init_base() {
		self::$base = basename(dirname(__FILE__));
	}

	protected static function init_l10n() {
		$j = join_path(self::$base, 'locale');
		load_plugin_textdomain(self::$domain, false, $j);
	}
	
	public static function init() {
		self::init_base();
		self::init_l10n();
		add_action('media_upload_fromvideoplatform', array(__CLASS__, 'menu_handle'));
		add_filter('media_upload_tabs', array(__CLASS__, 'media_menu'));
		add_shortcode('youtube', array(__CLASS__,  'shortcode_handler'));
		add_shortcode('vimeo', array(__CLASS__,  'shortcode_handler'));
	}

	public static function media_menu($tabs) {
		if ($_REQUEST['type'] != 'video')
			return $tabs;
		$newtab = array('fromvideoplatform' => __('From Video Platform', self::$domain));
		return array_merge($tabs, $newtab);
	}

	public static function media_process($url, $title, $width, $height) {
		wp_enqueue_script('jquery-ui-resizable');
		media_upload_header();
		$post_id = intval($_REQUEST['post_id']);

		$form_action_url = admin_url("media-upload.php?type=video&tab=fromvideoplatform&post_id=$post_id");
		$form_action_url = apply_filters('media_upload_form_url', $form_action_url, $type);

		$form = tag('form')->attr(array(
			'enctype' => 'multipart/form-data',
			'method' => 'post',
			'action' => esc_attr($form_action_url),
			'class' => 'media-upload-form type-form validate',
			'id' => 'fromvideoplatform-form'
		));
		$form->append(tag('input')->attr(array(
			'type' => 'hidden',
			'name' => 'post_id',
			'id' => 'post_id',
			'value' => $post_id
		)));
		$form->append(wp_nonce_field('media-form'), tag('h3')->attr('class', 'media-title')->append(__('Embed Video from Platform', self::$domain)));

		if ($url)
			$form->attr('class', 'error')->append(sprintf(__('<em>%s</em> belongs to no supported video platform'), $url));
		$form->append(
			tag('div')->attr('id', 'media-items')->append(
				tag('div')->attr('class', 'media-item media-blan')->append(
					tag('table')->attr('class', 'describe')->append(
						tag('tr')->append(
							tag('th')->attr(array('valign' => 'top', 'scope' => 'row', 'class' => 'label'))->append(
								tag('span')->attr('class', 'alignleft')->append(
									tag('label')->attr('for', 'insertonly[href]')->append(__('Video URL', self::$domain))
								),
								tag('span')->attr('class', 'alignright')->append(
									tag('abbr')->attr(array('title' => 'required', 'class' => 'required'))->append('*')
								)
							),
							tag('td')->attr('class', 'field')->append(
									tag('input')->attr(array(
										'id' => 'insertonly[href]',
										'name' => 'insertonly[href]',
										'value' => $url,
										'type' => 'text',
										'aria-required' => 'true'
									))
							)
						),
						tag('tr')->append(
							tag('th')->attr(array('valign' => 'top', 'scope' => 'row', 'class' => 'label'))->append(
								tag('span')->attr('class', 'alignleft')->append(
									tag('label')->attr('for', 'insertonly[title]')->append(__('Title', self::$domain))
								),
								tag('span')->attr('class', 'alignright')->append(
									tag('abbr')->attr(array('title' => 'required', 'class' => 'required'))->append('')
								)
							),
							tag('td')->attr('class', 'field')->append(
									tag('input')->attr(array(
										'id' => 'insertonly[title]',
										'name' => 'insertonly[title]',
										'value' => $title,
										'type' => 'text',
										'aria-required' => 'true'
									))
							)
						),
						tag('tr')->append(
							tag('td')->append('&nbsp;'),
							tag('td')->attr('class', 'help')->append(__('Title text, e.g. &#8220;Lucy on YouTube&#8221;', self::$domain))
						),
						tag('tr')->append(
							tag('th')->attr(array('valign' => 'top', 'scope' => 'row', 'class' => 'label'))->append(
								tag('span')->attr('class', 'alignleft')->append(
									tag('label')->attr('for', 'insertonly[width]')->append(__('Size', self::$domain))
								),
								tag('span')->attr('class', 'alignright')->append(
									tag('abbr')->attr(array('title' => 'required', 'class' => 'required'))->append('*')
								)
							),
							tag('td')->attr('class', 'field')->append(
									tag('input')->attr(array(
										'id' => 'insertonly-width',
										'name' => 'insertonly[width]',
										'value' => $width,
										'style' => 'width: auto',
										'type' => 'text',
										'aria-required' => 'true'
									)),
									__(' by ', self::$domain),
									tag('input')->attr(array(
										'id' => 'insertonly-height',
										'name' => 'insertonly[height]',
										'value' => $height,
										'style' => 'width: auto',
										'type' => 'text',
										'aria-required' => 'true'
									)),
									__(' Pixels', self::$domain)
							)
						),
						tag('tr')->append(
							tag('td')->attr('colspan', 2)->append(
								tag('div')->attr(array(
									'class' => 'error',
									'id' => 'video-size-preview'
								))->css(array(
									'width' => $width,
									'height' => $height,
									'margin' => '0 auto',
									'text-align' => 'center',
									'overflow' => 'hidden'
								))->append(tag('span')->css('vertical-align', 'middle')->append(__('Preview', self::$domain)))
							)
						)
					)
				)
			)
		);
		$form->append(_insert_into_post_button('video'));
		
		$form->append(tag('script')->attr('type', 'text/javascript')->append(
			"//<!--\n",
'var $j = jQuery.noConflict();
$j(function() {
	$j("#insertonly-height, #insertonly-width").keyup(function(event) {
		var h = parseInt($j("#insertonly-height").attr("value"));
		var w = parseInt($j("#insertonly-width").attr("value"));
		$j("#video-size-preview").height(h);
		$j("#video-size-preview").width(w);
	});
	$j("#fromvideoplatform-form").submit(function(event) {
		$j("#insertonly-height").attr("value", $j("#video-size-preview").height());
		$j("#insertonly-width").attr("value", $j("#video-size-preview").width());
		return true;
	});
});',
			"\n//-->"
		));

		echo $form;
	}

	public static function menu_handle() {
		if ( !empty($_POST['insertonlybutton']) ) {
			$shortcode = '';
			$matches = array();
			extract($_POST['insertonly']);
			error_log("href: $href ; title: $title ; width: $width; height: $height");
			$dimensions = array('width' => $width, 'height' => $height);
//			$title = $_POST['insertonly']['title'];
//			$href = $_POST['insertonly']['href'];

			foreach (self::$rules as $key => $value) {
				if (preg_match($key, $href, &$matches) > 0) {
					$shortcode = nsprintf($value, array_merge($matches, $dimensions));
					break;
				}
			}

			if (!$shortcode)
				return wp_iframe(array(__CLASS__, 'media_process'), $href, $title, $width, $height);

			if ($title) {
				$arguments = array_merge(array('title' => $title, 'content' => $shortcode), $dimensions);
				$shortcode = nsprintf('[caption id="%title%" width="%width%" caption="%title%"]%content%[/caption]', $arguments);
			}

			return media_send_to_editor($shortcode);
		} else {
			return wp_iframe(array(__CLASS__, 'media_process'), '', '', 400, 300);
		}
	}

	public static function shortcode_handler($atts, $content, $tag) {
		$result = '';
		$id = (isset($atts[1])) ? $atts[0] : '';
		$width = (isset($atts[2])) ? $atts[1] : 400;
		$height = (isset($atts[3])) ? $atts[2] : 300;
		if ($tag == 'youtube') {
			$result = tag('object')->attr('style', "height: {$height}px; width: {$height}px")->append(
					tag('param')->attr('name', 'movie')->attr('value', "http://www.youtube.com/v/{$id}?version=3"),
					tag('param')->attr('name', 'allowFullScreen')->attr('value', 'true'),
					tag('param')->attr('name', 'allowScriptAccess')->attr('value', 'always'),
					tag('embed')->attr(array(
						'src' => "http://www.youtube.com/v/{$id}?version=3",
						'type' => 'application/x-shockwave-flash',
						'allowfullscreen' => 'true',
						'allowScriptAccess' => 'always',
						'width' => $width,
						'height' => $height
					))
			);
		} elseif ($tag == 'vimeo') {
			$result = tag('iframe')->attr(array(
				'src' => 'http://player.vimeo.com/video/' . $id,
				'width' => $width,
				'height' => $height,
			))->append(' ');
		}
		return strval($result);
	}
}

Nice_Video_Embedder::init();

?>