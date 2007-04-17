{***************************************************************************
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
 ***************************************************************************}
<!--
{*

a$_mode_ : ('errormsg'|'close'|'')
a$_enterControl_ : 0 to keep the browser's behaviour,
                   1 to disable submit on enter key press,
                   2 to enable submit on enter key press by simulating
                     a click on the submit/image element positioned
                     after the currently focused element.

*}

IF a$_mode_ == 'errormsg'

	IF a$_errormsg
		--><div class="errormsg"><!--
		LOOP a$_errormsg -->{$VALUE}<br /><!-- END:LOOP
		--></div><!--
	END:IF

ELSEIF a$_mode_ == 'close' --></form><!--

ELSE

	SET a$action --><!-- IF !a$action -->{g$__URI__}<!-- ELSE -->{base:a$action:1}<!-- END:IF --><!-- END:SET
	IF !a$id --><!-- SET a$id -->FiD{g+1$GLOBID}<!-- END:SET --><!-- END:IF

	--><form accept-charset="UTF-8" {a$|htmlArgs}><!--

	LOOP a$_hidden
		--><input type="hidden" name="{$name}" value="{$value}" /><!--
	END:LOOP

	IF !g$_FORM
		SET g$_FORM -->1<!-- END:SET

		--><style type="text/css">
		label {cursor: default}
		textarea {overflow: visible}
		textarea.toomuch {background-color: #FFD2D2}
		.errormsg {color: red}
		.required {font-weight: bold}
		</style><!--

		CLIENTSIDE
			SET $js --><!-- AGENT 'js/v' --><!-- END:SET
		END:CLIENTSIDE

		SERVERSIDE
			--><script type="text/javascript" src="{base:'js/v'}"></script><!--
		END:SERVERSIDE
	END:IF

	--><script type="text/javascript">/*<![CDATA[*/
{$js|allowhtml}
lF=document.getElementById({a$id|js});<!--
IF a$_enterControl_ -->FeC({a$_enterControl_});<!-- END:IF -->//]]></script ><!--

	IF !g$_UPLOAD && a$_upload --><!-- SET g$_UPLOAD -->1<!-- END:SET --><script type="text/javascript" src="{base:'js/upload'}"></script ><!-- END:IF

END:IF -->