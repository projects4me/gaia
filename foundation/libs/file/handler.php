<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\File;

/**
 * This class is used for basic filing operations. Foundation applications rely
 * on metadata and configuragtions which are stored using files in the fs. This
 * class provides functions that helps to retireve the data stored
 * 
 */
class Handler
{
    /**
     * This function will read all the files in the given $folder and will
     * return all them merged in an array.
     * 
     * @param string $folder
     * @return array
     */
    public static function readFolder($folder){
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
    public static function readFile($path)
    {
        if (file_exists($path))
            return include $path;
    }
    
}
