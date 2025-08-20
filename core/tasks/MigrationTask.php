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
}
