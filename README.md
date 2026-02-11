# Sitemap Crawler

The extension reads a sitemap (including sitemap index), writes the discovered URLs to a file, and then triggers re-indexing via CLI by fetching the URLs. Each run is logged.

## Installation
`composer require code711/sitemapcrawler`

## Crawl sitemap
A URL list is created in `var/transient/crawled_urls.txt`
The date of the last run from the log is compared with the sitemap date. Only if there are **newer changes** are those pages included.

`vendor/bin/typo3 code711:crawler:sitemap <sitemap-url> [maxurls]`

## Update index
URLs from crawled_urls.txt are requested.

`vendor/bin/typo3 code711:crawler:index`

## Clean up logs
Run data is stored in `tx_sitemapcrawler_domain_model_log`. This command should be used to clean up logs when the crawler runs regularly.

`vendor/bin/typo3 code711:crawler:cleanup --keep=3 --mode=0`

`mode=0` for the crawl run
`mode=1` for the index run
Fields: `lastrun`, `count`, `title`

If you want to rerun the index completely, you can simply delete all logs.

`vendor/bin/typo3 code711:crawler:cleanup`
