<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Scaffold;

/**
 * Plans the files a new behaviour touches: the class, its test and the model metadata's `behaviors` entry.
 * Writing is ResourceScaffold::write, which is all-or-nothing.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Scaffold
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class BehaviorScaffold
{
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
     * @param BehaviorSpec $spec
     * @return string[] repository relative path => new file text
     * @throws ScaffoldException
     */
    public function plan(BehaviorSpec $spec): array
    {
        $changes = (new BehaviorRenderer($this->root))->render($spec);
        foreach (array_keys($changes) as $path) {
            if (is_file($this->root . '/' . $path)) {
                throw new ScaffoldException("{$path} already exists; {$spec->name} looks scaffolded already");
            }
        }
        $changes[$spec->metadataPath()] = $this->register($spec);

        return $changes;
    }

    /**
     * @param BehaviorSpec $spec
     * @return string the model metadata with the behaviour added last in `behaviors`
     * @throws ScaffoldException
     */
    private function register(BehaviorSpec $spec): string
    {
        $file = $this->root . '/' . $spec->metadataPath();
        if (!is_file($file)) {
            throw new ScaffoldException("Model {$spec->model} has no metadata file: {$spec->metadataPath()}");
        }
        $text = str_replace("\r\n", "\n", file_get_contents($file));
        if (!preg_match('/(\'behaviors\' => array\(\n)(.*?)(\n    \),)/s', $text, $match, PREG_OFFSET_CAPTURE)) {
            throw new ScaffoldException("{$spec->metadataPath()} has no 'behaviors' array to register {$spec->name} in");
        }
        if (strpos($match[2][0], "'{$spec->name}'") !== false) {
            throw new ScaffoldException("{$spec->name} is already registered in {$spec->metadataPath()}");
        }
        $list = rtrim($match[2][0]);
        $list = ($list === '' ? '' : rtrim($list, ',') . ",\n") . "        '{$spec->name}'";

        return substr_replace($text, $list, $match[2][1], strlen($match[2][0]));
    }
}
