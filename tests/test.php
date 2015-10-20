<?php

require_once('../vendor/autoload.php');

$connections = 250;
$locks_per_connection = 100;

$dsn = 'tcp://127.0.0.1:49915';

function rand_string()
{
    $str = '';
    $num = 30;
    do {
        $str .= chr(rand(32, 255));
    } while (--$num > 0);
    return $str;
}

$services = [];

for ($i=0; $i<$connections; $i++) {
    $service = new \Advlock\Advlock($dsn);
    $services[] = $service;

    for ($n=0; $n<$locks_per_connection; $n++) {
        $key = rand_string();
        $result = $service->set($key);
        assert('$result === true', 'Must successfully set the lock');
        $result = $service->set($key);
        assert('$result === false', 'Must not set the duplicating lock');
        $result = $service->del($key);
        assert('$result === true', 'Must successfully release lock');
        $result = $service->set($key);
        assert('$result === true', 'Must successfully set the lock after it is released');

        echo ".";
    }

    echo "\n$i / $connections\n";
}

foreach ($services as $service) {
    $service->close();
}

// at this point all the previously set locks must be released
