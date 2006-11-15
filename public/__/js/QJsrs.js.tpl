{*/**************************************************************************
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
 **************************************************************************/*}
/*
* Init this JavaScript Remote Scripting object with
* varname = new QJsrs($url, $POST, $antiXSJ), where $url is a server script aimed at
* generating the result.
* Set $POST to true if you want a POST request
* to be made to the server.
* Set $antiXSJ to true if the server needs an anti-cross-site-javascript token
*
* Then call this server script _asynchronously_
* with varname.push($vararray, $function)
* Multiple .push() calls are executed sequentialy : the last call is executed
* only when the previous one is finished.
*
* use varname.replace($vararray, $function) to empty the call sequence and then do the request
*
* $vararray is an associative array, which is going to be passed to the server script.
*
* $function(result) is called when the result is loaded.
*
* Cancel the callback pool with varname.abort()
*/

$window = window;

if (!$window.QJsrs)
{

function $emptyFunction() {};

// Preload the XMLHttp object and detects browser capabilities.
QJsrs = $window.ScriptEngineMajorVersion;
if (QJsrs && QJsrs() >= 5) eval('try{QJsrs=new ActiveXObject("Microsoft.XMLHTTP")&&2}catch(QJsrs){QJsrs=1}');
else QJsrs = $window.XMLHttpRequest ? new XMLHttpRequest && 3 : 1;

QJsrs = (function()
{

var $contextPool = [],
	$loadCounter = 0,
	$masterTimer = 0,
	$document = document,
	$div = 0,
	$emptyFunction = $window.$emptyFunction,
	$win = $window,
	$XMLHttp = QJsrs - 1;

$document.write('<div id="divQJsrs" style="position:absolute;visibility:hidden"></div>');

function $QJsrsContext($name)
{
	var $this = this,
		$container,
		$callback,
		$html;

	$this.$load = function($url, $contextCallback, $post, $local, $XMLHttpPreferred)
	{
		$callback = $contextCallback;

		$this.q = $url;

		if ($post || !$local)
			$url[3] = $post,
			$url[4] = $local,
			$url = home('QJsrs.html', 1);
		else $url = $url[0] + $url[1];

		// For GET requests, we prefer direct <script> tag creation rather than XMLHttpRequest :
		// this prevents a caching bug in Firefox < 1.5 and works in IE even when ActiveX is disabled
		if (!($XMLHttpPreferred && $local && $XMLHttp) && !$post && ('Gecko' == navigator.product || 'object' == typeof $document.onreadystatechange) && $document.createElement)
			$container = $QJsrs.$withScript($this.q[0] + $this.q[1], function() {$this.$driver($this.$release);});
		else if ($local && $XMLHttp)
		{
			$container = $XMLHttp - 1 ? new XMLHttpRequest : new ActiveXObject('Microsoft.XMLHTTP');
			$container.onreadystatechange = function()
			{
				4 == $container.readyState && $this.$driver($this.$release, $container.responseText, 1);
			}

			if ($post)
				$container.open('POST', $this.q[0], 1),
				$container.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded'),
				$container.send($this.q[1]);
			else
				$container.open('GET', $url, 1),
				$container.send('');
		}
		else if ($html) $win.frames[$name].location.replace($url);
		else
		{
			if (!$div) $div = $document.getElementById ? $document.getElementById('divQJsrs') : $document.all.divQJsrs;

			if ($div.appendChild && (!$win.ScriptEngine || 5.5 <= ScriptEngineMajorVersion() + ScriptEngineMinorVersion() / 10))
			{
				$html = $document.createElement('iframe');
				$html.name = $name;
				$html.src = $url;
				$html.width = $html.height = $html.frameBorder = 0;
				$html.tabIndex = -1;
				$div.appendChild($html);
			}
			else $div.innerHTML += '<iframe name='+ $name +' src="'+ $url.replace(/"/g, '&quot;') +'" width="0" height="0" frameborder="0" tabindex="-1"></iframe>',

			$html = 1;
		}
	}

	$this.$release = function($result)
	{
		if ($container)
		{
			if ($container.onload) $container.onload = $emptyFunction;
			$container.onreadystatechange = $emptyFunction;
			$container.abort();
		}

		if ($callback && $result>='' || $result < 0) $callback($result);

		$container = $callback = 0;
	}
}

$window.loadQJsrs = function($context, $result)
{
	$context = $contextPool[ parseInt($context.name ? $context.name.substr(1) : $context) ];

	if ($result >= '') $QJsrs.$setTimeout(function() {$context.$driver($context.$release, $result);}, 0); // The timeout is a workaround for a bug with relative directories

	return $context;
}

function $QJsrs($URL, $POST, $antiXSJ, $XMLHttpPreferred)
{
	var $this = this,
		$pool = [],
		$poolLen = 0,
		$localTimer = 0,
		$context, $callback, $url = home(''), $i = '?',
		$LOCAL = location;

	if (!$URL.indexOf($url)) $URL = $URL.substr($url.length);
	if ($URL.indexOf($i)<0) $URL += $i;

	$URL = home($URL);
	
	$LOCAL = 0 == $URL.indexOf($LOCAL.protocol+'//'+$LOCAL.hostname);
	$POST = $POST ? 1 : 0;

	$this.driver = function($callback, $text, $raw)
	{
		if ($text>='')
		{
			if ($raw) eval('$text=' + $text.replace(/<\/.*/, '').substr(39));
		}
		else $text = window.q;

		$callback( eval('$text=' + $text) );
	}

	$this.replace = function($vararray, $function)
	{
		$this.abort();
		$this.push($vararray, $function);
	}

	$this.push = function($vararray, $function)
	{
		if (!$loadCounter)
		{
			if ($masterTimer) $masterTimer = clearTimeout($masterTimer);
			else $QJsrs.onloading();
		}

		++$loadCounter;

		$function = $function || $emptyFunction;

		if (($antiXSJ || $POST) && !$vararray.T$) $vararray.T$ = antiXSJ;

		$url = [];
		for ($i in $vararray) $url.push('&' + eUC($i) + '=' + eUC($vararray[$i])); // Be aware that Konquerors for(..in..) loop does not preserve the order of declaration
		$url = [$URL, $url.join(''), $vararray];

		if ($context) $pool[$poolLen++] = [$url, $function];
		else
		{
			if ($localTimer) $localTimer = clearTimeout($localTimer);
			else $this.onloading();

			$context = $contextPool.length;
			for ($i = 0; $i < $context; ++$i) if (!$contextPool[$i].$driver) break;
			if ($i == $context) $contextPool[$i] = new $QJsrsContext('_' + $i), // The '_' prefix prevents confusion of frames['0'] and frames[0] for some browsers

			'' + $function; // Dummy line, but if missing, both IE and Mozilla bug !?
			$callback = $function;

			$context = $contextPool[$i];
			$context.$driver = $this.driver,
			$context.$load($url, $release, $POST, $LOCAL, $XMLHttpPreferred);
		}
	}

	$this.abort = function()
	{
		if ($context) $context.$release(), $release(0, 1);
	}

	function $release($a, $abort)
	{
		$abort
			? $loadCounter = $poolLen = $pool.length = 0
			: $callback($a), --$loadCounter;

		if ($poolLen)
		{
			$a = $pool[0];
			$pool = $pool.slice(1);
			$poolLen--;
			$callback = $a[1];

			return $context.$load($a[0], $release, $POST, $LOCAL, $XMLHttpPreferred);
		}

		$callback = $context = $context.$driver = 0;

		$localTimer = $QJsrs.$setTimeout($QJsrs.onloaded, 10);
		if (!$loadCounter) $masterTimer = $QJsrs.$setTimeout($this.onloaded, 10);
	}

	$this.onloading = $this.onloaded = $emptyFunction;
}

$QJsrs.$setTimeoutId = 0;
$QJsrs.$setTimeoutPool = [];
$QJsrs.onloading = $QJsrs.onloaded = $emptyFunction;

return $QJsrs;

})();

QJsrs.$setTimeout = function($function, $timeout, $i)
{
	$i = ++QJsrs.$setTimeoutId;
	QJsrs.$setTimeoutPool[$i] = $function;
	return setTimeout('QJsrs.$setTimeoutPool['+$i+']();QJsrs.$setTimeoutPool['+$i+']=null', $timeout);
}

QJsrs.$withScript = function($url, $callback)
{
	var $script = document.createElement('script');
	
	$script.abort = function()
	{
		$script.parentNode.removeChild($script);
		$script = 0;
	}

	$script.type = 'text/javascript';
	$script.charset = 'utf-8';
	$script.src = $url;
	$script.onload = $script.onreadystatechange = function($event)
	{
		if (
			!(
				   ($event = $event || window.event)
				&& ($event = $event.target || $event.srcElement)
				&& ('undefined' != typeof $event.readyState)
			)
			|| 'loaded'   == $event.readyState
			|| 'complete' == $event.readyState
		) $callback();
	}

	document.getElementsByTagName('head')[0].appendChild($script);

	return $script;
}

}
