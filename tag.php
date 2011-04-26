<?php
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
?>
