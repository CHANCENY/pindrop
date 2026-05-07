<?php

namespace Simp\Pindrop\System;


use Simp\Pindrop\Database\DatabaseService;

class System
{
    private string $gitBinary = "git";
    private string $version = "unknown";

    private string $curlBinary = "curl";

    private string $unzipBinary = "unzip";

    private string $tarBinary = "tar";

    public function __construct(protected DatabaseService $databaseService)
    {
        $git = exec('which git');
        if (str_ends_with($git, "git")) {
            $this->gitBinary = $git;
        }

        $curl = exec('which curl');
        if (str_ends_with($curl, "curl")) {
            $this->curlBinary = $curl;
        }

        $unzip = exec('which unzip');
        if (str_ends_with($unzip, "unzip")) {
            $this->unzipBinary = $unzip;
        }

        $tar = exec('which tar');
        if (str_ends_with($tar, "tar")) {
            $this->tarBinary = $tar;
        }

        $select = "SELECT version FROM system_information LIMIT 1";
        $result = $this->databaseService->query($select)?->fetch();
        if ($result) {
            $this->version = $result['version'];
        }
    }

    public function checkLatestVersion()
    {

        // get release information from github
        $link = "$this->gitBinary ls-remote --tags https://github.com/CHANCENY/pindrop.git";
        $output = exec($link);
        preg_match_all('/refs\/tags\/(v?[0-9]+\.[0-9]+\.[0-9]+)/', $output, $matches);
        $version = $matches[1][0] ?? "unknown";
        return $version;
    }

    public function checkSystemVersion()
    {
        return $this->version;
    }

    public function downloadVersion(string $version)
    {
        $version = trim($version);
        // download the specified version from github
        $downloadDirectory = $_ENV["ROOT"] . "/storage/downloads/pindrop-$version";
        if (!is_dir($downloadDirectory)) {
            mkdir($downloadDirectory, 0777, true);
        }

        $zipFile = "$downloadDirectory/pindrop-$version.zip";
        if (file_exists($zipFile)) {
            return true;
        }

        $link = "$this->curlBinary -L -o $downloadDirectory/pindrop-$version.zip https://github.com/CHANCENY/pindrop/archive/refs/tags/$version.zip";
        exec($link);

        if (file_exists("$downloadDirectory/pindrop-$version.zip")) {
            return true;
        }
        return false;
    }

    public function updateSystem(string $version)
    {
        $version = trim($version);
        // update the system using the downloaded version
        $downloadDirectory = $_ENV["ROOT"] . "/storage/downloads/pindrop-$version";
        $destinationDirectory = $_ENV["ROOT"] . "/storage/downloads/pindrop-$version/unpack";
        $zipFile = "$downloadDirectory/pindrop-$version.zip";

        if (file_exists($zipFile)) {
            @mkdir($destinationDirectory, 0777, true);

            $command = "$this->unzipBinary $zipFile -d $destinationDirectory";
            exec($command);

            sleep(3); // Wait for the unzip process to complete

            if (is_dir($destinationDirectory)) {
                // Here you can add code to replace the existing system files with the new ones from the
                $pindrop_directory = $_ENV["ROOT"] . "/pindrop";

                // make backup
                $backupDirectory = $_ENV['ROOT'] . "/backups/" . date('Y-m-d-H-i-s');
                if (!is_dir($backupDirectory)) {
                    mkdir($backupDirectory, 0777, true);
                }

                $backupFile = "$backupDirectory/pindrop.tar.gz";
                $backupCommand = "$this->tarBinary -czvf $backupFile $pindrop_directory";
                exec($backupCommand);
                exec("$this->tarBinary -czvf $backupDirectory/themes.tar.gz {$_ENV['ROOT']}/themes");
                exec("$this->tarBinary -czvf $backupDirectory/modules.tar.gz {$_ENV['ROOT']}/modules");
                exec("$this->tarBinary -czvf $backupDirectory/configs.tar.gz {$_ENV['ROOT']}/configs");
                exec("$this->tarBinary -czvf $backupDirectory/cli.tar.gz {$_ENV['ROOT']}/cli");
                exec("$this->tarBinary -czvf $backupDirectory/docs.tar.gz {$_ENV['ROOT']}/docs");

                $list = array_diff(scandir($destinationDirectory) ?? [], ['.', '..']);
                $new_pindrop_directory = "";
                foreach ($list as $file) {
                    $new_pindrop_directory = "$destinationDirectory/$file";
                    if (is_dir($new_pindrop_directory) && str_contains($new_pindrop_directory, $version)) {
                        break;
                    }
                }

                if (!empty($new_pindrop_directory)) {

                    // remove old pindrop directory
                    exec("rm -rf $pindrop_directory");

                    // move new pindrop directory to root
                    exec("mv $new_pindrop_directory $pindrop_directory");

                    // remove themes root
                    exec("rm -rf {$_ENV['ROOT']}/themes");

                    // move new themes directory to root
                    exec("mv $pindrop_directory/themes {$_ENV['ROOT']}/themes");

                    // remove modules root
                    exec("rm -rf {$_ENV['ROOT']}/modules");

                    // move new modules directory to root
                    exec("mv $pindrop_directory/modules {$_ENV['ROOT']}/modules");

                    // remove configs root
                    exec("rm -rf {$_ENV['ROOT']}/configs");

                    // move new configs directory to root
                    exec("mv $pindrop_directory/configs {$_ENV['ROOT']}/configs");

                    // remove cli root
                    exec("rm -rf {$_ENV['ROOT']}/cli");

                    // move new cli directory to root
                    exec("mv $pindrop_directory/cli {$_ENV['ROOT']}/cli");

                    // remove docs root
                    exec("rm -rf {$_ENV['ROOT']}/docs");

                    // move new docs directory to root
                    exec("mv $pindrop_directory/docs {$_ENV['ROOT']}/docs");

                }
            }

            sleep(5); // Wait for the file operations to complete
            // remove destinationDirectory
            exec("rm -rf $destinationDirectory");
            return true;
        }
        return false;
    }

    public function changeVersion(string $version)
    {
        $version = trim($version);
        $updateQuery = "UPDATE system_information SET version = '$version'";
        $this->databaseService->query($updateQuery)->rowCount();
    }

}
