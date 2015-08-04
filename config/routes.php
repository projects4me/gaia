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

use Foundation\Mvc\Router;

    $router = new Router();

    //Remove trailing slashes automatically
    $router->removeExtraSlashes(true);

    global $settings;
    
    foreach($settings->routes as $approute)
    {
        $method = '';
        if (isset($approute->method))
        {
            $method = $approute->method;
            unset($approute->method);
        }
        
        $path = '';
        if (isset($approute->path))
        {
            $path = $approute->path;
            unset($approute->path);
        }
         
        $type = $approute->type;
        unset($approute->type);
        
        if ($type == 'rest')
        {
            switch ($method)
            {
                case 'GET':
                $router->addGet($path,(array) $approute);
                break;
            
                case 'POST':
                $router->addPost($path,(array) $approute);
                break;
            
                case 'PUT':
                $router->addPut($path,(array) $approute);
                break;
            
                case 'PATCH':
                $router->addPatch($path,(array) $approute);
                break;
            
                case 'DELETE':
                $router->addDelete($path,(array) $approute);
                break;
            }
        }
        else if ($type == 'system')
        {
            switch ($method)
            {
                case 'notfound':
                $router->notFound((array) $approute);
                break;
            
                case 'default':
                $router->setDefaults((array) $approute);
                break;
            }
        }
        else if($type == 'app')
        {
            $router->add($path,(array) $approute);
        }
    }

return $router;