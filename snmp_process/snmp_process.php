#!/usr/bin/php -q
<?php
//
// snmp_process.php
//	Uses snmp to monitor for minimum/maximum number of named processes running.
//
//

define("VERSION", "0.2");
define("VERDATE", "October 09, 2006");

define("SNMP_TIMEOUT", 10000000); // 10 seconds
define("SNMP_RETRIES", 3);

function usage($msg = NULL)
{
	print(basename($_SERVER["argv"][0]) ." v". VERSION ." (". VERDATE ."), by Aaron M. Segura\n");
	print("Usage:\n\t-H\tHost to check\n\t-n\tCommunity Name\n\t-p\tProcess Name (exact)\n");
	print("\t-a\tProcess Arguments (regex)\n");
	print("\t-c\tMinimum Critical Threshold\n\t-C\tMaximum Critical Threshold\n");
	print("\t-w\tMinimum Warning Threshold\n\t-W\tMaximum Warning Threshold\n");

	if ( strlen($msg) )
		print("\n*** $msg\n");
	
	exit(1);
}

	// main()
	//

	$opts = getopt("H:n:c:C:w:W:p:a:");

	foreach ( $opts as $opt => $arg )
	{
		switch($opt)
		{
			case "H":
				$host = $arg;
			break;

			case "n":
				$community = $arg;
			break;

			case "a":
				$params = $arg;
			break; 

			case "p":
				$process = $arg;
			break;

			case "c":
				if ( ereg("^[0-9]+$", $arg)	)
					$min_crit = $arg;
				else
					usage("Improper value for -c: $arg");
			break;

			case "C":
				if ( ereg("^[0-9]+$", $arg) )
					$max_crit = $arg;
				else
					usage("Improper value for -C: $arg");
			break;

			case "w":
				if ( ereg("^[0-9]+$", $arg) )
					$min_warn = $arg;
				else
					usage("Improper value for -w: $arg");
			break;

			case "W":
				if ( ereg("^[0-9]+$", $arg) )
					$max_warn = $arg;
				else
					usage("Improper value for -W: $arg");
			break;
		} // switch()
	} // foreach()

	if ( ! isset($host) )
		usage("Must specify -H");

	if ( ! isset($process) )
		usage("Must specify -p");
	
	if ( ! isset($community) )
		$community = "public";

	// Default behavior is to check for at least one running process
	//
	if ( ! (isset($min_crit) || isset($max_crit) || isset($min_warn) || isset($max_warn)) )
		$min_crit = 0;

	snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
	echo "0 - ";
	$proc_table = @snmprealwalk($host, $community, "hrSWRunName", SNMP_TIMEOUT, SNMP_RETRIES);

	echo "done\n";
	$count = 0;

	echo count($proc_table) ." entries\n";
	if ( count($proc_table) > 1 )
	{
		foreach ( $proc_table as $oid => $proc )
		{
			if ( eregi("^${process}$", $proc) )
			{
				if ( isset($params) )
				{	
					$pid = substr($oid, strrpos($oid, ".")+1);
					echo "1 - ";
					$tmp = @snmpget($host, $community, "HOST-RESOURCES-MIB::hrSWRunParameters.$pid", SNMP_TIMEOUT, SNMP_RETRIES);
					echo "done\n";
					if ( ereg("$params", $tmp) )
						$count++;
				}
				else
					$count++;
			}
		}
	}

	echo "X\n";

	// Used for output purposes
	//
	if ( isset($params) )
		$progstr = "\"$process $params\"";
	else
		$progstr = "\"$process\"";

	if ( isset($max_crit) && ($count >= $max_crit) )
	{
		print("PROCESS CRITICAL - ($count >= $max_crit) $progstr processes running | count=$count\n");
		exit(2);
	}
	else
		if ( isset($min_crit) && ($count <= $min_crit) )
		{
			print("PROCESS CRITICAL - ($count <= $min_crit) $progstr processes running | count=$count\n");
			exit(2);
		}
		else
			if ( isset($max_warn) && ($count >= $max_warn) )
			{
				print("PROCESS WARNING - ($count >= $max_warn) $progstr processes running | count=$count\n");
				exit(1);
			}
			else
				if ( isset($min_warn) && ($count <= $min_warn) )
				{
					print("PROCESS WARNING - ($count <= $min_warn) $process processes running | count=$count\n");
					exit(1);
				}
				
	print("PROCESS OK - $count $progstr processes running | count=$count\n");
?>
