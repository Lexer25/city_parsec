@echo off
chcp 1251 > nul

rem ============================================================
rem  parsecFullUpdate.bat
rem
rem  Полное обновление персоны в Parsec.
rem  Последовательно вызывает 4 Minion-задачи:
rem    1) parsecChangeGuid
rem    2) parsecSyncPeope
rem    3) parsecSyncIdentifiers
rem    4) parsecSyncAccessGroups
rem
rem  Использование:
rem    parsecFullUpdate.bat --id_pep=1378
rem    parsecFullUpdate.bat --id_pep=1378 --add=1
rem ============================================================

set "ID_PEP="

rem --- Разбор аргументов ---
set "ARG1=%~1"
set "ARG2=%~2"

rem Вариант 1: --id_pep=NNN
echo %ARG1% | findstr /b /c:"--id_pep=" >nul
if not errorlevel 1 (
    for /f "tokens=2 delims==" %%a in ("%ARG1%") do set "ID_PEP=%%a"
    goto args_done
)

rem Вариант 2: --id_pep NNN
if "%ARG1%"=="--id_pep" (
    set "ID_PEP=%ARG2%"
    goto args_done
)

rem Вариант 3: просто NNN
echo %ARG1% | findstr /r /c:"^[0-9][0-9]*$" >nul
if not errorlevel 1 (
    set "ID_PEP=%ARG1%"
    goto args_done
)

:args_done

if "%ID_PEP%"=="" (
    echo.
    echo ОШИБКА: не указан id_pep.
    echo Использование: parsecFullUpdate.bat --id_pep=NNN [--add=1]
    echo Пример:        parsecFullUpdate.bat --id_pep=1378
    echo.
    exit /b 1
)

set "PHP=C:\xampp\php\php.exe"
set "MINION=C:\xampp\htdocs\city\modules\minion\minion"

echo ============================================================
echo  ПОЛНОЕ ОБНОВЛЕНИЕ ПЕРСОНЫ
echo  id_pep      = %ID_PEP%
echo ============================================================
echo.

echo ------------------------------------------------------------
echo [1/4] parsecChangeGuid --id_pep=%ID_PEP%
echo ------------------------------------------------------------
"%PHP%" "%MINION%" --task=parsecChangeGuid --id_pep=%ID_PEP% --dry_run=0
echo.
ping -n 2 127.0.0.1 > nul
echo ------------------------------------------------------------
echo [2/4] parsecSyncPeope --id_pep=%ID_PEP%
echo ------------------------------------------------------------
"%PHP%" "%MINION%" --task=parsecSyncPeope --id_pep=%ID_PEP% --add=1
echo.
ping -n 2 127.0.0.1 > nul
echo ------------------------------------------------------------
echo [3/4] parsecSyncIdentifiers --id_pep=%ID_PEP%
echo ------------------------------------------------------------
"%PHP%" "%MINION%" --task=parsecSyncIdentifiers --id_pep=%ID_PEP% --add=1
echo.
ping -n 2 127.0.0.1 > nul
echo ------------------------------------------------------------
echo [4/4] parsecSyncAccessGroups --id_pep=%ID_PEP%
echo ------------------------------------------------------------
"%PHP%" "%MINION%" --task=parsecSyncAccessGroups --id_pep=%ID_PEP% --add=1
echo.

echo ============================================================
echo  ГОТОВО. Полное обновление персоны id_pep=%ID_PEP% завершено.
echo ============================================================

exit /b 0