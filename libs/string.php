<?php
/* SVN FILE: $Id$ */
/**
 * String handling methods.
 *
 *
 * PHP versions 4 and 5
 *
 * CakePHP(tm) :  Rapid Development Framework <http://www.cakephp.org/>
 * Copyright 2005-2008, Cake Software Foundation, Inc.
 *			1785 E. Sahara Avenue, Suite 490-204
 *			Las Vegas, Nevada 89104
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @filesource
 * @copyright		Copyright 2005-2008, Cake Software Foundation, Inc.
 * @link				http://www.cakefoundation.org/projects/info/cakephp CakePHP(tm) Project
 * @package			cake
 * @subpackage		cake.cake.libs
 * @since			CakePHP(tm) v 1.2.0.5551
 * @version			$Revision$
 * @modifiedby		$LastChangedBy$
 * @lastmodified	$Date$
 * @license			http://www.opensource.org/licenses/mit-license.php The MIT License
 */
/**
 * String handling methods.
 *
 *
 * @package		cake
 * @subpackage	cake.cake.libs
 */
class String extends Object {
/**
 * Gets a reference to the String object instance
 *
 * @return object String instance
 * @access public
 * @static
 */
	function &getInstance() {
		static $instance = array();

		if (!isset($instance[0]) || !$instance[0]) {
			$instance[0] =& new String();
		}
		return $instance[0];
	}
/**
 * Generate a random UUID
 *
 * @see http://www.ietf.org/rfc/rfc4122.txt
 * @return RFC 4122 UUID
 * @static
 */
	function uuid() {
		$node = env('SERVER_ADDR');
		$pid = null;

		if (strpos($node, ':') !== false) {
			if (substr_count($node, '::')) {
				$node = str_replace('::', str_repeat(':0000', 8 - substr_count($node, ':')) . ':', $node);
			}
			$node = explode(':', $node) ;
			$ipv6 = '' ;

			foreach ($node as $id) {
				$ipv6 .= str_pad(base_convert($id, 16, 2), 16, 0, STR_PAD_LEFT);
			}
			$node =  base_convert($ipv6, 2, 10);

			if (strlen($node) < 38) {
				$node = null;
			} else {
				$node = crc32($node);
			}
		} elseif (empty($node)) {
			$host = env('HOSTNAME');

			if (empty($host)) {
				$host = env('HOST');
			}

			if (!empty($host)) {
				$ip = gethostbyname($host);

				if ($ip === $host) {
					$node = crc32($host);
				} else {
					$node = ip2long($ip);
				}
			}
		} elseif ($node !== '127.0.0.1') {
			$node = ip2long($node);
		} else {
			$node = null;
		}

		if (empty($node)) {
			$node = crc32(Configure::read('Security.salt'));
		}

		if (function_exists('zend_thread_id')) {
			$pid = zend_thread_id();
		} else {
			$pid = getmypid();
		}

		if (!$pid || $pid > 65535) {
			$pid = mt_rand(0, 0xfff) | 0x4000;
		}

		list($timeMid, $timeLow) = explode(' ', microtime());
		$uuid = sprintf("%08x-%04x-%04x-%02x%02x-%04x%08x", (int)$timeLow, (int)substr($timeMid, 2) & 0xffff,
					mt_rand(0, 0xfff) | 0x4000, mt_rand(0, 0x3f) | 0x80, mt_rand(0, 0xff), $pid, $node);

		return $uuid;
	}
/**
 * Tokenizes a string using $separator, ignoring any instance of $separator that appears between $leftBound
 * and $rightBound
 *
 * @param string $data The data to tokenize
 * @param string $separator The token to split the data on
 * @return string
 * @access public
 * @static
 */
	function tokenize($data, $separator = ',', $leftBound = '(', $rightBound = ')') {
	    if(empty($data) || is_array($data)) {
	        return $data;
	    }

		$depth = 0;
		$offset = 0;
		$buffer = '';
		$results = array();
		$length = strlen($data);

		while ($offset <= $length) {
			$tmpOffset = -1;
			$offsets = array(strpos($data, $separator, $offset), strpos($data, $leftBound, $offset), strpos($data, $rightBound, $offset));
			for ($i = 0; $i < 3; $i++) {
				if ($offsets[$i] !== false && ($offsets[$i] < $tmpOffset || $tmpOffset == -1)) {
					$tmpOffset = $offsets[$i];
				}
			}
			if ($tmpOffset !== -1) {
				$buffer .= substr($data, $offset, ($tmpOffset - $offset));
				if ($data{$tmpOffset} == $separator && $depth == 0) {
					$results[] = $buffer;
					$buffer = '';
				} else {
					$buffer .= $data{$tmpOffset};
				}
				if ($data{$tmpOffset} == $leftBound) {
					$depth++;
				}
				if ($data{$tmpOffset} == $rightBound) {
					$depth--;
				}
				$offset = ++$tmpOffset;
			} else {
				$results[] = $buffer . substr($data, $offset);
				$offset = $length + 1;
			}
		}
		if (empty($results) && !empty($buffer)) {
			$results[] = $buffer;
		}

		if (!empty($results)) {
			$data = array_map('trim', $results);
		} else {
			$data = array();
		}
		return $data;
	}
/**
 * Replaces variable placeholders inside a $str with any given $data. Each key in the $data array corresponds to a variable
 * placeholder name in $str. Example:
 *
 * Sample: String::insert('My name is :name and I am :age years old.', array('name' => 'Bob', '65'));
 * Returns: My name is Bob and I am 65 years old.
 *
 * Available $options are:
 * 	before: The character or string in front of the name of the variable placeholder (Defaults to ':')
 * 	after: The character or string after the name of the variable placeholder (Defaults to null)
 * 	escape: The character or string used to escape the before character / string (Defaults to '\')
 * 	format: A regex to use for matching variable placeholders. Default is: '/(?<!\\)\:%s/' (Overwrites before, after, breaks escape / clean)
 * 	clean: A boolean, if set to true all variable placeholders that were not overwritten with $data items are going to be removed, including whitespace around them.
 *
 * @param string $str A string containing variable placeholders
 * @param string $data A key => val array where each key stands for a placeholder variable name to be replaced with val
 * @param string $options An array of options, see description above
 * @return string
 * @access public
 */
	function insert($str, $data, $options = array()) {
		$options = array_merge(array(
			'before' => ':',
			'after' => null,
			'escape' => '\\',
			'format' => null,
			'clean' => false), $options);

		$format = $options['format'];
		if (!isset($format)) {
			$format = sprintf(
				'/(?<!%s)%s%%s%s/',
				preg_quote($options['escape'], '/'),
				str_replace('%', '%%', preg_quote($options['before'], '/')),
				str_replace('%', '%%', preg_quote($options['after'], '/'))
			);
		}

		foreach ($data as $key => $val) {
			$key = sprintf($format, preg_quote($key, '/'));
			$str = preg_replace($key, $val, $str);
		}
		if (!isset($options['format']) && isset($options['before'])) {
			$str = str_replace($options['escape'].$options['before'], $options['before'], $str);
		}
		if ($options['clean']) {
			$str = preg_replace(sprintf('/(%s[^\s]+[\s]*|[\s]*%s[^\s]+)/', $options['before'], $options['before']), '', $str);
		}
		return $str;
	}
}
?>