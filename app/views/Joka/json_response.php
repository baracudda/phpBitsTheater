<?php
header('Content-Type: application/json; charset=utf-8');
print(json_encode($v->result)); // putting JSON_UNESCAPED_UNICODE in 2nd param is 5.4+ only
