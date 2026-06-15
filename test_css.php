<?php
$url = 'http://localhost/kronoconnect/assets/styles/krono-auth.css?v=1.1';
$headers = get_headers($url);
echo "Headers for $url:\n";
print_r($headers);
