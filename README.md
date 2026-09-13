# TrendingArticles [BETA]

> [!CAUTION]  
> TrendingArticles is experimental. Use in production is discouraged.

TrendingArticles is a MediaWiki extension for MediaWiki 1.46+ that displays trending articles on category pages. Continue reading for limitations and recommendations.

> [!NOTE]  
> TrendingArticles may be refered to as simply `Trending` with-in the source code

# Installation

## Requirements

* MediaWiki 1.46+

## Install via Git

You can use Git to install `TrendingArticles` in your MediaWiki extensions folder:

```
cd extensions/
git clone https://github.com/wikux/mediawiki-extensions-TrendingArticles.git
```

Depending on your infrastructure you may be interested in Git submodules. Alternatively, you can simply download the `TrendingArticles` folder and move it to the `extensions/` folder.

> [!IMPORTANT]  
> Running the update maintenance script is required.

## Suggestions

The below extensions are also suggested to be installed:

* PageImages
* ShortDescription

# Configuration

You must update the database schema via the update.php maintenance script:

```
php maintenance/run.php update
```

This will create two new tables in your database: `trending_pageview`, `trending_pageview_daily`. You may want to verify these were successfuly created as this is an experimental extension.

If you want to test the category page functionality immediately, you can run:

```sql
USE my_wiki_database;
UPDATE trending_pageview_daily
    SET tpd_count = 400
    WHERE tpd_page_id = 57; -- or whatever page ID you want to test it on
```

Then navigate to any category it is in.

# Experimental Disclaimer

View counting may be off. This extension is experimental. Install PageImages and ShortDescription for the intended thumbnail and description experience.
