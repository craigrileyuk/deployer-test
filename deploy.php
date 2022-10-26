<?php


require __DIR__ . '/vendor/autoload.php';

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;

class AUIDeploy
{
    protected string $php;
    protected string $composer;
    protected Filesystem $filesystem;

    public function __construct()
    {
        $phpBinaryFinder = new PhpExecutableFinder();
        $this->php = $phpBinaryFinder->find();

        $executableFinder = new ExecutableFinder();
        $this->composer = $executableFinder->find('composer');
        $this->git = $executableFinder->find('git');

        $this->root = __DIR__;
        $this->filesystem = new Filesystem;

        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $this->env = $dotenv->safeLoad();

        if (empty($this->php)) {
            throw new \Exception("Unable to find PHP binary");
        }

        if (empty($this->composer)) {
            throw new \Exception("Unable to find Composer binary");
        }
    }

    public function pull()
    {
        $this->runCommand([$this->git, "pull"]);
    }

    public function composerUpdate()
    {
        $this->runCommand([$this->php, $this->composer, "update", "--no-interaction"]);
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
            $this->filesystem->deleteDirectory($extractPath);
            $archive->extractTo($extractPath);
            $archive->close();
        } else {
            throw new \Exception("Unable to extract assets package");
        }
    }

    public function installAssets()
    {
        $source = $this->root . "/compiled-assets";
        $target = $this->env['BUILD_DIR'] ?? 'public/build';
        $target = $this->root . "/" . $target;

        $this->filesystem->moveDirectory($source, $target, true);
    }

    public function runMigrations()
    {
        $this->runCommand([$this->php, 'artisan', "migrate"]);
    }

    public function clearCache()
    {
        $this->runCommand([$this->php, 'artisan', "optimize:clear"]);
    }

    public function optimise()
    {
        $this->runCommand([$this->php, 'artisan', "optimize"]);
    }

    public function link()
    {
        $this->runCommand([$this->php, 'artisan', "storage:link", '--force']);
    }

    private function runCommand($input)
    {
        $process = new Process($input);
        $process->setTimeout(300);
        $process->setWorkingDirectory($this->root);
        $process->run();

        if ($process->isSuccessful()) {
            echo $process->getOutput();
        } else {
            $output = !empty($process->getErrorOutput()) ? $process->getErrorOutput() : $process->getOutput();
            echo $output;
            die();
        }
    }

    public function down()
    {
        $this->runCommand([$this->php, 'artisan', 'down']);
    }
    public function up()
    {
        $this->runCommand([$this->php, 'artisan', 'up']);
    }
}

$deploy = new AUIDeploy();

$deploy->pull();
$deploy->composerUpdate();
$deploy->extractAssets();
$deploy->down();
$deploy->installAssets();
$deploy->runMigrations();
$deploy->clearCache();
$deploy->optimise();
$deploy->link();
$deploy->up();
