<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Audit;

use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\FixPreview;
use WebxUi\Audit\Content\AuditContentSources;
use WebxUi\Audit\Content\ContentField;
use WebxUi\Audit\Content\ContentRecord;
use WebxUi\Audit\Contracts\AuditContentSource;
use WebxUi\Audit\Contracts\AuditFix;
use WebxUi\Audit\Hosts\HostClassifier;
use WebxUi\Audit\Runs\ContentUrl;

/**
 * `widgets.wait-for-consent` for `widgets.before_consent`: the iframe or the script that asks a
 * third party at once, rewritten where it is stored so that it waits (§9.3) — an iframe's `src`
 * becomes `data-src`, a script becomes `type="text/plain"` with its own type in `data-webx-type`,
 * and both get `data-webx-consent="<category>"` of the address. The consent script turns them
 * back on when the visitor agrees to that category.
 *
 * Where it is stored is what the run found in the database: the fields of every content source
 * (`audit_content_urls`) with the address's host — a text block with a pasted player, a page's
 * body, an insert a module keeps. Each field is read again and changed through the module's model
 * (`AuditContentSource::replace()`), so the history journal has it. What a template prints — a
 * theme's view, a block type's template — is in no field: the fix is not offered, and the
 * finding's text says to wrap it in `<x-webx-consent>` or use the `video` / `map` block.
 */
final readonly class WaitForConsent implements AuditFix
{
    public const string ID = 'widgets.wait-for-consent';

    public function __construct(private AuditContentSources $sources) {}

    public function id(): string
    {
        return self::ID;
    }

    public function textNamespace(): string
    {
        return 'webx-widgets';
    }

    public function fixes(): array
    {
        return [BeforeConsent::CHECK];
    }

    public function available(Finding $finding): bool
    {
        return $this->targets($finding) !== [];
    }

    public function preview(Finding $finding): FixPreview
    {
        $changes = [];
        $category = ThirdParty::category($finding->key);

        foreach ($this->targets($finding) as [, $record, $field, $count]) {
            $changes[] = [
                'label' => $record->label,
                'field' => $field->locale === null ? $field->name : $field->name.' · '.$field->locale,
                'count' => $count,
                'before' => $finding->key,
                'after' => 'data-webx-consent="'.$category.'"',
                'edit_url' => $record->editUrl,
            ];
        }

        return new FixPreview($changes);
    }

    public function apply(Finding $finding): void
    {
        $category = (string) ThirdParty::category($finding->key);

        foreach ($this->targets($finding) as [$source, $record, $field]) {
            [$value] = self::rewrite($field->value, $finding->key, $category);
            $source->replace($record, $field, $value);
        }
    }

    /**
     * The value with every iframe and script that asks for `$url` made to wait, and how many.
     * A structured value (a block tree as JSON) is rewritten string by string and given back as
     * the same JSON.
     *
     * @return array{string, int}
     */
    public static function rewrite(string $value, string $url, string $category): array
    {
        $decoded = in_array(ltrim($value)[0] ?? '', ['{', '['], true) ? json_decode($value, true) : null;

        if (is_array($decoded)) {
            $count = 0;

            array_walk_recursive($decoded, static function (mixed &$item) use ($url, $category, &$count): void {
                if (is_string($item)) {
                    [$item, $done] = self::rewriteHtml($item, $url, $category);
                    $count += $done;
                }
            });

            return [$count > 0 ? ContentField::json($decoded) : $value, $count];
        }

        return self::rewriteHtml($value, $url, $category);
    }

    /** @return array{string, int} */
    private static function rewriteHtml(string $html, string $url, string $category): array
    {
        $count = 0;
        $consent = ' data-webx-consent="'.htmlspecialchars($category, ENT_QUOTES).'"';

        $html = (string) preg_replace_callback('~<iframe\b([^>]*)>~i', static function (array $tag) use ($url, $consent, &$count): string {
            $src = self::attribute($tag[1], 'src');

            if ($src === null || ! self::same($src, $url)) {
                return $tag[0];
            }

            $count++;
            // Marked already and asking anyway: the mark stays, only the address moves.
            $marked = self::attribute($tag[1], 'data-webx-consent') !== null;

            return '<iframe'.preg_replace('~(\s)src(\s*=)~i', '$1data-src$2', $tag[1], 1).($marked ? '' : $consent).'>';
        }, $html);

        $html = (string) preg_replace_callback('~<script\b([^>]*)>(.*?)</script>~is', static function (array $tag) use ($url, $consent, &$count): string {
            $src = self::attribute($tag[1], 'src');
            $type = self::attribute($tag[1], 'type');
            $asks = $src !== null ? self::same($src, $url) : str_contains(html_entity_decode($tag[2]), self::bare($url));

            if (! $asks || strtolower(trim((string) $type)) === 'text/plain') {
                return $tag[0];
            }

            $count++;
            $marked = self::attribute($tag[1], 'data-webx-consent') !== null;
            $attributes = (string) preg_replace('~\s+type\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)~i', '', $tag[1]);
            $own = $type !== null && $type !== '' && ! str_contains(strtolower($type), 'javascript') ? ' data-webx-type="'.htmlspecialchars($type, ENT_QUOTES).'"' : '';

            return '<script type="text/plain"'.($marked ? '' : $consent).$own.$attributes.'>'.$tag[2].'</script>';
        }, $html);

        return [$html, $count];
    }

    private static function attribute(string $attributes, string $name): ?string
    {
        if (preg_match('~(?:^|\s)'.preg_quote($name, '~').'\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))~i', $attributes, $match) !== 1) {
            return null;
        }

        return html_entity_decode($match[1] !== '' ? $match[1] : (($match[2] ?? '') !== '' ? $match[2] : ($match[3] ?? '')));
    }

    /** `//host/path` — how a snippet may write the address it loads, without the scheme. */
    private static function bare(string $url): string
    {
        return (string) preg_replace('~^https?:~i', '', $url);
    }

    /** The same address, written with `&amp;` or without, with `https:` or protocol-relative. */
    private static function same(string $written, string $url): bool
    {
        $normal = static fn (string $address): string => str_starts_with($address, '//') ? 'https:'.$address : $address;

        return $normal(trim($written)) === $normal($url);
    }

    /**
     * The fields of this run's records that hold an address on the same host and still ask for
     * this one — read now, not when the run found them.
     *
     * @return list<array{AuditContentSource, ContentRecord, ContentField, int}>
     */
    private function targets(Finding $finding): array
    {
        $host = HostClassifier::hostOf($finding->key);
        $category = ThirdParty::category($finding->key);

        if ($finding->runId === null || $host === null || $category === null) {
            return [];
        }

        $places = ContentUrl::query()
            ->where('run_id', $finding->runId)
            ->where('host', $host)
            ->get(['source', 'record_id', 'field', 'locale'])
            ->unique(static fn (ContentUrl $row): string => $row->source.'|'.$row->record_id.'|'.$row->field.'|'.$row->locale);

        $targets = [];

        foreach ($places as $place) {
            $source = $this->sources->get((string) $place->source);
            $record = $source?->find((string) $place->record_id);

            if ($source === null || $record === null) {
                continue;
            }

            foreach ($source->fields($record) as $field) {
                if ($field->name !== $place->field || (string) $field->locale !== (string) $place->locale) {
                    continue;
                }

                [, $count] = self::rewrite($field->value, $finding->key, $category);

                if ($count > 0) {
                    $targets[] = [$source, $record, $field, $count];
                }
            }
        }

        return $targets;
    }
}
