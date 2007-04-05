/***************************************************************************
 *
 *   Copyright : (C) 2006 Nicolas Grekas. All rights reserved.
 *   Email     : nicolas.grekas+patchwork@espci.org
 *   License   : http://www.gnu.org/licenses/gpl.txt GNU/GPL, see COPYING
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

$window = window;
$parent = parent;

/* push */
if (!([].push && [].push(0))) Array.prototype.push = function()
{
	var $this = this, $argv = $this.push.arguments, $i = 0;
	for (; $i < $argv.length ; ++$i) $this[$this.length] = $argv[$i];
	return $this.length;
}

$decodeURIComponent = $window.decodeURIComponent || function($string)
{
	var $dec = [],
		$len = $string.length,
		$i = 0,
		$c,
		$s = String.fromCharCode;

	function $nextCode() {return parseInt('0x' + $string.substr(++$i, 2, $i += 2)) - 128;}

	while ($i < $len)
	{
		if ('%' != $string.charAt($i)) $dec.push($string.charAt($i++));
		else
		{
			$c = $nextCode();

			$dec.push($s(
				  $c < 0
				? $c + 128
				: (
					$c < 96
					? ($c - 64 << 6) + $nextCode()
					: (
						$c < 112
						?  (($c -  96 << 6) + $nextCode() << 6) + $nextCode()
						: ((($c - 112 << 6) + $nextCode() << 6) + $nextCode() << 6) + $nextCode()
					)
				)
			));
		}
	}

	return $dec.join('');
}

$encodeURIComponent = $window.encodeURIComponent || function($string)
{
	var $c, $s, $i = 0, $enc = [], $preserved = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_.!~*'()";

	while ($i < $string.length)
	{
		$c = $string.charCodeAt($i++);

		if (56320 <= $c && $c < 57344) continue;

		if (55296 <= $c && $c < 56320)
		{
			if ($i >= $string.length) continue;

			$s = $string.charCodeAt($i++);
			if ($s < 56320 || $c >= 56832) continue;

			$c = ($c - 55296 << 10) + $s + 9216;
		}

		$s = String.fromCharCode;
		$enc.push(
			  $c < 128
			? $s($c)
			: (
				$c < 2048
				? $s(192 + ($c >> 6), 128 + ($c & 63))
				: (
					$c < 65536
					? $s(224 + ($c >> 12), 128 + ($c >>  6 & 63), 128 + ($c & 63))
					: $s(240 + ($c >> 18), 128 + ($c >> 12 & 63), 128 + ($c >> 6 & 63), 128 + ($c & 63))
				)
			)
		);
	}

	$enc = $enc.join('');
	$string = [];

	for ($i = 0; $i<$enc.length; ++$i) $string.push(
		  -1 == $preserved.indexOf($enc.charAt($i))
		? '%'+$enc.charCodeAt($i).toString(16).toUpperCase()
		: $enc.charAt($i)
	);

	return $string.join('');
}

$glue = 'Z%Y';
$name = $window.name.split($glue);

if (3 == $name.length && 'undefined' != typeof $window.q)
{
	$window.name += $glue + $encodeURIComponent(q);
	location.replace($decodeURIComponent($name[2]));
}
else if ($parent && $parent.loadQJsrs)
{
	if (4 == $name.length)
	{
		$window.name = $decodeURIComponent($name[1]);
		q = $decodeURIComponent($name[3]);
	}

	if ('undefined' != typeof $window.q) $parent.loadQJsrs($window, q);
	else
	{
		q = $parent.loadQJsrs($window).q;

		if (q && q.length)
		{
			if (!q[4]) $window.name = $glue + $encodeURIComponent($window.name) + $glue + $encodeURIComponent(location);

			if (q[3])
			{
				$document = document;
				$value = q[2];

				$form = ['<form accept-charset="UTF-8" method="post">'];
				for ($i in $value) if ('function' != typeof $value[$i]) $form.push('<input />');
				$document.write($form.join('') + '</form>');

				onload = function()
				{
					$form = $document.forms[0];
					$form.action = q[0];

					for ($i in $value) if ('function' != typeof $value[$i])
						$elt = $form[$document++],
						$elt.name = $i,
						$elt.value = $value[$i],
						++$i;

					$window = $parent = q = $document = $form = $form.submit();
				}
			}
			else $window = $parent = q = location.replace(q[0] + q[1]);
		}
	}
}
