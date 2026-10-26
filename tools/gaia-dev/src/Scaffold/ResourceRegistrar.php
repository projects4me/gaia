<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Scaffold;

/**
 * Registers a new resource in the files that list every resource: the models list, the routes and the
 * placeholder maps of the backend catalog generator. Each method takes a file's text and returns it changed.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Scaffold
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ResourceRegistrar
{
    public const MODELS = 'core/config/models.php';
    public const ROUTES = 'core/config/routes.php';
    public const GENERATOR = 'tools/api-tester/bin/generate-backend-apis.php';

    /**
     * @param ResourceSpec $spec
     * @param string $text core/config/models.php
     * @return string
     * @throws ScaffoldException
     */
    public function models(ResourceSpec $spec, string $text): string
    {
        $text = $this->lf($text);
        if (!preg_match('/\$config\[\'models\'\] = \[\n(.*?)\n\];/s', $text, $match, PREG_OFFSET_CAPTURE)) {
            throw new ScaffoldException(self::MODELS . ' has no models list');
        }
        $lines = explode("\n", $match[1][0]);
        foreach ($lines as $line) {
            if (trim($line, " ,'") === $spec->name) {
                throw new ScaffoldException("{$spec->name} is already in " . self::MODELS);
            }
        }
        $position = count($lines);
        foreach ($lines as $index => $line) {
            if (strcasecmp(trim($line, " ,'"), $spec->name) > 0) {
                $position = $index;
                break;
            }
        }
        if ($position === count($lines)) {
            $lines[$position - 1] = rtrim($lines[$position - 1], ',') . ',';
            $lines[] = "    '{$spec->name}'";
        } else {
            array_splice($lines, $position, 0, ["    '{$spec->name}',"]);
        }

        return substr($text, 0, $match[1][1]) . implode("\n", $lines) . substr($text, $match[1][1] + strlen($match[1][0]));
    }

    /**
     * The route is copied from the reference resource's entry and placed after the reference's family.
     *
     * @param ResourceSpec $spec
     * @param string $text core/config/routes.php
     * @return string
     * @throws ScaffoldException
     */
    public function routes(ResourceSpec $spec, string $text): string
    {
        $text = $this->lf($text);
        $golden = strtolower(ResourceRenderer::GOLDEN);
        if (strpos($text, "            '{$spec->resource}' => array(") !== false) {
            throw new ScaffoldException("Route {$spec->resource} already exists in " . self::ROUTES);
        }
        preg_match_all('/            \'' . $golden . '[a-z]*\' => array\(\n.*?\n            \),\n/s', $text, $blocks, PREG_OFFSET_CAPTURE);
        if (empty($blocks[0])) {
            throw new ScaffoldException('The golden route ' . $golden . ' is missing from ' . self::ROUTES);
        }
        $reference = null;
        foreach ($blocks[0] as $block) {
            if (strpos($block[0], "'{$golden}' => array(") !== false) {
                $reference = $block[0];
            }
        }
        $last = end($blocks[0]);
        if ($reference === null) {
            throw new ScaffoldException('The golden route ' . $golden . ' is missing from ' . self::ROUTES);
        }
        $entry = str_replace(["'{$golden}'", "/{$golden}'"], ["'{$spec->resource}'", "/{$spec->resource}'"], $reference);
        $end = $last[1] + strlen($last[0]);

        return substr($text, 0, $end) . $entry . substr($text, $end);
    }

    /**
     * Adds the id placeholder, the foreign key placeholder and the "don't patch" flag the generator needs,
     * or it sends literal ids (#165, #188, #193).
     *
     * @param ResourceSpec $spec
     * @param string $text tools/api-tester/bin/generate-backend-apis.php
     * @return string
     * @throws ScaffoldException
     */
    public function generator(ResourceSpec $spec, string $text): string
    {
        $text = $this->lf($text);
        $key = $spec->fixtureKey();
        if (strpos($text, "'{$spec->resource}' => '{{$key}}',") !== false) {
            throw new ScaffoldException("{$spec->resource} already has a placeholder in " . self::GENERATOR);
        }
        $text = $this->sortedEntry($text, '/\$fixtureIds = \[\n(.*?)\n\];/s', $spec->resource, "    '{$spec->resource}' => '{{$key}}',");
        $text = $this->appendEntry($text, '/static \$fk = \[\n(.*?)\n    \];/s', "        '{$key}' => '{{$key}}',");

        return $this->appendEntry($text, '/static \$avoid = \[\n(.*?)\n    \];/s', "        '{$key}' => true,");
    }

    /**
     * @param string $text
     * @param string $pattern matches the array body in group 1
     * @param string $key the new entry's key
     * @param string $line
     * @return string the text with the line before the first greater key
     * @throws ScaffoldException
     */
    private function sortedEntry(string $text, string $pattern, string $key, string $line): string
    {
        if (!preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE)) {
            throw new ScaffoldException('The backend generator no longer has the array ' . $pattern);
        }
        $lines = explode("\n", $match[1][0]);
        $position = count($lines);
        foreach ($lines as $index => $existing) {
            if (preg_match('/^\s*\'([^\']+)\' =>/', $existing, $found) && strcmp($found[1], $key) > 0) {
                $position = $index;
                break;
            }
        }
        array_splice($lines, $position, 0, [$line]);

        return substr($text, 0, $match[1][1]) . implode("\n", $lines) . substr($text, $match[1][1] + strlen($match[1][0]));
    }

    /**
     * @param string $text
     * @param string $pattern matches the array body in group 1
     * @param string $line
     * @return string the text with the line added at the end of the array
     * @throws ScaffoldException
     */
    private function appendEntry(string $text, string $pattern, string $line): string
    {
        if (!preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE)) {
            throw new ScaffoldException('The backend generator no longer has the array ' . $pattern);
        }
        $end = $match[1][1] + strlen($match[1][0]);

        return substr($text, 0, $end) . "\n" . $line . substr($text, $end);
    }

    /**
     * @param string $text
     * @return string
     */
    private function lf(string $text): string
    {
        return str_replace("\r\n", "\n", $text);
    }
}
