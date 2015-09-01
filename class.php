<?php
	/**
	* @author vinrosete
	* @description This is a class that handles the map path drop, length calculation
	*/
	class map {
		
		public $MATRIX, $GRID, $MAX, $X, $Y, $DROP, $LENGTH;
		private $PATHS, $PATH, $PATH_SEARCH, $TOTAL_STEPS, $HAS_PATHS;
		
		private $USE_SQLITE, $DB;
		
		public function __construct()
		{
			$this->MATRIX = array();
			$this->GRID = array(0, 0);
			$this->MAX = 0;
			$this->X = 0;
			$this->Y = 0;
			$this->DROP = 0;
			
			$this->PATH = array();
			$this->PATHS = array();
			$this->PATH_SEARCH = array();
			$this->HAS_PATHS = false;
			$this->TOTAL_STEPS = 0;
			$this->USE_SQLITE = true; // configure false if disable sqlite and use array
			
			date_default_timezone_set("Asia/Singapore");
		}
		
		//call to create the map matrix
		public function create($stream = null) 
		{
			if (!empty($stream))
			{
				$lines = 0;
				echo "Writing to array:         ";
				while ($line = fgets($stream)) 
				{
				  if (!empty($line)) 
				  {
					 if ($lines == 0)
					 {
						 //extract the line matrix size save into grid variable
						$this->GRID = explode(" ",$line);
						$this->GRID[0] = isset($this->GRID[0]) ? (int) $this->GRID[0] : 0;	
						$this->GRID[1] = isset($this->GRID[1]) ? (int) $this->GRID[1] : 0; 
					 }
					 else
					 {
						 //extract the line matrix values and save into row variable
						$row = explode(" ",$line);
						$y = ($lines - 1);
						foreach ($row as $x => $area)
						{
							//make sure the grid size delcared is followed in values
							if (($x+1) <= $this->GRID[0] && ($y+1) <= $this->GRID[1]) 
							{
								//save the area value into the matrix variable
								$this->MATRIX[$y][$x] = $area;
								//get the current highest area elevation
								if ($area > $this->MAX) 
								{
									$this->MAX = $area;
									$this->X = $x; // save the x-coordinate of highest elevation
									$this->Y = $y; // save the y-coordinate of highest elevation
								}
							}
						}	 
					 }
				  }
				  ++$lines;
				  echo "\033[6D";
				  echo str_pad($lines, 4, ' ', STR_PAD_LEFT) . " #";
				  //manual override for configuration @construct to use sqlite or not
				  if ($this->USE_SQLITE) {
					  $this->initializeDB();
				  }
				}
				fclose($stream);
				// begin lookup at 0,0 but the path can start anywhere
				$this->findPath(0, 0);
				$this->buildPath();
				if ($this->USE_SQLITE) $this->getDropAndLengthDB();
				else $this->getDropAndLength();
			}
			else
			{
				echo "\nThe map file is empty.";
			}
		}
		
		//find the steps of the path (y-x coordinates)
		private function findPath($y=0, $x=0)
		{
			if ($this->USE_SQLITE)
				$this->DB->exec("BEGIN TRANSACTION");
				
			$timestart = microtime(true);
			$stepBuilt = 0;
			echo "\n[".date("Y-m-d H:i:s")."]: Checking directions:            ";
			for($_y = $y; $_y < $this->GRID[1]; $_y++)
			{
				for($_x = $x; $_x < $this->GRID[0]; $_x++)
				{
					echo "\033[12D";
				  	echo str_pad("($_x|$_y)", 12, ' ', STR_PAD_LEFT);
					$area = $this->MATRIX[$_y][$_x];
					$right = $this->isMovableRight($_x, $_y, $area);
					$left = $this->isMovableLeft($_x, $_y, $area);
					$down = $this->isMovableDown($_x, $_y, $area);
					$up = $this->isMovableUp($_x, $_y, $area);
					$cursor = array($_y, $_x); //current focused coordinate
					if ($right) //path goes right
					{
						$steps = array($cursor, $right);
						$tkey = implode(".", $right);
						$uniqueid = implode("-",$cursor)."-".$tkey;
						
						if ($this->USE_SQLITE) 
						{
							$this->insertIntoMap(
								$uniqueid, array($cursor[0],$cursor[1],$right[0],$right[1])
							);
						} 
						else 
						{
							array_push($this->PATH, $steps); //create steps for right direction
							$this->PATH_SEARCH[$tkey][] = $steps;	
						} 
						++$stepBuilt;
					}
					if ($left) //path goes left
					{
						$steps = array($cursor, $left);
						$tkey = implode(".", $left);
						$uniqueid = implode("-",$cursor)."-".$tkey;
						
						if ($this->USE_SQLITE) 
						{
							$this->insertIntoMap(
								$uniqueid,
								array (
									$cursor[0],
									$cursor[1],
									$left[0],
									$left[1]
								)
							);
						} 
						else 
						{
							array_push($this->PATH, $steps); //create steps for left direction
							$this->PATH_SEARCH[$tkey][] = $steps; 
						}
						++$stepBuilt;
					}
					if ($down) //path goes down
					{
						$steps = array($cursor, $down);
						$tkey = implode(".", $down);
						$uniqueid = implode("-",$cursor)."-".$tkey;
						
						if ($this->USE_SQLITE) 
						{
							$this->insertIntoMap(
								$uniqueid,
								array (
									$cursor[0],
									$cursor[1],
									$down[0],
									$down[1]	
								)
							);
						}  
						else 
						{
							array_push($this->PATH, $steps); //create steps for down direction
							$this->PATH_SEARCH[$tkey][] = $steps; 
						}
						++$stepBuilt;
					}
					if ($up) //path goes up
					{
						$steps = array($cursor, $up);
						$tkey = implode(".", $up);
						$uniqueid = implode("-",$cursor)."-".$tkey;
						
						if ($this->USE_SQLITE) 
						{
							$this->insertIntoMap(
								$uniqueid,
								array (
									$cursor[0],
									$cursor[1],
									$up[0],
									$up[1]	
								)
							);
						}  
						else 
						{
							array_push($this->PATH, $steps); //create steps for down direction
							$this->PATH_SEARCH[$tkey][] = $steps; 
						}
						++$stepBuilt;
					}
					if (isset($steps)) unset($steps);
					unset($cursor);
					unset($area);
					unset($right);
					unset($down);
					unset($left);
				}
			}
			
			if ($this->USE_SQLITE)
				$this->DB->exec("COMMIT");
				
			$timeend = microtime(true);
			$timeelapsed = $timeend - $timestart;
			$this->TOTAL_STEPS = $stepBuilt;
			echo "\nTotal built steps: ".$stepBuilt;
			echo "\nTime Elapsed: ".$timeelapsed." second(s)";
		}
		
		private function isMovableRight ($x=0, $y=0, $area=0)
		{
			if (isset($this->MATRIX[$y][$x+1]) && (int)$this->MATRIX[$y][$x+1] < (int)$area)
			{
				return array($y, $x+1);
			}
			
			return false;
		}
		
		private function isMovableLeft ($x=0, $y=0, $area=0)
		{
			if(isset($this->MATRIX[$y][$x-1]) && (int)($this->MATRIX[$y][$x-1]) < (int)($area))
			{
				return array($y, $x-1);
			}
			
			return false;
		}
		
		private function isMovableDown ($x=0, $y=0, $area=0)
		{
			if (isset($this->MATRIX[$y+1][$x]) && (int)$this->MATRIX[$y+1][$x] < (int)$area)
			{
				return array($y+1, $x);
			}
			
			return false;
		}
		
		private function isMovableUp ($x=0, $y=0, $area=0)
		{
			if (isset($this->MATRIX[$y-1][$x]) && (int)$this->MATRIX[$y-1][$x] < (int)$area)
			{
				return array($y-1, $x);
			}
			
			return false;
		}
		
		public function buildPath()
		{
			if ($this->USE_SQLITE){
				return $this->buildPathFromDB();
			}
			
			if (empty($this->PATH))
			{
				echo "\nNo Path found!\n";
				return;
			}
				
			echo "\n[".date("Y-m-d H:i:s")."]: Building path...       ";
			
			foreach($this->PATH as $i => $step)
			{
				// check steps if it creates a path.
				$this->findFromSteps($step);
				// check if paths were built 
				if (!empty($this->PATHS)) {
					$this->findFromPath($step);
				}
				echo "\033[12D";
				echo str_pad($i, 12, ' ', STR_PAD_LEFT)."";
				echo "/".$this->TOTAL_STEPS."        ";
				echo "\033[".(strlen($this->TOTAL_STEPS)+8)."D";
				unset($this->PATH[$i]);
			}
			
			if (!empty($this->PATHS)) {
				foreach($this->PATHS as $i => $step)
				{
					$this->findFromPath($step, $i);
				}
			}
		}
		
		private function findFromSteps($step = array(), $index = 0)
		{
			$key = implode(".", current($step));
			reset($step);
			
			if (!isset($this->PATH_SEARCH[$key])) return;
			
			// go through each step of the unique path
			foreach ($this->PATH_SEARCH[$key] as $fstep) 
			{
				//create temporary variable to assign the current step.
				$tmpStep = $step;
				unset($tmpStep[0]); //remove the step prepare for merging steps
				$npath = array_merge($fstep, $tmpStep); // merge steps to create path
				if (!in_array($npath,$this->PATHS))
					array_push($this->PATHS,$npath); // create new path
			}
		}
		
		private function findFromPath($step = array(), $index = 0)
		{
			// go through each path to find matching steps to add to path
			foreach($this->PATHS as $k => $path)
			{
				if($k == $index) continue;
				
				$lastPath = end($path);
				reset($path);
				
				if(
					$step[0][0] === $lastPath[0] &&
					$step[0][1] === $lastPath[1]
				) {
					//create temporary variable to assign the current step.
					$tmpStep = $step;
					unset($tmpStep[0]); //remove the step prepare for merging steps
					$npath = array_merge($path, $tmpStep);// merge steps to create path
					if (!in_array($npath,$this->PATHS))
						array_push($this->PATHS, $npath);  // create new path
				}
			}
		}
		
		private function buildPathFromDB()
		{
			$offset = 0;
			$limit = 10000;
			$count = 0;
			
			echo "\n[".date("Y-m-d H:i:s")."]: Building path...            ";
			
			do
			{
				$count = 0;
				$results = $this->DB->query("SELECT * FROM map LIMIT ".$limit." OFFSET ".$offset);
				while ($row = $results->fetchArray()) {
					$this->findFromStepsDB($row);
					if ($this->HAS_PATHS) {
						$this->findFromPathDB($this->createStepArray($row));
					}
					$count++;
					/* commented to show percentage instead of total paths searched.
					echo "\033[12D";
					echo str_pad($offset+$count, 12, ' ', STR_PAD_LEFT);
					echo "/".$this->TOTAL_STEPS;
					echo "\033[".(strlen($this->TOTAL_STEPS)+1)."D";
					*/
					echo "\033[12D";
					echo str_pad(round((($offset+$count)/$this->TOTAL_STEPS) * 100, 2), 10, ' ', STR_PAD_LEFT)." %";
				}
				$offset += $limit;
			} while ($count > 0);
			
			echo "\n[".date("Y-m-d H:i:s")."]: Finalizing path...            ";
			
			if ($this->HAS_PATHS) {
				$offset = 0;
				do
				{
					$i = 0;
					$result = $this->DB->query("SELECT * FROM path LIMIT ".$limit." OFFSET ".$offset);
					while($row = $result->fetchArray()) {
						$this->findFromPathDB(unserialize($row['array']));
						$i++;
						echo "\033[12D";
						echo str_pad($offset+$i, 10, ' ', STR_PAD_LEFT)." #";
					}
					$offset += $limit;
				} while ($i > 0);
			}
			
		}
		
		private function findFromStepsDB($row = array())
		{
			$results = $this->DB->query("SELECT * FROM map WHERE end_x = ".$row['current_x']." AND end_y = ".$row['current_y']);
			while($nrow = $results->fetchArray()) {
				$fstep = $this->createStepArray($nrow);
				$step = $this->createStepArray($row);
				unset($step[0]);
				$npath = array_merge($fstep, $step);// merge steps to create path
				/*if (!in_array($npath,$this->PATHS))
					array_push($this->PATHS, $npath);  // create new path
				*/
				$this->insertIntoPath($npath);
			}
			unset($results);
		}
		
		private function findFromPathDB($step = array())
		{
			// go through each path to find matching steps to add to path
			if (isset($step[0])) {
				$result = $this->DB->query("SELECT * FROM path WHERE end_x = ".$step[0][1]." AND end_y = ".$step[0][0]);
				while($row = $result->fetchArray()) {
					$path = unserialize($row['array']);
					unset($step[0]); //remove the step prepare for merging steps
					$npath = array_merge($path, $step);// merge steps to create path
					$this->insertIntoPath($npath);
				}
			}
		}
		
		private function createStepArray($row)
		{
			return array(
				array(
					$row['current_y'],
					$row['current_x']
				),
				array(
					$row['end_y'],
					$row['end_x']
				)
			);
		}
		
		public function getDropAndLength()
		{
			$highestLength = 0;
			foreach($this->PATHS as $i => $path)
			{
				if(count($path) >= $highestLength)
				{
					$highestLength = count($path);
					$this->LENGTH = count($path);
					$drop = $this->calculateDrop(current($path),end($path));
					if ($this->DROP < $drop) {
						$this->DROP = $drop;
					}
					$vals = array();
					foreach($path as $step)
					{
						$vals[] = $this->MATRIX[$step[0]][$step[1]];
					}
					echo "\nLENGTH $highestLength PATH : ". implode("-",$vals);
				}
				unset($this->PATHS[$i]);
			}
		}
		
		public function getDropAndLengthDB()
		{
			$limit = 10000;
			$offset = 0;
			do
			{
				$i = 0;
				$result = $this->DB->query("SELECT * FROM path LIMIT ".$limit." OFFSET ".$offset);
				while($row = $result->fetchArray()) {
					$path = unserialize($row['array']);
					if(count($path) >= $highestLength)
					{
						//reset drop when a new higher length is set
						if (count($path) > $highestLength) $this->DROP = 0;
						
						$highestLength = count($path);
						$this->LENGTH = count($path);
						$drop = $this->calculateDrop(current($path),end($path));
						
						// check if there is a higher drop than previous drops
						if ($this->DROP < $drop) {
							$this->DROP = $drop;
						}
						
						$vals = array();
						foreach($path as $step)
						{
							$vals[] = $this->MATRIX[$step[0]][$step[1]];
						}
						echo "\nLENGTH $highestLength PATH : ". implode("-",$vals);
					}
					$i++;
				}
				$offset += $limit;
			} while ($i > 0);
		}
		
		private function calculateDrop($start, $end)
		{
			return ($this->MATRIX[$start[0]][$start[1]] - $this->MATRIX[$end[0]][$end[1]]);
		}
		
		public function printPath()
		{
			echo json_encode($this->PATHS);
		}
		
		/**
		* DB-related functions START
		*/
		private function initializeDB()
		{
			$this->DB = new db();
			//http://stackoverflow.com/questions/15413575/why-is-sqlite-so-slow-2-q-s-on-a-specific-machine
			$this->DB->exec("pragma synchronous = off;");
			//initialize tables
			$this->DB->exec("DROP TABLE IF EXISTS map");
			$this->DB->exec("DROP TABLE IF EXISTS path");
			$this->DB->exec(
				"CREATE TABLE map (
					ID INTEGER PRIMARY KEY AUTOINCREMENT, 
					current_x INTEGER, 
					current_y INTEGER,
					end_x INTEGER,
					end_y INTEGER
				)"
			);
			$this->DB->exec(
				"CREATE TABLE path (
					ID INTEGER PRIMARY KEY AUTOINCREMENT,
					end_x INTEGER,
					end_y INTEGER, 
					array TEXT
				)"
			);
			//https://www.sqlite.org/optoverview.html#skipscan
			//optimize search query
			$this->DB->exec("CREATE INDEX map_idx1 on map (end_x, end_y);");
			$this->DB->exec("CREATE INDEX path_idx1 on path (end_x, end_y);");
			//optimize insert query remove duplicates from path
			$this->DB->exec("CREATE UNIQUE INDEX path_uidx1 on path (array);");
		}
		
		private function insertIntoMap($unique_id = "", $data = array())
		{
			$this->DB->query(
				"INSERT INTO map (current_y, current_x, end_y, end_x) VALUES (".
				$data[0].", ".$data[1].", ".$data[2].", ".$data[3].")"
			);
		}
		
		private function insertIntoPath($data = array())
		{
			$end = end($data);
			reset($data);
			try {
				@$this->DB->query(
					"INSERT INTO path (array, end_x, end_y) VALUES (\"".serialize($data)."\", ".$end[1].", ".$end[0].")"	
				);
			} catch (Exception $ex) {
				//nothing to do when duplicate insert
			}
			$this->HAS_PATHS = true;
		}
		/**
		* DB-related functions END
		*/
		
		public function __desctruct()
		{
			unset($this->MATRIX);
			unset($this->PATH);
			unset($this->PATHS);
		}
	} 
?>
