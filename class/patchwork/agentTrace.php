<?php /*********************************************************************
 *
 *   Copyright : (C) 2007 Nicolas Grekas. All rights reserved.
 *   Email     : p@tchwork.org
 *   License   : http://www.gnu.org/licenses/agpl.txt GNU/AGPL
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU Affero General Public License as
 *   published by the Free Software Foundation, either version 3 of the
 *   License, or (at your option) any later version.
 *
 ***************************************************************************/


class extends patchwork
{
	static function resolve($agent)
	{
		static $cache = array();

		if (isset($cache[$agent])) return $cache[$agent];
		else $cache[$agent] =& $trace;

		$args = array();
		$BASE = p::__BASE__();

		$agent = rawurlencode($agent);
		$agent = strtr($agent, array(
			'%21'=>'!','%7E'=>'~','%2A'=>'*','%28'=>'(','%29'=>')','%2C'=>',',
			'%2F'=>'/','%3A'=>':','%40'=>'@','%24'=>'$','%3B'=>';'
		));

		$agent = p::base($agent, true);
		$agent = preg_replace("'^.*?://[^/]*'", '', $agent);

		$h = patchwork_http_socket($_SERVER['SERVER_ADDR'], $_SERVER['SERVER_PORT'], isset($_SERVER['HTTPS']));

		$keys  = p::$lang;
		$keys  = "GET {$agent}?p:=k:{$keys} HTTP/1.0\r\n";
		$keys .= "Host: {$_SERVER['HTTP_HOST']}\r\n";
		$keys .= "Connection: close\r\n\r\n";

		fwrite($h, $keys);
		$keys = array();
		while (false !== $a = fgets($h)) $keys[] = $a;
		fclose($h);

		$h = '\'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'';
		$h = "/w\.k\((-?[0-9]+),($h),($h),($h),\[((?:$h(?:,$h)*)?)\]\)/su";

		if (!preg_match($h, implode('', $keys), $keys))
		{
			W('Error while getting meta info data for ' . htmlspecialchars($agent));
			p::disable(true);
		}

		$appId = (int) $keys[1];
		$base = stripcslashes(substr($keys[2], 1, -1));
		$agent = stripcslashes(substr($keys[3], 1, -1));
		$a = stripcslashes(substr($keys[4], 1, -1));
		$keys = eval('return array(' . $keys[5] . ');');

		if ('' !== $a)
		{
			$args['__0__'] = $a;

			$i = 0;
			foreach (explode('/', $a) as $a) $args['__' . ++$i . '__'] = $a;
		}

		if ($base === $BASE) $appId = $base = false;
		else p::watch('foreignTrace');

		return $trace = array($appId, $base, $agent, $keys, $args);
	}

	static function send($agent)
	{
		header('Content-Type: text/javascript');
		p::setMaxage(-1);

		echo 'w.k(',
			p::$appId, ',',
			jsquote( p::$base ), ',',
			jsquote( 'agent_index' === $agent ? '' : patchwork_class2file(substr($agent, 6)) ), ',',
			jsquote( isset($_GET['__0__']) ? $_GET['__0__'] : '' ), ',',
			'[', implode(',', array_map('jsquote', p::agentArgs($agent))), ']',
		')';
	}
}
