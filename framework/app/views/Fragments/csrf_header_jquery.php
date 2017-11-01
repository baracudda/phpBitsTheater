<?php
$dbAuth = $v->getProp('Auth');
list( $theCsrfCookieName, $theCsrfHeaderName) = $dbAuth->getCsrfCookieHeaderNames();
$v->returnProp($dbAuth);
if (!empty($theCsrfCookieName) && !empty($theCsrfHeaderName))
{
	$jsCode = <<<EOD
function getCookie(c) {
  if(document.cookie.length > 0) {
    var i = document.cookie.indexOf(c+"=");
    if(i!=-1) {
      i += c.length+1;
      var j = document.cookie.indexOf(";",i);
      if(j==-1) j = document.cookie.length;
      return unescape(document.cookie.substring(i,j));
    }
  }
  return "";
}
$(function(){ $.ajaxSetup({headers: {"{$theCsrfHeaderName}": getCookie("{$theCsrfCookieName}")}}); });

EOD;
	print($v->createJsTagBlock($jsCode));
}