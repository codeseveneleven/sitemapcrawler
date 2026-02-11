<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project.
 * (c) 2025 12bis3
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 * The TYPO3 project - inspiring people to share!
 *
 * @copyright 2025 12bis3
 */

namespace Code711\Sitemapcrawler\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Code711\Sitemapcrawler\Service\LogService;

#[AsCommand(
    name: 'code711:crawler:index',
    description: 'Update search index',
)]
class UpdateIndexCommand extends Command
{
    public function __construct(
        private readonly LogService $logService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Path to the URLs file
        $urlsFile = $this->getUrlsFilePath();

        // Check if URLs file exists
        if (!file_exists($urlsFile)) {
            $output->writeln('Nothing to do here for now');
            return Command::SUCCESS;
        }

        // Import URLs file
        $urls = file($urlsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (empty($urls)) {
            $output->writeln('Nothing to do here for now');
            return Command::SUCCESS;
        }

        foreach ($urls as $url) {
            $content = file_get_contents($url);
            if (!$content) {
                $output->writeln('Error: Content could not be retrieved for URL: ' . $url);
            }
            $output->writeln('Success: Index for URL updated: ' . $url);
        }

        $errors = $this->logService->writeLastRunLog(count($urls), 1);
        foreach ($errors as $error) {
            $output->writeln($error);
        }

        return Command::SUCCESS;
    }

    private function getUrlsFilePath(): string
    {
        $transientPath = Environment::getVarPath() . '/transient';
        if (!is_dir($transientPath)) {
            GeneralUtility::mkdir_deep($transientPath);
        }
        return $transientPath . '/crawled_urls.txt';
    }
}
