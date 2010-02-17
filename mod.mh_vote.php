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
			if (!$this->_validate_limit(mh()->TMPL->fetch_param('limit')))
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
			'RET' => mh()->TMPL->fetch_param('return')?mh()->TMPL->fetch_param('return'):mh()->functions->fetch_current_uri()
		)));
		$str.= $tagdata;
		$str.= '</form>';
		
		// return form
		return $str;
	}
	
	
	
	
	public function trailing()
	{
		$tagdata = mh()->TMPL->tagdata;
		
		$id = mh()->TMPL->fetch_param('id');
		$me = mh()->db->get_where('exp_mh_vote_meta', array('unique_id' => $id))->row();
		
		if (!$me)
		{
			return '';
		}
		
		mh()->db->where('count > '.$me->count);
		mh()->db->orderby('count asc');
		mh()->db->limit(1);
		$result = mh()->db->get('mh_vote_meta')->row();
		
		if ($result)
		{
			return mh()->TMPL->parse_variables($tagdata, array(array(
				'id' => $result->unique_id,
				'trailing_by' => $result->count-$me->count,
				'pluralize' => $me->count-$result->count>1?'s':''
			)));
		}
		
		else
		{
			$no_results_match = LD.'if is_first'.RD.'(.*?)'.LD.'\/if'.RD;
			if (preg_match('/'.$no_results_match.'/sm', $tagdata))
			{
				$tagdata = preg_replace('/^.*'.$no_results_match.'?.*$/sm', '$1', $tagdata);
			}
			else
			{
				$tagdata = '';
			}
		}
		
		return $tagdata;
	}
	
	
	
	
	public function leading()
	{
		$tagdata = mh()->TMPL->tagdata;
		
		$id = mh()->TMPL->fetch_param('id');
		$me = mh()->db->get_where('exp_mh_vote_meta', array('unique_id' => $id))->row();
		
		if (!$me)
		{
			return '';
		}
		
		mh()->db->where('count < '.$me->count);
		mh()->db->orderby('count desc');
		mh()->db->limit(1);
		$result = mh()->db->get('mh_vote_meta')->row();
		
		if ($result)
		{
			return mh()->TMPL->parse_variables($tagdata, array(array(
				'id' => $result->unique_id,
				'leading_by' => $me->count-$result->count,
				'pluralize' => $me->count-$result->count>1?'s':''
			)));
		}
		
		else
		{
			$no_results_match = LD.'if is_last'.RD.'(.*?)'.LD.'\/if'.RD;
			if (preg_match('/'.$no_results_match.'/sm', $tagdata))
			{
				$tagdata = preg_replace('/^.*'.$no_results_match.'?.*$/sm', '$1', $tagdata);
			}
			else
			{
				$tagdata = '';
			}
		}
		
		return $tagdata;
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
		
		// update meta
		if (($count = mh()->db->where('unique_id', $params['id'])->count_all_results('mh_votes')) == 1)
		{
			mh()->db->insert('exp_mh_vote_meta', array(
				'count' => $count,
				'unique_id' => $params['id']
			));
		}
		
		else
		{
			mh()->db->update('exp_mh_vote_meta', array(
				'count' => $count
			), array('unique_id' => $params['id']));
		}
		
		// store it somewhere?
		if (($store_in = @$params['store_in']) && ($store_key = @$params['store_key']))
		{
			$store_with = @$params['store_with']?$params['store_with']:'entry';
			
			switch ($store_with)
			{
				case 'member':
					$field_id = mh()->db->get_where('exp_member_fields', array('m_field_name' => $store_in))->row('m_field_id');
					mh()->db->update('exp_member_data', array('m_field_id_'.$field_id => $count), array('member_id' => $store_key));
					break;
				
				case 'entry':
					$field_id = mh()->db->get_where('exp_channel_fields', array('field_name' => $store_in))->row('field_id');
					mh()->db->update('exp_channel_data', array('field_id_'.$field_id => $count), array('entry_id' => $store_key));
					break;
			}
		}
		
		if (!session_id()) session_start();
		$_SESSION['mh_vote'] = 'voted';
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
	
	
	
	
	public function _validate_limit($limit)
	{
		$ip = mh()->db->where('voter_ip', mh()->input->ip_address());
		$user_agent = mh()->db->where('voter_useragent', mh()->input->server('HTTP_USER_AGENT'));
		
		switch ($limit)
		{
			case 'minute':
				$min = strtotime(date('Y-m-d H:i:00'));
				break;
			
			case 'hour':
				$min = strtotime(date('Y-m-d H:00:00'));
				break;
			
			case 'day':
				$min = strtotime(date('Y-m-d 00:00:00'));
				break;
			
			case 'week':
				$min = strtotime(date('Y-m-d 00:00:00', strtotime('-'.date('w').' days')));
				break;
			
			case 'month':
				$min = strtotime(date('Y-m-01 00:00:00'));
				break;
			
			case 'year':
				$min = strtotime(date('Y-01-01 00:00:00'));
				break;
		}
		
		
		mh()->db->where("vote_date > {$min}");
		return mh()->db->get('exp_mh_votes')->num_rows==0;
	}

}