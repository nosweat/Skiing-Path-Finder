<?php
	/**
	* @author vinrosete
	* @description This is a class that handles the map path drop, length calculation
	*/
	class map {
		
		public $MATRIX, $GRID, $MAX, $X, $Y, $DROP, $LENGTH;
		private $PATHS, $PATH;
		
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
								//save the area value into the matrix varaible
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
				}
				fclose($stream);
				// begin lookup at 0,0 but the path can start anywhere
				$this->findPath(0, 0);
				$this->buildPath();
				$this->getDropAndLength();
			}
			else
			{
				echo "\nThe map file is empty.";
			}
		}
		
		//find the steps of the path (y-x coordinates)
		private function findPath($y=0, $x=0)
		{
			echo "\nChecking directions:            ";
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
					$cursor = array($_y, $_x); //current focused coordinate
					if ($right) //path goes right
					{
						$steps = array($cursor, $right);
						array_push($this->PATH,$steps); //create steps for right direction
					}
					if ($left) //path goes left
					{
						$steps = array($cursor, $left);
						array_push($this->PATH,$steps); //create steps for left direction
					}
					if ($down) //path goes down
					{
						$steps = array($cursor, $down);
						array_push($this->PATH,$steps); //create steps for down direction
					}
				}
			}
			echo "\nTotal built steps: ".count($this->PATH);
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
		
		public function buildPath()
		{
			if (empty($this->PATH))
			{
				echo "No Path found!";
				return;
			}
				
			echo "\nBuilding path...       ";
			foreach($this->PATH as $i => $step)
			{
				// check steps if it creates a path.
				$this->findFromSteps($step, $i);
				// check if paths were built 
				if (!empty($this->PATHS)) {
					$this->findFromPath($step);
				}
				echo "\033[7D";
				echo str_pad($i, 5, ' ', STR_PAD_LEFT)." #";
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
			// go through each step to find a matching path
			foreach ($this->PATH as $i => $fstep) 
			{
				// do not check step from its own index
				if ($index == $i) continue;
				// check step exists from other paths
				$lastFstep = end($fstep);
				reset($fstep);
				
				if (
					$step[0][0] === $lastFstep[0] &&
					$step[0][1] === $lastFstep[1]
				)
				{
					//create temporary variable to assign the current step.
					$tmpStep = $step;
					unset($tmpStep[0]); //remove the step prepare for merging steps
					$npath = array_merge($fstep, $tmpStep); // merge steps to create path
					if (!in_array($npath,$this->PATHS))
						array_push($this->PATHS,$npath); // create new path
				}
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
		
		public function getDropAndLength()
		{
			$highestLength = 0;
			foreach($this->PATHS as $path)
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
			}
		}
		
		private function calculateDrop($start, $end)
		{
			return ($this->MATRIX[$start[0]][$start[1]] - $this->MATRIX[$end[0]][$end[1]]);
		}
		
		public function printPath()
		{
			echo json_encode($this->PATHS);
		}
		
		public function __desctruct()
		{
			unset($this->MATRIX);
			unset($this->PATH);
			unset($this->PATHS);
		}
	} 
?>
