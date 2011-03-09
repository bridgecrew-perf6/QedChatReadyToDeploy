<?php

	ignore_user_abort (true);

	set_error_handler (ErrorHandler);

	function ErrorHandler ($number, $description, $file, $line)
	{
		if (error_reporting () & $number)
		{
			header ("HTTP/1.1 503 Service unavailable");
			
			global $recorded;
			if ($recorded)
				echo "Dein Post konnte zwar eingetragen, doch m�glicherweise nicht an die Clients gesendet werden!<br>";
			else
				echo "Dein Post konnte m�glicherweise nicht eingetragen werden!<br>";
		
			echo "Der Fehler mit Nummer $number trat in $file in Zeile $line auf.";
			if ($description != "")
				echo "<br>Beschreibung: $descriptions";
			exit ();
		}
	}
	
	$recorded = false;
	
	require_once ("data.php");
	require_once ("common.php");	

	/*if (strstr (getenv("HTTP_USER_AGENT"), "Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.8.1.8) Gecko/20071004 Iceweasel/2.0.0.8 (Debian-2.0.0.6+2.0.0.8-0etch1)") !== FALSE
		&& $post["ip"] == "89.14.166.134")
		exit ();*/

	
	/*if (SECURE_POSTS)
	{
		$sem = sem_get (SEM_SECURE_POSTS_KEY);
		sem_acquire ($sem);

		$authorized = true;
		if (!isset ($_POST["key"]))
			$authorized = false;
		else
			$authorized = is_key_valid ($_POST["key"]);

		sem_release ($sem);
			
		if (!$authorized)
		{
			header ("HTTP/1.1 401 Unauthorized");
			echo "Bot-Schutz ist aktiviert. Falls du einen Browser verwendest, lade erst die send.html und send.js (dort jeweils auf aktualisieren klicken) und dann den Chat und warte bis er vollst�ndig geladen hat.";
			die ();
		}
	}*/

	$post = array ("name" => demagicalize_string ($_POST["name"]),
				   "message" => demagicalize_string ($_POST["message"]),
				   "ip" => getenv ("REMOTE_ADDR"),
				   "date" => date ("Y-m-d H-i-s"),
				   "delay" => ((!is_numeric ($_POST["delay"]) | $_POST["delay"] < 0) ? "NULL" : $_POST["delay"]));

	//if (FLOOD)
	/*if (substr ($post["message"], 0, 11) == "%21showpost")
	{	   
		$sem = sem_get (SEM_FLOOD_KEY);
		sem_acquire ($sem);
	
		$time = time ();
		$found = false;
		$valid = true;
		
		$ipTable = file (FLOOD_FILE);
		for ($j = 0; $j != count ($ipTable); ++$j)
		{
			$array = explode (" - ", chop ($ipTable[$j]));
			if ($array[0] == $post["ip"])
			{
				$found = true;
				$array = explode ("; ", $array[1]);
				$newArray = array ($time);
				for ($i = 0; $i != count ($array); ++$i)
					if ($time - $array[$i] < 5)//FLOOD_INTERVAL)
						array_push ($newArray, $array[$i]);
				
				if (count ($newArray) > 1)//FLOOD_MAX_POSTS)
				{
					$valid = false;
					break;
				}
			
				$ipTable[$j] = $post["ip"] . " - " . implode ("; ", $newArray) . "\n";
			}
		}
		
		if ($valid)
		{
    		if (!$found)
    			array_push ($ipTable, $post["ip"] . " - " . $time . "\n");
    		
    		$file = fopen ("flood.txt", "w");
    		for ($j = 0; $j != count ($ipTable); ++$j)
    			fwrite ($file, $ipTable[$j]);
    		fclose ($file);
	    }
	    
		sem_release ($sem);
		
		if (!$valid)
		{
    		header ("HTTP/1.1 403 Forbidden");
			echo "You got 0wned by teh s3rv3r, fl00d3r!!!!!!ONEONE!!1111337";
			die ();
		}
	}*/
	
	if (POST_LIMITS)
	{
    	if (strlen ($post["message"]) > POST_LIMITS_MAX_LENGTH)
    	    $valid = false;
    	else if (substr_count ($post["message"], "\n") >= POST_LIMITS_MAX_LINES)
    	    $valid = false;
        else
        {
            $whitespaces = array (' ', "\t", "\n");
            $offset = 0;

            for ($found = true; $found; )
            {
                $found = false;
                for ($i = 0; $i != count ($whitespaces); ++$i)
                {
                    $next = strpos ($post["message"], $whitespaces[$i], $offset);
                    if ($next !== false && $next - $offset - 1 <= POST_LIMITS_MAX_CONTIGUOUS_NWSP)
                    {
                        $offset = $next + 1;
                        $found = true;
                        break;
                    }
                }
            }
            
            $valid = (strlen ($post["message"]) - $offset <= POST_LIMITS_MAX_CONTIGUOS_NWSPS);
	 		if (!$valid)
			{
	    		header ("HTTP/1.1 403 Forbidden");
				echo "Aus Traffic-Gr�nden d�rfen Posts momentan nicht allzu gro� sein.<br>" . $offset . " " . strlen ($post["message"]);
				die ();
			}
        }
            	   
	}


	

	do_post ($post);

?>