<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Libraries\Utils\TransactionScope;
use Phalcon\Db\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for running work in one transaction (a record and its labels).
 */
class TransactionScopeTest extends TestCase
{
    /**
     * @var string[]
     */
    private $calls = [];

    public function testCommitsWhenTheWorkSucceeded()
    {
        $result = $this->scope()->run(function () {
            return 201;
        }, function ($status) {
            return $status === 201;
        });

        $this->assertSame(201, $result);
        $this->assertSame(['begin', 'commit'], $this->calls);
    }

    public function testRollsBackWhenTheWorkFailed()
    {
        $result = $this->scope()->run(function () {
            return 422;
        }, function ($status) {
            return $status < 300;
        });

        $this->assertSame(422, $result);
        $this->assertSame(['begin', 'rollback'], $this->calls);
    }

    public function testAFailedLabelWriteRollsBackTheStatusAndIsRethrown()
    {
        $failure = new \Gaia\Exception\Exception('Could not save the en-us label of status in_progress');

        try {
            $this->scope()->run(function () use ($failure) {
                // The status is saved, then its label write throws (ValueLabelsComponent).
                throw $failure;
            }, function () {
                return true;
            });
            $this->fail('The failure must be rethrown');
        } catch (\Gaia\Exception\Exception $e) {
            $this->assertSame($failure, $e);
        }

        $this->assertSame(['begin', 'rollback'], $this->calls);
    }

    /**
     * @return TransactionScope
     */
    private function scope()
    {
        $connection = $this->createMock(AdapterInterface::class);
        $underTransaction = false;
        $connection->method('begin')->willReturnCallback(function () use (&$underTransaction) {
            $this->calls[] = 'begin';
            $underTransaction = true;
            return true;
        });
        $connection->method('commit')->willReturnCallback(function () use (&$underTransaction) {
            $this->calls[] = 'commit';
            $underTransaction = false;
            return true;
        });
        $connection->method('rollback')->willReturnCallback(function () use (&$underTransaction) {
            $this->calls[] = 'rollback';
            $underTransaction = false;
            return true;
        });
        $connection->method('isUnderTransaction')->willReturnCallback(function () use (&$underTransaction) {
            return $underTransaction;
        });

        return new TransactionScope($connection);
    }
}
