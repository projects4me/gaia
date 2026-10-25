<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Security;

use Gaia\Libraries\Security\Acl;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the write checks on Acl that look past the payload: partial
 * updates read over the stored record, and reference fields (e.g.
 * Ideaplanissue.issueId) must point into a project in scope.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AclWriteReferencesTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\Phalcon\Di\FactoryDefault::class)) {
            $this->markTestSkipped('Phalcon is not available in this PHP runtime');
        }
    }

    /**
     * A PATCH that sends relatedId alone keeps the stored relatedTo, so the
     * new parent is resolved through the right path.
     *
     * @return void
     */
    public function testPartialUpdateIsReadOverTheStoredRecord()
    {
        $acl = $this->acl([], ['loadStoredAttributes']);
        $acl->method('loadStoredAttributes')->willReturn([
            'id' => 'c-1',
            'relatedTo' => 'issue',
            'relatedId' => 'issue-in-scope',
            'comment' => 'old',
        ]);

        $merged = $acl->mergeOverStoredRecord('Comment', ['relatedId' => 'issue-elsewhere'], 'c-1');

        $this->assertSame('issue', $merged['relatedTo']);
        $this->assertSame('issue-elsewhere', $merged['relatedId']);
    }

    /**
     * A reference field is checked against the project of the record it
     * names.
     *
     * @return void
     */
    public function testReferenceIsCheckedAgainstItsProject()
    {
        $acl = $this->acl($this->issueReference(), ['resolveReferenceProjectId', 'assertProjectIdInScope']);
        $acl->method('resolveReferenceProjectId')->willReturn('backend-project');
        $acl->expects($this->once())->method('assertProjectIdInScope')->with('backend-project');

        $acl->assertReferencesInScope('Ideaplanissue', ['ideaId' => 'idea-1', 'issueId' => 'issue-1']);
    }

    /**
     * A payload without the reference field, or a missing record, is not
     * checked here (the model validates a missing record).
     *
     * @return void
     */
    public function testAbsentOrMissingReferenceIsNotChecked()
    {
        $acl = $this->acl($this->issueReference(), ['resolveReferenceProjectId', 'assertProjectIdInScope']);
        $acl->method('resolveReferenceProjectId')->willReturn(null);
        $acl->expects($this->never())->method('assertProjectIdInScope');

        $acl->assertReferencesInScope('Ideaplanissue', ['key' => 'A1']);
        $acl->assertReferencesInScope('Ideaplanissue', ['issueId' => 'missing']);
    }

    /**
     * Models without references have nothing to check.
     *
     * @return void
     */
    public function testModelWithoutReferencesIsNotChecked()
    {
        $acl = $this->acl([], ['resolveReferenceProjectId', 'assertProjectIdInScope']);
        $acl->expects($this->never())->method('resolveReferenceProjectId');
        $acl->expects($this->never())->method('assertProjectIdInScope');

        $acl->assertReferencesInScope('Ideamessage', ['issueId' => 'issue-1']);
    }

    /**
     * @return array
     */
    private function issueReference()
    {
        return [
            'acl' => [
                'references' => [
                    'issueId' => ['relatedModel' => 'Issue', 'relatedKey' => 'id', 'projectField' => 'projectId'],
                ],
            ],
        ];
    }

    /**
     * An Acl whose metaManager returns the given model metadata.
     *
     * @param  array $metadata
     * @param  array $mockedMethods
     * @return Acl
     */
    private function acl(array $metadata, array $mockedMethods)
    {
        $metaManager = new class ($metadata) {
            private $metadata;

            public function __construct(array $metadata)
            {
                $this->metadata = $metadata;
            }

            public function getModelMeta($modelName)
            {
                return $this->metadata;
            }
        };
        $di = new \Phalcon\Di\FactoryDefault();
        $di->setShared('metaManager', function () use ($metaManager) {
            return $metaManager;
        });

        return $this->getMockBuilder(Acl::class)
            ->setConstructorArgs([$di, Acl::RESOLUTION_PERMISSIVE])
            ->setMethods($mockedMethods)
            ->getMock();
    }
}
