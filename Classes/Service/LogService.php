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

namespace Code711\Sitemapcrawler\Service;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;

readonly class LogService
{
    public function __construct(
        private ConnectionPool $connectionPool,
        private DataHandler $dataHandler
    ) {}

    public function writeLastRunLog(int $urlCount, int $mode): array
    {
        Bootstrap::initializeBackendAuthentication();

        $data['tx_sitemapcrawler_domain_model_log']['NEW_1'] = [
            'pid' => 1,
            'title' => 'Last crawler run at ' . date('Y-m-d H:i:s', time()) . ' - ' . $urlCount,
            'lastrun' => time(),
            'mode' => $mode,
            'count' => $urlCount,
        ];

        $this->dataHandler->start($data, []);
        $this->dataHandler->process_datamap();

        return $this->dataHandler->errorLog;
    }

    /**
     * @throws Exception
     */
    public function getLastRunLog(int $mode): array|false
    {
        return $this->connectionPool
            ->getConnectionForTable('tx_sitemapcrawler_domain_model_log')
            ->select(
                ['lastrun'],
                'tx_sitemapcrawler_domain_model_log',
                ['mode' => $mode],
                [],
                ['lastrun' => 'DESC'],
            )
            ->fetchAssociative();
    }

    /**
     * @throws Exception
     */
    public function getLastRunTime(int $mode): int
    {
        $lastRunLog = $this->getLastRunLog($mode);
        if (!$lastRunLog) {
            return 0;
        }
        return (int)$lastRunLog['lastrun'] ?? 0;
    }

    /**
     * @throws Exception
     */
    public function cleanupLog(int $keep, int $mode): int
    {
        $queryBuilder = $this->connectionPool
            ->getConnectionForTable('tx_sitemapcrawler_domain_model_log')
            ->createQueryBuilder();

        $uids = $queryBuilder
            ->select('uid')
            ->from('tx_sitemapcrawler_domain_model_log')
            ->where(
                $queryBuilder->expr()->eq('mode', $mode)
            )
            ->setFirstResult($keep)
            ->orderBy('lastrun', 'DESC')
            ->executeQuery()
            ->fetchFirstColumn();

        if (!$uids) {
            return 0;
        }

        $queryBuilder = $this->connectionPool
            ->getConnectionForTable('tx_sitemapcrawler_domain_model_log')
            ->createQueryBuilder();

        return $queryBuilder
            ->delete('tx_sitemapcrawler_domain_model_log')
            ->where($queryBuilder->expr()->in('uid', $uids))
            ->executeStatement();
    }
}
