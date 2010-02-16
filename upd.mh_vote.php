<?php

require_once PATH_THIRD.'mh/mh'.EXT;

class Mh_vote_upd {

	var $version = '1.0.0';
	
	
	
	
	function install()
	{
		$sql[] = "INSERT INTO exp_modules (module_name, module_version, has_cp_backend) VALUES ('Mh_vote', '{$this->version}', 'y')";
		$sql[] = "ALTER TABLE exp_channel_titles ADD `mh_votes` INT NOT NULL DEFAULT 0";
		$sql[] = "CREATE TABLE `exp_mh_votes` ( `id` INT( 10 ) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY , `unique_id` VARCHAR( 255 ) NOT NULL, `vote_date` INT( 11 ), `voter_ip` VARCHAR( 16 ), `voter_useragent` VARCHAR ( 255 ) )";
		$sql[] = "CREATE TABLE `exp_mh_vote_params` ( `param_date` INT ( 11 ), `xid` VARCHAR( 40 ) NOT NULL, `params` VARCHAR( 255 ) NOT NULL )";
		$sql[] = "INSERT INTO exp_modules (module_name, module_version, has_cp_backend) VALUES ('Mh_vote', '{$this->version}', 'y')";
		$sql[] = "INSERT INTO exp_actions (class, method) VALUES ('Mh_vote', '_do_vote')";
		
		foreach ($sql as $query)
		{
			mh()->db->query($query);
		}

		return TRUE;
	}
	
	
	
	
	function uninstall()
	{
		$query = mh()->db->query("SELECT module_id FROM exp_modules WHERE module_name = 'Mh_vote'");
		
		$sql[] = "DELETE FROM exp_module_member_groups WHERE module_id = '".$query->row('module_id') ."'";
		$sql[] = "DELETE FROM exp_modules WHERE module_name = 'Mh_vote'";
		$sql[] = "DELETE FROM exp_actions WHERE class = 'Mh_vote'";
		$sql[] = "DELETE FROM exp_actions WHERE class = 'Mh_vote_mcp'";
		$sql[] = "ALTER TABLE `exp_channel_titles` DROP `mh_votes`";
		$sql[] = "DROP TABLE `exp_mh_votes`";
		$sql[] = "DROP TABLE `exp_mh_vote_params`";
	
		foreach ($sql as $query)
		{
			mh()->db->query($query);
		}

		return TRUE;
	}
	
	
	
	
	function update($current='')
	{
		return FALSE;
	}

}