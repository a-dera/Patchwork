<?php

class pipe_echo
{
	static function php($format = '')
	{
		$args = func_get_args();

		if ($format == '') $args = implode('', $args);
		else $args = preg_replace("'%([0-9])'eu", 'CIA::string(@$args[$1+1])', CIA::string($format));

		return $args;
	}

	static function js()
	{
		?>/*<script>*/

P$<?php echo substr(__CLASS__, 5)?> = function($format)
{
	$format = str($format);

	var $args = arguments, $i = 1, $firstChar;

	if ($format != '')
	{
		$format = $format.split('%');

		for (; $i<$format.length; ++$i)
		{
			$firstChar = $format[$i].substr(0, 1);
			if ($firstChar.length && $firstChar != '%')
			{
				if (0 <= $firstChar && $firstChar <= 9) $format[$i] = str($args[$firstChar/1+1]) + $format[$i].substr(1);
				else $format[$i] = '%' + $format[$i];
			}
		}
	}
	else
	{
		$format = [];
		for (; $i<$args.length; ++$i) $format[$i] = $args[$i];
	}

	return $format.join('');
}

<?php	}
}
