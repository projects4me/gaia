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

namespace Foundation;

/**
 * This class is used for basic filing operations. Foundation applications rely
 * on metadata and configuragtions which are stored using files in the fs. This
 * class provides functions that helps to retireve the data stored
 * 
 */
class fileHandler
{
    /**
     * This function will read all the files in the gived $folder and will
     * return all them merged in an array.
     * 
     * @param string $folder
     * @return array
     */
    public function readFolder($folder){
        $data = array();
        if (is_dir($folder)){
            $files = array();
            $files = scandir($folder,0);
            if (is_array($files)){
                $allowed_extenstions = array('.php');
                foreach($files as $file)
                {
                    $file_ext = substr($file, (strlen($file)-4),strlen($file));
                    if (in_array($file_ext,$allowed_extenstions))
                    {
                        $returned_data = self::readFile($folder.'/'.$file);
                        if (is_array($returned_data))
                            $data = array_merge($data,$returned_data);
                    }
                }
            }
        }
        return $data;
    }
    
    /**
     * This function returns the array stored in the file at $path
     * 
     * @todo expection handling
     * @param string $path
     * @return array
     */
    public function readFile($path)
    {
        if (file_exists($path))
            return include $path;
    }
    
}