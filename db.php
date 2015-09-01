<?php 
	class db extends SQLite3
	{
		function __construct()
		{
			@unlink('mapsqlitedb.db');
			$this->open('mapsqlitedb.db');
		}
	}
?>
