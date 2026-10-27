<?php

use Phalcon\Cli\Task;

/**
 * Migration Task
 * 
 * This task handles database migrations for the Gaia application
 * 
 * @package Gaia\Core\Tasks
 */
class MigrationTask extends Task
{
    /**
     * Default action - shows help
     */
    public function mainAction()
    {
        echo "Migration Task - Available actions:" . PHP_EOL;
        echo "  migrate - Run all pending migrations" . PHP_EOL;
        echo "  status  - Show migration status" . PHP_EOL;
        echo "  issuetypes - Turn issue type names into values with en-us labels" . PHP_EOL;
    }

    /**
     * Run all pending migrations
     */
    public function migrateAction()
    {
        try {
            echo "Starting database migration..." . PHP_EOL;
            
            // Get the migration driver from DI
            $migrationDriver = $this->di->get('migrationDriver');
            
            // Run the migration
            $migrationDriver->migrate();
            
            echo "Database migration completed successfully!" . PHP_EOL;
        } catch (\Exception $e) {
            echo "Migration failed: " . $e->getMessage() . PHP_EOL;
            exit(1);
        }
    }

    /**
     * Show migration status
     */
    public function statusAction()
    {
        try {
            echo "Checking migration status..." . PHP_EOL;
            
            // Get the migration driver from DI
            $migrationDriver = $this->di->get('migrationDriver');
            
            // For now, just check if the migration driver is available
            echo "Migration driver is available and ready." . PHP_EOL;
        } catch (\Exception $e) {
            echo "Error checking migration status: " . $e->getMessage() . PHP_EOL;
            exit(1);
        }
    }

    /**
     * Turn issue type names into values with an en-us label each
     * (IssueTypeValueMigration). Safe to run more than once.
     */
    public function issuetypesAction()
    {
        try {
            $db = $this->di->get('db');
            $migration = new \Gaia\Libraries\Utils\IssueTypeValueMigration(
                $db,
                \Gaia\Libraries\Utils\ValueLabels::forIssueTypes(),
                \Gaia\Libraries\Utils\TranslationCache::fromConfig(),
                \Gaia\Libraries\Utils\TranslationLocales::fromConfig()->all(),
                $this->systemAdmin($db)
            );
            $plan = $migration->run();

            echo 'Renamed ' . count($plan->renames()) . ' issue type(s), added '
                . count($plan->labels()) . ' en-us label(s).' . PHP_EOL;
            foreach ($plan->skipped() as $id) {
                echo "Skipped issue type {$id}: its name has no letters, digits or spaces to keep." . PHP_EOL;
            }
        } catch (\Throwable $e) {
            echo "Issue type migration failed: " . $e->getMessage() . PHP_EOL;
            exit(1);
        }
    }

    /**
     * The system admin (user id 1, as in db/seeds/translations.sql) that
     * migration-written rows are recorded as.
     *
     * @return array id and name
     */
    private function systemAdmin($db)
    {
        $id = '1';
        $row = $db->fetchOne(
            'SELECT name FROM users WHERE id = :id',
            \Phalcon\Db\Enum::FETCH_ASSOC,
            ['id' => $id]
        );
        if (!$row) {
            throw new \RuntimeException("System admin user {$id} not found.");
        }

        return ['id' => $id, 'name' => $row['name']];
    }
}
