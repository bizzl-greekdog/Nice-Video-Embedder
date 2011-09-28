<?php
/*
Plugin Name:	Nice Video Embedder
Plugin URI:		https://github.com/bizzl-greekdog/Nice-Video-Embedder
Description:	Adds a tab to the upload dialog and some shortcodes to make the embedding of videos from certain platforms easier.
Version:		2.6.0
Author:			Benjamin Kleiner
Author URI:		https://github.com/bizzl-greekdog
License:		LGPL3
*/
/*
    Copyright (c) 2011 Benjamin Kleiner <bizzl@users.sourceforge.net>
 
    This file is part of Nice Video Embedder.

    Nice Video Embedder is free software: you can redistribute it and/or modify
    it under the terms of the GNU Lesser General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Nice Video Embedder is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Lesser General Public License for more details.

    You should have received a copy of the GNU Lesser General Public License
    along with Nice Video Embedder. If not, see <http://www.gnu.org/licenses/>.
*/

if (!function_exists('join_path')) {

	// This is an implementation of pythons sys.path.join()
	// As the name suggest, join_path takes all arguments and joins
	// them using the directory separator.
	function join_path() {
		$fuck = func_get_args();
		$flat = (object)array('flat' => array());
		array_walk_recursive($fuck, create_function('&$v, $k, &$t', '$t->flat[] = $v;'), $flat);
		$f = implode(DIRECTORY_SEPARATOR, $flat->flat);
		return preg_replace('/(?<!:)\\' . DIRECTORY_SEPARATOR . '+/', DIRECTORY_SEPARATOR, $f);
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
	protected static $defaultWidth = 540;

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
		self::$defaultWidth = get_option("bizzl_nve_default_width", self::$defaultWidth);
		add_action('admin_init', array(__CLASS__, 'admin_init'));
		add_action('media_upload_fromvideoplatform', array(__CLASS__, 'menu_handle'));
		add_filter('media_upload_tabs', array(__CLASS__, 'media_menu'));
		add_shortcode('youtube', array(__CLASS__,  'shortcode_handler'));
		add_shortcode('vimeo', array(__CLASS__,  'shortcode_handler'));
		add_action('admin_enqueue_scripts', array(__CLASS__, 'init_scripts'));
		add_filter('query_vars', array(__CLASS__, 'query_vars'));
		add_action('parse_request',array(__CLASS__, 'do_preview'));
	}
	
	public static function admin_init() {
		add_settings_section('bizzl_nve', __('Nice Video Embedder', self::$domain), array(__CLASS__, 'settings_section'), 'media');
		add_settings_field('bizzl_nve_default_width', __('Default Size', self::$domain), array(__CLASS__, 'default_width_setting'), 'media', 'bizzl_nve');
		register_setting('media', 'bizzl_nve_default_width', 'intval');
	}
	
	public static function default_width_setting() {
		$g = new TagGroup();
		$g->append(label('bizzl_nve_default_width', __('Width', self::$domain)), "\n", tag('input')->attr(array(
			'type' => 'text',
			'value' => get_option("bizzl_nve_default_width", self::$defaultWidth),
			'id' => 'bizzl_nve_default_width',
			'name' => 'bizzl_nve_default_width'
		))->addClass('small-text'));
		echo $g;
	}
	
	public static function query_vars($v) {
		$v[] = 'nve-preview';	
		return $v;
	}
	
	public static function do_preview(&$wp) {
	    if (array_key_exists('nve-preview', $wp->query_vars)) {
	    	add_filter('the_content', array(__CLASS__, 'inject_preview'));
	    	add_filter('template_include', 'get_single_template');
	        require_once(ABSPATH . WPINC . '/template-loader.php');
	        exit();
	    }
    	return;
	}
	
	function inject_preview() {
		global $wp;
		list($href, $width, $height, $title) = explode('|', $wp->query_vars['nve-preview'], 4);
		$dimensions  = array('width' => $width, 'height' => $height);
		foreach (self::$rules as $key => $value) {
			if (preg_match($key, $href, &$matches) > 0) {
				$shortcode = nsprintf($value, array_merge($matches, $dimensions));
				break;
			}
		}
		if ($title) {
			$arguments = array_merge(array('title' => $title, 'content' => $shortcode), $dimensions);
			$shortcode = nsprintf('[caption id="%title%" width="%width%" caption="%title%"]%content%[/caption]', $arguments);
		}
		//echo pre($shortcode);
		echo do_shortcode($shortcode);
	}
	
	public static function init_scripts() {
		wp_register_script('jquery_bizzl_enabling', plugins_url('js/jquery.bizzl.enabling.min.js', __FILE__), array('jquery'), '1.0.0');
		wp_enqueue_script('jquery_bizzl_enabling');
	}

	public static function media_menu($tabs) {
		if ($_REQUEST['type'] != 'video')
			return $tabs;
		$newtab = array('fromvideoplatform' => __('From Video Platform', self::$domain));
		return array_merge($tabs, $newtab);
	}

	public static function media_process($url, $title, $width, $height) {
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
									tag('label')->attr('for', 'insertonly-href')->append(__('Video URL', self::$domain))
								),
								tag('span')->attr('class', 'alignright')->append(
									tag('abbr')->attr(array('title' => 'required', 'class' => 'required'))->append('*')
								)
							),
							tag('td')->attr('class', 'field')->append(
									tag('input')->attr(array(
										'id' => 'insertonly-href',
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
									tag('label')->attr('for', 'insertonly-title')->append(__('Title', self::$domain))
								),
								tag('span')->attr('class', 'alignright')->append(
									tag('abbr')->attr(array('title' => 'required', 'class' => 'required'))->append('')
								)
							),
							tag('td')->attr('class', 'field')->append(
									tag('input')->attr(array(
										'id' => 'insertonly-title',
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
									tag('label')->attr('for', 'insertonly-width')->append(__('Size', self::$domain))
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
										'aria-required' => 'true',
										'disabled' => 'disabled'
									)),
									__(' Pixels', self::$domain)
							)
						),
						tag('tr')->append(
							tag('th')->attr(array('valign' => 'top', 'scope' => 'row', 'class' => 'label'))->append(
								tag('span')->attr('class', 'alignleft')->append(
									tag('label')->attr('for', 'ratio')->append(__('Ratio', self::$domain))
								),
								tag('span')->attr('class', 'alignright')->append(
									tag('abbr')->attr(array('title' => 'required', 'class' => 'required'))->append('')
								)
							),
							tag('td')->attr('class', 'field')->append(
									tag('input')->attr(array(
										'name' => 'ratio',
										'type' => 'radio',
										'aria-required' => 'true',
										'checked' => 'checked',
										'value' => '/ 16 * 9'
									)),
									tag('span')->attr('class', 'radio-label')->append(__('16:9', self::$domain)),
									tag('span')->css(array('display' => 'inline-block', 'width' => 50))->append(' '),
									tag('input')->attr(array(
										'name' => 'ratio',
										'type' => 'radio',
										'aria-required' => 'true',
										'value' => '/ 4 * 3'
									)),
									tag('span')->attr('class', 'radio-label')->append(__('4:3', self::$domain)),
									tag('span')->css(array('display' => 'inline-block', 'width' => 50))->append(' '),
									tag('input')->attr(array(
										'id' => 'custom-ratio',
										'name' => 'ratio',
										'type' => 'radio',
										'aria-required' => 'true'
									)),
									tag('span')->attr('class', 'radio-label')->append(__('Custom Ratio', self::$domain))
							)
						),
						tag('tr')->css('display', 'none')->attr("id", "video-size-preview")->append(
							tag('th')->attr(array('valign' => 'top', 'scope' => 'row', 'class' => 'label'))->append(
								tag('span')->attr('class', 'alignleft')->append(
									tag('label')->attr('for', 'ratio')->append(__('Preview', self::$domain))
								),
								tag('span')->attr('class', 'alignright')->append(
									tag('abbr')->attr(array('title' => 'required', 'class' => 'required'))->append('')
								)
							),
							tag('td')->append(
								tag('a')->attr(array(
									'target' => '_blank',
									'data-base' => home_url() . '?nve-preview='
								))->append(__('Open', self::$domain))
							)
						)
					)
				)
			)
		);
		$form->append(_insert_into_post_button('video'));
		
		$form->append(tag('script')->attr('type', 'text/javascript')->append(
			"//<!--\n",
'
jQuery.noConflict()(function($) {
	function updateHeight() {
		var h = parseInt($("#insertonly-height").val());
		var r = $("input[type=radio]:checked");
		if (r.attr("id") != "custom-ratio") {
			h = Math.floor(eval($("#insertonly-width").val() + r.val()));
			$("#insertonly-height").val(h);
		}
		return h;
	}
	$("input[type=text]").bind("change keyup focus", function(event) {
		var h = updateHeight();
		var w = $("#insertonly-width").val();
		var v = $("#insertonly-href").val();
		var t = $("#insertonly-title").val();
		var base = $("#video-size-preview a").attr("data-base");
		if (v) {
			$("#video-size-preview a").attr("href", base + v + "|" + w + "|" + h + "|" + t);
			$("#video-size-preview").show();
		} else
			$("#video-size-preview").hide();
	});
	$("#fromvideoplatform-form").submit(function(event) {
		$("#insertonly-height").removeAttr("disabled");
		return true;
	});
	$("#fromvideoplatform-form input[type=radio]").click(function(e) {
		$("#insertonly-height").enabled($("#custom-ratio:checked").length);
		$("#insertonly-height, #insertonly-width").keyup();
	});
	$("#fromvideoplatform-form .radio-label").click(function(e) {
		$(this).prev("input").attr("checked", true).click();
	}).css({
		 MozUserSelect: "none",
		 KhtmlUserSelect: "none",
		 WebkitUserSelect: "none",
		 userSelect: "none"
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
			$dimensions = array('width' => $width, 'height' => $height);

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
			return wp_iframe(array(__CLASS__, 'media_process'), '', '', self::$defaultWidth, floor(self::$defaultWidth / 16 * 9));
		}
	}

	public static function shortcode_handler($atts, $content, $tag) {
		$result = '';
		$id = (isset($atts[0])) ? $atts[0] : '';
		$width = (isset($atts[1])) ? $atts[1] : self::$defaultWidth;
		$height = (isset($atts[2])) ? $atts[2] : self::$defaultWidth / 4 * 3;
		if ($tag == 'youtube') {
			$result = tag('iframe', true)->attr(array(
				'class' => 'youtube nve-video',
				'type' => 'text/html',
				'width' => $width,
				'height' => $height,
				'frameborder' => 0,
				'src' => "http://www.youtube.com/embed/{$id}"
			))->css(array(
				'width' => $width,
				'height' => $height,
			));
		} elseif ($tag == 'vimeo') {
			$result = tag('iframe', true)->attr(array(
				'class' => 'vimeo nve-video',
				'src' => 'http://player.vimeo.com/video/' . $id,
				'width' => $width,
				'height' => $height,
			))->css(array(
				'width' => $width,
				'height' => $height,
			));
		}
		return strval($result);
	}
}

Nice_Video_Embedder::init();

?>