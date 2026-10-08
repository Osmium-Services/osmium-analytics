<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumAnalytics\Models;

use Osmium\Core\Library\OsmiumPDO;

class PageHitUrl extends PageHitBase
{
    public function __construct(OsmiumPDO $database)
    {
        parent::__construct($database, 'page_hit_urls');
    }

    /**
     * Get or create a URL entry, returning its ID, or 0 if it could not be created.
     *
     * Reads before writing. InnoDB allocates an AUTO_INCREMENT value before it
     * detects a duplicate key, so an unconditional INSERT IGNORE burns an ID on
     * every page view of an already-known URL - which exhausted the column on
     * live at 65,535 despite the site only ever having 206 distinct URLs.
     *
     * INSERT IGNORE is still used for the genuinely-new case, where it keeps
     * concurrent requests safe; the re-read afterwards picks up whichever one won.
     */
    public function getOrCreateId(string $pageUrl): int
    {
        $existingId = $this->findId($pageUrl);
        if ($existingId) return $existingId;

        $insertSql = $this->loadSqlFile('pagehit-url-get-or-create.sql');
        $this->database->prepare(sql: $insertSql, bindings: [':url' => $pageUrl])->execute();

        return $this->findId($pageUrl);
    }

    /**
     * Look up an existing URL's ID, returning 0 when there is no row.
     *
     * A 0 after the insert means the insert was rejected, and lets the caller
     * skip tracking rather than violate the foreign key.
     */
    private function findId(string $pageUrl): int
    {
        $selectSql = $this->loadSqlFile('pagehit-url-get-by-url.sql');
        $result = $this->database->prepare(sql: $selectSql, bindings: [':url' => $pageUrl])->single();

        $lookupFailed = !\is_array($result) || !isset($result['id']);
        if ($lookupFailed) return 0;

        return (int) $result['id'];
    }
}
