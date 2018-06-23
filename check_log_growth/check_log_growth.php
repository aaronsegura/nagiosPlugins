#!/usr/bin/php -q
<?php
// Check Log Growth, by Aaron M. Segura
//

define(VERSION, "0.3");
define(VERDATE, "10/05/2006");

function usage($msg)
{
	print(basename($_SERVER["argv"][0]) ." v". VERSION ." (". VERDATE .")\n\n");
	print(basename($_SERVER["argv"][0]) ." -l <logfile> [-t <tmpdir>] -c <critspec> -w <warnspec>\n");
	print("  -l <logfile> 	Logfile to check\n");
	print("  -c <critspec>	Critical Growth Threshold\n");
	print("  -w <warnspec>	Warning Growth Threshold\n");
	print("  -t <tmpdir>	Directory to use for state files\n");

	if ( $msg )
		print("\n*!* $msg\n");

	exit(1);
}


$opts = getopt("w:c:l:t:");

foreach ( $opts as $key => $arg )
{
	switch ( $key )
	{
		case "l":
			if ( file_exists($arg) )
				$logfile = $arg;
			else
			{
				print("LOG GROWTH OK - $arg - Log doesn't exist | growth=0\n");
				exit(0);
			}
		break;

		case "w":
			if ( ereg("^[0-9]+$", $arg) )
				$warning_threshold = $arg;
			else
				usage("Invalid argument to -w\n");
		break;

		case "c":
			if ( ereg("^[0-9]+$", $arg) )
				$critical_threshold = $arg;
			else
				usage("Invalid argument to -c\n");
		break;

		case "t":
			if ( is_dir($arg) && is_writable($arg) )
				$tmpdir = $arg;
			else
				usage("$arg is not a writable directory\n");
		break;
	}
}

if ( ! isset($critical_threshold) )
	usage("Must specify -c");

if ( ! isset($warning_threshold) )
	usage("Must specify -w");

if ( ! isset($logfile) )
	usage("Must specify -l");

if ( ! isset($tmpdir) )
	$tmpdir="/tmp";

	$statefile = $tmpdir . "/" . strtr($logfile, "/", "_") ."_growth_state";

	if ( file_exists($statefile) )
		$oldsize = @file_get_contents($statefile);
	else
		$oldsize = 0;	

	if ( ($fp = fopen($logfile, "r")) === FALSE )
	{
		print("LOG GROWTH UNKNOWN - Cannot open $logfile for reading | growth=0");
		exit(255);
	}
	else
	{
		$fstat = fstat($fp);
		fclose($fp);

		$newsize = $fstat["size"];
		$delta = $newsize - $oldsize;	
		@file_put_contents($statefile, $newsize);

		if ( $delta > 1000000 )
			$delta_plain = round($delta/1000000, 1) . "MB";
		else
			if ( $delta > 1000 )
				$delta_plain = round($delta/1000, 1) . "KB";
			else
				$delta_plain = $delta ." bytes";

		if ( $delta > $critical_threshold )
		{
			print("LOG GROWTH CRITICAL - $logfile grew by $delta_plain | growth=$delta\n");
			exit(2);
		}
		else
			if ( $delta > $warning_threshold )
			{
				print("LOG GROWTH WARNING - $logfile grew by $delta_plain | growth=$delta\n");
				exit(1);
			}
			else
			{
				if ( $delta <= 0 )
					$delta = $newsize;

				print("LOG GROWTH OK - $logfile grew by $delta_plain | growth=$delta\n");
				exit(0);
			}
	}
?>
