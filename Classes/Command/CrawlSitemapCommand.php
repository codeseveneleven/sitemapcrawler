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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Code711\Sitemapcrawler\Service\LogService;

#[AsCommand(
    name: 'code711:crawler:sitemap',
    description: 'Crawls sitemap for urls',
)]
class CrawlSitemapCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('sitemap', InputArgument::REQUIRED, 'Sitemap URL');
        $this->addArgument('maxurls', InputArgument::OPTIONAL, 'Max Urls', 10000);
    }

    public function __construct(
        private readonly LogService $logService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // not working from scheduler
        $outputFile = $this->getUrlsFilePath();
        $maxUrls = (int)$input->getArgument('maxurls');
        $sitemapUrl = $input->getArgument('sitemap');

        $error = $this->validateUrl($sitemapUrl, $output);
        if ($error) {
            return Command::FAILURE;
        }

        // Extract domain from sitemap URL for filtering
        $parsed = parse_url($sitemapUrl);
        $referenceDomain = $parsed['scheme'] . '://' . $parsed['host'];

        // Test if sitemap is reachable
        $output->writeln('Testing connection to sitemap...');
        $testResult = $this->fetchUrl($sitemapUrl);
        if ($testResult['success'] === false) {
            $output->writeln('Error: Could not fetch sitemap from ' . $sitemapUrl);
            $output->writeln('Reason: ' . $testResult['error']);
            $output->writeln('Please check:');
            $output->writeln('  - Is the sitemap URL correct?');
            $output->writeln('  - Is the sitemap accessible?');
            $output->writeln('  - Do you have internet connection?');
            return Command::FAILURE;
        }
        $output->writeln('Connection successful!');

        // Open file for writing BEFORE parsing
        $csvHandle = fopen($outputFile, 'w');
        if ($csvHandle === false) {
            $output->writeln('Error: Could not create file: ' . $outputFile);
            return Command::FAILURE;
        }

        $output->writeln('Writing URLs to ' . $outputFile);

        // Parse sitemap and write to file in real-time
        $result = $this->getSitemapUrls($sitemapUrl, $referenceDomain, $maxUrls, $csvHandle, $output);

        // Close file
        fclose($csvHandle);

        // Check if any URLs were found
        if ($result['urlCount'] === 0) {
            $output->writeln('Warning: No URLs found!');
            unlink($outputFile); // Delete empty file
            return Command::FAILURE;
        }

        $output->writeln('✓ File successfully created: ' . $outputFile);
        $output->writeln('✓ Total URLs exported: ' . $result['urlCount']);
        $output->writeln('Next steps:');
        $output->writeln('  1. Review the file: ' . $outputFile);
        $output->writeln('  2. Run: vendor/bin/typo3 code711:crawler:index');

        $errors = $this->logService->writeLastRunLog($result['urlCount'], 0);
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

    private function validateUrl(string $url, OutputInterface $output): int
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $output->writeln('Error: Invalid URL format: ' . $url);
            return Command::FAILURE;
        }

        $parsed = parse_url($url);

        if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'])) {
            $output->writeln('Error: URL must use http or https protocol.');
            return Command::FAILURE;
        }

        if (!isset($parsed['host'])) {
            $output->writeln('Error: Invalid URL - no host found.');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function fetchUrl(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (compatible; BackstopJS-Crawler/1.0)\r\n" .
                    "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n" .
                    "Accept-Language: en-US,en;q=0.5\r\n",
                'timeout' => 30,
                'ignore_errors' => true,
                'follow_location' => true,
                'max_redirects' => 5,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $html = @file_get_contents($url, false, $context);

        if ($html === false) {
            return [
                'success' => false,
                'error' => 'Connection failed',
                'content' => null,
            ];
        }

        // Check HTTP response code
        if (isset($http_response_header)) {
            preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
            $statusCode = isset($matches[1]) ? (int)$matches[1] : 0;

            if ($statusCode >= 400) {
                if ($statusCode == 404) {
                    $errorMessage = 'Page not found (404)';
                } elseif ($statusCode == 403) {
                    $errorMessage = 'Access forbidden (403)';
                } elseif ($statusCode == 500) {
                    $errorMessage = 'Server error (500)';
                } elseif ($statusCode < 500) {
                    $errorMessage = 'Client error (' . $statusCode . ')';
                } else {
                    $errorMessage = 'Server error (' . $statusCode . ')';
                }

                return [
                    'success' => false,
                    'error' => $errorMessage,
                    'content' => null,
                ];
            }
        }

        return [
            'success' => true,
            'error' => null,
            'content' => $html,
        ];
    }

    /**
     * Get URLs from sitemap.xml
     */
    public function getSitemapUrls(string $sitemapUrl, string $domain, int $maxUrls, $csvHandle, OutputInterface $output): array
    {
        $visited = [];
        $urlCount = 0;
        $errorLog = [];

        $output->writeln('Starting sitemap parser...');
        $output->writeln('Sitemap URL: ' . $sitemapUrl);
        $output->writeln('Max URLs: ' . $maxUrls);

        // Parse the sitemap (handles both sitemap index and regular sitemaps)
        $this->parseSitemap($sitemapUrl, $domain, $maxUrls, $urlCount, $visited, $errorLog, $csvHandle, $output);

        $output->writeln('Sitemap parsing complete!');
        $output->writeln('Total URLs found: ' . $urlCount);
        $output->writeln('Errors encountered: ' . count($errorLog));

        if (!empty($errorLog)) {
            $output->writeln('-- Errors ---');
            foreach ($errorLog as $error) {
                $output->writeln('  - ' . $error['reason'] . ': ' . $error['url']);
            }
        }

        return [
            'urlCount' => $urlCount,
            'visited' => 0,
            'errors' => $errorLog,
        ];
    }

    /**
     * @throws \DateMalformedStringException
     * @throws Exception
     */
    public function parseSitemap(string $sitemapUrl, string $domain, int $maxUrls, int &$urlCount, array &$visited, array &$errorLog, $csvHandle, OutputInterface $output): array
    {
        // Fetch sitemap content
        $result = $this->fetchUrl($sitemapUrl);

        if ($result['success'] === false) {
            $errorLog[] = [
                'url' => $sitemapUrl,
                'reason' => $result['error'],
            ];
            $output->writeln('[ERROR] Could not fetch sitemap: ' . $result['error']);
            return [];
        }

        $xml = $result['content'];

        // Try to parse XML
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = @$dom->loadXML($xml);

        if (!$loaded) {
            $errorLog[] = [
                'url' => $sitemapUrl,
                'reason' => 'Invalid XML format',
            ];
            $output->writeln('[ERROR]  Invalid XML format in sitemap');
            return [];
        }

        // Check if it's a sitemap index (contains <sitemapindex>)
        $sitemapIndexElements = $dom->getElementsByTagName('sitemapindex');

        if ($sitemapIndexElements->length > 0) {
            $output->writeln('Detected sitemap index, processing sub-sitemaps...');
            return $this->parseSitemapIndex($dom, $domain, $maxUrls, $urlCount, $visited, $errorLog, $csvHandle, $output);
        }

        // It's a regular sitemap, extract URLs
        $output->writeln('Processing sitemap: ' . $sitemapUrl);
        return $this->extractUrlsFromSitemap($dom, $domain, $maxUrls, $urlCount, $visited, $csvHandle, $output);
    }

    /**
     * @throws Exception
     * @throws \DateMalformedStringException
     */
    public function parseSitemapIndex(\DOMDocument $dom, string $domain, int $maxUrls, int &$urlCount, array &$visited, array &$errorLog, $csvHandle, OutputInterface $output): array
    {
        $urls = [];
        $sitemapElements = $dom->getElementsByTagName('sitemap');

        $output->writeln('Found ' . $sitemapElements->length . ' sub-sitemap(s)');

        foreach ($sitemapElements as $sitemapElement) {
            if ($urlCount >= $maxUrls) {
                $output->writeln('Max URLs reached (' . $maxUrls . '), stopping...');
                break;
            }

            $lastmodElements = $sitemapElement->getElementsByTagName('lastmod');
            if ($lastmodElements->length > 0) {
                $lastmoddate = new \DateTime(trim($lastmodElements->item(0)->nodeValue));
                $lastmod = $lastmoddate->getTimestamp();
                $lastlog = $this->logService->getLastRunTime(0);
                if ($lastmod < $lastlog) {
                    $output->writeln('Skipping sitemap because it was last modified before the last run');
                    return [];
                }
            }

            $locElements = $sitemapElement->getElementsByTagName('loc');
            if ($locElements->length > 0) {
                $subSitemapUrl = trim($locElements->item(0)->nodeValue);

                // Recursively parse the sub-sitemap
                $output->writeln('Loading sub-sitemap: ' . $subSitemapUrl);
                $subUrls = $this->parseSitemap($subSitemapUrl, $domain, $maxUrls, $urlCount, $visited, $errorLog, $csvHandle, $output);
                $urls = array_merge($urls, $subUrls);
            }
        }

        return $urls;
    }

    /**
     * Extract URLs from a regular sitemap
     * @throws Exception
     * @throws \DateMalformedStringException
     */
    public function extractUrlsFromSitemap(\DOMDocument $dom, string $domain, int $maxUrls, int &$urlCount, array &$visited, $csvHandle, OutputInterface $output): array
    {
        $urls = [];
        $urlElements = $dom->getElementsByTagName('url');

        $output->writeln('Processing ' . $urlElements->length . ' URLs from sitemap...');

        foreach ($urlElements as $urlElement) {
            if ($urlCount >= $maxUrls) {
                $output->writeln('Max URLs reached (' . $maxUrls . '), stopping...');
                break;
            }

            $lastmodElements = $urlElement->getElementsByTagName('lastmod');
            if ($lastmodElements->length > 0) {
                $lastmoddate = new \DateTime(trim($lastmodElements->item(0)->nodeValue));
                $lastmod = $lastmoddate->getTimestamp();
                $lastlog = $this->logService->getLastRunTime(0);
                if ($lastmod < $lastlog) {
                    $output->writeln('Skipping url because it was last modified before the last run');
                    $output->writeln('Last mod date: ' . $lastmoddate->format('Y-m-d H:i:s'));
                    $output->writeln('Last run date: ' . date('Y-m-d H:i:s', $lastlog));
                    continue;
                }
            }

            $locElements = $urlElement->getElementsByTagName('loc');
            if ($locElements->length > 0) {
                $url = trim($locElements->item(0)->nodeValue);

                // Normalize URL
                $normalizedUrl = $this->normalizeUrl($url);

                if (!$normalizedUrl) {
                    continue;
                }

                // Filter: Check if URL belongs to the domain
                if ($domain && !str_starts_with($normalizedUrl, $domain)) {
                    continue;
                }

                // Filter: Skip file URLs
                if ($this->isFileUrl($normalizedUrl)) {
                    continue;
                }

                // Filter: Skip special protocols
                if ($this->isSpecialProtocol($normalizedUrl)) {
                    continue;
                }

                // Check for duplicates
                if (isset($visited[$normalizedUrl])) {
                    continue;
                }

                $visited[$normalizedUrl] = true;

                // Write to file immediately if handle is provided
                if ($csvHandle !== null) {
                    fwrite($csvHandle, $normalizedUrl . "\n");
                    fflush($csvHandle);
                }

                $urlCount++;
                $urls[] = $normalizedUrl;

                // Progress indicator
                if ($urlCount % 100 == 0) {
                    $output->writeln('URLs processed: ' . $urlCount);
                }
            }
        }

        $output->writeln('URLs processed: ' . $urlCount);

        return $urls;
    }

    public function normalizeUrl(string $url): false|string
    {
        // Remove fragment
        $url = preg_replace('/#.*$/', '', $url);

        // Parse URL
        $parsed = parse_url($url);

        if (!$parsed || !isset($parsed['host'])) {
            return false;
        }

        // Validate scheme
        $scheme = $parsed['scheme'] ?? 'http';
        if (!in_array(strtolower($scheme), ['http', 'https'])) {
            return false;
        }

        $host = $parsed['host'];
        $path = $parsed['path'] ?? '/';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';

        // Validate host (basic check)
        if (empty($host) || strlen($host) < 3) {
            return false;
        }

        // Check for invalid characters in path
        if (preg_match('/[<>"{}|\\\\^`\[\]]/', $path)) {
            return false;
        }

        // Remove trailing slash (except for root)
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        $normalizedUrl = $scheme . '://' . $host . $path . $query;

        // Final validation: must be a valid URL
        if (!filter_var($normalizedUrl, FILTER_VALIDATE_URL)) {
            return false;
        }

        return $normalizedUrl;
    }

    /**
     * Check if URL points to a file
     */
    public function isFileUrl(string $url): false|int
    {
        $extensions = [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp',
            'mp4', 'avi', 'mov', 'wmv', 'flv', 'mp3', 'wav',
            'zip', 'rar', '7z', 'tar', 'gz', 'bz2',
            'exe', 'dmg', 'pkg', 'deb', 'rpm',
            'ico', 'webmanifest', 'xml', 'json', 'css', 'js',
        ];

        $pattern = '/\.(' . implode('|', $extensions) . ')$/i';
        return preg_match($pattern, parse_url($url, PHP_URL_PATH));
    }

    /**
     * Check if URL uses special protocols
     */
    public function isSpecialProtocol(string $url): bool
    {
        $protocols = [
            'tel:',
            'mailto:',
            'javascript:',
            'ftp:',
            'data:',
            'file:',
            'sms:',
            'callto:',
            'skype:',
            'whatsapp:',
        ];

        foreach ($protocols as $protocol) {
            if (stripos($url, $protocol) === 0) {
                return true;
            }
        }

        // Also check for malformed protocols
        if (preg_match('/^[a-z]+:/i', $url) && !preg_match('/^https?:/i', $url)) {
            return true;
        }

        return false;
    }
}
