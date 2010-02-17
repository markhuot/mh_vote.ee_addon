<?php

require_once PATH_THIRD.'mh/mh'.EXT;

class Mh_vote {




	public $me = array();
	
	
	
	
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
	
	
	
	
	public function stats()
	{
		$tagdata = mh()->TMPL->tagdata;
		
		$id = $this->_id(mh()->TMPL->tagparams);
		$me = $this->_me($id);
		
		if (!$me)
		{
			return mh()->TMPL->parse_variables($tagdata, array(array()));
		}
		
		mh()->db->orderby('count desc');
		$places = mh()->db->get('mh_vote_meta');
		$results = $places->result();
		
		$leading = $this->_leading($results, $id);
		$trailing = $this->_trailing($results, $id);
		$tied = $this->_tied($results, $id);
		list($place, $total_places) = $this->_place($results, $id);
		
		foreach (array('leading', 'trailing', 'tied') as $dir)
		{
			$adjacent = $$dir;
			
		 	preg_match_all('/'.LD.'mh_vote:'.$dir.'(.*?)'.RD.'(.*?)'.LD.'\/mh_vote:'.$dir.RD.'/sm', $tagdata, $act_tagdata);
		 	if ($act_tagdata)
		 	{
		 		foreach ($act_tagdata[0] as $key => $full_match)
		 		{
		 			$params = mh()->functions->assign_parameters($act_tagdata[1][$key]);
		 			
		 			$parsed = '';
		 			foreach ($adjacent as $adjacent_votee)
		 			{
		 				$parsed.= mh()->TMPL->parse_variables($act_tagdata[2][$key], array($this->_data(@$adjacent_votee->unique_id)));
		 			}
		 			
		 			$parsed = trim($parsed);
		 			
		 			if (isset($params['backspace']) && is_numeric($params['backspace']))
		 			{
		 				$parsed = substr($parsed, 0, strlen($parsed)-$params['backspace']);
		 			}
		 			
		 			$tagdata = str_replace($full_match, $parsed, $tagdata);
		 		}
		 	}
		}
		
		
		$leading_by = $me->count - @$leading[0]->count;
		$trailing_by = @$trailing[0]->count - $me->count;
		
		$tagdata = mh()->TMPL->parse_variables($tagdata, array(array(
			'mh_vote:place' => $place,
			'mh_vote:is_tied' => count($tied)>0,
			'mh_vote:suffix' => date('S', strtotime("2000-01-{$place}")),
			'mh_vote:votes' => $me->count,
			'mh_vote:is_first' => $place==1,
			'mh_vote:leading_by' => $leading_by>0?$leading_by:0,
			'mh_vote:leading_count' => count($leading),
			'mh_vote:is_last' => $place==$total_places,
			'mh_vote:trailing_by' => $trailing_by>0?$trailing_by:0,
			'mh_vote:trailing_count' => count($trailing),
			'mh_vote:to_lead' => $trailing_by>0?$trailing_by+1:0
		)));

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
		
		// generate id
		$id = $this->_id($params);
		
		// insert vote
		mh()->db->insert('exp_mh_votes', array(
			'unique_id' => $id,
			'vote_date' => time(),
			'voter_ip' => mh()->input->ip_address(),
			'voter_useragent' => mh()->input->server('HTTP_USER_AGENT')
		));
		
		// update meta
		if (($count = mh()->db->where('unique_id', $id)->count_all_results('mh_votes')) == 1)
		{
			mh()->db->insert('exp_mh_vote_meta', array(
				'count' => $count,
				'unique_id' => $id
			));
		}
		
		else
		{
			mh()->db->update('exp_mh_vote_meta', array(
				'count' => $count
			), array('unique_id' => $id));
		}
		
		// store it somewhere?
		if (@$params['store_in'])
		{
			if (substr($id, 0, 1) == 'm')
			{
				$field_id = mh()->db->get_where('exp_member_fields', array('m_field_name' => $params['store_in']))->row('m_field_id');
				mh()->db->update('exp_member_data', array('m_field_id_'.$field_id => $count), array('member_id' => substr($id, 1)));
			}
			else
			{
				$field_id = mh()->db->get_where('exp_channel_fields', array('field_name' => $params['store_in']))->row('field_id');
				mh()->db->update('exp_channel_data', array('field_id_'.$field_id => $count), array('entry_id' => substr($id, 1)));
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
	
	
	
	
	public function _data($unique_id)
	{
		if (!$unique_id) return array();
		
		$id = substr($unique_id, 1);
		switch (substr($unique_id, 0, 1) == 'm')
		{
			case 'm':
				mh()->db->join('exp_member_data', 'exp_member_data.member_id=exp_members.member_id', 'LEFT');
				$row = mh()->db->get_where('exp_members', array('exp_members.member_id' => $id))->row_array();
				break;
			
			case 'e':
				mh()->db->join('exp_channel_data', 'exp_channel_data.entry_id=exp_channel_titles.entry_id', 'LEFT');
				$row = mh()->db->get_where('exp_channel_title', array('exp_channel_title.entry_id' => $id))->row_array();
				break;
		}
		
		foreach (array_keys($row) as $key)
		{
			$row["mh_vote:{$key}"] = $row[$key];
			unset($row[$key]);
		}
		
		return $row;
	}
	
	
	
	
	public function _id($params)
	{
		if (@$params['member_id']) return 'm'.$params['member_id'];
		
		return 'e'.@$params['entry_id'];
	}
	
	
	
	
	public function _me($id)
	{
		if (!isset($this->me[$id]))
		{
			$this->me[$id] = mh()->db->get_where('exp_mh_vote_meta', array('unique_id' => $id))->row();
		}
		
		return $this->me[$id];
	}
	
	
	
	
	public function _place($results, $id)
	{
		$place = 1;
		$my_place = 1;
		
		foreach ($results as $count => $result)
		{
			if (isset($results[$count-1]) && $result->count < $results[$count-1]->count)
			{
				$place++;
			}
			
			if ($result->unique_id == $id)
			{
				$my_place = $place;
			}
		}
		
		return array($my_place, $place);
	}
	
	
	
	
	public function _trailing($results, $id)
	{
		foreach ($results as $count => $result)
		{
			if ($result->unique_id == $id)
			{
				while (isset($results[$count]) && $results[$count]->count == $result->count)
				{
					$count--;
				}
				
				if (isset($results[$count]))
				{
					return array_merge(array($results[$count]), $this->_tied($results, @$results[$count]->unique_id));
				}
			}
		}
		
		return array();
	}
	
	
	
	
	public function _leading($results, $id)
	{
		foreach ($results as $count => $result)
		{
			if ($result->unique_id == $id)
			{
				while (isset($results[$count]) && $results[$count]->count == $result->count)
				{
					$count++;
				}
				
				if (isset($results[$count]))
				{
					return array_merge(array($results[$count]), $this->_tied($results, @$results[$count]->unique_id));
				}
			}
		}
		
		return array();
	}
	
	
	
	
	public function _tied($results, $id)
	{
		if (!$id) return array();
		
		$tied = array();
		
		foreach ($results as $count => $me)
		{
			if ($me->unique_id == $id)
			{
				foreach ($results as $result)
				{
					if ($result->count == $me->count && $result->unique_id != $me->unique_id)
					{
						$tied[] = $result;
					}
				}
				
				break;
			}
		}
		
		return $tied;
	}

}











