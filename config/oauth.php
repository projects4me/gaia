<?php

$config['oauth'] = array(
    "sessionTimeout" => "1", // in day
    "sessionTimeoutForRememberMe" => "14", // in day
    "failedLoginAttemptsLimit" => "5",
    "failedLoginLockTime" => "24", // in hours
);

return $config;
