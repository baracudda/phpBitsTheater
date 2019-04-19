<?php
use BitsTheater\Scene; /* @var $v Scene */
print("</div>\n");  //using php here to avoid the "no start tag" warning
?><div id="container-footer">
	<p><?php
		$thePatronList = $v->getRes('website/list_patrons_html');
		print(implode($v->getRes('website/list_patrons_glue'),$thePatronList));
	?></p>
	<p class="smalltext"><?php
		$theCreditList = $v->getRes('website/list_credits_html');
		print(implode($v->getRes('website/list_credits_glue'),$theCreditList));
	?></p>
</div>
</body>
</html>
