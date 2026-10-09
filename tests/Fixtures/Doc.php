<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests\Fixtures;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Routing\Contracts\Visible;

/**
 * An entity the address registry found: all the switcher reads of it is its key, for its rows in
 * `routes`, and whether it is shown in a language. No table — nothing loads it.
 */
final class Doc extends Model implements Visible
{
    /** @var list<string> Languages it is hidden in — a draft of a translation. */
    public array $hiddenIn = [];

    public function isVisible(?string $locale = null): bool
    {
        return ! in_array($locale, $this->hiddenIn, true);
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeVisible(Builder $query, ?string $locale = null): Builder
    {
        return $query;
    }

    public function visibleUpdatedAt(): ?CarbonInterface
    {
        return null;
    }
}
