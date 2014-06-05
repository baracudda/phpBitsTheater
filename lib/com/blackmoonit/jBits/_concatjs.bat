@echo off
goto :TopOfCode
==========================

/*=========================== MINIFY INFO!!! ===============================*
 * Minification from http://www.jsmini.com/ using Basic, no jquery included.
 * The resulting mini code goes into the 'jbits_mini.js' file.
 *==========================================================================*/

From: http://stackoverflow.com/a/2665184
You could create a batch file with the following contents:
@echo off
pushd "%~1"
for /r %%x in (*.js) do (
    type "%%~x"
)
popd

and then run it via: jslint.bat PATH > tmp.js

If you don't want to use redirection, you can try:
@echo off
pushd "%~1"
echo.>tmp.js
for /r %%x in (*.js) do (
    copy tmp.js + "%%~x" tmp.js > NUL
)
popd

note that for simplicity, I haven't bothered doing any error-checking 
e.g. checking whether an argument is supplied (although if one isn't, 
it'll just use the current directory), testing that tmp.js doesn't 
already exist, etc.
=======================
:TopOfCode
del _concat_js.txt
::copy /A *.js _concat_js.txt /B /Y
:: ^ the above wont work because we need a specific order of files to make the mini-js version work correctly.

copy /A Utils.js _concat_js.txt /B /Y
copy /A _concat_js.txt+BasicObj.js _concat_js.txt /B /Y
copy /A _concat_js.txt+AjaxDataUpdater.js _concat_js.txt /B /Y

::copy /A _concat_js.txt+blah1.js _concat_js.txt /B /Y
::copy /A _concat_js.txt+blah2.js _concat_js.txt /B /Y
::copy /A _concat_js.txt+blah3.js _concat_js.txt /B /Y
