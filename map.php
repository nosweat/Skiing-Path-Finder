<?php
	/**
	* @author vinrosete
	* @description This is a command-line php script that accepts input for the map file location.
	* The output of this script is length of the path, the drop, & the special email. e.g. length=5, drop=8
	* @usage Run Terminal on Mac, or Command-prompt on Windows & execute script "php map.php"
	* Enter the map file location.
	*/
	ini_set("display_errors","yes");
	ini_set("memory_limit", -1);
	include("class.php");
	
	print "\n\n===============================\n\n";
	print "Welcome to Skiing Solution!";
	print "\n\n===============================\n\n";
	
	$prompt = 'Enter the map file location: ';
	
	function prompt($msg)
	{
		if (PHP_OS == 'WINNT') 
		{
		  echo $msg;
		  return stream_get_line(STDIN, 1024, PHP_EOL);
		} 
		else 
		{
		  return readline($msg);
		}
	}
	
	function openMapFile($prompt="")
	{
		$mapFile = prompt($prompt);
		try 
		{
			$map = @fopen($mapFile,"r");
			return $map;
		}
		catch (Exception $ex)
		{
			print "\nMap file does not exist!\n";
			return false;
		}
	}
	
	function label($string = "")
	{
		echo "\n\n$string\n\n";
	}
	
	$mapClass = new map();
	$map = openMapFile($prompt);
	
	// repeat while map not found and input was not interrupted by user	
	do 
	{
		if ($map) 
		{
			$mapClass->create($map);
			label("O U T P U T");
			echo "highest elevation : ". $mapClass->MAX. "\n X-coordinate: ".$mapClass->X."\n Y-coordinate: ".$mapClass->Y.
			"\n Drop = ".$mapClass->DROP. "\n Length = ". $mapClass->LENGTH;
			
			echo "\n\nAnswer Email : ".$mapClass->LENGTH.$mapClass->DROP."@redmart.com";
			break;
		}
		else 
		{
			if(prompt("File not found. Do you want to try again? (y/n) : ") === "y")
			{
				$map = openMapFile($prompt);	
				if ($map) {
					$mapClass->create($map);
					label("O U T P U T");
					echo "highest elevation : ". $mapClass->MAX. "\n X-coordinate: ".$mapClass->X."\n Y-coordinate: ".$mapClass->Y.
					"\n Drop = ".$mapClass->DROP. "\n Length = ". $mapClass->LENGTH;
					
					echo "\n\nAnswer Email : ".$mapClass->LENGTH.$mapClass->DROP."@redmart.com";
				}
			}
			else
			{
				echo "Thank you for using my program!";
				break;
			}
		}	
	} while (!$map); 
	
	
	print "\n\n"
?>
