#!/usr/bin/env php
<?php

function pf($string, $end)
{
    return (substr($string, -strlen($end)) === $end ? substr($string, 0, -strlen($end)) : false);
}

$servers = array();

foreach ($_SERVER as $key => $value) {
    $name = pf($key, '_PORT_80_TCP_ADDR');
    if ($name === false) {
        continue;
    }
    
    $url = 'http://' . str_replace('tcp://', '', $value) . '/';
    
    $name = strtolower($name);
    $servers[$name] = $url;
    
    echo '/' . $name . ' => ' . $url . PHP_EOL;
}

if (!$servers) {
    echo 'No servers found' . PHP_EOL;
    exit(1);
}

$config = '# automatically generated by /apply-from-env.php
server {
    listen 80;
';

foreach ($servers as $name => $url) {
    $config .= '

    # proxy for ' . $name . '
    location /' . $name . '/ {
        proxy_pass ' . $url . ';
        
        # rewrite redirect / location headers to match this subdir
        proxy_redirect default;
        proxy_redirect / $scheme://$http_host/' . $name . '/;
        
        proxy_set_header Host $http_host;
        proxy_set_header X-Forwarded-For $remote_addr;
    }

    # requests without trailing slash will be forwarded to include slash
    location = /' . $name . ' {
        return 301 $scheme://$http_host$request_uri/;
    }';
}

$config .= '
}
';

file_put_contents('/etc/nginx/sites-enabled/default', $config);

