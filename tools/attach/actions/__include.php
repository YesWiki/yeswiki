<?php
	if (!defined("WIKINI_VERSION"))
	{
	        die ("acc&egrave;s direct interdit");
	}

	$oldpage = $this->page['tag'];
	$this->tag = trim($this->GetParameter('page'));
	$this->page = $this->LoadPage($this->tag);
?>
