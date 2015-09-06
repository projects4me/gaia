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

namespace Foundation\Mvc;
use Phalcon\Mvc\Model as PhalconModel;
use Phalcon\Mvc\Model\Message;
use Phalcon\Mvc\Model\Validator\Uniqueness;
use Phalcon\Mvc\Model\Validator\InclusionIn;
use Foundation\metaManager;


/**
 * This class is the base model in the foundation framework and is used to
 * overwrite the default functionality of Phalcon\Mvc\Model in order to
 * introdcude manual meta-data extensions along with other changes
 * 
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation\Mvc
 * @category Model
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Model extends \Phalcon\Mvc\Model
{
    protected $metadata;
    

        public function initialize()
    {
        $this->metadata = metaManager::getModelMeta(get_class($this));
        $this->loadRelationships();
    }
    
    /**
     * This function is responsibles for loading all the relationships defined
     * in the model meta data
     */
    protected function loadRelationships()
    {
        // Load each of the relationship types one by one
        if (isset($this->metadata['relationships']['hasOne']))
        {
            foreach($this->metadata['relationships']['hasOne'] as $alias => $relationship)
            {
                $this->hasOne( 
                    $relationship['primaryKey'],
                    $relationship['relatedModel'],
                    $relationship['relatedKey'],
                    array(
                        'alias' => $alias
                    )
                );
            }
        }
        
        // Load each of the relationship types one by many
        if (isset($this->metadata['relationships']['hasMany']))
        {
            foreach($this->metadata['relationships']['hasMany'] as $alias => $relationship)
            {
                $this->hasMany( 
                    $relationship['primaryKey'],
                    $relationship['relatedModel'],
                    $relationship['relatedKey'],
                    array(
                        'alias' => $alias
                    )
                );
            }
        }
        
        // Load each of the relationship types many by one
        if (isset($this->metadata['relationships']['belongsTo']))
        {
            foreach($this->metadata['relationships']['belongsTo'] as $alias => $relationship)
            {
                $this->belongsTo( 
                    $relationship['primaryKey'],
                    $relationship['relatedModel'],
                    $relationship['relatedKey'],
                    array(
                        'alias' => $alias
                    )
                );
            }
        }
        
        // Load each of the relationship types many by many
        if (isset($this->metadata['relationships']['hasManyToMany']))
        {
            foreach($this->metadata['relationships']['hasManyToMany'] as $alias => $relationship)
            {
                $this->hasManyToMany( 
                    $relationship['primaryKey'],
                    $relationship['relatedModel'],
                    $relationship['rhsKey'],
                    $relationship['lhsKey'],
                    $relationship['secondaryModel'],
                    $relationship['secondaryKey'],
                    array(
                        'alias' => $alias
                    )
                );
            }
        }
    }

    /**
     * This function read the meta data stored for a model and returns an array
     * with parsed in a format that PhalconModel can understand
     * 
     * @return array
     */
    public function metaData()
    {
        $metadata = metaManager::getModelMeta(get_class($this));
        $this->setSource($metadata['tableName']);
        return $metadata;
    }
    
    /**
     * For some reason the tableName being set in the function metaData gets
     * overridden so we are seting the table again when the object is being
     * constructed
     */
    public function onConstruct()
    {
        $metadata = metaManager::getModelMeta(get_class($this));
        $this->setSource($metadata['tableName']);    
    }
}
