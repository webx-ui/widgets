<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests\Fixtures;

use WebxUi\Audit\Content\ContentField;
use WebxUi\Audit\Content\ContentRecord;
use WebxUi\Audit\Contracts\AuditContentSource;

/** A content source over an array — id → label and fields — that remembers what a fix wrote. */
final class AuditContent implements AuditContentSource
{
    /** @var array<string, array{label: string, fields: array<string, string>}> */
    public array $records = [];

    /** @var list<array{record: string, field: string, value: string}> */
    public array $replaced = [];

    public function id(): string
    {
        return 'posts';
    }

    public function records(): iterable
    {
        foreach (array_keys($this->records) as $id) {
            yield $this->find((string) $id);
        }
    }

    public function find(string $id): ?ContentRecord
    {
        return isset($this->records[$id]) ? new ContentRecord($id, $this->records[$id]['label'], true, '/posts/'.$id) : null;
    }

    public function fields(ContentRecord $record): iterable
    {
        foreach ($this->records[$record->id]['fields'] as $name => $value) {
            yield new ContentField($name, $value, 'en');
        }
    }

    public function replace(ContentRecord $record, ContentField $field, string $value): void
    {
        $this->replaced[] = ['record' => $record->id, 'field' => $field->name, 'value' => $value];
        $this->records[$record->id]['fields'][$field->name] = $value;
    }
}
