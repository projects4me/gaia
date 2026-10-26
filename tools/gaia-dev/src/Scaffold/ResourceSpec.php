<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Scaffold;

/**
 * What `gaia-dev scaffold resource` was asked for: the model name, its fields, the parent it is scoped
 * through, the behaviours and the profiles to give ACL cases for.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Scaffold
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ResourceSpec
{
    private const TYPES = ['varchar', 'text', 'int', 'bool', 'datetime', 'date', 'float', 'decimal'];
    private const VERDICTS = ['allow' => 1, 'members' => 2, 'deny' => 0];

    /**
     * @var string model name, e.g. Report
     */
    public $name;

    /**
     * @var string lower case resource name, e.g. report
     */
    public $resource;

    /**
     * @var string
     */
    public $table;

    /**
     * @var array[] field definitions: name, type, length, required, default
     */
    public $fields = [];

    /**
     * @var string|null parent model, e.g. Project
     */
    public $parent;

    /**
     * @var string[]|null behaviours replacing the reference's list, null to keep it
     */
    public $behaviors;

    /**
     * @var int[] profile name => permission value for <resource>.get
     */
    public $acl = [];

    /**
     * @param string $name
     * @param array $options parsed command line options: fields, parent, behaviors, acl, table
     * @throws ScaffoldException
     */
    public function __construct(string $name, array $options)
    {
        if (!preg_match('/^[A-Z][a-z0-9]+$/', $name)) {
            throw new ScaffoldException("Resource name '{$name}' must be one capitalised word of letters and digits, e.g. Report (the models list sorts on it)");
        }
        $this->name = $name;
        $this->resource = strtolower($name);
        $this->table = isset($options['table']) ? (string) $options['table'] : $this->resource . 's';
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $this->table)) {
            throw new ScaffoldException("Table name '{$this->table}' must be lower case letters, digits and underscores");
        }
        $this->fields = isset($options['fields']) ? $this->parseFields((string) $options['fields']) : [];
        if (isset($options['parent'])) {
            if (!preg_match('/^[A-Z][a-z0-9]+$/', (string) $options['parent'])) {
                throw new ScaffoldException("--parent={$options['parent']} must be a model name such as Project");
            }
            $this->parent = (string) $options['parent'];
        }
        if (isset($options['behaviors'])) {
            $this->behaviors = array_values(array_filter(explode(',', (string) $options['behaviors'])));
            foreach ($this->behaviors as $behavior) {
                if (!preg_match('/^[a-z][A-Za-z0-9]*Behavior$/', $behavior)) {
                    throw new ScaffoldException("Behaviour '{$behavior}' must look like softDeleteBehavior");
                }
            }
        }
        if (isset($options['acl'])) {
            $this->acl = $this->parseAcl((string) $options['acl']);
        }
    }

    /**
     * @return string the parent's foreign key, e.g. projectId
     */
    public function parentKey(): string
    {
        return strtolower($this->parent) . 'Id';
    }

    /**
     * @return string the fixture placeholder for a record of this resource
     */
    public function fixtureKey(): string
    {
        return $this->resource . 'Id';
    }

    /**
     * @param string $list name:type[:length][:required][:default=value],...
     * @return array[]
     * @throws ScaffoldException
     */
    private function parseFields(string $list): array
    {
        $fields = [];
        foreach (array_filter(explode(',', $list)) as $item) {
            $parts = explode(':', $item);
            $field = ['name' => $parts[0], 'type' => $parts[1] ?? '', 'length' => null, 'required' => false, 'default' => null];
            if (!preg_match('/^[a-z][A-Za-z0-9]*$/', $field['name']) || !in_array($field['type'], self::TYPES, true)) {
                throw new ScaffoldException("Field '{$item}' must look like name:type[:length][:required][:default=x] with a type of " . implode(', ', self::TYPES));
            }
            if (in_array($field['name'], ['id', 'deleted', 'createdUser', 'modifiedUser', 'dateCreated', 'dateModified'], true)) {
                throw new ScaffoldException("Field '{$field['name']}' is generated; leave it out of --fields");
            }
            foreach (array_slice($parts, 2) as $part) {
                if (ctype_digit($part)) {
                    $field['length'] = $part;
                } elseif ($part === 'required') {
                    $field['required'] = true;
                } elseif (strpos($part, 'default=') === 0) {
                    $field['default'] = substr($part, 8);
                } else {
                    throw new ScaffoldException("Unknown field option '{$part}' in '{$item}'");
                }
            }
            if ($field['type'] === 'varchar' && $field['length'] === null) {
                throw new ScaffoldException("Field '{$item}' needs a length, e.g. {$field['name']}:varchar:100");
            }
            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * @param string $list profile:verdict,... with verdict allow, members or deny
     * @return int[]
     * @throws ScaffoldException
     */
    private function parseAcl(string $list): array
    {
        $acl = [];
        foreach (array_filter(explode(',', $list)) as $item) {
            $parts = explode(':', $item);
            if (count($parts) !== 2 || !isset(self::VERDICTS[$parts[1]])) {
                throw new ScaffoldException("--acl entry '{$item}' must look like aclMembers:members (allow, members or deny)");
            }
            $acl[$parts[0]] = self::VERDICTS[$parts[1]];
        }

        return $acl;
    }
}
