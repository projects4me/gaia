<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * Proposes other forms of a query's words, the way records may write them: a developer abbreviation or its full word,
 * the British or American spelling, two adjacent words joined into one (task board, taskboard) and a joined word split
 * into a pair (taskboard, task board). It only proposes them: QueryExpansion keeps those that occur in a record the
 * user may read.
 *
 * Only words of letters that stand alone in the text are expanded (not config in config.php), and never stop words:
 * a pair is never split or joined around one, so logout does not become log out. The forms of one word come before joins, and joins before splits (the most even split first),
 * and at most maxAlternatives different forms are proposed, so the lookups that check them stay bounded.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class QueryExpander
{
    /**
     * Developer abbreviations and the full words they stand for; each is proposed both ways.
     *
     * @var array
     */
    public const ABBREVIATIONS = array(
        'admin' => array('administrator', 'administration'),
        'app' => array('application'),
        'apps' => array('applications'),
        'arg' => array('argument'),
        'args' => array('arguments'),
        'attr' => array('attribute'),
        'attrs' => array('attributes'),
        'auth' => array('authentication', 'authorization'),
        'btn' => array('button'),
        'cert' => array('certificate'),
        'certs' => array('certificates'),
        'config' => array('configuration'),
        'db' => array('database'),
        'desc' => array('description'),
        'dev' => array('development', 'developer'),
        'dir' => array('directory'),
        'dirs' => array('directories'),
        'doc' => array('document'),
        'docs' => array('documentation', 'documents'),
        'env' => array('environment'),
        'envs' => array('environments'),
        'impl' => array('implementation'),
        'info' => array('information'),
        'img' => array('image'),
        'imgs' => array('images'),
        'lib' => array('library'),
        'libs' => array('libraries'),
        'msg' => array('message'),
        'msgs' => array('messages'),
        'nav' => array('navigation'),
        'notif' => array('notification'),
        'notifs' => array('notifications'),
        'org' => array('organization'),
        'param' => array('parameter'),
        'params' => array('parameters'),
        'perm' => array('permission'),
        'perms' => array('permissions'),
        'pkg' => array('package'),
        'pref' => array('preference'),
        'prefs' => array('preferences'),
        'prod' => array('production'),
        'repo' => array('repository'),
        'repos' => array('repositories'),
        'req' => array('request'),
        'spec' => array('specification'),
        'stats' => array('statistics'),
        'sync' => array('synchronization'),
        'tmp' => array('temporary'),
        'util' => array('utility'),
        'utils' => array('utilities'),
        'var' => array('variable'),
        'vars' => array('variables'),
        'ver' => array('version'),
    );

    /**
     * British and American word endings, [british, american]; a word may also carry a plural s after one. The ending
     * alone also proposes non-words (editor: editour); QueryExpansion drops them, as no record has them.
     *
     * @var array
     */
    public const SPELLINGS = array(
        array('isation', 'ization'),
        array('our', 'or'),
        array('ise', 'ize'),
        array('yse', 'yze'),
        array('elled', 'eled'),
        array('tre', 'ter'),
        array('ogue', 'og'),
        array('ence', 'ense'),
    );

    /**
     * @var int
     */
    private $maxAlternatives;

    /**
     * @var int
     */
    private $spellingMinLength;

    /**
     * @var int
     */
    private $splitMinPart;

    /**
     * @param int $maxAlternatives Different forms proposed at most per query, a whole number from 1
     * @param int $spellingMinLength Letters a word needs before its spelling is changed, a whole number from 1
     * @param int $splitMinPart Letters each half of a split word needs, a whole number from 1
     * @throws \Gaia\Exception\Exception When one is out of bounds
     */
    public function __construct($maxAlternatives, $spellingMinLength, $splitMinPart)
    {
        $this->maxAlternatives = $this->wholeFromOne($maxAlternatives, 'maximum of query alternatives');
        $this->spellingMinLength = $this->wholeFromOne($spellingMinLength, 'letters a respelled word needs');
        $this->splitMinPart = $this->wholeFromOne($splitMinPart, 'letters each half of a split word needs');
    }

    /**
     * This function returns the proposed alternatives of the query's words, each
     * ['at' => the positions in SearchQuery::words() it replaces (one, or two adjacent for a join),
     *  'form' => its words (one, or two for a split)].
     * At most maxAlternatives different forms are proposed; a repeated word gets the same forms at each position,
     * which count once.
     *
     * @param SearchQuery $query
     * @return array
     */
    public function proposals(SearchQuery $query)
    {
        $words = $query->words();
        $forms = array();
        $joins = array();
        $splits = array();
        foreach ($words as $at => $word) {
            if (!$this->expandable($query, $at)) {
                continue;
            }
            foreach (array_merge($this->abbreviationsOf($word), $this->spellingsOf($word)) as $form) {
                $forms[] = array('at' => array($at), 'form' => array($form));
            }
            if ($this->expandable($query, $at + 1)) {
                $joins[] = array('at' => array($at, $at + 1), 'form' => array($word . $words[$at + 1]));
            }
            foreach ($this->splitsOf($word) as $pair) {
                $splits[] = array('at' => array($at), 'form' => $pair);
            }
        }

        $proposals = array();
        $distinct = array();
        foreach (array_merge($forms, $joins, $splits) as $proposal) {
            $form = implode(' ', $proposal['form']);
            if (!isset($distinct[$form])) {
                if (count($distinct) === $this->maxAlternatives) {
                    continue;
                }
                $distinct[$form] = true;
            }
            $proposals[json_encode($proposal)] = $proposal;
        }

        return array_values($proposals);
    }

    /**
     * Whether the word at a position may be expanded: it stands alone in the text, is made of letters and is not a
     * stop word.
     *
     * @param SearchQuery $query
     * @param int $at
     * @return bool
     */
    private function expandable(SearchQuery $query, $at)
    {
        $words = $query->words();

        return isset($words[$at]) && $query->standsAlone($at) && $this->isWord($words[$at]);
    }

    /**
     * @param string $word
     * @return bool
     */
    private function isWord($word)
    {
        return preg_match('/^\p{L}+$/u', $word) === 1 && !in_array($word, SearchQuery::STOP_WORDS, true);
    }

    /**
     * @param string $word
     * @return array
     */
    private function abbreviationsOf($word)
    {
        $forms = isset(self::ABBREVIATIONS[$word]) ? self::ABBREVIATIONS[$word] : array();
        foreach (self::ABBREVIATIONS as $short => $full) {
            if (in_array($word, $full, true)) {
                $forms[] = $short;
            }
        }

        return $forms;
    }

    /**
     * @param string $word
     * @return array
     */
    private function spellingsOf($word)
    {
        if (mb_strlen($word, 'UTF-8') < $this->spellingMinLength) {
            return array();
        }
        $plural = substr($word, -1) === 's' ? 's' : '';
        $forms = array();
        foreach (array('', $plural) as $tail) {
            $stem = substr($word, 0, strlen($word) - strlen($tail));
            foreach (self::SPELLINGS as $pair) {
                foreach (array(array($pair[0], $pair[1]), array($pair[1], $pair[0])) as $swap) {
                    if ($stem !== $swap[0] && substr($stem, -strlen($swap[0])) === $swap[0]) {
                        $forms[] = substr($stem, 0, -strlen($swap[0])) . $swap[1] . $tail;
                    }
                }
            }
        }

        return array_values(array_unique($forms));
    }

    /**
     * The splits of a joined word into two words, the most even first.
     *
     * @param string $word
     * @return array Pairs of words
     */
    private function splitsOf($word)
    {
        $length = mb_strlen($word, 'UTF-8');
        $splits = array();
        for ($at = $this->splitMinPart; $at <= $length - $this->splitMinPart; $at++) {
            $pair = array(mb_substr($word, 0, $at, 'UTF-8'), mb_substr($word, $at, null, 'UTF-8'));
            if ($this->isWord($pair[0]) && $this->isWord($pair[1])) {
                $splits[abs($length - 2 * $at) * $length + $at] = $pair;
            }
        }
        ksort($splits);

        return array_values($splits);
    }

    /**
     * @param mixed $value
     * @param string $name
     * @return int
     * @throws \Gaia\Exception\Exception When it is not a whole number from 1
     */
    private function wholeFromOne($value, $name)
    {
        if (!is_int($value) || $value < 1) {
            throw new \Gaia\Exception\Exception("The search {$name} must be a whole number from 1.");
        }

        return $value;
    }
}
