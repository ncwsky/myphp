@echo off

@setlocal

set MY_PATH=%~dp0

if "%PHP_COMMAND%" == "" set PHP_COMMAND=php.exe

"%PHP_COMMAND%" "%MY_PATH%my" %*

@endlocal
