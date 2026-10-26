<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Scaffold;

/**
 * Renders the metadata, model and controller of a new resource from the golden reference files
 * (the Idea set named in docs/dev/bundles.md): the reference text is read from the repository and
 * the name substituted, so a change to the framework's shape shows up in the golden test.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Scaffold
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ResourceRenderer
{
    public const GOLDEN = 'Idea';
    public const GOLDEN_TABLE = 'ideas';

    /**
     * Fields every resource has after its own, taken from the reference in this order.
     */
    private const AUDIT_FIELDS = [
        'createdUser', 'createdUserName', 'modifiedUser', 'modifiedUserName', 'dateCreated', 'dateModified', 'deleted',
    ];

    /**
     * @var string
     */
    private $root;

    /**
     * @param string $root repository root
     */
    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/\\');
    }

    /**
     * @param ResourceSpec $spec
     * @return string[] repository relative path => file text
     * @throws ScaffoldException
     */
    public function render(ResourceSpec $spec): array
    {
        return [
            "app/metadata/model/{$spec->name}.php" => $this->metadata($spec),
            "app/models/{$spec->name}.php" => $this->rename($spec, $this->golden('app/models/' . self::GOLDEN . '.php')),
            "app/api/v1/controllers/{$spec->name}Controller.php" => $this->controller($spec),
        ];
    }

    /**
     * @param string $relative
     * @return string the reference file with LF line endings
     * @throws ScaffoldException
     */
    private function golden(string $relative): string
    {
        $path = $this->root . '/' . $relative;
        if (!is_file($path)) {
            throw new ScaffoldException("Golden reference {$relative} not found");
        }

        return str_replace("\r\n", "\n", file_get_contents($path));
    }

    /**
     * @param ResourceSpec $spec
     * @param string $text
     * @return string the text with the reference's names replaced by the resource's
     */
    private function rename(ResourceSpec $spec, string $text): string
    {
        return str_replace(
            [self::GOLDEN . 'Controller', self::GOLDEN, self::GOLDEN_TABLE, strtoupper(self::GOLDEN_TABLE)],
            [$spec->name . 'Controller', $spec->name, $spec->table, strtoupper($spec->table)],
            $text
        );
    }

    /**
     * @param ResourceSpec $spec
     * @return string
     * @throws ScaffoldException
     */
    private function controller(ResourceSpec $spec): string
    {
        $text = $this->golden('app/api/v1/controllers/' . self::GOLDEN . 'Controller.php');
        // Live events are Bundle E, added by hand when the resource needs them.
        $text = preg_replace('/\{\n    \/\*\*\n     \* Components that this controller uses\..*?\n    public \$uses = [^\n]*\n\}/s', "{\n}", $text, 1, $count);
        if ($count !== 1) {
            throw new ScaffoldException('The golden controller no longer has the $uses block the scaffolder strips');
        }

        return $this->rename($spec, $text);
    }

    /**
     * @param ResourceSpec $spec
     * @return string
     * @throws ScaffoldException
     */
    private function metadata(ResourceSpec $spec): string
    {
        $text = $this->golden('app/metadata/model/' . self::GOLDEN . '.php');
        if (!preg_match('/(        \'id\' => array\(.*?\n        \),\n)/s', $text, $id)) {
            throw new ScaffoldException('The golden metadata has no id field');
        }
        $fields = $id[1];
        if ($spec->parent !== null) {
            $fields .= $this->parentField($spec, $text);
        }
        foreach ($spec->fields as $field) {
            $fields .= $this->field($field);
        }
        foreach (self::AUDIT_FIELDS as $name) {
            $fields .= $this->fieldBlock($text, $name);
        }
        $text = $this->replaceOnce('/(    \'fields\' => array\(\n).*?(\n    \),\n    \'indexes\')/s', '${1}' . $this->escape(rtrim($fields, "\n"), true) . '${2}', $text);
        $text = $this->replaceOnce('/(\'indexes\' => array\(\n        \'id\' => \'primary\',\n)(        \'projectId\' => \'INDEX\',\n)?/', '$1' . ($spec->parent !== null ? "        '{$spec->parentKey()}' => 'INDEX',\n" : ''), $text);
        $text = $this->replaceOnce('/(\'hasMany\' => array\().*?(\n        \),\n    \),)/s', '$1$2', $text);
        $text = $this->relationships($spec, $text);
        if ($spec->behaviors !== null) {
            $list = "        '" . implode("',\n        '", $spec->behaviors) . "'";
            $text = $this->replaceOnce('/(\'behaviors\' => array\(\n).*?(\n    \),)/s', '$1' . $this->escape($list, true) . '$2', $text);
        }
        $text = $this->acl($spec, $text);

        return $this->rename($spec, $text);
    }

    /**
     * @param ResourceSpec $spec
     * @param string $text the reference metadata
     * @return string
     */
    private function parentField(ResourceSpec $spec, string $text): string
    {
        return str_replace(
            ['projectId', 'PROJECT'],
            [$spec->parentKey(), strtoupper($spec->parent)],
            $this->fieldBlock($text, 'projectId')
        );
    }

    /**
     * @param string $text
     * @param string $name
     * @return string the reference's definition of the field
     * @throws ScaffoldException
     */
    private function fieldBlock(string $text, string $name): string
    {
        if (!preg_match('/(        \'' . $name . '\' => array\(\n.*?\n        \),\n)/s', $text, $match)) {
            throw new ScaffoldException("The golden metadata has no {$name} field");
        }

        return $match[1];
    }

    /**
     * @param array $field
     * @return string the field in the reference's layout
     */
    private function field(array $field): string
    {
        $label = 'LBL_' . strtoupper(ResourceRenderer::GOLDEN_TABLE) . '_' . strtoupper(preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $field['name']));
        $lines = [
            "            'name' => '{$field['name']}',",
            "            'label' => '{$label}',",
            "            'type' => '{$field['type']}',",
        ];
        if ($field['length'] !== null) {
            $lines[] = "            'length' => '{$field['length']}',";
        }
        $lines[] = "            'null' => " . ($field['required'] ? 'false' : 'true') . ',';
        if ($field['default'] !== null) {
            $default = is_numeric($field['default']) ? $field['default'] : "'" . addslashes($field['default']) . "'";
            $lines[] = "            'default' => {$default},";
        }

        return "        '{$field['name']}' => array(\n" . implode("\n", $lines) . "\n        ),\n";
    }

    /**
     * @param ResourceSpec $spec
     * @param string $text
     * @return string the metadata with the parent relationship, or none when the resource has no parent
     */
    private function relationships(ResourceSpec $spec, string $text): string
    {
        $pattern = '/            \'project\' => array\(\n.*?\n            \),\n/s';
        if ($spec->parent === null) {
            return $this->replaceOnce($pattern, '', $text);
        }
        $block = str_replace(
            ["'project'", "'projectId'", '\\\\Project'],
            ["'" . strtolower($spec->parent) . "'", "'{$spec->parentKey()}'", '\\\\' . $spec->parent],
            $this->block($pattern, $text)
        );

        return $this->replaceOnce($pattern, $this->escape($block, true), $text);
    }

    /**
     * @param ResourceSpec $spec
     * @param string $text
     * @return string the metadata with its ACL group set to the parent, or none without a parent
     */
    private function acl(ResourceSpec $spec, string $text): string
    {
        $pattern = '/\n    \'acl\' => \[.*?\n    \],/s';
        if ($spec->parent === null) {
            return $this->replaceOnce($pattern, '', $text);
        }
        $block = "\n    'acl' => [\n        'groups' => ['{$spec->parent}'],\n        'groupKeys' => [\n            '{$spec->parent}' => '{$spec->parentKey()}',\n        ],\n    ],";

        return $this->replaceOnce($pattern, $this->escape($block, true), $text);
    }

    /**
     * @param string $pattern
     * @param string $text
     * @return string the first match
     * @throws ScaffoldException
     */
    private function block(string $pattern, string $text): string
    {
        if (!preg_match($pattern, $text, $match)) {
            throw new ScaffoldException('The golden metadata no longer has a block the scaffolder expects');
        }

        return $match[0];
    }

    /**
     * @param string $pattern
     * @param string $replacement
     * @param string $text
     * @return string
     * @throws ScaffoldException when the reference does not match, so a changed shape fails loudly
     */
    private function replaceOnce(string $pattern, string $replacement, string $text): string
    {
        $result = preg_replace($pattern, $replacement, $text, 1, $count);
        if ($result === null || $count !== 1) {
            throw new ScaffoldException('The golden metadata no longer matches the shape the scaffolder expects: ' . $pattern);
        }

        return $result;
    }

    /**
     * @param string $text
     * @param bool $literal
     * @return string the text safe to use as a preg replacement
     */
    private function escape(string $text, bool $literal): string
    {
        return $literal ? preg_replace('/([\\\\$])/', '\\\\$1', $text) : $text;
    }
}
