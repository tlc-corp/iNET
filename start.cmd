@echo off
TITLE iNET
cd /d %~dp0

if exist bin\php\php.exe (
	set PHPRC=""
	set PHP_BINARY=bin\php\php.exe
) else (
	set PHP_BINARY=php
)

if exist iNET.phar (
	set POCKETMINE_FILE=iNET.phar
) else (
	if exist src\pocketmine\PocketMine.php (
		set POCKETMINE_FILE=src\pocketmine\PocketMine.php
	) else (
		echo "Couldn't find a valid iNET installation"
		pause
		exit 1
	)
)

if exist bin\php\php_wxwidgets.dll (
%PHP_BINARY% %POCKETMINE_FILE% --enable-gui %*
) else (
		%PHP_BINARY% -c bin\php %POCKETMINE_FILE% %*
)
