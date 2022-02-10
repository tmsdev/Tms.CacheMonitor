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

### Flow commands
Run `./flow cachemonitor` to see a full list of available Flow commands.

### Settings
The package starts logging without any further configuration. Please see the default configuration in case you need to customize something.

```yaml
Tms:
  CacheMonitor:
    # Settings for the cache flush logging
    logCacheFlush:
      cacheIdentifiers:
        - # Optional "name" key is used as table header in the CLI command output
          identifier: 'Neos_Fusion_Content'
          name: 'Content Cache'

    # Settings for logging Flowpack.FullPageCache events
    logFullPageCache:
      # Which Flowpack.FullPageCache cache info states you'd like to log?
      # One benefit of using Flowpack.FullPageCache is that there are zero SQL queries involved for cache hits.
      # Therefore we add 'HIT' logging only in development context by default.
      cacheInfos: ['SKIP', 'MISS']

      # Flownative.FullPageCache uses a cookies & query string whitelist approach to decide if a response is fully cachable.
      # Over time you most likely need to adjust the FullPageCache configuration as requests with unknown cookies and/or
      # query strings hit your site. These logs help you to keep track of new cookie and/or query strings.
      # Run './flow cachemonitor:info' to see the results
      disallowedCookieParams: true
      disallowedQueryParams: true
```

## Acknowledgments
Development sponsored by [tms.development - Online Marketing and Neos CMS Agency](https://www.tms-development.de/)
