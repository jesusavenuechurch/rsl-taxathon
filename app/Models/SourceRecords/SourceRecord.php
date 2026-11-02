<?php

namespace App\Models\SourceRecords;

use App\Domain\Records\Channel;
use App\Domain\Records\Channels;
use App\Domain\Records\Field;
use App\Models\SourceImport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A row as received from an RSL channel. Never edited: a correction is a new
 * version of the same record_id, and only superseded_at may be set on the
 * old one. Rules read current versions; evidence pins the version it saw.
 */
abstract class SourceRecord extends Model
{
    public const CHANNEL = '';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(function (SourceRecord $record) {
            $changed = array_diff(array_keys($record->getDirty()), ['superseded_at', 'updated_at']);
            if ($changed !== []) {
                throw new LogicException("Source records are never edited; import a new version of {$record->record_id} instead.");
            }
        });
        static::deleting(fn (SourceRecord $r) => throw new LogicException("Source record {$r->record_id} cannot be deleted."));
    }

    public static function channel(): Channel
    {
        return Channels::get(static::CHANNEL);
    }

    protected function casts(): array
    {
        $casts = ['version' => 'integer', 'superseded_at' => 'datetime'];

        foreach (static::channel()->fields as $field) {
            $casts[$field->attribute] = match ($field->type) {
                Field::MONEY, Field::INTEGER => 'integer',
                Field::YES_NO => 'boolean',
                Field::DATE => 'date',
                default => 'string',
            };
        }

        return $casts;
    }

    /** Values computed from the file's columns at import (e.g. normalized names). */
    public static function derivedAttributes(array $attributes): array
    {
        return [];
    }

    /** Called after a new version is inserted, to write child rows. */
    public function afterImport(): void {}

    /** @param Builder<static> $query */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNull('superseded_at');
    }

    public function isCurrent(): bool
    {
        return $this->superseded_at === null;
    }

    public function tin(): ?string
    {
        $attribute = static::channel()->tinAttribute;

        return $attribute ? $this->getAttribute($attribute) : null;
    }

    public function sourceImport(): BelongsTo
    {
        return $this->belongsTo(SourceImport::class);
    }

    /** The taxpayer this row belongs to, by TIN (as RSL systems join them). */
    public function taxpayer(): BelongsTo
    {
        return $this->belongsTo(Taxpayer::class, static::channel()->tinAttribute ?? 'tin', 'tin')->whereNull('superseded_at');
    }

    /** All versions of this record, oldest first. */
    public function versions()
    {
        return static::query()->where('record_id', $this->record_id)->orderBy('version')->get();
    }

    public static function normalizeName(string $name): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($name)));
    }
}
