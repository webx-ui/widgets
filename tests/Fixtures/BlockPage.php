<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Versions\HasDraft;
use WebxUi\Admin\Versions\HasVersions;
use WebxUi\Blocks\HasBlocks;
use WebxUi\Routing\HasUrl;

/**
 * A page made of blocks, with a draft and an address: what `module-pages` gives a site.
 *
 * @property int $id
 * @property string|null $title
 * @property string|null $slug
 */
final class BlockPage extends Model
{
    use HasBlocks;
    use HasDraft;
    use HasUrl;
    use HasVersions;

    protected $table = 'pages';

    protected $guarded = [];
}
