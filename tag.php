<?php
if (!function_exists('tag')) {
	class Tag {
		private $name = '';
		private $attributes = array();
		private $children = array();
		private $style = array();
		
		private function parseCSS($input) {
			$input = strpos($input, ';') > -1 ? explode($input, ';') : array($input);
			foreach ($input as $entry) {
				$m = explode(':', $entry, 2);
				if ($m)
					$this->style[trim($m[0])] = trim($m[1]);
			}
		}

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
				if ($value == 'style')
					return clone $this->style;
				else
					return $this->attributes[$name];
			if (isset($this->attributes['style'])) {
				$this->parseCSS($this->attributes['style']);
				unset($this->attributes['style']);
			}
			return $this;
		}
		
		public function css($name, $value = NULL) {
			if ($value !== NULL)
				$this->style[$name] = $value;
			elseif (is_array($name))
				$this->style = array_merge($this->style, $name);
			elseif (strpos($name, ':') > -1)
				$this->parseCSS($name);
			else
				return $this->style[$name];
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
			if (count($this->style)) {
				$css = array();
				foreach ($this->style as $key => $value) {
					if (!$value)
						continue;
					if (is_int($value))
						$value = "{$value}px";
					elseif (is_float($value))
						$value = "{$value}pt";
					elseif (is_array($value))
						$value = implode(' ', $value);
					else
						$value = strval($value);
					array_push($css, "{$key}: {$value}");
				}
				$css = implode('; ', $css);
				$result .= ' style="' . htmlentities2($css) . '"';
			}
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
?>
