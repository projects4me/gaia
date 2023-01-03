<?php
/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Utils;

/**
 * This class is used to contain the application wide Utility functions
 *
 * @class Util
 */
class Util {

	/**
	 * Convert an object into an array
     *
	 * @param  object $d Object to be converted
	 * @return array The converted array
	 */		
    public function objectToArray($d) {
        if (is_object($d))
            $d = get_object_vars($d);

        return is_array($d) ? array_map(__METHOD__, $d) : $d;
    }

    /**
     * Convert an array to an abject
     *
     * @param  array $d Array to be converted
     * @return object The converted object
     */
    public function arrayToObject($d) {
        return is_array($d) ? (object) array_map(__METHOD__, $d) : $d;
    }

    /**
     * This function is used to extract valued form an array or object
     *
     * @param mixed $collection
     * @param mixed $index
     * @return array
    /**

     */
    public function getDataFromArray(mixed $collection, mixed $index) :array
    {
    	$data = array();
    	foreach ($collection as $key => $value) {
    		if ( is_object($value)  )
    			$data[] = $value->$index;
    		else if ( is_array($value) )
    			$data[] = $value[$index];
    		else
    			return array();
    	}
    	return $data;
    }

    /**
     * Verify if exit subarray
     * @param  array $arr -> array to verify if exits subarray  
     * @return boolean     true if exist and false if not 
     */
    public function existSubArray($arr){        
        foreach ($arr as $value) 
            if (is_array($value))
              return true;        
        return false;
    }

    /**
     * This function extracts the namespace from a class name and returns it
     *
     * @param string $className
     * @return string $className without namespace
     * @todo change the model and REST controller to use this
     */
    public static function classWithoutNamespace(string $className) :string
    {
        $parts = explode('\\', $className);
        return end($parts);
    }

    public static function convertToCamelCase($string) 
    {
        return ucwords($string);
    }
}
?>
