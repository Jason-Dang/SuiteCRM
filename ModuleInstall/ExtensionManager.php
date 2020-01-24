<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

/**
 * Class ExtensionManager
 */
class ExtensionManager
{
    /**
     * @var array
     */
    protected static $moduleList = [];

    /**
     * @var
     */
    protected static $logger;

    /**
     * Sets the static module and extension lists.
     *
     * @return void
     */
    protected static function initialise()
    {
        static::$moduleList = get_module_dir_list();
        static::$logger = LoggerManager::getLogger();
    }

    /**
     * Compiles source files for given extension to targeted file applying any given filters.
     *
     * @param string $extension Name of Extension i.e. 'Language'
     * @param string $targetFileName Name of target file
     * @param string $filter To filter file names such as language prefixes
     * @param bool $applicationOnly Whether or not to only compile application extensions
     * @return void
     */
    public static function compileExtensionFiles(
        $extension,
        $targetFileName,
        $filter = '',
        $applicationOnly = false
    ) {
        static::initialise();

        if ($extension === 'Language' && strpos($targetFileName, $filter) !== 0) {
            $targetFileName = $filter . $targetFileName;
        }

        if (!$applicationOnly) {
            static::compileModuleExtensions($extension, $targetFileName, $filter);
        }

        static::compileApplicationExtensions($extension, $targetFileName, $filter);
    }

    /**
     * @param $extension
     * @param $targetFileName
     * @param string $filter
     * @return void
     */
    public static function compileApplicationExtensions(
        $extension,
        $targetFileName,
        $filter = ''
    ) {
        static::$logger->{'debug'}("Merging application files for $targetFileName in $extension");

        $extensionContents = '<?php' . PHP_EOL . '// WARNING: The contents of this file are auto-generated' . PHP_EOL;
        $extPath = "application/Ext/$extension";
        $moduleInstall  = "custom/Extension/$extPath";
        $shouldSave = false;

        if (is_dir($moduleInstall)) {
            $dir = dir($moduleInstall);

            while ($entry = $dir->read()) {
                $shouldSave = true;
                if ($entry === '.' || $entry === '..' || strtolower(substr($entry, -4)) !== '.php') {
                    continue;
                }

                if (!is_file("$moduleInstall/$entry")) {
                    continue;
                }

                if (!empty($filter) && substr_count($entry, $filter) <= 0) {
                    continue;
                }

                $file = file_get_contents("$moduleInstall/$entry");
                $extensionContents .= PHP_EOL . static::removePhpTagsFromString($file);
            }
        }

        $extensionContents .= "\n?>";

        if ($shouldSave) {
            if (!file_exists("custom/$extPath")) {
                mkdir_recursive("custom/$extPath", true);
            }

            $out = sugar_fopen("custom/$extPath/$targetFileName", 'wb');
            fwrite($out, $extensionContents);
            fclose($out);

            return;
        }

        if (file_exists("custom/$extPath/$targetFileName")) {
            unlink("custom/$extPath/$targetFileName");
        }
    }

    /**
     * @param $extension
     * @param $targetFileName
     * @param string $filter
     * @return void
     */
    public static function compileModuleExtensions(
        $extension,
        $targetFileName,
        $filter = ''
    ) {
        $moduleExtPath = "custom/Extension/modules/<module>/$extension to custom/modules/<module>/$extension$targetFileName";
        static::$logger->{'debug'}(self::class . "::compileExtensionFiles() : Merging module files in $moduleExtPath");

        foreach (static::$moduleList as $module) {
            $extensionContents = '<?php' . PHP_EOL . '// WARNING: The contents of this file are auto-generated' . PHP_EOL;
            $extPath = "modules/$module/Ext/$extension";
            $moduleInstall  = "custom/Extension/$extPath";
            $shouldSave = false;

            if (is_dir($moduleInstall)) {
                $dir = dir($moduleInstall);
                $shouldSave = true;
                $override = [];

                while ($entry = $dir->read()) {
                    if ($entry === '.' || $entry === '..' || strtolower(substr($entry, -4)) !== '.php') {
                        continue;
                    }

                    if (!is_file("$moduleInstall/$entry")) {
                        continue;
                    }

                    if (!empty($filter) && substr_count($entry, $filter) <= 0) {
                        continue;
                    }

                    if (strpos($entry, '_override') === 0) {
                        $override[] = $entry;
                        continue;
                    }

                    $file = file_get_contents("$moduleInstall/$entry");
                    static::$logger->{'debug'}(self::class . "->compileExtensionFiles(): found {$moduleInstall}{$entry}") ;
                    $extensionContents .= PHP_EOL . static::removePhpTagsFromString($file);
                }

                foreach ($override as $entry) {
                    $file = file_get_contents("$moduleInstall/$entry");
                    $extensionContents .= PHP_EOL . static::removePhpTagsFromString($file);
                }
            }

            $extensionContents .= PHP_EOL . '?>';

            if ($shouldSave) {
                if (!file_exists("custom/$extPath")) {
                    mkdir_recursive("custom/$extPath", true);
                }

                $out = sugar_fopen("custom/$extPath/$targetFileName", 'wb');
                fwrite($out, $extensionContents);
                fclose($out);

                continue;
            }

            if (file_exists("custom/$extPath/$targetFileName")) {
                unlink("custom/$extPath/$targetFileName");
            }
        }
    }

    /**
     * @param string $string
     * @return string
     */
    public static function removePhpTagsFromString($string)
    {
        return str_replace(
            ['<?php', '?>', '<?PHP', '<?'],
            '',
            $string
        );
    }
}
