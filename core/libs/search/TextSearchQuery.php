<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

use Gaia\Db\Migration\PostgresSearchDdl;

/**
 * A keyword query as a Postgres tsquery, in the text search config the type's searchVector is built with, so the query
 * is parsed the way the records were. The words are read with plainto_tsquery (every word, no operators, so quotes and
 * a leading dash are plain text); a word with alternatives matches it or any of them (OR), a split pair as a phrase
 * (phraseto_tsquery), and a joined pair matches both words or the joined word. The words of a path or file name are
 * read apart, the way the searchVector indexes them, so a pasted path (OAuth/callback) finds its record. Every value is
 * a bound parameter.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TextSearchQuery
{
    /**
     * This function returns the tsquery of a keyword query, in parentheses, with its parameters.
     *
     * @param SearchTarget $target
     * @param KeywordQuery $keywords
     * @return array ['sql' => string, 'bind' => array]
     */
    public function expression(SearchTarget $target, KeywordQuery $keywords)
    {
        $bind = $this->configBind($target);
        if ($keywords->groups() === array()) {
            return array('sql' => '(' . $this->words(':search_fts') . ')', 'bind' => $bind + array(
                'search_fts' => $this->splitWords($keywords->text()),
            ));
        }

        $parts = array();
        $next = 1;
        if ($keywords->text() !== '') {
            $parts[] = $this->words(':search_fts');
            $bind['search_fts'] = $this->splitWords($keywords->text());
        }
        foreach ($keywords->groups() as $group) {
            $sides = array();
            foreach ($group['words'] as $i => $word) {
                $sides[] = $this->alternatives($word, $group['forms'][$i], $bind, $next);
            }
            $sql = implode(' && ', $sides);
            if ($group['joined'] !== null) {
                $sql = '((' . $sql . ') || ' . $this->words($this->parameter($group['joined'], $bind, $next)) . ')';
            }
            $parts[] = $sql;
        }

        return array('sql' => '(' . implode(' && ', $parts) . ')', 'bind' => $bind);
    }

    /**
     * This function returns the tsquery of a phrase: its words next to each other, in order, with its parameters.
     *
     * @param SearchTarget $target
     * @param string $phrase
     * @return array ['sql' => string, 'bind' => array]
     */
    public function phrase(SearchTarget $target, $phrase)
    {
        return array(
            'sql' => '(phraseto_tsquery(' . $this->config() . ', :search_phrase))',
            'bind' => $this->configBind($target) + array('search_phrase' => $phrase),
        );
    }

    /**
     * This function returns the SQL of the text search config, over the parameters of expression() and phrase().
     *
     * @return string
     */
    public function config()
    {
        return 'CAST(:search_config AS regconfig)';
    }

    /**
     * This function returns the parameter of the type's text search config.
     *
     * @param SearchTarget $target
     * @return array
     */
    private function configBind(SearchTarget $target)
    {
        $vector = $target->field(SearchBlock::VECTOR);
        $config = isset($vector['generated']['config'])
            ? $vector['generated']['config']
            : PostgresSearchDdl::DEFAULT_TEXT_SEARCH_CONFIG;

        return array('search_config' => $config);
    }

    /**
     * A word or any of its forms, a form of two words as a phrase.
     *
     * @param string $word
     * @param array $forms
     * @param array $bind Gets the parameters
     * @param int $next The number of the next parameter
     * @return string
     */
    private function alternatives($word, array $forms, array &$bind, &$next)
    {
        $terms = array($this->words($this->parameter($word, $bind, $next)));
        foreach ($forms as $form) {
            $parameter = $this->parameter(implode(' ', $form), $bind, $next);
            $terms[] = count($form) === 1
                ? $this->words($parameter)
                : 'phraseto_tsquery(' . $this->config() . ', ' . $parameter . ')';
        }

        return count($terms) === 1 ? $terms[0] : '(' . implode(' || ', $terms) . ')';
    }

    /**
     * @param string $parameter
     * @return string
     */
    private function words($parameter)
    {
        return 'plainto_tsquery(' . $this->config() . ', ' . $parameter . ')';
    }

    /**
     * This function reads the separators of paths and file names in a text as spaces.
     *
     * @param string $text
     * @return string
     */
    private function splitWords($text)
    {
        return str_replace(PostgresSearchDdl::WORD_SEPARATORS, ' ', $text);
    }

    /**
     * This function binds a value to the next numbered parameter (search_fts1, search_fts2, ...) and returns its
     * placeholder.
     *
     * @param string $value
     * @param array $bind
     * @param int $next The number of the next parameter, counted up
     * @return string
     */
    private function parameter($value, array &$bind, &$next)
    {
        $name = 'search_fts' . $next++;
        $bind[$name] = $this->splitWords($value);

        return ':' . $name;
    }
}
