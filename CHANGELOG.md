## 0.0.1 (February 15, 2014)

NOTES:
    This is the initial release of TYPO3-Analytics (v0.0.1).
    It starts with 13 different RabbitMQ consumer and 4 RabbitMQ producer written in PHP (the NNTP consumer and producer are not production ready yet):

    - Consumer
        - Download a git resource
        - Download a http resource
        - Extract a tar.gz archive
        - Crawl a Gerrit server
        - Crawl a single Gerrit project
        - Crawl a Gitweb site
        - Crawl a NNTP server
        - Crawl a single NNTP group
        - Analyze the filesize of a file
        - Analyze a VCS repository via CVSAnaly
        - Analyze PHP source code via PHPLoc
        - Analyze PHP source code via pDepend
        - Analyze used programming languages via github linguist
    - Producer
        - Messages to crawl a Gerrit code review system
        - Messages to download TYPO3 releases from get.typo3.org
        - Messages to crawl a Gitweb site
        - Messages to crawl a NNTP server

BLOGPOST:
    - [TYPO3-Analytics: Release of version v0.0.1](http://andygrunwald.blogspot.de/2014/02/typo3-analytics-release-of-version-v001.html)
