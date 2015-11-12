<?php

/* 
 * Projects4Me Community Edition is an open source project management software 
 * developed by PROJECTS4ME Inc. Copyright (C) 2015-2016 PROJECTS4ME Inc.
 * 
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 (GNU AGPL v3) as
 * published be the Free Software Foundation with the addition of the following 
 * permission added to Section 15 as permitted in Section 7(a): FOR ANY PART OF 
 * THE COVERED WORK IN WHICH THE COPYRIGHT IS OWNED BY PROJECTS4ME Inc., 
 * Projects4Me DISCLAIMS THE WARRANTY OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT 
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU AGPL v3 for more details.
 * 
 * You should have received a copy of the GNU AGPL v3 along with this program; 
 * if not, see http://www.gnu.org/licenses or write to the Free Software 
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 * 
 * You can contact PROJECTS4ME, Inc. at email address contact@projects4.me.
 * 
 * The interactive user interfaces in modified source and object code versions 
 * of this program must display Appropriate Legal Notices, as required under 
 * Section 5 of the GNU AGPL v3.
 * 
 * In accordance with Section 7(b) of the GNU AGPL v3, these Appropriate Legal 
 * Notices must retain the display of the "Powered by Projects4Me" logo. If the 
 * display of the logo is not reasonably feasible for technical reasons, the 
 * Appropriate Legal Notices must display the words "Powered by Projects4Me".
 */

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

