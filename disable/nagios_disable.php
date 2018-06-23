#!/usr/bin/php -q
<?php

// Aaron Segura 02/12/2008
//
//	Disables active checks for given hosts/services.
//

// User defined configs...
//
define(NAGIOSCMD, "/usr/local/groundwork/nagios/var/spool/nagios.cmd");


// *** Don't touch me below here, ever...ever ever ever never ever neever nerverton ***
//
define(VERSION, "0.1");
define(VERDATE, "02/12/2008");

function usage($msg = NULL)
{
	if ( strlen($msg) > 0 )
		print("\n*** $msg\n\n");	

	print(basename($_SERVER["argv"][0]) ." v". VERSION ." (". VERDATE .") by Aaron Segura\n\n");
	print("Usage: ". basename($_SERVER["argv"][0]) ." <-e|-d> [-H <s|h>|-S <s|h>] [-g group] [-h host] [-s service] [-n]\n");
	print("-e 	Enable Checks for Specified hosts or services\n");
	print("-d 	Disable Checks for Specified hosts or services\n");
	print("-H	Disable/Enable Checks for \"h\"osts or \"s\"ervices in a specific HostGroup\n");
	print("-S	Disable/Enable Checks for \"h\"osts or \"s\"ervices in a specific ServiceGroup\n");
	print("-g	Host or Service Group associated with -H or -S request\n");
	print("-h	Disable/Enable Checks for host, except when used with -s.  Cannot be used with -H or -S\n");
	print("-s	Disable/Enable Checks for service, cannot be used with -H or -S.  May be \"all\" when used with -h.\n");
	print("-n	Dry run.  Don't actually disable anything, but show what would have been done.\n");
	exit();
}

$fixed = 0;

// main()

// Parse cmdline
$opt = getopt("H:h:S:s:g:nde");

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

		case "e":
			if ( ! isset($op) )
				$op = "ENABLE";
			else
				usage("Cannot use -e and -d together");
		break;

		case "d":
			if ( ! isset($op) )
				$op = "DISABLE";
			else
				usage("Cannot use -e and -d together");
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

if ( ! isset($op) )
	usage("Must specify one of -e(nable) or -d(isable)");

if ( isset($group) )
{
	if ( isset($hgtype))
		switch($hgtype)
		{
			case "s":
				$cmd = "${op}_HOSTGROUP_SVC_CHECKS";
			break;
		
			case "h":	
				$cmd = "${op}_HOSTGROUP_HOST_CHECKS";
			break;
		}
	
	if ( isset($sgtype) )	
		switch($sgtype)
		{
			case "s":
				$cmd = "${op}_SERVICEGROUP_SVC_CHECKS";
			break;
			
			case "h":
				$cmd = "${op}_SERVICEGROUP_HOST_CHECKS";
			break;
		}
}
else 
{
	if ( isset($host) )
	{
		if ( isset($service) )
			if ( ereg("^all$", $service) )
				$cmd = "${op}_HOST_SVC_CHECKS";
			else
				$cmd = "${op}_SVC_CHECK";
		else
			$cmd = "${op}_HOST_CHECK";
	}
}
	
switch($cmd)
{
	case "${op}_HOSTGROUP_HOST_CHECKS":
	case "${op}_HOSTGROUP_SVC_CHECKS":
	case "${op}_SERVICEGROUP_HOST_CHECKS":
	case "${op}_SERVICEGROUP_SVC_CHECKS":
		$exec = "[". time() ."] $cmd;$group\n";
	break;

	case "${op}_HOST_CHECK":
	case "${op}_HOST_SVC_CHECKS":
		$exec = "[". time() ."] $cmd;$host\n";
	break;

	case "${op}_SVC_CHECK":
		$exec = "[". time() ."] $cmd;$host;$service\n";
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
