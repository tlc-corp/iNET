<?php

namespace pocketmine\utils;

use LogLevel;
use pocketmine\Thread;
use pocketmine\Worker;
use pocketmine\Server;

class MainLogger extends \AttachableThreadedLogger{
	protected $logFile;
	protected $logStream;
	protected $shutdown;
	protected $logDebug;
	private $enabled;
	/** @var MainLogger */
	public static $logger = null;

	/**
	 * @param string $logFile
	 * @param bool   $logDebug
	 *
	 * @throws \RuntimeException
	 */
	public function __construct($logFile, $logDebug = false){
		if(static::$logger instanceof MainLogger){
			throw new \RuntimeException("MainLogger has been already created");
		}
		static::$logger = $this;
		$this->enabled=false;
		file_put_contents($logFile, "", FILE_APPEND);
		$this->logFile = $logFile;
		$this->logDebug = (bool) $logDebug;
		$this->logStream = \ThreadedFactory::create();
		$this->start();
	}

	/**
	 * @return MainLogger
	 */
	public function Disable(){
		$this->enabled = false;
	}
	
	public function Enable(){
		$this->enabled = true;
	}
	 
	public static function getLogger(){
		return static::$logger;
	}

	public function emergency($message, $name = "Emergency"){
		$this->send($message, \LogLevel::EMERGENCY, $name, TextFormat::RED);
	}

	public function alert($message, $name = "ALERT"){
		$this->send($message, \LogLevel::ALERT, $name, TextFormat::BLUE);
	}

	public function critical($message, $name = "Critical"){
		$this->send($message, \LogLevel::CRITICAL, $name, TextFormat::DARK_RED);
	}

	public function error($message, $name = "Error"){
		$this->send($message, \LogLevel::ERROR, $name, TextFormat::DARK_PURPLE);
	}

	public function warning($message, $name = "Warning"){
		$this->send($message, \LogLevel::WARNING, $name, TextFormat::YELLOW);
	}

	public function notice($message, $name = "Notice"){
		$this->send($message, \LogLevel::NOTICE, $name, TextFormat::GOLD);
	}

	public function info($message, $name = "Info"){
		$this->send($message, \LogLevel::INFO, $name, TextFormat::AQUA);
	}

	public function debug($message, $name = "Debug"){
		if($this->logDebug === false){
			return;
		}
		$this->send($message, \LogLevel::DEBUG, $name, TextFormat::GRAY);
	}

	/**
	 * @param bool $logDebug
	 */
	public function setLogDebug($logDebug){
		$this->logDebug = (bool) $logDebug;
	}

	public function logException(\Exception $e, $trace = null){
		if($trace === null){
			$trace = $e->getTrace();
		}
		$errstr = $e->getMessage();
		$errfile = $e->getFile();
		$errno = $e->getCode();
		$errline = $e->getLine();

		$errorConversion = [
			0 => "EXCEPTION",
			E_ERROR => "E_ERROR",
			E_WARNING => "E_WARNING",
			E_PARSE => "E_PARSE",
			E_NOTICE => "E_NOTICE",
			E_CORE_ERROR => "E_CORE_ERROR",
			E_CORE_WARNING => "E_CORE_WARNING",
			E_COMPILE_ERROR => "E_COMPILE_ERROR",
			E_COMPILE_WARNING => "E_COMPILE_WARNING",
			E_USER_ERROR => "E_USER_ERROR",
			E_USER_WARNING => "E_USER_WARNING",
			E_USER_NOTICE => "E_USER_NOTICE",
			E_STRICT => "E_STRICT",
			E_RECOVERABLE_ERROR => "E_RECOVERABLE_ERROR",
			E_DEPRECATED => "E_DEPRECATED",
			E_USER_DEPRECATED => "E_USER_DEPRECATED",
		];
		if($errno === 0){
			$type = LogLevel::CRITICAL;
		}else{
			$type = ($errno === E_ERROR or $errno === E_USER_ERROR) ? LogLevel::ERROR : (($errno === E_USER_WARNING or $errno === E_WARNING) ? LogLevel::WARNING : LogLevel::NOTICE);
		}
		$errno = isset($errorConversion[$errno]) ? $errorConversion[$errno] : $errno;
		if(($pos = strpos($errstr, "\n")) !== false){
			$errstr = substr($errstr, 0, $pos);
		}
		$errfile = \pocketmine\cleanPath($errfile);
		$this->log($type, get_class($e) . ": \"$errstr\" ($errno) in \"$errfile\" at line $errline");
		foreach(@\pocketmine\getTrace(1, $trace) as $i => $line){
			$this->debug($line);
		}
	}

	public function log($level, $message){
		switch($level){
			case LogLevel::EMERGENCY:
				$this->emergency($message);
				break;
			case LogLevel::ALERT:
				$this->alert($message);
				break;
			case LogLevel::CRITICAL:
				$this->critical($message);
				break;
			case LogLevel::ERROR:
				$this->error($message);
				break;
			case LogLevel::WARNING:
				$this->warning($message);
				break;
			case LogLevel::NOTICE:
				$this->notice($message);
				break;
			case LogLevel::INFO:
				$this->info($message);
				break;
			case LogLevel::DEBUG:
				$this->debug($message);
				break;
		}
	}

	public function shutdown(){
		$this->shutdown = true;
	}

	protected function send($message, $level, $prefix, $color){
		$now = time();

		$thread = \Thread::getCurrentThread();
		if($thread === null){
			$threadName = TextFormat::RED."system";
		}elseif($thread instanceof Thread or $thread instanceof Worker){
			$threadName = TextFormat::BLUE.$thread->getThreadName() . "";
		}else{
			$threadName = (new \ReflectionClass($thread))->getShortName() . "";
		}

		$message = TextFormat::toANSI(TextFormat::GRAY . " " . date("H:i:s", $now) . "  " . $threadName . "> " . TextFormat::RESET . $color ."<" . $prefix . ">" . TextFormat::LIGHT_PURPLE . " " . $message . TextFormat::RESET);
		//$message = TextFormat::toANSI(TextFormat::AQUA . "[" . date("H:i:s") . "] ". TextFormat::RESET . $color ."<".$prefix . ">" . " " . $message . TextFormat::RESET);
		$cleanMessage = TextFormat::clean($message);

		if(!Terminal::hasFormattingCodes()){
			echo $cleanMessage . PHP_EOL;
		}else{
			echo $message . PHP_EOL;
		}

		if($this->attachment instanceof \ThreadedLoggerAttachment){
			$this->attachment->call($level, $message);
		}

		$this->logStream[] = date("Y-m-d", $now) . " " . $cleanMessage . "\n";
		if($this->logStream->count() === 1){
			$this->synchronized(function(){
				$this->notify();
			});
		}
	}

	public function run(){
		$this->shutdown = false;
		while($this->shutdown === false){
			$this->synchronized(function (){
				while($this->logStream->count() > 0 and $this->enabled){
					$chunk = $this->logStream->shift();
					file_put_contents($this->logFile, $chunk, FILE_APPEND);
				}	
				$this->wait(25000);
			});
		}
				
		if($this->logStream->count() > 0){
			while($this->logStream->count() > 0 and $this->enabled){
				$chunk = $this->logStream->shift();
				file_put_contents($this->logFile, $chunk, FILE_APPEND);
			}
		}
	}
}
