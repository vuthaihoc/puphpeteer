<?php

namespace Nesk\Puphpeteer;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use Nesk\Rialto\AbstractEntryPoint;
use vierbergenlars\SemVer\{version, expression, SemVerException};

class Puppeteer extends AbstractEntryPoint
{
    /**
     * Instanciate Puppeteer's entry point.
     */
    public function __construct(array $userOptions = [])
    {
        if (!empty($userOptions['logger']) && $userOptions['logger'] instanceof LoggerInterface) {
            $this->checkPuppeteerVersion($userOptions['executable_path'] ?? 'node', $userOptions['logger']);
        }

        parent::__construct(
            __DIR__.'/PuppeteerConnectionDelegate.js',
            new PuppeteerProcessDelegate,
            ['read_timeout' => 35],
            $userOptions
        );
    }

    private function checkPuppeteerVersion(string $nodePath, LoggerInterface $logger): void {
        $currentVersion = $this->currentPuppeteerVersion($nodePath);
        $acceptedVersions = $this->acceptedPuppeteerVersion();

        try {
            $semver = new version($currentVersion);
            $expression = new expression($acceptedVersions);

            if (!$semver->satisfies($expression)) {
                $logger->warning(
                    "The installed version of Puppeteer (v$currentVersion) doesn't match the requirements"
                    ." ($acceptedVersions), you may encounter issues."
                );
            }
        } catch (SemVerException $exception) {
            $logger->warning("Puppeteer doesn't seem to be installed.");
        }
    }

    private function currentPuppeteerVersion(string $nodePath): ?string {
        $process = new Process([$nodePath, __DIR__.'/get-puppeteer-version.js']);
        $process->mustRun();

        return json_decode($process->getOutput());
    }

    private function acceptedPuppeteerVersion(): string {
        $npmManifestPath = __DIR__.'/../package.json';
        $npmManifest = json_decode(file_get_contents($npmManifestPath));

        return $npmManifest->dependencies->puppeteer;
    }
}
