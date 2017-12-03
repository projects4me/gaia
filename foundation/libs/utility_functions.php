<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Foundation;

/**
 * This function returns time based GUIDs.
 * 
 * The source of this function is https://gist.github.com/drewjoh/365502
 * The function is without any warranty. This function does not garantees
 * non-conflict. The GUIDs generated are not random but sequential therefore 
 * should not be used if security is a concern.
 * 
 * @assert (1, 1) == 1
 * @return string guid
 */
function create_guid(){
    // Time based PHP Unique ID
    $uid = uniqid(NULL, TRUE);
    // Random SHA1 hash
    $rawid = strtoupper(sha1(uniqid(rand(), true)));
    // Produce the results
    $result = substr($uid, 6, 8);
    $result .= substr($uid, 0, 4);
    $result .= '-'.substr(sha1(substr($uid, 3, 3)), 0, 4);
    $result .= '-'.substr(sha1(substr(time(), 3, 4)), 0, 4);
    $result .= '-'.strtolower(substr($rawid, 10, 12));
    // Return the result
    return $result;
}

