<!--*

This template displays a QSelect control.
It has the same parameters as input.tpl

*--><!--

SET a$id -->{a$name}<!-- END:SET --><!--
SET a$class -->{a$class|default:'QSelect'}<!-- END:SET --><!--

IF !a$title --><!--
	SET a$title
		-->{a$_caption_}<!--
	END:SET --><!--
END:IF --><!--


SET $CAPTION --><!--
	IF a$_caption_
		--><label for="{a$id}" class="{a$class}" onclick="return IlC(this)"><!--
		IF a$_mandatory --><span class="mandatory"><!-- END:IF
		-->{a$_caption_}<!--
		IF a$_mandatory --></span><!-- END:IF
		--></label><!--
	END:IF --><!--
END:SET --><!--


SET $INPUT --><!--

	SET $INPUT -->{root:'_QS'|urlencode}<!-- END:SET --><!--
	IF a$_mandatory --><span class="mandatory"><!-- END:IF --><!--
	IF !g$_QS --><script type="text/javascript" src="{~}js/QSelect"></script ><!-- END:IF

	--><span class="QSstyle"><input autocomplete="off" {a$|htmlArgs} /><img src="{~}QSelect/b.gif" id="{$INPUT}b{a$name}" onmouseover="this.src={root:'QSelect/bh.gif'|js}" onmouseout="this.src={root:'QSelect/b.gif'|js}" onmousedown="this.src={root:'QSelect/bp.gif'|js}" onmouseup="this.onmouseover()" alt=" " title="" /></span><script type="text/javascript">/*<![CDATA[*/<!--

	IF !g$_QS --><!--
		SET g$_QS
			--><div id="{$INPUT}d1" style="position:absolute;visibility:hidden;z-index:9"><form action=""><div id="{$INPUT}d2" style="position:absolute"><img src="{~}QSelect/tr.png" width="5" height="10" /><br /><img src="{~}QSelect/r.png" width="5" height="5" id="{$INPUT}i1" /><br /><img src="{~}QSelect/br.png" width="5" height="5" /></div><div id="{$INPUT}d3" style="position:absolute"><img src="{~}QSelect/bl.png" width="10" height="5" /><img src="{~}QSelect/b.png" width="5" height="5" id="{$INPUT}i2" /></div><select id="{$INPUT}s" size="7"></select></form></div><!--
		END:SET -->w.appendToBody+={g$_QS|js};<!--
	END:IF -->
	lE=gLE({a$name|js})
	lE.lock={a$_lock_|js}

	lE.gS=function(){return valid(this<!-- LOOP a$_valid -->,{$VALUE|js}<!-- END:LOOP -->)}

	lE.cS=function(){return IcES([0<!-- LOOP a$_elements -->,{$name|js},{$onempty|js},{$onerror|js}<!-- END:LOOP -->],this.form)};<!-- IF a$_focus_ -->lE.focus()<!-- END:IF -->/*]]>*/</script ><script type="text/javascript" src="{root:a$_src_}"></script><!--

	IF a$_mandatory --></span><!-- END:IF --><!--

END:SET --><!--


SET $ERROR --><!--
	IF a$_errormsg -->{a$_beforeError_|default:g$inputBeforeError}<span class="errormsg">{a$_errormsg}</span>{a$_afterError_|default:g$inputAfterError}<!-- END:IF --><!--
END:SET


-->{a$_format_|default:g$inputFormat|echo:$CAPTION:$INPUT:$ERROR}
