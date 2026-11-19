<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Search;

use Gaia\Libraries\Search\CandidateQuery;
use Gaia\Libraries\Search\DisplayLoader;
use Gaia\Libraries\Search\ExactMatcher;
use Gaia\Libraries\Search\FtsSearcher;
use Gaia\Libraries\Search\FuzzySearcher;
use Gaia\Libraries\Search\RankFusion;
use Gaia\Libraries\Search\SearchableRegistry;
use Gaia\Libraries\Search\SearchRequest;
use Gaia\Libraries\Search\SearchScope;
use Gaia\Libraries\Search\SearchService;
use Gaia\Libraries\Search\Snippet;
use Gaia\Libraries\Search\TextSearchQuery;
use Gaia\Libraries\Security\Acl;
use PHPUnit\Framework\TestCase;

/**
 * SearchService over Issue (agaia-4666): exact, FTS and fuzzy matching fused into one ranking, inside the user's
 * project scope and field ACL. Each test adds its issues in a transaction it rolls back.
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
     * @var \Phalcon\Db\Adapter\AdapterInterface
     */
    private $db;

    /**
     * @var SearchTestAcl
     */
    private $acl;

    protected function setUp(): void
    {
        $db = \Phalcon\Di::getDefault()->getShared('db');
        if ($db->getType() !== 'pgsql') {
            $this->markTestSkipped('Search needs a PostgreSQL database');
        }
        $this->db = $db;
        $this->acl = new SearchTestAcl(\Phalcon\Di::getDefault(), Acl::RESOLUTION_PERMISSIVE);
        $this->db->begin();
    }

    protected function tearDown(): void
    {
        if ($this->db !== null && $this->db->isUnderTransaction()) {
            $this->db->rollback();
        }
    }

    public function testRanksIssuesMatchingInTheSubjectAndByMoreSearchersFirst()
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

    public function testPinsTheIssueWhoseNumberIsTheQuery()
    {
        $mentions = $this->addIssue('Follow up on 987654301', 'Same as 987654301.');
        $numbered = $this->addIssue('Unrelated title', null, 987654301);

        foreach (array('987654301', '#987654301') as $query) {
            $results = $this->search($query);

            $this->assertSame($numbered, $results[0]['id'], $query);
            $this->assertSame(array('exact'), $results[0]['matchedBy'], $query);
            $this->assertContains($mentions, array_column($results, 'id'), $query);
        }
    }

    public function testATextQueryNeverMatchesTheIssueNumber()
    {
        $this->addIssue('Unrelated title', null, 987654302);

        $this->assertSame(array(), $this->search('#98765430x'));
    }

    public function testFindsASubjectDespiteATypo()
    {
        $issue = $this->addIssue('Zebracorn import fails', null);

        $results = $this->search('zebracron');

        $this->assertSame(array($issue), array_column($results, 'id'));
        $this->assertSame(array('fuzzy'), $results[0]['matchedBy']);
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
    public function oddQueries()
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

    /**
     * @param string $query
     * @param int|null $limit
     * @return array
     */
    private function search($query, $limit = null)
    {
        return $this->service()->search(new SearchRequest(array('issue'), $query, $limit));
    }

    /**
     * @return SearchService
     */
    private function service()
    {
        $di = \Phalcon\Di::getDefault();
        $metaManager = $di->get('metaManager');
        $candidates = new CandidateQuery($this->db);
        $tsquery = new TextSearchQuery();

        return new SearchService(
            SearchableRegistry::fromMetadata($metaManager),
            $metaManager,
            $this->acl,
            new SearchScope($this->acl, $di->get('modelsManager')),
            array(
                new ExactMatcher($candidates),
                new FtsSearcher($candidates, $tsquery),
                new FuzzySearcher($candidates, 0.3),
            ),
            new RankFusion(),
            new DisplayLoader($candidates, new Snippet($tsquery))
        );
    }

    /**
     * This function adds an issue and returns its id.
     *
     * @param string $subject
     * @param string|null $description
     * @param int|null $number
     * @param string $projectId
     * @param int $deleted
     * @return string
     */
    private function addIssue($subject, $description, $number = null, $projectId = self::PROJECT_A, $deleted = 0)
    {
        $id = sprintf('a4666%03d-0000-4000-8000-%012d', random_int(0, 999), random_int(0, 999999999));
        $user = '1';
        $this->db->execute(
            'INSERT INTO "issues" ("id", "subject", "description", "issueNumber", "projectId", "projectShortcode",'
            . ' "deleted", "createdUser", "createdUserName", "modifiedUser", "modifiedUserName", "owner", "assignee",'
            . ' "reportedUser", "typeId", "priority", "isPlanned", "isReopened")'
            . ' VALUES (?, ?, ?, COALESCE(?, nextval(pg_get_serial_sequence(\'issues\', \'issueNumber\'))), ?, ?,'
            . ' ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)',
            array($id, $subject, $description, $number, $projectId, 'srch', $deleted, $user, 'Test', $user, 'Test',
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
