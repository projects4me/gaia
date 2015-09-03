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

namespace Foundation\Mvc\Model;

use Phalcon\Db\Column as Column;
use Phalcon\Db\Index as Index;
use Phalcon\Db\Reference as Reference;
use Foundation\fileHandler;
use Phalcon\Mvc\Model\Migration as PhalconMigration;

/**
 * This class is designed to allow table creation and modification based on the
 * meta-data defined in the appliction.  
 * This class acts as a wrapper for the Phalcon\Mvc\Model\Migration class by
 * providing additional function that allows us to read the metadata for all the
 * model defined in the application and sync the database tables accordingly
 * 
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation\Mvc\Model
 * @category Migration
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */

new PhalconMigration();

class Migration 
{
    /**
     * This function uses the metaManager to get the model meta for all the
     * models and prepares the array readable by PhalconPHP
     * 
     * @return array Phalcon understable meta array
     */
    private function getMeta()
    {
        
    }
    
    public function init()
    {
        $meta = fileHandler::readFolder('../app/metadata/model');
        print_r($meta);
        
    }
}