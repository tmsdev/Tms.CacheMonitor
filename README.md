# Use Tms.CacheMonitor to analyse content caches in Neos CMS

A simple database-driven solution for monitoring and analyzing content caches in Neos CMS.

## Features

- [x] [Flowpack.FullPageCache](https://github.com/Flowpack/Flowpack.FullPageCache) support: Keep track of cache **HIT**s, **MISS**es & **SKIP**s (also logs disallowed query strings and cookie names)
- [x] Flow commands to analyse log data
- [x] Keep track of content cache flushes
- [ ] Keep track of content caches with TTLs
- [ ] Backend module

## Installation

⚠️⚠️⚠️ **WARNING: This is currently experimental code, do not rely on it and use it at your own risk. If you find this package useful, I will gladly accept any kind of contribution.**

```bash
composer require tms/cachemonitor dev-main
````

## Usage

The package currently only supports [Flowpack.FullPageCache](https://github.com/Flowpack/Flowpack.FullPageCache) activities. Have a look at the default settings:

```yaml
Tms:
  CacheMonitor:
    fullPageCacheLogger:
      skip: true
      miss: true

      # WARNING: A benefit of using Flowpack.FullPageCache is that there are zero SQL queries involved for cache hits.
      # By logging also cache hits you may experience performance issues
      hit: false

      # Flownative.FullPageCache uses a cookies & query string whitelist approach to decide if a response is fully cachable.
      # Over time you most likely need to adjust the FullPageCache configuration as requests with unknown cookies and/or
      # query strings hit your site. These logs help you to keep track of new cookie and/or query strings.
      logDisallowedCookieParams: true
      logDisallowedQueryParams: true
```

## Acknowledgments
Development sponsored by [tms.development - Online Marketing and Neos CMS Agency](https://www.tms-development.de/)
