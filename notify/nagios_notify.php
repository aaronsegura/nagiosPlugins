#!/usr/bin/php -q
<?php
//
// Aaron Segura 07/10/2006
//
// 	Semi-Intelligent Notification Script for Nagios 2.x.  Uses templates in the format of:
//
//  SUBJECT
//	BODY
//	BODY
//	...
//
//  Any valid NAGIOS Macros may be used in the context of SUBJECT or BODY.  #IF #ELSE #ENDIF directives
// 		are also understood, in the format of "#IF NAGIOSMACRONAME(op)VALUE", where (op) may be any of
//		= > < >= <= ~= != .  NO SPACES AROUND THE OPERATOR.  See sample templates for examples.
//

// Define the Template Directory
define("TEMPLATEDIR", "/usr/local/share/notify_templates/");

//
// *** Don't touch me below here unless you're a qualified Reverend Doctor. ***
//

define("VERSION", "0.5");
define("VDATE", "November 10, 2008");

// Default behavior is to write
$write = array("1");

// Basic usage statement // error reporting
function usage($msg = NULL)
{
	if ( isset($msg) )
		echo "*** $msg\n\n";

	print(basename($_SERVER["argv"][0]) ." v". VERSION ." (". VDATE .") by Aaron Segura\n\n");
	print("Usage: ". basename($_SERVER["argv"][0]) ." <-t template> <-c contact e-mail> <-f from> [-g TAG] [-x]\n");
	print("-t	Template File\n");
	print("-c	Contact E-mail\n");
	print("-f	From Address\n");
	print("-g	Subject Tag, will be prepended to subject line in template\n");
	print("-x	Just print the message to stdout, do not mail\n\n");

	exit();
}

// This function will take care of parsing the #IF...#ENDIF conditional
// 	statements in the template, as well as other #(LOGIC)
//
function parse_template_logic($body)
{
	global $write;
	$return = array();

	foreach($body as $line)
	{
		$words = split(" ", $line);	
		
		switch(trim($words[0]))
		{
			case "#IF":
				if ( $write[count($write)-1] == 1 )
				{
					$cond = rtrim(implode(" ", array_slice($words, 1)));
					$parts = preg_split("/>=|<=|!=|~=|>|<|=/", $cond);
					$operator = substr($cond, strlen($parts[0]), strlen($cond) - strlen($parts[0]) - strlen($parts[1]));
	
					switch($operator)
					{
						case ">=":
							if ( $_ENV["NAGIOS_".$parts[0]] >= $parts[1] )
							{
								if ( $write[count($write)-1] == 0 )
									$write[] = 0;
								else
									$write[] = 1;	
							}
							else
								$write[] = 0;
						break;
		
						case "<=":
							if ( $_ENV["NAGIOS_".$parts[0]] <= $parts[1] )
							{
								if ( $write[count($write)-1] == 0 )
									$write[] = 0;
								else
									$write[] = 1;	
							}
							else
								$write[] = 0;	
						break;
	
						case "!=":
							if ( $_ENV["NAGIOS_".$parts[0]] != $parts[1] )
							{
								if ( $write[count($write)-1] == 0 )
									$write[] = 0;
								else
									$write[] = 1;	
							}
							else
								$write[] = 0;
						break;
	
						case "~=":
							if ( preg_match($parts[1], $_ENV["NAGIOS_".$parts[0]]) )
							{
								if ( $write[count($write)-1] == 0 )
									$write[] = 0;
								else
									$write[] = 1;	
							}
							else
								$write[] = 0;
						break;
	
						case "<":
							if ( $_ENV["NAGIOS_".$parts[0]] < $parts[1] )
							{
								if ( $write[count($write)-1] == 0 )
									$write[] = 0;
								else
									$write[] = 1;	
							}
							else
								$write[] = 0;
						break;
	
						case ">":
							if ( $_ENV["NAGIOS_".$parts[0]] > $parts[1] )
							{
								if ( $write[count($write)-1] == 0 )
									$write[] = 0;
								else
									$write[] = 1;	
							}
							else
								$write[] = 0;
						break;
	
						case "=":
							if ( $_ENV["NAGIOS_".$parts[0]] == $parts[1] )
							{
								if ( $write[count($write)-1] == 0 )
									$write[] = 0;
								else
									$write[] = 1;	
							}
							else
								$write[] = 0;
						break;
		
						default:
							print("*** WARNING: Unknown Operator \"$operator\" on line ". $x+2 ."\n");
					}						
				}
			break;

			case "#ENDIF":
				array_pop($write);
			break;

			case "#ELSE":
				switch($write[count($write)-1])
				{
					case "0":
						array_pop($write);
						$write[] = 1;
					break;

					case "1":
						array_pop($write);
						$write[] = 0;
					break;
				}
			break;

			case "#INCLUDE":
				if ( $write[count($write)-1] == 1 )
				{
					$filename = rtrim(implode(" ", array_slice($words,1)));
					if ( file_exists($filename) )
					{
						$tmp = file($filename);
	
						$tmp = replace_macros($tmp);
						$tmp = parse_template_logic($tmp);
						
						foreach ( $tmp as $line )
							$return[] = $line;
					}
				}
			break;

			case "#EXEC":
				if ( $write[count($write)-1] == 1 )
				{
					$cmd = rtrim(implode(" ", array_slice($words,1)));

					if ( $pp = popen($cmd, "r") ) 
					{
						while ( !feof($pp) )
							$tmp[] = rtrim(fgets($pp));

						pclose($pp);
					}
	
					$tmp = replace_macros($tmp);
					$tmp = parse_template_logic($tmp);
			
					foreach ( $tmp as $line )
						$return[] = $line;
				}
			break;

			case "#DIE":
				if ( $write[count($write)-1] == 1 )
					exit(0);
			break;
	
			default:
				if ( $write[count($write)-1] == 1 )
					$return[] = rtrim($line);
		}			
	}
	return($return);
}

function replace_macros($file)
{
	// Replace all Nagios Macro identifiers with their values
	$x = 0;
	while ( $x < count($file))
	{
		if ( preg_match_all("/\\$\w+\\$/", $file[$x], $macros) > 0 )
		{
			foreach ($macros[0] as $macro)
			{
				$macro = substr($macro, 1, strlen($macro)-2);
				if ( isset($_ENV["NAGIOS_$macro"]) )
					$file[$x] = str_replace("\$${macro}\$", $_ENV["NAGIOS_$macro"], $file[$x]);
			}
		}
		$x++;
	}
	return($file);
}
	
	// main()
	$opt = getopt("c:t:f:g:hx");

	if ( count($opt) == 0 )
		usage();

	// Parse cmdline options
	foreach ( $opt as $key => $val )
	{
		switch ( $key )
		{
			case "t":
				if ( file_exists($val) )
					$template = $val;
				else if ( file_exists(TEMPLATEDIR ."/$val") )
						$template = TEMPLATEDIR ."/$val";
					else
						usage("Cannot find template $val");
			break;
		
			case "c":
				$contact = $val;
			break;

			case "h":
				usage();
			break;
	
			case "f":
				$from = $val;
			break;
		
			case "g":
				$tag = $val;
			break;

			case "x":
				$print = 1;
			break;
		}
	}

	if ( ! isset($template) )
		usage("Must specify a template (-t <template>)");

	if ( ! isset($contact) && $print != 1 )
		usage("Must specify a contact (-c <email>)");

	if ( ! isset($from) && $print != 1 )
		usage("Must specify a \"from\" address (ie. \"Nagios <admin@nagios>\")");

	// Read Template
	$file = file($template);

	$file = replace_macros($file);
	$returned = parse_template_logic($file);

	// Tag the subject line, if needed.
	$subject = $tag . $returned[0];
	$body    = array_slice($returned, 1);

	// Do the Dirty
	if ( $print == 1 )
	{
		print("$subject\n");
		foreach($body as $line)
			print("$line\n");
	}
	else
	{
		// Mail the dirty
		if ( mail($contact, $subject, implode("\n", $body), "From: $from") )
			exit();
		else
		{
			echo "E-mail failed\n";
			exit(1);
		}
	}	
?>
