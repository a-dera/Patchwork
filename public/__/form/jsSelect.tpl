<!--*

This template displays a jsSelect control.
It has the same parameters as input.tpl

*--><!--

SET a$id -->{a$name}<!-- END:SET --><!--
SET a$class -->{a$class|default:'jsSelect'}<!-- END:SET --><!--

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

	IF a$_mandatory --><span class="mandatory"><!-- END:IF --><!--

	SET $id -->{a$name}<!-- END:SET
	
	--><script src="{a$_src_}"></script><script><!--

	jsSelectPrint({a$|htmlArgs|escape:'js'},{a$multiple|escape:'js'},{a$_firstItem|escape:'js'},{a$_firstCaption|escape:'js'},[<!-- LOOP a$_value -->{$VALUE|escape:'js'},<!-- END:LOOP -->0])//--></script><script><!--

	lE=gLE({a$name|escape:'js'})
	jsSelectInit(lE,[<!-- LOOP a$_value -->{$VALUE|escape:'js'},<!-- END:LOOP -->0])
	lE.gS=IgSS;
	lE.cS=function(){return IcES([0<!-- LOOP a$_elements -->,{$name|escape:'js'},{$onempty|escape:'js'},{$onerror|escape:'js'}<!-- END:LOOP -->],this.form)};<!-- IF a$_focus_ -->lE.focus()<!-- END:IF -->//--></script><!--
	
	SERVERSIDE
		--><noscript><input {a$|htmlArgs}></noscript><!--
	END:SERVERSIDE --><!--

	IF a$_mandatory --></span><!-- END:IF --><!--

END:SET --><!--


SET $ERROR --><!--
	IF a$_errormsg -->{a$_beforeError_|default:g$inputBeforeError}<span class="errormsg">{a$_errormsg}</span>{a$_afterError_|default:g$inputAfterError}<!-- END:IF --><!--
END:SET


-->{a$_format_|default:g$inputFormat|echo:$CAPTION:$INPUT:$ERROR}
