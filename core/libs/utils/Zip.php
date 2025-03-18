<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Utils;

/**
 * This class provides utility functions for working with zip archives.
 *
 * @class Zip
 * @package Gaia\Libraries\Utils
 * @author Rana Nouman <ranamnouman@gmail.com>
 */
class Zip
{
    /**
     * Creates a zip archive containing the provided files and returns the path to the created zip file.
     *
     * @param array  $files   An array of file paths to the files to be included in the zip archive.
     * @param string $moduleName The name of the module, used to generate the zip file name.
     * @param bool   $deleteFiles Whether to delete the files after zipping.
     *
     * @return string The file path to the created zip archive.
     * @throws \Gaia\Exception\Exception If the zip archive could not be created.
     */
    public static function createZipArchive($files, $moduleName, $deleteFiles = false)
    {
        global $currentUser;
        $userId = $currentUser->id;
        $tempDir = APP_PATH . DS . 'filesystem' . DS . 'temp' . DS . $userId . DS . 'zips';

        // Create the directory if it doesn't exist
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0700, true);
        }

        $modulePluralizedName = strtolower($moduleName) . 's';
        $zipFileName = 'export'. '-' . $modulePluralizedName . '-' . date('Y-m-d H-i-s') . '.zip';
        $zipPath = $tempDir . DS . $zipFileName;
        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE) === true) {
            foreach ($files as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();
        } else {
            throw new \Gaia\Exception\Exception('Failed to create zip archive');
        }

        // Remove files after zipping
        foreach ($files as $file) {
            unlink($file);
        }

        return [$zipPath, $zipFileName];
    }
}
