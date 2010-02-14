<?php

require_once PATH_THIRD.'mh/mh'.EXT;

class Mh_vote {

	public function form()
	{
		$str = mh()->functions->form_declaration(array('hidden_fields' => array(
			'ACT' => mh()->functions->fetch_action_id('Mh_vote', '_do_vote'),
			'RET' => mh()->TMPL->fetch_param('return')?mh()->TMPL->fetch_param('return'):mh()->functions->fetch_current_uri()
		)));
		
		$str.= mh()->TMPL->tagdata;
		
		$str.= '</form>';
		
		return $str;
	}
	
	public function _do_vote()
	{
		
	}

}