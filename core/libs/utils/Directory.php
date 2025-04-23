<?php

namespace Gaia\Libraries\Utils;

/**
 * This class provides utility functions for directory operations.
 *
 * @class DirectoryUtils
 * @package Gaia\Libraries\Utils
 * @author Rana Nouman <ranamnouman@gmail.com>
 */
class Directory
{
    /**
     * Creates a directory if it does not exist.
     *
     * @param string $path The path of the directory to create.
     * @param int $permissions The permissions to set for the directory.
     *
     * @return bool Returns true if the directory was created or already exists, false on failure.
     */
    public static function createDirectoryIfNotExists($path, $permissions = 0700)
    {
        global $logger;

        if (!is_dir($path)) {
            try {
                if (!mkdir($path, $permissions, true)) {
                    throw new \Exception("Failed to create directory: $path");
                }
            } catch (\Exception $e) {
                $logger->error($e->getMessage());
                throw new \Gaia\Exception\Exception("Internal Server Error");
            }
        }
        return true;
    }
}
