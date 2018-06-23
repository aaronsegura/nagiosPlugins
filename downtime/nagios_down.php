#!/usr/bin/php -q
<?php

// Aaron Segura 07/12/2006
//
// Schedule downtime according to cmdline parameters.  Useful in cron for
//	scheduling recurring downtime.	
//

// User defined configs...
//
define(NAGIOSCMD, "/usr/local/groundwork/nagios/var/spool/nagios.cmd");


// *** Don't touch me below here, ever...ever ever ever never ever neever nerverton ***
//
define(VERSION, "0.2");
define(VERDATE, "07/13/2006");

function usage($msg = NULL)
{
	if ( strlen($msg) > 0 )
		print("\n*** $msg\n\n");	

	print(basename($_SERVER["argv"][0]) ." v". VERSION ." (". VERDATE .") by Aaron Segura\n\n");
	print("Usage: ". basename($_SERVER["argv"][0]) ." [-H <s|h>]|-S <s|h>] [-g group] [-h host] [-s service] <-f|-d duration> <-b begin> <-e end> <-a author> <-c comment> [-n]\n");
	print("-H	Schedule Downtime for \"h\"osts or \"s\"ervices in a specific HostGroup\n");
	print("-S	Schedule Downtime for \"h\"osts or \"s\"ervices in a specific ServiceGroup\n");
	print("-g	Host or Service Group associated with -H or -S request\n");
	print("-h	Schedule downtime for host, except when used with -s.  Cannot be used with -H or -S\n");
	print("-s	Schedule downtime for service, cannot be used with -H or -S.  May be \"all\" when used with -h.\n");
	print("-f	Set a fixed schedule, otherwise downtime runs for <duration> seconds\n");
	print("-d	Duration of downtime, in seconds.  Cannot be used with -f\n");
	print("-b	Beginning of Downtime.\n");
	print("-e 	End of Downtime.\n");
	print("-a 	Author: Who is scheduling the downtime?\n");
	print("-c	Comment: Reason for scheduling the downtime.\n");
	print("-n	Dry run.  Don't actually schedule anything, but show what would have been scheduled.\n");
	print("\n\t-b and -e options can take any string recognized by PHP's strtotime function.\n");
	print("\tExample: \"13:30 tomorrow\", \"Thursday\", etc... \n\tsee http://www.php.net/manual/en/function.strtotime.php for details\n");
	exit();
}

$fixed = 0;

// main()

// Parse cmdline
$opt = getopt("H:h:S:s:g:fd:b:e:a:c:n");

if ( count($opt) == 0 )
	usage();

foreach ($opt as $key => $val)
{
	switch($key)
	{
		case "H":
			if ( ereg("^[hs]{1}$", $val) )
				$hgtype = $val;							
			else
				usage("Invalid usage of -H.  Must be followed by either \"h\" or \"s\"");
		break;

		case "h":
			$host = $val;
		break;

		case "S":
			if ( ereg("^[hs]{1}$", $val) )
				$sgtype = $val;
			else
				usage("Invalid usage of -S.  Must be followed by either \"h\" or \"s\"");
		break;

		case "s":
			$service = $val;
		break;

		case "g":
			$group = $val;
		break;

		case "f":
			$fixed = 1;
		break;

		case "d":
			$duration = $val;
		break;

		case "b":
			$start = $val;
		break;

		case "e":
			$end = $val;
		break;

		case "a":
			$author = $val;
		break;

		case "c":
			$comment = $val;
		break;
	
		case "n":
			$dryrun = 1;
		break;
	}
}		

// "Sanitize" the arguments...STOP THE INSANITY!
// 

if ( isset($sgtype) && isset($hgtype) )
	usage("-S and -H cannot be used together");

if ( isset($sgtype) || isset($hgtype) )
	if ( ! isset($group) )
		usage("-g must be passed when using -H or -S");

if ( isset($group) && (isset($host) || isset($service) ) )
	usage("-g cannot be used with -h or -s, only -H or -S");

if ( (!( isset($host) || isset($hgtype) || isset($sgtype))) )
	usage("Must specify at least one of -H, -S or -h");

if ( $fixed > 0 && $duration > 0 )
	usage("-f and -d cannot be used together");

if ( $fixed == 0 && $duration == 0 )
	usage("Must specify one of -d or -f");

if ( !isset($start) )
	usage("Must specify a start time (-b)");
else
	if ( ($start_time = strtotime($start)) <= 0 )
		usage("\"$start\" is not a valid starting time");

if ( !isset($end) )
	usage("Must specify an end time (-e)");
else
	if ( ($end_time = strtotime($end)) <= 0 )
		usage("\"$end\" is not a valid ending time");

if ( $fixed == 1 )
	$duration = $end_time - $start_time;

if ( !isset($author) )
	usage("Must specify an event author (-a)");

if ( !isset($comment) )
	usage("Must specify an event comment (-c)");

if ( isset($group) )
{
	if ( isset($hgtype))
		switch($hgtype)
		{
			case "s":
				$cmd = "SCHEDULE_HOSTGROUP_SVC_DOWNTIME";
			break;
		
			case "h":	
				$cmd = "SCHEDULE_HOSTGROUP_HOST_DOWNTIME";
			break;
		}
	
	if ( isset($sgtype) )	
		switch($sgtype)
		{
			case "s":
				$cmd = "SCHEDULE_SERVICEGROUP_SVC_DOWNTIME";
			break;
			
			case "h":
				$cmd = "SCHEDULE_SERVICEGROUP_HOST_DOWNTIME";
			break;
		}
}
else 
{
	if ( isset($host) )
	{
		if ( isset($service) )
			if ( ereg("^all$", $service) )
				$cmd = "SCHEDULE_HOST_SVC_DOWNTIME";
			else
				$cmd = "SCHEDULE_SVC_DOWNTIME";
		else
			$cmd = "SCHEDULE_HOST_DOWNTIME";
	}
}
	

switch($cmd)
{
	case "SCHEDULE_HOSTGROUP_HOST_DOWNTIME":
	case "SCHEDULE_HOSTGROUP_SVC_DOWNTIME":
	case "SCHEDULE_SERVICEGROUP_HOST_DOWNTIME":
	case "SCHEDULE_SERVICEGROUP_SVC_DOWNTIME":
		$exec = "[". time() ."] $cmd;$group;$start_time;$end_time;$fixed;0;$duration;$author;$comment\n";
	break;

	case "SCHEDULE_HOST_DOWNTIME":
	case "SCHEDULE_HOST_SVC_DOWNTIME":
		$exec = "[". time() ."] $cmd;$host;$start_time;$end_time;$fixed;0;$duration;$author;$comment\n";
	break;

	case "SCHEDULE_SVC_DOWNTIME":
		$exec = "[". time() ."] $cmd;$host;$service;$start_time;$end_time;$fixed;0;$duration;$author;$comment\n";
	break;
}

if ( isset($dryrun) )
	print("Here is what WOULD have happened...\n\n$exec");
else
{
	if ( ($fp = fopen(NAGIOSCMD, "w")) === FALSE )
	{
		print("*** Cannot open ". NAGIOSCMD ." for writing.  Check your setup/permissions.\n");
		exit();
	}
	else
	{
		if ( fwrite($fp, $exec) != strlen($exec) )
			print("*** Error writing to ". NAGIOSCMD ."\n");
		fclose($fp);
	}
}

?>
