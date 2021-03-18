<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

define("DB_HOST","eu-cdbr-west-03.cleardb.net");
define("DB_USER","b732da40fe7602");
define("DB_PASS","b9f521a5");
define("DB_NAME","heroku_c042227ca7e58a6");
function add_log($text=false){
	if($text){
		$text = date("d-M-y H:i:s")." - ".$text."\n";
		$dir = __DIR__."/logs";
		if(!is_dir($dir))
			mkdir($dir);
		return file_put_contents($dir."/".date("d-m-y").".log", $text, FILE_APPEND|LOCK_EX);
	}
}

require_once($_SERVER["DOCUMENT_ROOT"]."/bot_client19022020/BitrixRequest.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/w4Fx0zsOc6erhZqE/src/classes/Database.php");
require_once(__DIR__."/BotLogic.php");
new ServiceDeskWABot();
 ?>