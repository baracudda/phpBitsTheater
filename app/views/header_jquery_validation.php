<?php
//microsoft jquery validator
print '<script type="text/javascript" src="http://code.jquery.com/jquery-1.4.2.min.js"></script>'."\n";
print '<script type="text/javascript" src="http://ajax.microsoft.com/ajax/jquery.validate/1.7/jquery.validate.min.js"></script>'."\n";
print $recite->_actor->formValidation($recite->form_name);
