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



/**** Post-configuration stage 0 ****/

$patchwork_appId = (int) /*<*/sprintf('%020d', __patchwork_bootstrapper::$appId)/*>*/;
define('PATCHWORK_PATH_TOKEN', /*<*/__patchwork_bootstrapper::$token/*>*/);


$CONFIG += array(
	'debug.allowed'  => true,
	'debug.password' => '',
	'turbo' => false,
);

defined('DEBUG') || define('DEBUG', $CONFIG['debug.allowed'] && (!$CONFIG['debug.password'] || isset($_COOKIE['debug_password']) && $CONFIG['debug.password'] == $_COOKIE['debug_password']) ? 1 : 0);
defined('TURBO') || define('TURBO', !DEBUG && $CONFIG['turbo']);

unset($CONFIG['debug.allowed'], $CONFIG['debug.password'], $CONFIG['turbo']);


isset($CONFIG['umask']) && umask($CONFIG['umask']);


// file_exists replacement on Windows
// Private use for the preprocessor
// Fix a bug with long file names
// In debug mode, checks if character case is strict.

/*#>*/if ('\\' === DIRECTORY_SEPARATOR)
/*#>*/{
		if (/*<*/version_compare(PHP_VERSION, '5.2', '<')/*>*/ || DEBUG)
		{
			if (DEBUG)
			{
				function win_file_exists($file)
				{
					if (file_exists($file) && $realfile = realpath($file))
					{
						$file = strtr($file, '/', '\\');

						$i = strlen($file);
						$j = strlen($realfile);

						while ($i-- && $j--)
						{
							if ($file[$i] != $realfile[$j])
							{
								if (strtolower($file[$i]) === strtolower($realfile[$j]) && !(0 === $i && ':' === substr($file, 1, 1))) trigger_error("Character case mismatch between requested file and its real path ({$file} vs {$realfile})");
								break;
							}
						}

						return true;
					}
					else return false;
				}
			}
			else
			{
				function win_file_exists($file) {return file_exists($file) && (strlen($file) < 100 || realpath($file));}
			}

			function win_is_file($file)       {return win_file_exists($file) && is_file($file);}
			function win_is_dir($file)        {return win_file_exists($file) && is_dir($file);}
			function win_is_link($file)       {return win_file_exists($file) && is_link($file);}
			function win_is_executable($file) {return win_file_exists($file) && is_executable($file);}
			function win_is_readable($file)   {return win_file_exists($file) && is_readable($file);}
			function win_is_writable($file)   {return win_file_exists($file) && is_writable($file);}
		}
/*#>*/}


// __autoload(): the magic part

/*#>*/@copy(__patchwork_bootstrapper::$pwd . 'autoloader.php', './.patchwork.autoloader.php')
/*#>*/	|| @unlink('./.patchwork.autoloader.php') + copy(__patchwork_bootstrapper::$pwd . 'autoloader.php', './.patchwork.autoloader.php');
/*#>*/
/*#>*/if ('\\' === DIRECTORY_SEPARATOR)
/*#>*/{
/*#>*/	$a = new COM('Scripting.FileSystemObject');
/*#>*/	$a->GetFile(__patchwork_bootstrapper::$cwd . '.patchwork.autoloader.php')->Attributes |= 2; // Set hidden attribute
/*#>*/}

function __autoload($searched_class)
{
	$a = strtolower($searched_class);

	if (TURBO && $a =& $GLOBALS['patchwork_autoload_cache'][$a])
	{
		if (is_int($a))
		{
			$b = $a;
			unset($a);
			$a = $b - /*<*/__patchwork_bootstrapper::$offset/*>*/;

			$b = $searched_class;
			$i = strrpos($b, '__');
			false !== $i && 0 === strcspn(substr($b, $i+2), '0123456789') && $b = substr($b, 0, $i);

			$a = $b . '.php.' . DEBUG . (0>$a ? -$a . '-' : $a);
		}

		$a = './.class_' . $a . /*<*/'.' . __patchwork_bootstrapper::$token . '.zcache.php'/*>*/;

		$GLOBALS[/*<*/'a' . __patchwork_bootstrapper::$token/*>*/] = false;

		if (file_exists($a))
		{
			patchwork_include($a);

			if (class_exists($searched_class, false)) return;
		}
	}

	class_exists('__patchwork_autoloader', false) || require TURBO ? './.patchwork.autoloader.php' : /*<*/__patchwork_bootstrapper::$pwd . 'autoloader.php'/*>*/;

	__patchwork_autoloader::autoload($searched_class);
}


// patchworkProcessedPath(): private use for the preprocessor (in files in the include_path)

function patchworkProcessedPath($file)
{
/*#>*/	if ('\\' === DIRECTORY_SEPARATOR)
		false !== strpos($file, '\\') && $file = strtr($file, '\\', '/');

	if (false !== strpos('.' . $file, './') || /*<*/'\\' === DIRECTORY_SEPARATOR/*>*/ && ':/' === substr($file, 1, 2))
	{
		if ($f = realpath($file)) $file = $f;

		$p =& $GLOBALS['patchwork_path'];

		for ($i = /*<*/__patchwork_bootstrapper::$last + 1/*>*/; $i < /*<*/count($patchwork_path)/*>*/; ++$i)
		{
			if (substr($file, 0, strlen($p[$i])) === $p[$i])
			{
				$file = substr($file, strlen($p[$i]));
				break;
			}
		}

		if (/*<*/count($patchwork_path)/*>*/ === $i) return $f;
	}

	$file = 'class/' . $file;

	$source = resolvePath($file);

	if (false === $source) return false;

	$level = $GLOBALS['patchwork_lastpath_level'];

	$file = strtr($file, '\\', '/');
	$cache = DEBUG . (0>$level ? -$level . '-' : $level);
	$cache = './.' . strtr(str_replace('_', '%2', str_replace('%', '%1', $file)), '/', '_')
		. '.' . $cache . /*<*/'.' . __patchwork_bootstrapper::$token . '.zcache.php'/*>*/;

	if (file_exists($cache) && (TURBO || filemtime($cache) > filemtime($source))) return $cache;

	patchwork_preprocessor::execute($source, $cache, $level, false);

	return $cache;
}



/**** Post-configuration stage 1 ****/



function E($msg = '__Δms') {return patchwork::log($msg, false, false);}
function strlencmp($a, $b) {return strlen($b) - strlen($a);}


// Fix config

$CONFIG += array(
	'clientside'            => true,
	'i18n.lang_list'        => array(),
	'maxage'                => 2678400,
	'P3P'                   => 'CUR ADM',
	'xsendfile'             => !empty($_SERVER['PATCHWORK_XSENDFILE']),
	'document.domain'       => '',
	'session.save_path'     => /*<*/__patchwork_bootstrapper::$zcache/*>*/,
	'session.cookie_path'   => '/',
	'session.cookie_domain' => '',
	'session.auth_vars'     => array(),
	'session.group_vars'    => array(),
	'translator.adapter'    => false,
	'translator.options'    => array(),
);


// Prepare for I18N

$a =& $CONFIG['i18n.lang_list'];
$a ? (is_array($a) || $a = explode('|', $a)) : ($a = array('' => '__'));
define('PATCHWORK_I18N', 2 <= count($a));

$b = array();

foreach ($a as $k => &$v)
{
	if (is_int($k))
	{
		$v = (string) $v;

		if (!isset($a[$v]))
		{
			$a[$v] = $v;
			$b[] = preg_quote($v, '#');
		}

		unset($a[$k]);
	}
	else $b[] = preg_quote($v, '#');
}

unset($a, $v);

usort($b, 'strlencmp');
$b = '(' . implode('|', $b) . ')';


/* patchwork's context initialization
*
* Setup needed environment variables if they don't exists :
*   $_SERVER['PATCHWORK_BASE']: application's base part of the url. Lang independant (ex. /myapp/__/)
*   $_SERVER['PATCHWORK_REQUEST']: request part of the url (ex. myagent/mysubagent/...)
*   $_SERVER['PATCHWORK_LANG']: lang (ex. en) if application is internationalized
*/

$a = strpos($_SERVER['REQUEST_URI'], '?');
$a = false === $a ? $_SERVER['REQUEST_URI'] : substr($_SERVER['REQUEST_URI'], 0, $a);
$a = rawurldecode($a);

/*#>*/$a = true;
/*#>*/
/*#>*/switch (true)
/*#>*/{
/*#>*/case isset($_SERVER['REDIRECT_PATCHWORK_REQUEST']):
/*#>*/case isset($_SERVER['PATCHWORK_REQUEST'])         :
/*#>*/case isset($_SERVER['ORIG_PATH_INFO'])            : 
/*#>*/case isset($_SERVER['PATH_INFO'])                 : break;
/*#>*/
/*#>*/default:
/*#>*/	// Check if the webserver supports PATH_INFO
/*#>*/
/*#>*/	$h = isset($_SERVER['HTTPS']) ? 'ssl' : 'tcp';
/*#>*/	$h = fsockopen("{$h}://{$_SERVER['SERVER_ADDR']}", $_SERVER['SERVER_PORT'], $errno, $errstr, 30);
/*#>*/	if (!$h) throw new Exception("Socket error n°{$errno}: {$errstr}");
/*#>*/
/*#>*/	$a = strpos($_SERVER['REQUEST_URI'], '?');
/*#>*/	$a = false === $a ? $_SERVER['REQUEST_URI'] : substr($_SERVER['REQUEST_URI'], 0, $a);
/*#>*/	'/' === substr($a, -1) && $a .= basename(isset($_SERVER['ORIG_SCRIPT_NAME']) ? $_SERVER['ORIG_SCRIPT_NAME'] : $_SERVER['SCRIPT_NAME']);
/*#>*/
/*#>*/	$a  = "GET {$a}/_?exit$ HTTP/1.0\r\n";
/*#>*/	$a .= "Host: {$_SERVER['HTTP_HOST']}\r\n";
/*#>*/	$a .= "Connection: close\r\n\r\n";
/*#>*/
/*#>*/	fwrite($h, $a);
/*#>*/	$a = fgets($h, 14);
/*#>*/	fclose($h);
/*#>*/
/*#>*/	$a = strpos($a, ' 200');
/*#>*/}
/*#>*/
/*#>*/if ($a)
/*#>*/{
		switch (true)
		{
		case isset($_SERVER['REDIRECT_PATCHWORK_REQUEST']): $r = $_SERVER['REDIRECT_PATCHWORK_REQUEST']; break;
		case isset($_SERVER['PATCHWORK_REQUEST'])         : $r = $_SERVER['PATCHWORK_REQUEST']         ; break;
		case isset($_SERVER['ORIG_PATH_INFO'])            : $r = $_SERVER['ORIG_PATH_INFO']            ; break;
		case isset($_SERVER['PATH_INFO'])                 : $r = $_SERVER['PATH_INFO']                 ; break;

		case '/' === substr($a, -1): $a .= basename(isset($_SERVER['ORIG_SCRIPT_NAME']) ? $_SERVER['ORIG_SCRIPT_NAME'] : $_SERVER['SCRIPT_NAME']);
		default: $r = '';
		}

		$a .= '/';
/*#>*/}
/*#>*/else
/*#>*/{
		$r = $_SERVER['QUERY_STRING'];
		$j = strpos($r, '?');
		false !== $j || $j = strpos($r, '&');

		if (false !== $j)
		{
			$r = substr($r, 0, $j);
			$_SERVER['QUERY_STRING'] = substr($_SERVER['QUERY_STRING'], $j+1);

			parse_str($_SERVER['QUERY_STRING'], $_GET);

/*#>*/		if (get_magic_quotes_gpc())
/*#>*/		{
				$k = array(&$_GET);
				for ($i = 0, $j = 1; $i < $j; ++$i)
				{
					foreach ($k[$i] as &$v)
					{
						if (is_array($v)) $k[$j++] =& $v;
						else
						{
/*#>*/						if (ini_get('magic_quotes_sybase'))
								$v = str_replace("''", "'", $v);
/*#>*/						else
								$v = stripslashes($v);
						}
					}

					reset($k[$i]);
					unset($k[$i]);
				}

				unset($k, $v);
/*#>*/		}
		}
		else if ('' !== $r)
		{
			$_SERVER['QUERY_STRING'] = '';

			reset($_GET);
			$j = key($_GET);
			unset($_GET[$j], $_GET[$j]); // Double unset against a PHP security hole
		}

		$j = explode('/', urldecode($r));
		$r = array();
		$v = 0;

		foreach ($j as $j)
		{
			if ('.' === $j) continue;
			if ('..' === $j) $r ? array_pop($r) : ++$v;
			else $r[]= $j;
		}

		$r = implode('/', $r);

		if ($v)
		{
			'/' !== substr($a, -1) && $a .= '/';
			$a = preg_replace("'[^/]*/{1,{$v}}$'", '', $a);
			'' === $a && $a = '/';
			$a = strtr($a . $r, array('%' => '%25', '?' => '%3F', "\r" => '%0D', "\n" => '%0A'));
			'' !== $_SERVER['QUERY_STRING'] && $a .= '?' . $_SERVER['QUERY_STRING'];

			header('HTTP/1.1 301 Moved Permanently');
			header('Location: ' . $a);

			exit;
		}
/*#>*/}

$r = preg_replace("'/[./]*/'", '/', '/' . $r . '/');
$a = preg_replace("'/[./]*/'", '/', '/' . $a);

/*#>*/if ($a && '\\' === DIRECTORY_SEPARATOR)
/*#>*/{
		// Workaround for http://bugs.php.net/bug.php?id=44001

		if ('/' !== $r && false !== strpos($a, './') && false === strpos($r, './'))
		{
			$r = explode('/', $r);
			$j = count($r) - 1;

			$a = explode('/', strrev($a), $j);

			for ($i = 0; $i < $j; ++$i) $r[$j - $i] .= str_repeat('.', strspn($a[$i], '.'));

			$a = strrev(implode('/', $a));
			$r = implode('/', $r);
		}
/*#>*/}

$_SERVER['PATCHWORK_REQUEST'] = (string) substr($r, 1, -1);

isset($_SERVER['REDIRECT_PATCHWORK_BASE']) && $_SERVER['PATCHWORK_BASE'] = $_SERVER['REDIRECT_PATCHWORK_BASE'];
isset($_SERVER['REDIRECT_PATCHWORK_LANG']) && $_SERVER['PATCHWORK_LANG'] = $_SERVER['REDIRECT_PATCHWORK_LANG'];

if (isset($_SERVER['PATCHWORK_BASE']))
{
	if ('/' === substr($_SERVER['PATCHWORK_BASE'], 0, 1)) $_SERVER['PATCHWORK_BASE'] = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PATCHWORK_BASE'];

	if (!isset($_SERVER['PATCHWORK_LANG']))
	{
		$k = explode('__', $_SERVER['PATCHWORK_BASE'], 2);
		if (2 === count($k))
		{
			$k = '#' . preg_quote($k[0], '#') . $b . '#';
			preg_match($k, 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $a, $k)
				&& $_SERVER['PATCHWORK_LANG'] = (string) array_search($k[1], $CONFIG['i18n.lang_list']);
		}
		else if (PATCHWORK_I18N) switch (substr($_SERVER['PATCHWORK_BASE'], -1))
		{
		case '/': 
		case '?': $_SERVER['PATCHWORK_BASE'] .= '__/'; break;
		default:
/*#>*/		if ($a)
				$_SERVER['PATCHWORK_BASE'] .= '/__/';
/*#>*/		else
				$_SERVER['PATCHWORK_BASE'] .= '?__/';
		}
	}
}
else
{
	$a = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $a;

/*#>*/if ($a)
		$_SERVER['PATCHWORK_BASE'] = substr($a, 0, -strlen($r)) . '/' . (PATCHWORK_I18N ? '__/' : '');
/*#>*/else
		$_SERVER['PATCHWORK_BASE'] = $a . '?' . (PATCHWORK_I18N ? '__/' : '');
}

if (isset($_SERVER['PATCHWORK_LANG']))
{
	$a =& $CONFIG['i18n.lang_list'];
	$b =& $_SERVER['PATCHWORK_LANG'];

	isset($a[$b]) || $b = (string) array_search($b, $a);

	unset($a, $b);
}
else if ('__/' === substr($_SERVER['PATCHWORK_BASE'], -3) && preg_match("#^/{$b}/#", $r, $a))
{
	$_SERVER['PATCHWORK_LANG'] = array_search($a[1], $CONFIG['i18n.lang_list']);
	$_SERVER['PATCHWORK_REQUEST'] = (string) substr($r, strlen($a[1])+2, -1);
}
else $_SERVER['PATCHWORK_LANG'] = '';

reset($CONFIG['i18n.lang_list']);
PATCHWORK_I18N || $_SERVER['PATCHWORK_LANG'] = key($CONFIG['i18n.lang_list']);
define('PATCHWORK_DIRECT', '_' === $_SERVER['PATCHWORK_REQUEST']);
