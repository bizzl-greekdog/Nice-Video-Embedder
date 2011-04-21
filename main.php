<?php
/*
Plugin Name: Wordpress Video Plugin Helper
Plugin URI: 
Description: Adds a tab to the upload dialog to make the use of the video plugin easier.
Version: 2.0.0
Author: Benjamin Kleiner <bizzl@users.sourceforge.net>
Author URI: 
License: LGPL3
*/

if (!function_exists('join_path')) {
	function join_path() {
		return implode(DIRECTORY_SEPARATOR, func_get_args());
	}
}

if (!function_exists('nsprintf')) {
	function nsprintf($subject, $arguments) {
		foreach ($arguments as $key => $value)
			$subject = str_replace ("%$key%", $value, $subject);
		return $subject;
	}
}

if (!function_exists('tag')) {
	class Tag {
		private $name = '';
		private $attributes = array();
		private $children = array();

		public function __construct($tagName) {
	//		parent::__construct();
			$this->name = $tagName;
		}

		public function attr($name, $value = NULL) {
			if ($value !== NULL)
				$this->attributes[$name] = $value;
			elseif (is_array($name))
				$this->attributes = array_merge($this->attributes, $name);
			else
				return $this->attributes[$name];
			return $this;
		}

		public function append($child) {
			if (func_num_args () > 1)
				$child = func_get_args();
			if (is_array($child))
				$this->children = array_merge($this->children, $child);
			else
				array_push($this->children, $child);
			return $this;
		}

		public function __toString() {
			$result = '<' . $this->name;
			foreach ($this->attributes as $key => $value)
					$result .= ' ' . $key . '="' . htmlentities2($value) . '"';

			if (count($this->children)) {
				$result .= '>';
				foreach ($this->children as $child)
						$result .= $child;
				$result .= "</{$this->name}>";
			} else
				$result .= ' />';
			return $result;
		}
	}

	function tag($tagName) {
		return new Tag($tagName);
	}
}

class Wordpress_Video_Plugin_Helper {

	protected static $domain = 'wordpress-video-plugin-helper';
	protected static $base = '';
	protected static $rules = array(
		'#youtube\.com/watch\?.*v=(?P<ID>.{11})#i' => '[youtube %ID%]',
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
	}

	public static function media_menu($tabs) {
		if ($_REQUEST['type'] != 'video')
			return $tabs;
		$newtab = array('fromvideoplatform' => __('From Video Platform', self::$domain));
		return array_merge($tabs, $newtab);
	}

	public static function media_process($url, $title) {
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
						)
					)
				)
			)
		);
		$form->append(_insert_into_post_button('video'));

		echo $form;
	}

	public static function menu_handle() {
		if ( !empty($_POST['insertonlybutton']) && !empty($_POST['insertonly']['href']) ) {
			$shortcode = '';
			$matches = array();
			$dimensions = array('width' => 400, 'height' => 300);
			extract($_POST['insertonly']);
//			$title = $_POST['insertonly']['title'];
//			$href = $_POST['insertonly']['href'];

			foreach (self::$rules as $key => $value) {
				if (preg_match($key, $href, &$matches) > 0) {
					$shortcode = nsprintf($value, array_merge($matches, $dimensions));
					break;
				}
			}

			if (!$shortcode)
				return wp_iframe(array(__CLASS__, 'media_process'), $href, $title);

			if ($title) {
				$arguments = array_merge(array('title' => $title, 'content' => $shortcode), $dimensions);
				$shortcode = nsprintf('[caption id="%title%" width="%width%" caption="%title%"]%content%[/caption]', $arguments);
			}

			return media_send_to_editor($shortcode);
		} else {
			return wp_iframe(array(__CLASS__, 'media_process'), '', '');
		}
	}

}

Wordpress_Video_Plugin_Helper::init();

?>
 
