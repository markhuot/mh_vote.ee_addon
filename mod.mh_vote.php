<?php

require_once PATH_THIRD.'mh/mh'.EXT;

class Mh_vote {

	public function votes()
	{
		mh()->db->where('unique_id', mh()->TMPL->fetch_param('id'));
		return mh()->db->count_all_results('mh_votes');
	}
	
	
	
	
	public function form()
	{
		// get tagdata
		$tagdata = mh()->TMPL->tagdata;
		
		// check limit
		if (in_array(mh()->TMPL->fetch_param('limit'), array('minute', 'hour', 'day', 'week', 'month', 'year')))
		{
			$ip = mh()->db->where('voter_ip', mh()->input->ip_address());
			$user_agent = mh()->db->where('voter_useragent', mh()->input->server('HTTP_USER_AGENT'));
			
			$min = strtotime(date('Y-m-d 00:00:00'));
			mh()->db->where("vote_date > {$min}");
			
			$existing = mh()->db->get('exp_mh_votes');
			
			if ($existing->num_rows > 0)
			{
				$no_results_match = LD.'if voted'.RD.'(.*?)'.LD.'\/if'.RD;
				if (preg_match('/'.$no_results_match.'/sm', $tagdata))
				{
					$tagdata = preg_replace('/^.*'.$no_results_match.'?.*$/sm', '$1', $tagdata);
				}
				else
				{
					$tagdata = '';
				}
				
				return $tagdata;
			}
		}
		
		// parse captcha
		if (mh()->TMPL->fetch_param('use_captcha'))
		{
			$tagdata = $this->_tmpl_parse_captcha($tagdata);
		}
		
		// generate hash
		$hash = mh()->functions->add_form_security_hash('{XID_HASH}');
		
		// save params
		mh()->db->insert('exp_mh_vote_params', array(
			'param_date' => time(),
			'xid' => $hash,
			'params' => serialize(mh()->TMPL->tagparams)
		));
		
		// generate form
		$str = mh()->functions->form_declaration(array('hidden_fields' => array(
			'XID' => $hash,
			'ACT' => mh()->functions->fetch_action_id('Mh_vote', '_do_vote'),
			'RET' => mh()->TMPL->fetch_param('return')?mh()->TMPL->fetch_param('return'):mh()->functions->fetch_current_uri(),
			'id' => mh()->TMPL->fetch_param('id')
		)));
		$str.= $tagdata;
		$str.= '</form>';
		
		// return form
		return $str;
	}
	
	
	
	
	public function _tmpl_parse_captcha($tagdata)
	{
		// let EE make our captcha! thanks EE!
		return mh()->TMPL->parse_variables($tagdata, array(array(
			'captcha' => mh()->functions->create_captcha(),
			'captcha_word' => ''
		)));
	}
	
	
	
	
	public function _do_vote()
	{
		// get params
		$params = mh()->db->get_where('exp_mh_vote_params', array('xid' => mh()->input->post('XID')))->row('params');
		if (!$params)
		{
			//mh()->lang->load('lang.mh_vote');
			return mh()->output->fatal_error(mh()->lang->line('could_not_find_params'));
		}
		$params = unserialize($params);
		
		// clear old params
		mh()->db->delete('exp_mh_vote_params', '`param_date` < '.time());
		
		// check captcha
		if ($params['use_captcha'] == 'yes' && !$this->_validate_captcha())
		{
			return mh()->output->fatal_error(mh()->lang->line('captcha_incorrect'));
		}
		
		// insert vote
		mh()->db->insert('exp_mh_votes', array(
			'unique_id' => $params['id'],
			'vote_date' => time(),
			'voter_ip' => mh()->input->ip_address(),
			'voter_useragent' => mh()->input->server('HTTP_USER_AGENT')
		));
		
		mh()->load->helper('url');
		redirect(mh()->input->post('RET'));
		exti();
	}
	
	
	
	
	public function _validate_captcha()
	{
		$query = mh()->db->query("SELECT COUNT(*) AS count FROM exp_captcha 
		WHERE word='".mh()->db->escape_str(mh()->input->post('captcha'))."' 
		AND ip_address = '".mh()->input->ip_address()."' 
		AND date > UNIX_TIMESTAMP()-7200");
		
		if ($query->row('count') == 0)
		{
			return FALSE;
		}
		
		mh()->db->query("DELETE FROM exp_captcha 
		WHERE (word='".mh()->db->escape_str(mh()->input->post('captcha'))."' 
		AND ip_address = '".mh()->input->ip_address()."') 
		OR date < UNIX_TIMESTAMP()-7200");
		
		return TRUE;
	}

}