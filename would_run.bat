@REM A Windows commandline script to check whether the supplied
@REM command (the first and only parameter to this script)
@REM is runnable as-is - i.e. it both:
@REM
@REM 1. Is either stipulated with a valid executable extension, or
@REM    it is stipulated without an extension but a matching file
@REM    *with* a valid executable extension exists meeting the
@REM    second condition, namely that it:
@REM
@REM 2. Exists either in, or relative to, the current directory, or
@REM    it exists absolutely, or it exists in the path.
@REM
@REM Doesn't output anything, but returns 0 if the command
@REM would run, and 1 if it would not. This return can be checked
@REM via %ERRORLEVEL%.
@REM
@REM Note: the script does not check whether the current user has
@REM permissions to run the command.
@REM
@REM By Laird Shaw, 2014.
@REM Adapted from http://blogs.msdn.com/b/oldnewthing/archive/2005/01/20/357225.aspx
@REM
@REM Don't output anything for the rest of this script.
@echo off

REM Set f to the command to check (the first - and should be
REM only - parameter to this script).
REM Do this through a FOR loop so that we can use the nifty features of
REM FOR variable references.
for %%f in (%1) do (
	REM Check whether the supplied command has an extension.
	REM (one of those nifty features).
	if /I "%%~xf"=="" (
		REM It doesn't (have an extension).
		REM Now iterate over all valid executable extensions.
		for %%e in (%PATHEXT%) do (
			REM Check whether the supplied command exists in the path
			REM when we append each valid executable extension to it.
			for %%g in (%%~f%%e) do (
				if NOT "%%~$PATH:g"=="" (
					REM It does. Exit with success return (zero).
					exit /b 0
				)
			)
			REM Check whether the supplied command exists by its full
			REM path (%%~ff) when we append each valid executable extension
			REM to it.
			if EXIST "%%~ff%%e" (
				REM It does. Exit with success return (zero).
				exit /b 0
			)
			REM Check whether the supplied command exists relative to
			REM the current directory when we append each valid executable
			REM extension to it.
			if EXIST "%cd%\%%~f%%e" (
				REM It does. Exit with success return (zero).
				exit /b 0
			)
		)
	) else (
		REM It does (have an extension).
		REM Now check whether it exists in the path or
		REM whether it exists as a file.
		set exists=0
		if NOT "%%~$PATH:f"=="" (
			set exists=1
		)
		if EXIST "%%~ff" (
			set exists=1
		)
		if defined exists (
			REM It does. Now check whether its extension is a
			REM valid executable extension.
			for %%c in (%PATHEXT%) do (
				if /I "%%~xf"=="%%c" (
					REM It is. Exit with success return (zero).
					exit /b 0
				)
			)
		)
	)
)
REM Exit with failure return (non-zero).
exit /b 1