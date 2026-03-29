<?php

$output = [];
exec("/var/www/deploy.sh 2>&1", $output);

echo "<pre>";
print_r($output);