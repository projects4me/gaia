<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Search;

use Gaia\Libraries\Search\CandidateQuery;
use Gaia\Libraries\Search\DisplayLoader;
use Gaia\Libraries\Search\ExactMatcher;
use Gaia\Libraries\Search\ExactTitleMatcher;
use Gaia\Libraries\Search\FtsSearcher;
use Gaia\Libraries\Search\FuzzySearcher;
use Gaia\Libraries\Search\PartialNumberMatcher;
use Gaia\Libraries\Search\QueryExpander;
use Gaia\Libraries\Search\QueryExpansion;
use Gaia\Libraries\Search\ReferenceMatcher;
use Gaia\Libraries\Search\SearchableRegistry;
use Gaia\Libraries\Search\SearchQuery;
use Gaia\Libraries\Search\SearchRequest;
use Gaia\Libraries\Search\SearchRoute;
use Gaia\Libraries\Search\SearchScope;
use Gaia\Libraries\Search\SearchService;
use Gaia\Libraries\Search\SemanticSearcher;
use Gaia\Libraries\Search\Snippet;
use Gaia\Libraries\Search\StrongestSignal;
use Gaia\Libraries\Search\TextSearchQuery;
use Gaia\Libraries\Search\UnknownWords;
use Gaia\Libraries\Security\Acl;
use Gaia\Exception\EmbeddingsUnavailableException;
use Gaia\Services\Embeddings;
use PHPUnit\Framework\TestCase;

/**
 * SearchService over Issue (agaia-4666, agaia-4668, agaia-4888, agaia-4890): exact, FTS, fuzzy and semantic matching
 * ranked by their strongest signal, number, partial number and reference lookups,
 * inside the user's project scope and field ACL. Semantic matching runs on a fake Embeddings and embeddings the test
 * writes. Each test adds its issues in a transaction it rolls back.
 *
 * @author   Agent Odysseus <agent.odysseus@projects4.me>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class SearchServiceTest extends TestCase
{
    /**
     * The test issues' projects; no project row is needed, the scope compares ids.
     *
     * @var string
     */
    private const PROJECT_A = 'a4666000-0000-4000-8000-00000000000a';
    private const PROJECT_B = 'a4666000-0000-4000-8000-00000000000b';

    /**
     * The search settings the tests run with, those of system.search.
     *
     * @var float|int
     */
    private const THRESHOLD = 0.15;
    private const MAX_DISTANCE = 0.5;
    private const CANDIDATE_DEPTH = 100;
    private const NUMBER_QUERY_WORDS = 5;

    /**
     * @var \Phalcon\Db\Adapter\AdapterInterface
     */
    private $db;

    /**
     * @var SearchTestAcl
     */
    private $acl;

    /**
     * @var SearchTestEmbeddings
     */
    private $embeddings;

    protected function setUp(): void
    {
        $db = \Phalcon\Di\Di::getDefault()->getShared('db');
        if ($db->getType() !== 'pgsql') {
            $this->markTestSkipped('Search needs a PostgreSQL database');
        }
        $this->db = $db;
        $this->acl = new SearchTestAcl(\Phalcon\Di\Di::getDefault(), Acl::RESOLUTION_PERMISSIVE);
        $this->embeddings = new SearchTestEmbeddings();
        $this->db->begin();
    }

    protected function tearDown(): void
    {
        if ($this->db !== null && $this->db->isUnderTransaction()) {
            $this->db->rollback();
        }
    }

    public function testRanksIssuesMatchingInTheSubjectFirst()
    {
        $inBoth = $this->addIssue('Zebracorn import fails', 'The zebracorn importer stops.');
        $inDescription = $this->addIssue('Importer stops', 'Seen while testing the zebracorn feed.');

        $results = $this->search('zebracorn');

        $this->assertSame(array($inBoth, $inDescription), array_column($results, 'id'));
        $this->assertSame(array('fts', 'fuzzy'), $results[0]['matchedBy']);
        $this->assertSame(array('fts'), $results[1]['matchedBy']);
        $this->assertGreaterThan($results[1]['score'], $results[0]['score']);
        $this->assertSame('issue', $results[0]['type']);
        $this->assertSame('Zebracorn import fails', $results[0]['title']);
        $this->assertStringContainsString('<mark>zebracorn</mark>', $results[0]['snippet']);
    }

    public function testQuotesAndADashArePlainTextNotOperators()
    {
        $both = $this->addIssue('Zebracorn import fails', null);
        $this->addIssue('Zebracorn feed stops', null);
        $plain = $this->search('zebracorn import');

        foreach (array('zebracorn -import', '"zebracorn import"', '"import zebracorn"') as $query) {
            $results = $this->search($query);

            $this->assertSame(array_column($plain, 'id'), array_column($results, 'id'), $query);
            $this->assertSame($both, $results[0]['id'], $query);
            $this->assertContains('fts', $results[0]['matchedBy'], $query);
        }
    }

    public function testANumberQueryFindsThatIssueAloneAndNoText()
    {
        $this->addIssue('Follow up on 987654301', 'Same as 987654301.');
        $numbered = $this->addIssue('Unrelated title', null, 987654301);

        foreach (array('987654301', '#987654301') as $query) {
            $results = $this->search($query);

            $this->assertSame(array($numbered), array_column($results, 'id'), $query);
            $this->assertSame(array('exact'), $results[0]['matchedBy'], $query);
            $this->assertSame(1.0, $results[0]['score'], $query);
        }
    }

    public function testANumberNobodyHasFindsNothingNotEvenText()
    {
        $this->addIssue('Follow up on 987654399', 'Same as 987654399.');

        $this->assertSame(array(), $this->search('987654399'));
    }

    public function testWithoutThatNumberFindsTheNumbersStartingWithItThenEndingWithItNewestFirst()
    {
        $this->acl->scope = Acl::SCOPE_MEMBERS;
        $this->acl->projectIds = array(self::PROJECT_A);
        $older = $this->addIssue('Unrelated one', null, 543901701);
        $newer = $this->addIssue('Unrelated two', null, 543901702);
        $shorter = $this->addIssue('Unrelated three', null, 54390171);
        $ending = $this->addIssue('Unrelated four', null, 905439017);
        $this->addIssue('Unrelated five', null, 543901703, self::PROJECT_B);
        $this->addIssue('Mentions 5439017 in its title', null, 111543911);

        $results = $this->search('5439017');

        $this->assertSame(array($shorter, $newer, $older, $ending), array_column($results, 'id'));
        foreach ($results as $result) {
            $this->assertSame(array('number'), $result['matchedBy']);
        }
        $this->assertEqualsWithDelta(0.5 + 0.5 * 7 / 8, $results[0]['score'], 1e-9);
        $this->assertEqualsWithDelta(0.5 + 0.5 * 7 / 9, $results[1]['score'], 1e-9);
        $this->assertEqualsWithDelta(self::THRESHOLD + (0.5 - self::THRESHOLD) * 7 / 9, $results[3]['score'], 1e-9);
    }

    public function testAnExactNumberLeavesThePartialNumbersOut()
    {
        $exact = $this->addIssue('Unrelated one', null, 54390181);
        $this->addIssue('Unrelated two', null, 543901811);

        $this->assertSame(array($exact), array_column($this->search('54390181'), 'id'));
    }

    public function testASingleDigitHasNoPartialNumbers()
    {
        $this->acl->scope = Acl::SCOPE_MEMBERS;
        $this->acl->projectIds = array(self::PROJECT_A);
        $this->addIssue('Unrelated one', null, 543901901);

        $this->assertSame(array(), $this->search('#5'));
    }

    public function testAReferenceFindsThatIssueInThatProjectWithCaseIgnored()
    {
        $issue = $this->addIssue('Unrelated title', null, 987654320);
        $this->addIssue('Mentions srch-987654320', 'srch 987654320', null);
        $this->addIssue('Another project', null, 987654322, self::PROJECT_B, 0, 'srchb');

        foreach (array('srch-987654320', 'SRCH-987654320', 'Srch-987654320') as $query) {
            $results = $this->search($query);

            $this->assertSame(array($issue), array_column($results, 'id'), $query);
            $this->assertSame(array('exact'), $results[0]['matchedBy'], $query);
        }
        // A known prefix with a number it does not have finds nothing, not the number elsewhere
        $this->assertSame(array(), $this->search('srchb-987654320'));
        $this->assertSame(array(), $this->search('srch-987654322'));
    }

    public function testAReferenceToAProjectTheUserIsNotInFindsNothing()
    {
        $this->addIssue('Unrelated title', null, 987654323);
        $this->addIssue('Theirs', null, 987654324, self::PROJECT_B, 0, 'srchb');
        $this->acl->scope = Acl::SCOPE_MEMBERS;
        $this->acl->projectIds = array(self::PROJECT_A);

        $this->assertSame(array(), $this->search('srchb-987654324'));
        $this->assertSame(array(), $this->search('srchb-987654323'));
    }

    public function testAnUnknownPrefixIsPlainTextWithoutTheNumber()
    {
        $numbered = $this->addIssue('Unrelated title', null, 987654325);
        $text = $this->addIssue('Zebracorn import fails', null);

        $results = $this->search('zebracorn-987654325');

        $this->assertNotContains($numbered, array_column($results, 'id'));
        $this->assertContains($text, array_column($results, 'id'));
    }

    public function testANumberInsideAShortQueryComesFirstThenTheTextMatches()
    {
        $numbered = $this->addIssue('Unrelated title', null, 987654330);
        $text = $this->addIssue('Zebracorn import fails since 987654330', null);

        foreach (array('zebracorn 987654330', '987654330 zebracorn', '#987654330 zebracorn') as $query) {
            $results = $this->search($query);

            $this->assertSame($numbered, $results[0]['id'], $query);
            $this->assertSame(array('exact'), $results[0]['matchedBy'], $query);
            $this->assertContains($text, array_column($results, 'id'), $query);
        }
    }

    public function testALongQueryLooksUpOnlyTheNumbersWrittenWithAHash()
    {
        $numbered = $this->addIssue('Unrelated title', null, 987654331);
        $this->addIssue('Zebracorn import fails', 'The zebracorn importer stops.');

        $bare = $this->search('the zebracorn importer stops again at row 987654331');
        $hashed = $this->search('the zebracorn importer stops again (see #987654331)');

        $this->assertNotContains($numbered, array_column($bare, 'id'));
        $this->assertSame($numbered, $hashed[0]['id']);
    }

    public function testANumberTheUserMayNotReadFindsNothingNotEvenText()
    {
        $numbered = $this->addIssue('Unrelated title', null, 987654350);
        $this->acl->deniedFields = array('issueNumber');

        $this->assertSame(array(), $this->search('987654350'));
        // Without the number the reference is plain text
        $this->assertNotContains($numbered, array_column($this->search('srch-987654350'), 'id'));
    }

    public function testAReferencePrefixTheUserMayNotReadIsPlainText()
    {
        $numbered = $this->addIssue('Unrelated title', null, 987654351);
        $text = $this->addIssue('Srch notes on 987654351', null);
        $this->acl->deniedFields = array('projectShortcode');

        $ids = array_column($this->search('srch-987654351'), 'id');

        $this->assertNotContains($numbered, $ids);
        $this->assertContains($text, $ids);
    }

    public function testLeadingZerosAndHugeNumbersAreNoErrors()
    {
        $issue = $this->addIssue('Unrelated title', null, 987654352);

        $this->assertSame(array($issue), array_column($this->search('0987654352'), 'id'));
        $this->assertSame(array($issue), array_column($this->search('srch-0987654352'), 'id'));
        $this->assertSame(array(), $this->search('00'));
        $this->assertSame(array(), $this->search('srch-99999999999999999999999'));
    }

    public function testANumberOfAnIssueTheUserCannotSeeFindsNothing()
    {
        $this->addIssue('Theirs', null, 987654340, self::PROJECT_B);
        $this->addIssue('Theirs too', null, 987654341, self::PROJECT_B);
        $this->acl->scope = Acl::SCOPE_MEMBERS;
        $this->acl->projectIds = array(self::PROJECT_A);

        foreach (array('987654340', '98765434', '7654340', 'zebracorn 987654340') as $query) {
            $this->assertSame(array(), $this->search($query), $query);
        }
    }

    public function testATextQueryNeverMatchesTheIssueNumber()
    {
        $this->addIssue('Unrelated title', null, 987654302);

        $this->assertSame(array(), $this->search('#98765430x'));
    }

    public function testPinsTheIssueWhoseTitleIsTheQueryWithCaseAndSpacingIgnored()
    {
        $longer = $this->addIssue('Zebracorn import fails on Mondays', null);
        $exact = $this->addIssue('Zebracorn  import fails', null);

        $results = $this->search('  zebracorn IMPORT   fails ');

        $this->assertSame(array($exact, $longer), array_column($results, 'id'));
        $this->assertContains('exact', $results[0]['matchedBy']);
        $this->assertSame(1.0, $results[0]['score']);
        $this->assertLessThan(1.0, $results[1]['score']);
    }

    public function testPinsTheIssueWhoseTitleHasANoBreakSpace()
    {
        $exact = $this->addIssue("Zebracorn\u{00A0}import fails", null);

        $results = $this->search("zebracorn import\u{00A0}fails");

        $this->assertSame(array($exact), array_column($results, 'id'));
        $this->assertContains('exact', $results[0]['matchedBy']);
    }

    public function testPinsTheIssueWhoseTitleEndsInACapitalSigma()
    {
        $exact = $this->addIssue('ΖΕΒΡΑΚΟΡΝ ΟΣ', null);

        $results = $this->search('ΖΕΒΡΑΚΟΡΝ ΟΣ');

        $this->assertSame(array($exact), array_column($results, 'id'));
        $this->assertContains('exact', $results[0]['matchedBy']);
    }

    public function testFindsByNumberWhenTheTitleIsUnreadable()
    {
        $numbered = $this->addIssue('Zebracorn import fails', null, 987654304);
        $this->acl->deniedFields = array('subject');

        $results = $this->search('987654304');

        $this->assertSame(array($numbered), array_column($results, 'id'));
        $this->assertSame(array('exact'), $results[0]['matchedBy']);
    }

    public function testAQueryWithoutLettersOrDigitsFindsNothing()
    {
        $this->addIssue('!!! ??? everywhere', '!!!');
        $this->embeddings->vectors['!!!'] = $this->vector(1);

        $this->assertSame(array(), $this->search('!!!'));
        $this->assertSame(0, $this->embeddings->calls);
    }

    public function testAStopWordQueryHasNoFuzzyMatches()
    {
        $this->addIssue('The end of an era', null);
        $this->addIssue('Them and those', null);

        $this->assertSame(array(), $this->search('the and of'));
    }

    public function testAQueryUnderThreeLettersHasNoFuzzyMatches()
    {
        $this->addIssue('Zqx import', null);

        $this->assertSame(array(), $this->search('zq'));
    }

    public function testTheFuzzySearcherSkipsStopWordsAndShortQueriesOnly()
    {
        $id = $this->addIssue('The zqx', null);
        $fuzzy = new FuzzySearcher(new CandidateQuery($this->db), 0.1);
        $target = $this->target();

        $this->assertSame(array(), $fuzzy->rank($target, new SearchQuery('the and of'), 10));
        $this->assertSame(array(), $fuzzy->rank($target, new SearchQuery('zq'), 10));
        $this->assertArrayHasKey($id, $fuzzy->rank($target, new SearchQuery('the zqx'), 10));
    }

    public function testScoresFromZeroToOneNeverRisingDownTheList()
    {
        $this->addIssue('Zebracorn import fails', 'The zebracorn importer stops.');
        $this->addIssue('Importer stops', 'Seen while testing the zebracorn feed.');
        $this->addIssue('Zebracorns', null);

        $scores = array_column($this->search('zebracorn'), 'score');

        $this->assertNotEmpty($scores);
        $previous = 1.0;
        foreach ($scores as $score) {
            $this->assertGreaterThanOrEqual(self::THRESHOLD, $score);
            $this->assertLessThanOrEqual($previous, $score);
            $previous = $score;
        }
    }

    public function testKeepsAMeaningMatchAtTheThresholdAndDropsOneUnderIt()
    {
        $this->embeddings->vectors['zebracorn'] = $this->vector(1);
        // Cosine distances 0.30 (meaning about 0.41) and 0.38 (meaning about 0.09), both within the max distance
        $kept = $this->addIssue('Alpha', null);
        $this->embed($kept, $this->vector(0.7, 0.714));
        $dropped = $this->addIssue('Beta', null);
        $this->embed($dropped, $this->vector(0.62, 0.7846));

        $results = $this->search('zebracorn');

        $this->assertSame(array($kept), array_column($results, 'id'));
        $this->assertEqualsWithDelta(0.41, $results[0]['score'], 0.01);
    }

    public function testFindsASubjectDespiteATypo()
    {
        $issue = $this->addIssue('Zebracorn import fails', null);

        $results = $this->search('zebracron');

        $this->assertSame(array($issue), array_column($results, 'id'));
        $this->assertSame(array('fuzzy'), $results[0]['matchedBy']);
    }

    public function testDropsFuzzyOnlyResultsWhenEveryWordIsKnownAndSomethingElseMatches()
    {
        $match = $this->addIssue('Impossible import', null);
        $noise = $this->addIssue('Zebracorn invisible', null);

        $ids = array_column($this->search('impossible'), 'id');
        $this->assertSame(array($match), $ids);
        $this->assertNotContains($noise, $ids);
    }

    public function testAnExcludedWordNobodyHasDoesNotCountAsUnknown()
    {
        $match = $this->addIssue('Impossible import', null);
        $noise = $this->addIssue('Zebracorn invisible', null);

        $ids = array_column($this->search('impossible -qwertyuiop'), 'id');
        $this->assertContains($match, $ids);
        $this->assertNotContains($noise, $ids);
    }

    public function testKeepsFuzzyOnlyResultsForAQueryWithAnUnknownWord()
    {
        $issue = $this->addIssue('Zebracorn import fails', null);

        $results = $this->search('zebracron import');

        $this->assertContains($issue, array_column($results, 'id'));
    }

    public function testAWordOnlyInAProjectTheUserCannotReadIsUnknown()
    {
        $this->addIssue('Zebracorn secret', null, null, self::PROJECT_B);
        $this->addIssue('Zebracorns open', null, null, self::PROJECT_A);
        $this->acl->scope = Acl::SCOPE_MEMBERS;
        $this->acl->projectIds = array(self::PROJECT_A);
        $words = new UnknownWords(new CandidateQuery($this->db), new TextSearchQuery());
        $target = $this->target();

        $this->assertSame(array('secret'), $words->of(array($target), new SearchQuery('secret zebracorn')));
        $this->assertSame(array(), $words->of(array($target), new SearchQuery('the 123 ab zebracorn')));
    }

    public function testFindsAJoinedWordWrittenAsTwoWordsAndTwoWordsWrittenJoined()
    {
        $joined = $this->addIssue('Quokkaboard filters reset', null);
        $apart = $this->addIssue('Quokka board columns overlap', null);
        $apartToo = $this->addIssue('Quokka board loses card order', null);

        foreach (array('quokkaboard', 'quokka board') as $query) {
            $keyword = $this->keywordMatches($this->search($query));
            sort($keyword);
            $expected = array($joined, $apart, $apartToo);
            sort($expected);
            $this->assertSame($expected, $keyword, $query);
        }
    }

    public function testASplitPairOnlyOneRecordHasIsNotUsed()
    {
        $this->addIssue('Wombatpanel opens twice', null);
        $once = $this->addIssue('Wombat panel docks left', null);

        $this->assertNotContains($once, $this->keywordMatches($this->search('wombatpanel')));
    }

    public function testFindsTheOtherSpellingAndMarksItInTheSnippet()
    {
        $id = $this->addIssue('Quokkacolor picker stuck', 'The quokkacolor picker never closes.');

        $items = $this->search('quokkacolour');

        $this->assertContains($id, $this->keywordMatches($items));
        $snippet = array_values(array_filter($items, function ($item) use ($id) {
            return $item['id'] === $id;
        }))[0]['snippet'];
        $this->assertStringContainsString('<mark>quokkacolor</mark>', $snippet);
    }

    public function testFindsTheFullWordOfAnAbbreviationAndTheOtherWay()
    {
        $full = $this->addIssue('Quokka configuration screen hides options', null);
        $short = $this->addIssue('Quokka config file ignored', null);

        $this->assertContains($full, $this->keywordMatches($this->search('quokka config')));
        $this->assertContains($short, $this->keywordMatches($this->search('quokka configuration')));
    }

    public function testLogoutIsNotReadAsLogOut()
    {
        $this->addIssue('Logout fails for quokkas', null);
        $apart = $this->addIssue('Log out button hidden for quokkas', null);
        $this->addIssue('Log out link hidden for quokkas', null);

        $this->assertNotContains($apart, $this->keywordMatches($this->search('logout quokkas')));
    }

    public function testATokenWithAWordThatHasFormsStillMatchesWhole()
    {
        $this->addIssue('Quokka configuration screen', null);
        $token = $this->addIssue('Quokka config.php is ignored', null);

        $this->assertContains($token, $this->keywordMatches($this->search('quokka config.php')));
        $this->assertContains($token, $this->keywordMatches($this->search('quokkas config.php')));
    }

    public function testChecksEachFormOncePerTypeAndQuery()
    {
        $this->addIssue('Quokkacolor picker', null);
        $candidates = new SearchTestCountingCandidates($this->db);
        $expansion = $this->expansion($candidates);
        $target = $this->target();

        $expansion->of($target, new SearchQuery('quokkacolour quokkacolour'));
        $expansion->of($target, new SearchQuery('quokkacolour quokkacolour'));
        $expansion->of($target, new SearchQuery('quokkacolour'));

        $this->assertArrayHasKey('quokkacolor', $candidates->lookups);
        $this->assertArrayHasKey('quokka colour', $candidates->lookups);
        $this->assertSame(array_fill_keys(array_keys($candidates->lookups), 1), $candidates->lookups);
    }

    public function testAFormOnlyInAProjectTheUserCannotReadIsNotUsed()
    {
        $this->addIssue('Quokkacolor secret', null, null, self::PROJECT_B);
        $this->addIssue('Quokkacolour open', null, null, self::PROJECT_A);
        $expansion = $this->expansion(new CandidateQuery($this->db));
        $query = new SearchQuery('quokkacolour');

        $this->assertNotSame(array(), $expansion->of($this->target(), $query)->groups());

        $this->acl->scope = Acl::SCOPE_MEMBERS;
        $this->acl->projectIds = array(self::PROJECT_A);
        $this->assertSame(array(), $this->expansion(new CandidateQuery($this->db))->of($this->target(), $query)->groups());
    }

    public function testASplitPairCountsOnlyTheRecordsTheUserMayRead()
    {
        $this->addIssue('Wombat panel docks left', null, null, self::PROJECT_A);
        $this->addIssue('Wombat panel docks right', null, null, self::PROJECT_B);
        $this->acl->scope = Acl::SCOPE_MEMBERS;
        $this->acl->projectIds = array(self::PROJECT_A);

        $keywords = $this->expansion(new CandidateQuery($this->db))->of($this->target(), new SearchQuery('wombatpanel'));

        $this->assertSame(array(), $keywords->groups());
    }

    public function testSearchersReportTheRawSignalOfEachMatch()
    {
        $this->embeddings->vectors['zebracorn'] = $this->vector(1);
        $id = $this->addIssue('Zebracorn import fails', 'The zebracorn importer stops.', 987654303);
        $this->embed($id, $this->vector(0.6, 0.8));
        $candidates = new CandidateQuery($this->db);
        $target = $this->target();

        $exact = (new ExactMatcher($candidates, self::NUMBER_QUERY_WORDS))->rank($target, new SearchQuery('987654303'), 10);
        $fts = (new FtsSearcher($candidates, new TextSearchQuery(), $this->expansion($candidates)))->rank($target, new SearchQuery('zebracorn'), 10);
        $fuzzy = (new FuzzySearcher($candidates, 0.3))->rank($target, new SearchQuery('zebracorn'), 10);
        $semantic = (new SemanticSearcher($candidates, $this->embeddings, 0.5))->rank($target, new SearchQuery('zebracorn'), 10);

        $this->assertSame(array($id => 1.0), $exact);
        $this->assertSame(array($id), array_keys($fts));
        $this->assertGreaterThan(0.0, $fts[$id]);
        $this->assertLessThan(1.0, $fts[$id]);
        $this->assertEqualsWithDelta(1.0, $fuzzy[$id], 0.0001);
        $this->assertEqualsWithDelta(0.4, $semantic[$id], 0.0001);
    }

    public function testSearchersGiveTheStrongerMatchTheStrongerSignalFirst()
    {
        $this->embeddings->vectors['zebracorn'] = $this->vector(1);
        $near = $this->addIssue('Alpha', null);
        $this->embed($near, $this->vector(0.95, 0.312));
        $far = $this->addIssue('Beta', null);
        $this->embed($far, $this->vector(0.7, 0.714));
        $strong = $this->addIssue('Zebracorn zebracorn zebracorn', null);
        $weak = $this->addIssue('Zebracorn', 'one more words');
        $candidates = new CandidateQuery($this->db);
        $target = $this->target();

        $semantic = (new SemanticSearcher($candidates, $this->embeddings, 0.5))->rank($target, new SearchQuery('zebracorn'), 10);
        $fts = (new FtsSearcher($candidates, new TextSearchQuery(), $this->expansion($candidates)))->rank($target, new SearchQuery('zebracorn'), 10);

        $this->assertSame(array($near, $far), array_keys($semantic));
        $this->assertLessThan($semantic[$far], $semantic[$near]);
        $this->assertSame(array($strong, $weak), array_keys($fts));
        $this->assertGreaterThan($fts[$weak], $fts[$strong]);
    }

    /**
     * @return \Gaia\Libraries\Search\SearchTarget
     */
    private function target()
    {
        $di = \Phalcon\Di\Di::getDefault();
        $metaManager = $di->get('metaManager');
        $registry = SearchableRegistry::fromMetadata($metaManager);
        $meta = $metaManager->getModelMeta($registry->forType('issue')['model']);
        $scope = (new SearchScope($this->acl, $di->get('modelsManager')))
            ->forModel($registry->forType('issue')['model'], $meta['fields']);

        return new \Gaia\Libraries\Search\SearchTarget(
            $registry->forType('issue'),
            $meta['tableName'],
            $meta['fields'],
            $scope['sql'],
            $scope['bind']
        );
    }

    public function testPutsTheFuzzyThresholdBackAfterTheSearch()
    {
        $this->db->execute("SELECT set_config('pg_trgm.word_similarity_threshold', '0.6', false)");
        $this->addIssue('Zebracorn import fails', null);

        $this->search('zebracron');

        $row = $this->db->fetchOne(
            "SELECT current_setting('pg_trgm.word_similarity_threshold') AS t",
            \Phalcon\Db\Enum::FETCH_ASSOC
        );
        $this->assertSame('0.6', $row['t']);
    }

    public function testAMembersScopeUserNeverSeesOtherProjectsIssues()
    {
        $own = $this->addIssue('Zebracorn in my project', null, null, self::PROJECT_A);
        $other = $this->addIssue('Zebracorn in their project', null, null, self::PROJECT_B);
        $this->addIssue('Zebracorn removed', null, null, self::PROJECT_A, 1);

        $this->assertEqualsCanonicalizing(array($own, $other), array_column($this->search('zebracorn'), 'id'));

        $this->acl->scope = Acl::SCOPE_MEMBERS;
        $this->acl->projectIds = array(self::PROJECT_A);
        $this->assertSame(array($own), array_column($this->search('zebracorn'), 'id'));

        $this->acl->projectIds = array();
        $this->assertSame(array(), $this->search('zebracorn'));
    }

    public function testScopesTheCandidatesBeforeTheLimit()
    {
        // Better matches the user may not see, more than the candidates each searcher gives
        for ($i = 0; $i < 4; $i++) {
            $this->addIssue('Zebracorn zebracorn zebracorn', 'Zebracorn zebracorn.', null, self::PROJECT_B);
        }
        $own = $this->addIssue('Old notes', 'Mentions a zebracorn once among many other words.');
        $this->acl->scope = Acl::SCOPE_MEMBERS;
        $this->acl->projectIds = array(self::PROJECT_A);

        $this->assertSame(array($own), array_column($this->search('zebracorn', 1), 'id'));
    }

    public function testEscapesTheSnippetAndMarksTheMatches()
    {
        $this->addIssue('Zebracorn page', 'Before <script>alert(1)</script> the zebracorn & "friends".');

        $results = $this->search('zebracorn');

        // ts_headline drops the tags; what is left is escaped around the marks
        $this->assertStringNotContainsString('<script', $results[0]['snippet']);
        $this->assertStringContainsString('<mark>zebracorn</mark> &amp; &quot;friends', $results[0]['snippet']);
        $this->assertSame(2, substr_count($results[0]['snippet'], '<'), 'only <mark> and </mark>');
    }

    public function testChangesASettingTheBackendHasNotDefinedYet()
    {
        // Like pg_trgm's threshold before the library loads on a new connection
        $name = 'p4m_search_test.threshold';
        $candidates = new CandidateQuery($this->db);

        $inside = $candidates->withSetting($name, '0.4', function () use ($name) {
            return $this->db->fetchOne(
                'SELECT current_setting(:name) AS v',
                \Phalcon\Db\Enum::FETCH_ASSOC,
                array('name' => $name)
            )['v'];
        });

        $this->assertSame('0.4', $inside);
        $after = $this->db->fetchOne(
            'SELECT current_setting(:name, true) AS v',
            \Phalcon\Db\Enum::FETCH_ASSOC,
            array('name' => $name)
        )['v'];
        $this->assertContains($after, array(null, ''));
    }

    public function testARecordCannotForgeASnippetMark()
    {
        $this->addIssue('Zebracorn page', "The zebracorn and \x02forged\x03 text.");

        $snippet = $this->search('zebracorn')[0]['snippet'];

        $this->assertSame(1, substr_count($snippet, '<mark>'));
        $this->assertStringContainsString('forged text', $snippet);
    }

    public function testOmitsADisplayFieldTheUserMayNotRead()
    {
        $issue = $this->addIssue('Zebracorn import fails', 'The zebracorn importer stops.');
        $this->acl->deniedFields = array('description');

        $results = $this->search('zebracorn');

        $this->assertSame(array($issue), array_column($results, 'id'));
        $this->assertSame('Zebracorn import fails', $results[0]['title']);
        $this->assertArrayNotHasKey('snippet', $results[0]);
        // FTS reads the description, so only fuzzy matched
        $this->assertSame(array('fuzzy'), $results[0]['matchedBy']);
    }

    /**
     * @dataProvider oddQueries
     */
    public function testAnOddQueryIsNoMatchNotAnError($query)
    {
        $this->addIssue('Zebracorn import fails', null, 987654304);

        $this->assertSame(array(), $this->search($query));
    }

    /**
     * @return array
     */
    public static function oddQueries()
    {
        return array(
            'stop words only' => array('the'),
            'number past int' => array('#99999999999999999999'),
            'quotes and colons' => array("'zz' :search_fts \" OR 1=1 --"),
        );
    }

    public function testSkipsSearchersOverAFieldTheUserMayNotRead()
    {
        $issue = $this->addIssue('Zebracorn import fails', 'The zebracorn importer stops.', 987654303);
        $this->acl->deniedFields = array('subject');

        // FTS reads the subject too, so only the exact match is left
        $this->assertSame(array(), $this->search('zebracorn'));

        $results = $this->search('987654303');
        $this->assertSame(array($issue), array_column($results, 'id'));
        $this->assertArrayNotHasKey('title', $results[0]);
        $this->assertArrayHasKey('snippet', $results[0]);
    }

    public function testDeniesTheRequestForATypeTheUserMayNotGet()
    {
        $this->acl->deniedModels = array('Issue');

        $this->expectException(\Gaia\Exception\Access::class);
        $this->search('zebracorn');
    }

    public function testRejectsAnUnknownType()
    {
        $this->expectException(\Gaia\Exception\BadRequest::class);
        $this->service()->search(new SearchRequest(array('nosuchtype'), 'zebracorn'));
    }

    public function testAQueryOfSeveralWordsPutsTheIssuesHoldingEveryWordFirst()
    {
        $this->embeddings->vectors['zebracorn quokka'] = $this->vector(1);
        $this->embeddings->vectors['zebracorn'] = $this->vector(1);
        $everyWord = $this->addIssue('Feed notes', 'The zebracorn quokka feed stops on Mondays.');
        $byMeaning = $this->addIssue('Coat colours fade', null);
        $this->embed($byMeaning, $this->vector(1));

        $several = $this->service()->search(new SearchRequest(array('issue'), 'zebracorn quokka'))->items();
        $one = $this->service()->search(new SearchRequest(array('issue'), 'zebracorn'))->items();

        $this->assertSame(array($everyWord, $byMeaning), array_column($several, 'id'));
        $this->assertSame(array($byMeaning, $everyWord), array_column($one, 'id'));
    }

    public function testFindsAParaphraseByMeaning()
    {
        $this->embeddings->vectors['login fails after password reset'] = $this->vector(0.9, 0.436);
        $match = $this->addIssue('Reset password link broken', 'The link in the email opens an error page.');
        $this->embed($match, $this->vector(1));
        $this->embed($this->addIssue('Dark mode colours', null), $this->vector(0, 0, 1));
        $this->addIssue('Login page slow', null);

        $results = $this->service()->search(new SearchRequest(array('issue'), 'login fails after password reset'));

        $this->assertSame(array($match), array_column($results->items(), 'id'));
        $this->assertContains('semantic', $results->items()[0]['matchedBy']);
        $this->assertFalse($results->isUnavailable(SemanticSearcher::KIND));
    }

    public function testRanksTheNearestEmbeddingFirst()
    {
        $this->embeddings->vectors['zebracorn'] = $this->vector(1);
        $far = $this->addIssue('Alpha', null);
        $this->embed($far, $this->vector(0.7, 0.714));
        $near = $this->addIssue('Beta', null);
        $this->embed($near, $this->vector(0.95, 0.312));

        $this->assertSame(array($near, $far), array_column($this->search('zebracorn'), 'id'));
    }

    public function testSemanticMatchesStayInTheScope()
    {
        $this->embeddings->vectors['zebracorn'] = $this->vector(1);
        $own = $this->addIssue('Alpha', null, null, self::PROJECT_A);
        $other = $this->addIssue('Beta', null, null, self::PROJECT_B);
        $removed = $this->addIssue('Gamma', null, null, self::PROJECT_A, 1);
        foreach (array($own, $other, $removed) as $id) {
            $this->embed($id, $this->vector(1));
        }
        $this->acl->scope = Acl::SCOPE_MEMBERS;
        $this->acl->projectIds = array(self::PROJECT_A);

        $this->assertSame(array($own), array_column($this->search('zebracorn'), 'id'));
    }

    public function testScansTheEmbeddingIndexIterativelyAndPutsTheSettingBack()
    {
        // Without an iterative scan, nearer records out of scope crowd the ones in it out of the HNSW candidates
        $this->db->execute("SELECT set_config('hnsw.iterative_scan', 'off', false)");
        $this->embeddings->vectors['zebracorn'] = $this->vector(1);
        $this->embed($this->addIssue('Alpha', null), $this->vector(1));
        $candidates = new SearchTestSettingSpy($this->db);

        $this->service(self::MAX_DISTANCE, $candidates)
            ->search(new SearchRequest(array('issue'), 'zebracorn'));

        $this->assertSame('strict_order', $candidates->settings['semantic']);
        $row = $this->db->fetchOne(
            "SELECT current_setting('hnsw.iterative_scan') AS s",
            \Phalcon\Db\Enum::FETCH_ASSOC
        );
        $this->assertSame('off', $row['s']);
    }

    public function testLeavesOutEmbeddingsPastTheMaxDistance()
    {
        $this->embeddings->vectors['zebracorn'] = $this->vector(1);
        $near = $this->addIssue('Alpha', null);
        $this->embed($near, $this->vector(0.95, 0.312));
        $this->embed($this->addIssue('Beta', null), $this->vector(0.8, 0.6));

        $results = $this->service(0.1)->search(new SearchRequest(array('issue'), 'zebracorn'));

        $this->assertSame(array($near), array_column($results->items(), 'id'));
    }

    public function testAresDownStillGivesTheOtherMatchesAndSaysSemanticIsUnavailable()
    {
        $this->embeddings->down = true;
        $issue = $this->addIssue('Zebracorn import fails', null);
        $this->embed($issue, $this->vector(1));

        $results = $this->service()->search(new SearchRequest(array('issue'), 'zebracorn'));

        $this->assertSame(array($issue), array_column($results->items(), 'id'));
        $this->assertNotContains('semantic', $results->items()[0]['matchedBy']);
        $this->assertTrue($results->isUnavailable(SemanticSearcher::KIND));
        $this->assertSame(1, $this->embeddings->calls);
    }

    public function testSkipsSemanticMatchingOverAFieldTheUserMayNotRead()
    {
        $this->embeddings->vectors['zebracorn'] = $this->vector(1);
        $this->embed($this->addIssue('Alpha', 'Hidden zebracorn notes.'), $this->vector(1));
        $this->acl->deniedFields = array('description');

        $results = $this->service()->search(new SearchRequest(array('issue'), 'zebracorn'));

        $this->assertSame(array(), $results->items());
        $this->assertSame(0, $this->embeddings->calls);
        $this->assertFalse($results->isUnavailable(SemanticSearcher::KIND));
    }

    /**
     * @dataProvider badMaxDistances
     */
    public function testRejectsAMaxDistanceOutOfBounds($maxDistance)
    {
        $this->expectException(\Gaia\Exception\Exception::class);
        new SemanticSearcher(new CandidateQuery($this->db), $this->embeddings, $maxDistance);
    }

    /**
     * @return array
     */
    public static function badMaxDistances()
    {
        return array(
            'negative' => array(-0.1),
            'past 2' => array(2.1),
            'not a number' => array('near'),
            'not finite' => array(NAN),
            'infinite' => array('1e999'),
        );
    }

    /**
     * @dataProvider badCandidateDepths
     */
    public function testRejectsACandidateDepthThatIsNotAPositiveWholeNumber($candidateDepth)
    {
        $this->expectException(\Gaia\Exception\Exception::class);
        $this->service(self::MAX_DISTANCE, null, $candidateDepth);
    }

    /**
     * @return array
     */
    public static function badCandidateDepths()
    {
        return array(
            'zero' => array(0),
            'negative' => array(-1),
            'fraction' => array(1.5),
            'not a number' => array('deep'),
        );
    }

    /**
     * @param string $query
     * @param int|null $limit
     * @return array
     */
    private function search($query, $limit = null)
    {
        return $this->service()->search(new SearchRequest(array('issue'), $query, $limit))->items();
    }

    /**
     * This function returns an embedding with the given leading values and zeros after them.
     *
     * @param float ...$values
     * @return array
     */
    private function vector(...$values)
    {
        return array_pad(array_map('floatval', $values), Embeddings::DIMENSIONS, 0.0);
    }

    /**
     * This function stores an issue's embedding, as Ares does.
     *
     * @param string $id
     * @param array $vector
     * @return void
     */
    private function embed($id, array $vector)
    {
        $this->db->execute(
            'UPDATE "issues" SET "searchEmbedding" = CAST(? AS vector) WHERE "id" = ?',
            array(json_encode($vector), $id)
        );
    }

    /**
     * @param float $maxDistance
     * @param CandidateQuery|null $candidates
     * @param mixed $candidateDepth
     * @return SearchService
     */
    private function service(
        $maxDistance = self::MAX_DISTANCE,
        ?CandidateQuery $candidates = null,
        $candidateDepth = self::CANDIDATE_DEPTH
    ) {
        $di = \Phalcon\Di\Di::getDefault();
        $metaManager = $di->get('metaManager');
        $candidates = $candidates ?? new CandidateQuery($this->db);
        $tsquery = new TextSearchQuery();
        $exact = new ExactMatcher($candidates, self::NUMBER_QUERY_WORDS);
        $expansion = $this->expansion($candidates);

        return new SearchService(
            SearchableRegistry::fromMetadata($metaManager),
            $metaManager,
            $this->acl,
            new SearchScope($this->acl, $di->get('modelsManager')),
            new SearchRoute(
                array(
                    $exact,
                    new ExactTitleMatcher($candidates),
                    new FtsSearcher($candidates, $tsquery, $expansion),
                    new FuzzySearcher($candidates, 0.3),
                    new SemanticSearcher($candidates, $this->embeddings, $maxDistance),
                ),
                $exact,
                new PartialNumberMatcher($candidates),
                new ReferenceMatcher($candidates)
            ),
            new StrongestSignal(self::THRESHOLD, 0.6, 0.3, 0.156, 0.402, 0.5, 0.5),
            new UnknownWords($candidates, $tsquery),
            new DisplayLoader($candidates, new Snippet($tsquery)),
            $expansion,
            $candidateDepth
        );
    }

    /**
     * This function returns the ids of the results the keyword search found.
     *
     * @param array $items
     * @return array
     */
    private function keywordMatches(array $items)
    {
        $ids = array();
        foreach ($items as $item) {
            if (in_array(FtsSearcher::KIND, $item['matchedBy'], true)) {
                $ids[] = $item['id'];
            }
        }

        return $ids;
    }

    /**
     * @param CandidateQuery $candidates
     * @return QueryExpansion
     */
    private function expansion(CandidateQuery $candidates)
    {
        return new QueryExpansion($candidates, new TextSearchQuery(), new QueryExpander(16, 5, 3), 2);
    }

    /**
     * This function adds an issue and returns its id.
     *
     * @param string $subject
     * @param string|null $description
     * @param int|null $number
     * @param string $projectId
     * @param int $deleted
     * @param string $shortcode
     * @return string
     */
    private function addIssue(
        $subject,
        $description,
        $number = null,
        $projectId = self::PROJECT_A,
        $deleted = 0,
        $shortcode = 'srch'
    ) {
        $id = sprintf('a4666%03d-0000-4000-8000-%012d', random_int(0, 999), random_int(0, 999999999));
        $user = '1';
        $this->db->execute(
            'INSERT INTO "issues" ("id", "subject", "description", "issueNumber", "projectId", "projectShortcode",'
            . ' "deleted", "createdUser", "createdUserName", "modifiedUser", "modifiedUserName", "owner", "assignee",'
            . ' "reportedUser", "typeId", "priority", "isPlanned", "isReopened")'
            . ' VALUES (?, ?, ?, COALESCE(?, nextval(pg_get_serial_sequence(\'issues\', \'issueNumber\'))), ?, ?,'
            . ' ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)',
            array($id, $subject, $description, $number, $projectId, $shortcode, $deleted, $user, 'Test', $user, 'Test',
                $user, $user, $user, 'srch-type', 'medium')
        );

        return $id;
    }
}

/**
 * An Acl whose grants and project scope a test sets, so the real record scope SQL runs over them.
 */
class SearchTestAcl extends Acl
{
    /**
     * @var int
     */
    public $scope = Acl::SCOPE_ALL;

    /**
     * @var array
     */
    public $projectIds = array();

    /**
     * @var array
     */
    public $deniedFields = array();

    /**
     * @var array
     */
    public $deniedModels = array();

    public function getEffectiveScope($resourceName)
    {
        return $this->scope;
    }

    public function getAccessibleProjectIds()
    {
        return $this->projectIds;
    }

    public function isModelActionAllowed($modelName, $action)
    {
        return !in_array($modelName, $this->deniedModels, true);
    }

    public function isFieldActionAllowed($modelName, $field, $action)
    {
        return !in_array($field, $this->deniedFields, true);
    }
}

/**
 * Embeddings that answer from a map of query to vector, without Ares. An unknown query gets a vector no test issue is
 * near; down makes every call fail as when Ares is unreachable.
 */
class SearchTestEmbeddings extends Embeddings
{
    /**
     * @var array
     */
    public $vectors = array();

    /**
     * @var bool
     */
    public $down = false;

    /**
     * @var int
     */
    public $calls = 0;

    public function embedQuery(string $text): array
    {
        $this->calls++;
        if ($this->down) {
            throw new EmbeddingsUnavailableException('Embeddings service request failed: no response');
        }
        if (isset($this->vectors[$text])) {
            return $this->vectors[$text];
        }
        $vector = array_fill(0, Embeddings::DIMENSIONS, 0.0);
        $vector[Embeddings::DIMENSIONS - 1] = 1.0;

        return $vector;
    }
}

/**
 * A candidate query that records the hnsw.iterative_scan setting the semantic candidate query runs under.
 */
class SearchTestSettingSpy extends CandidateQuery
{
    /**
     * @var array
     */
    public $settings = array();

    /**
     * @var \Phalcon\Db\Adapter\AdapterInterface
     */
    private $connection;

    public function __construct($db)
    {
        parent::__construct($db);
        $this->connection = $db;
    }

    public function signals(\Gaia\Libraries\Search\SearchTarget $target, $condition, $signal, $order, array $bind, $limit)
    {
        $row = $this->connection->fetchOne(
            "SELECT current_setting('hnsw.iterative_scan', true) AS s",
            \Phalcon\Db\Enum::FETCH_ASSOC
        );
        if (isset($bind['search_semantic'])) {
            $this->settings['semantic'] = $row['s'];
        }

        return parent::signals($target, $condition, $signal, $order, $bind, $limit);
    }
}

/**
 * A CandidateQuery that counts the lookups of each value it checks.
 */
class SearchTestCountingCandidates extends CandidateQuery
{
    /**
     * @var array
     */
    public $lookups = array();

    /**
     * {@inheritdoc}
     */
    public function exists(\Gaia\Libraries\Search\SearchTarget $target, $condition, array $bind)
    {
        $this->count($bind);

        return parent::exists($target, $condition, $bind);
    }

    /**
     * {@inheritdoc}
     */
    public function countUpTo(\Gaia\Libraries\Search\SearchTarget $target, $condition, array $bind, $max)
    {
        $this->count($bind);

        return parent::countUpTo($target, $condition, $bind, $max);
    }

    /**
     * @param array $bind
     * @return void
     */
    private function count(array $bind)
    {
        $value = isset($bind['search_fts']) ? $bind['search_fts'] : $bind['search_phrase'];
        $this->lookups[$value] = (isset($this->lookups[$value]) ? $this->lookups[$value] : 0) + 1;
    }
}
