<?php

class extends agent_pStudioWidget_reader_php
{
	const contentType = 'image/gif';

	protected $template = 'pStudioWidget/reader/gif';

	function compose($o)
	{
		if ($this->get->{'$serverside'})
		{
			header('Content-Type: ' . $this->contentType);
			p::readfile($this->realpath, false);
		}
		else $o->extension = $this->extension;

		return $o;
	}
}