<?php

class pipe_js
{
	static function php($string, $forceString = false)
	{
		$string = CIA::string($string);

		return $forceString || (string) $string !== (string) ($string-0)
			? ("'" . str_replace(
					array('&#039;', '&quot;', '&gt;', '&lt;', '&amp;', '\\'  , "'"  , "\r" , "\n" , '</' ),
					array("'"     , '"'     , '>'   , '<'   , '&'    , '\\\\', "\\'", '\\r', '\\n', '<\\/'),
					$string
				) . "'"
			)
			: $string;
	}

	static function js()
	{
		?>/*<script>*/

P$<?php echo substr(__CLASS__, 5)?> = function($string, $forceString)
{
	$string = str($string);

	return $forceString || (''+$string/1 != $string)
		? ("'" + $string.replace(
				/&#039;/g, "'").replace(
				/&quot;/g, '"').replace(
				/&gt;/g  , '>').replace(
				/&lt;/g  , '<').replace(
				/&amp;/g , '&').replace(
				/\\/g , '\\\\').replace(
				/'/g  , "\\'").replace(
				/\r/g , '\\r').replace(
				/\n/g , '\\n').replace(
				/<\//g, '<\\/'
			) + "'"
		)
		: $string/1;
}

<?php	}
}
