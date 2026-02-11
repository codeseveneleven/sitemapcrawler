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

use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Code711\Sitemapcrawler\Service\LogService;

#[AsCommand(
    name: 'code711:crawler:cleanup',
    description: 'Remove old logs',
)]
class CleanupLogsCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('keep', 'k', InputOption::VALUE_OPTIONAL, 'Amount to keep logs', 3);
        $this->addOption('mode', 'm', InputOption::VALUE_OPTIONAL, 'Mode', 0);
    }

    public function __construct(
        private readonly LogService $logService
    ) {
        parent::__construct();
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = $this->logService->cleanupLog((int)$input->getOption('keep'), (int)$input->getOption('mode'));
        $output->writeln('Deleted ' . $rows . ' old log entries.');

        return Command::SUCCESS;
    }
}
