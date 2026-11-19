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
     * Exit code of a dry run that plans changes (0: nothing planned, 1: failure)
     */
    const EXIT_CHANGES_PLANNED = 2;

    /**
     * Default action - shows help
     */
    public function mainAction()
    {
        echo "Migration Task - Available actions:" . PHP_EOL;
        echo "  migrate           - Run all pending migrations" . PHP_EOL;
        echo "  migrate --dry-run - Print the statements migrate would run, and run none" . PHP_EOL;
        echo "                      (exit 0: nothing planned, " . self::EXIT_CHANGES_PLANNED . ": changes planned, 1: failure)" . PHP_EOL;
        echo "  status            - Show migration status" . PHP_EOL;
    }

    /**
     * Run all pending migrations, or with --dry-run print what would run
     */
    public function migrateAction()
    {
        if (in_array('--dry-run', $this->dispatcher->getParams(), true)) {
            $exitCode = $this->dryRun();
            if ($exitCode !== 0) {
                exit($exitCode);
            }
            return;
        }

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
     * Prints the planned statements, one per line with their table, and returns the
     * exit code: 0 when nothing is planned, EXIT_CHANGES_PLANNED when a statement
     * changes the schema, 1 when planning fails
     *
     * @return int
     */
    private function dryRun()
    {
        try {
            $plan = $this->di->get('migrationDriver')->plan();
        } catch (\Exception $e) {
            echo "Migration dry run failed: " . $e->getMessage() . PHP_EOL;
            return 1;
        }

        $changes = $plan->changes();
        foreach ($changes as $statement) {
            echo $statement['table'] . "\t" . $this->oneLine($statement['sql']) . PHP_EOL;
        }

        $rebuilt = array_unique(array_column($plan->routine(), 'table'));
        if (!empty($rebuilt)) {
            echo count($rebuilt) . " existing view(s) are rebuilt from metadata on every run (not counted): "
                . implode(', ', $rebuilt) . PHP_EOL;
        }

        if (!$plan->hasChanges()) {
            echo "Dry run: nothing planned." . PHP_EOL;
            return 0;
        }

        echo "Dry run: " . count($changes) . " statement(s) planned, none run." . PHP_EOL;
        return self::EXIT_CHANGES_PLANNED;
    }

    /**
     * Collapses a statement's whitespace so it prints on one line
     *
     * @param string $sql
     * @return string
     */
    private function oneLine($sql)
    {
        return trim(preg_replace('/\s+/', ' ', $sql));
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
}
