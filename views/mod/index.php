<p style="text-align:center;margin:0 0 10px"><img style="border:2px solid #3E4C54" src="http://chart.apis.google.com/chart?<?php

	echo 'cht=bvg&';
	echo 'chs=700x400&';
	echo 'chco=76A4FB&';
	echo 'chxt=x,y&';
	echo 'chd=t:';
	foreach ($votes->result() as $count=>$hour)
	{
		echo $hour->count;
		echo $count+1!=$votes->num_rows?',':'';
	}
	echo '&chm=N*,000000,0,-1,11';
	echo '&chxr=0,0,'.$votes->num_rows.'|1,0,400&';
	echo 'chxt=x,x,y,y&';
	echo 'chxl=0:';
	foreach ($votes->result() as $count=>$hour)
	{
		echo '|'.substr($hour->hour, 11,2);
	}
	echo '|1:|Hour (MST)';
	echo '|2:|0|100|200|300|400';
	echo '|3:|Votes';
	echo '&chds=0,400';
	echo '&chma=100,30,30,50';

?>" />

<table class="mainTable" border="0" cellspacing="0" cellpadding="0">
	<thead>
		<tr>
			<th colspan="2">Details</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>Total Votes</td>
			<td><?=number_format($total_votes)?></td>
		</tr>
	</tbody>
</table>