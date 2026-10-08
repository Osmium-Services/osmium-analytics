<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumAnalytics\Models;

use Osmium\Core\Library\OsmiumPDO;
use Osmium\Core\Models\Model;

/**
 * Shared constructor for the page hit tables: each model points at its own table and the service's sql folder.
 */
abstract class PageHitBase extends Model
{
    protected ?string $sqlDir = __DIR__ . '/sql';

    public function __construct(OsmiumPDO $database, string $tableName)
    {
        parent::__construct(database: $database, tableName: $tableName);
    }
}
