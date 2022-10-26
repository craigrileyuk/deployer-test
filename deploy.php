<?php


require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\PhpExecutableFinder;

class AUIDeploy
{
    protected string $php;
    protected string $composer;

    public function __construct()
    {
        $phpBinaryFinder = new PhpExecutableFinder();
        $this->php = $phpBinaryFinder->find();

        $executableFinder = new ExecutableFinder();
        $this->composer = $executableFinder->find('composer');
        $this->git = $executableFinder->find('git');

        $this->root = __DIR__;


        if (empty($this->php)) {
            throw new \Exception("Unable to find PHP binary");
        }

        if (empty($this->composer)) {
            throw new \Exception("Unable to find Composer binary");
        }
    }

    public function pull()
    {
        $process = new Process([$this->git, "pull"]);
        $process->setTimeout(300);
        $process->setWorkingDirectory($this->root);
        $process->run();

        if ($process->isSuccessful()) {
            return $process->getOutput();
        } else {
            echo "ERROR: " . $process->getErrorOutput();
            die();
        }
    }

    public function composerUpdate()
    {
        $process = new Process([$this->php, $this->composer, "update", "--no-interaction"]);
        $process->setTimeout(300);
        $process->setWorkingDirectory($this->root);
        $process->run();

        if ($process->isSuccessful()) {
            return $process->getOutput();
        } else {
            throw new \Exception("Composer error:" . $process->getErrorOutput());
        }
    }

    public function extractAssets()
    {
        $zipPath = $this->root . "/assets.zip";
        $extractPath = $this->root . "/compiled-assets";

        if (!file_exists($zipPath)) {
            echo "ERROR: Couldn't find assets installer";
            die();
        }

        $archive = new ZipArchive;
        if ($archive->open($zipPath) === true) {
            /* $archive->extractTo($extractPath);
            $archive->close(); */
        } else {
            throw new \Exception("Unable to extract assets package");
        }
    }

    public function runMigrations()
    {
        Artisan::call('migrate');
    }
}

$deploy = new AUIDeploy();

$deploy->pull();
$deploy->composerUpdate();
$deploy->extractAssets();
$deploy->runMigrations();
