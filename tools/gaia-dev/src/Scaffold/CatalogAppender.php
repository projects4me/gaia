<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Scaffold;

/**
 * Appends cases to the text of an api-tester catalog without re-serialising it, so the
 * diff holds only the new cases. `acl.json` keeps `query`, `expects` and `store` on one
 * line; every other catalog is plain two-space JSON.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Scaffold
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CatalogAppender
{
    /**
     * Case keys `acl.json` writes on one line.
     */
    private const INLINE_KEYS = ['query', 'expects', 'store'];

    private const TAIL = "\n    }\n  ]\n}\n";

    /**
     * @param string $catalog catalog name, e.g. client or acl
     * @param string $text the catalog file as it is
     * @param array[] $cases the cases to append
     * @return string the new file text
     * @throws ScaffoldException on a duplicate id or an unrecognised layout
     */
    public function append(string $catalog, string $text, array $cases): string
    {
        $text = str_replace("\r\n", "\n", $text);
        if ($catalog === 'backend') {
            throw new ScaffoldException('backend.json is generated (tools/api-tester/bin/generate-backend-apis.php); add cases to client or acl');
        }
        if ($cases === []) {
            throw new ScaffoldException('No cases to append');
        }
        preg_match_all('/"id": "([^"]+)"/', $text, $found);
        $dupes = array_values(array_intersect(array_column($cases, 'id'), $found[1]));
        if ($dupes) {
            throw new ScaffoldException("case ids already exist in {$catalog}.json: " . implode(', ', $dupes));
        }
        if (substr($text, -strlen(self::TAIL)) !== self::TAIL) {
            throw new ScaffoldException("{$catalog}.json does not end with the expected '    }\n  ]\n}' layout");
        }

        $render = $catalog === 'acl' ? 'renderInline' : 'renderIndented';
        $blocks = array_map([$this, $render], $cases);
        $result = substr($text, 0, -strlen(self::TAIL)) . "\n    },\n" . implode(",\n", $blocks) . "\n  ]\n}\n";
        if (json_decode($result, true) === null) {
            throw new ScaffoldException("The appended {$catalog}.json would not be valid JSON");
        }

        return $result;
    }

    /**
     * @param array $case
     * @return string
     */
    private function renderInline(array $case): string
    {
        $lines = ['    {'];
        $count = count($case);
        $i = 0;
        foreach ($case as $key => $value) {
            $comma = ++$i < $count ? ',' : '';
            if (in_array($key, self::INLINE_KEYS, true) && is_array($value) && $value !== [] && !$this->isList($value)) {
                $inner = [];
                foreach ($value as $k => $v) {
                    $inner[] = $this->encode($k) . ': ' . $this->compact($v);
                }
                $lines[] = '      ' . $this->encode($key) . ': { ' . implode(', ', $inner) . ' }' . $comma;
            } else {
                $lines[] = '      ' . $this->encode($key) . ': ' . $this->pretty($value, 3) . $comma;
            }
        }
        $lines[] = '    }';

        return implode("\n", $lines);
    }

    /**
     * @param array $case
     * @return string
     */
    private function renderIndented(array $case): string
    {
        return '    ' . $this->pretty($case, 2);
    }

    /**
     * Two-space JSON, the way Python's json.dumps(indent=2) writes it (an empty array is written as []).
     *
     * @param mixed $value
     * @param int $level indentation level of the line the value starts on
     * @return string
     */
    private function pretty($value, int $level): string
    {
        if (!is_array($value)) {
            return $this->encode($value);
        }
        if ($value === []) {
            return '[]';
        }
        $pad = str_repeat('  ', $level + 1);
        $items = [];
        if ($this->isList($value)) {
            foreach ($value as $v) {
                $items[] = $pad . $this->pretty($v, $level + 1);
            }

            return "[\n" . implode(",\n", $items) . "\n" . str_repeat('  ', $level) . ']';
        }
        foreach ($value as $k => $v) {
            $items[] = $pad . $this->encode((string) $k) . ': ' . $this->pretty($v, $level + 1);
        }

        return "{\n" . implode(",\n", $items) . "\n" . str_repeat('  ', $level) . '}';
    }

    /**
     * @param array $value
     * @return bool
     */
    private function isList(array $value): bool
    {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }

    /**
     * One-line JSON with the separators Python's json.dumps uses (`[200, 204]`, `{"a": 1}`).
     *
     * @param mixed $value
     * @return string
     */
    private function compact($value): string
    {
        if (!is_array($value)) {
            return $this->encode($value);
        }
        $items = [];
        foreach ($value as $k => $v) {
            $items[] = ($this->isList($value) ? '' : $this->encode((string) $k) . ': ') . $this->compact($v);
        }

        return $this->isList($value) ? '[' . implode(', ', $items) . ']' : '{' . implode(', ', $items) . '}';
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function encode($value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
