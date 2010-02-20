<?php

require_once PATH_THIRD.'mh/mh'.EXT;

class Mh_vote_mcp {

	public function __construct()
	{

	}

	public function index()
	{
		return mh()->load->view('mod/index', array(
			'total_votes' => mh()->db->query('select count(*) as count from exp_mh_votes')->row('count'),
			'votes' => mh()->db->query('
			SELECT * , COUNT( * ) AS count, DATE_FORMAT( FROM_UNIXTIME( vote_date ) ,  "%Y-%m-%d %H:00:00" ) AS hour
			FROM exp_mh_votes
			GROUP BY hour
			ORDER BY hour DESC
			LIMIT 0 , 18')
		), TRUE);
	}

}