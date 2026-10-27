<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Utils;

use Phalcon\Db\Adapter\AdapterInterface;
use Phalcon\Db\Enum;

/**
 * Turns issue type names into values with an en-us label (IssueTypeValuePlan)
 * in one transaction, then drops the cached translation files.
 *
 * It writes through the connection rather than the models: the models' user
 * behaviours need a signed-in user, which the CLI does not have. Nothing is
 * deleted, and running it again changes nothing (see IssueTypeValuePlan).
 * Run it with `php cli.php migration issuetypes`.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Utils
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class IssueTypeValueMigration
{
    /**
     * @var AdapterInterface
     */
    private $db;

    /**
     * @var ValueLabels
     */
    private $labels;

    /**
     * @var TranslationCache
     */
    private $cache;

    /**
     * @var string[]
     */
    private $locales;

    /**
     * The user the label rows are written as: id and name.
     *
     * @var array
     */
    private $author;

    /**
     * @param AdapterInterface $db      The shared `db` connection.
     * @param ValueLabels      $labels  The issue type labels (for their paths).
     * @param TranslationCache $cache
     * @param string[]         $locales Locales whose cached file is dropped.
     * @param array            $author  The user the label rows are written as: id and name.
     */
    public function __construct(AdapterInterface $db, ValueLabels $labels, TranslationCache $cache, array $locales, array $author)
    {
        $this->author = $author;
        $this->db = $db;
        $this->labels = $labels;
        $this->cache = $cache;
        $this->locales = $locales;
    }

    /**
     * @return IssueTypeValuePlan What was changed.
     * @throws \Throwable When a write fails, after rolling back.
     */
    public function run()
    {
        $plan = new IssueTypeValuePlan($this->types(), $this->labelledValues());

        (new TransactionScope($this->db))->run(function () use ($plan) {
            foreach ($plan->renames() as $id => $value) {
                $this->db->execute(
                    'UPDATE issue_types SET name = :name, "dateModified" = NOW() WHERE id = :id',
                    ['name' => $value, 'id' => $id]
                );
            }
            foreach ($plan->labels() as $value => $label) {
                $this->insertLabel($value, $label);
            }
            return true;
        }, function ($done) {
            return $done === true;
        });

        foreach ($this->locales as $locale) {
            $this->cache->forget($locale);
        }

        return $plan;
    }

    /**
     * @return array Rows with id, name, system and deleted, of every type.
     */
    private function types()
    {
        return $this->db->fetchAll(
            'SELECT id, name, system, deleted FROM issue_types ORDER BY "dateCreated", id',
            Enum::FETCH_ASSOC
        );
    }

    /**
     * @return string[] Type values that already have a live en-us label.
     */
    private function labelledValues()
    {
        $prefix = $this->labels->pathFor('');
        $rows = $this->db->fetchAll(
            'SELECT path FROM translations WHERE locale = :locale AND deleted = FALSE AND path LIKE :prefix',
            Enum::FETCH_ASSOC,
            ['locale' => ValueLabelRules::REQUIRED_LOCALE, 'prefix' => addcslashes($prefix, '%_\\') . '%']
        );

        return array_map(function ($row) use ($prefix) {
            return substr($row['path'], strlen($prefix));
        }, $rows);
    }

    /**
     * Write the en-us label of a value, restoring a deleted row for the same
     * path since (locale, path) is unique.
     *
     * @param  string $value
     * @param  string $label
     * @return void
     */
    private function insertLabel($value, $label)
    {
        $this->db->execute(
            'INSERT INTO translations (id, locale, path, value, "dateCreated", "dateModified", deleted,
                 "createdUser", "createdUserName", "modifiedUser", "modifiedUserName")
             VALUES (:id, :locale, :path, :value, NOW(), NOW(), FALSE, :createdUser, :createdUserName, :modifiedUser, :modifiedUserName)
             ON CONFLICT (locale, path) DO UPDATE
                 SET value = EXCLUDED.value, deleted = FALSE, "dateModified" = NOW()
                 WHERE translations.deleted = TRUE',
            [
                'id' => create_guid(),
                'locale' => ValueLabelRules::REQUIRED_LOCALE,
                'path' => $this->labels->pathFor($value),
                'value' => $label,
                'createdUser' => (string) $this->author['id'],
                'createdUserName' => (string) $this->author['name'],
                'modifiedUser' => (string) $this->author['id'],
                'modifiedUserName' => (string) $this->author['name'],
            ]
        );
    }
}
