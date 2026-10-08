<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumAnalytics\Models;

use Osmium\Core\Library\OsmiumPDO;

class PageHitReferrer extends PageHitBase
{
    public function __construct(OsmiumPDO $database)
    {
        parent::__construct($database, 'page_hit_referrers');
    }

    /**
     * Get or create a referrer domain entry, returning its ID, or 0 on failure.
     *
     * Reads before writing for the same reason as PageHitUrl::getOrCreateId -
     * an unconditional INSERT IGNORE burns an AUTO_INCREMENT value on every hit
     * from an already-known domain.
     */
    public function getOrCreateId(string $domain): int
    {
        $existingId = $this->findId($domain);
        if ($existingId) return $existingId;

        $insertSql = $this->loadSqlFile('pagehit-referrer-get-or-create.sql');
        $this->database->prepare(sql: $insertSql, bindings: [':domain' => $domain])->execute();

        return $this->findId($domain);
    }

    private function findId(string $domain): int
    {
        $selectSql = $this->loadSqlFile('pagehit-referrer-get-by-domain.sql');
        $result = $this->database->prepare(sql: $selectSql, bindings: [':domain' => $domain])->single();

        $lookupFailed = !\is_array($result) || !isset($result['id']);
        if ($lookupFailed) return 0;

        return (int) $result['id'];
    }
}
