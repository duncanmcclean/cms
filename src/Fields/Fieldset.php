<?php

namespace Statamic\Fields;

use Facades\Statamic\Fields\FieldsetRepository;
use Statamic\Events\FieldsetCreated;
use Statamic\Events\FieldsetDeleted;
use Statamic\Events\FieldsetSaved;
use Statamic\Events\FieldsetSaving;
use Statamic\Facades;
use Statamic\Support\Str;

class Fieldset
{
    protected $handle;
    protected $contents = [];
    protected $afterSaveCallbacks = [];
    protected $withEvents = true;

    public function setHandle(string $handle)
    {
        $this->handle = $handle;

        return $this;
    }

    public function handle(): ?string
    {
        return $this->handle;
    }

    public function setContents(array $contents)
    {
        $fields = array_get($contents, 'fields', []);

        // Support legacy syntax
        if (! empty($fields) && array_keys($fields)[0] !== 0) {
            $fields = collect($fields)->map(function ($field, $handle) {
                return compact('handle', 'field');
            })->values()->all();
        }

        $contents['fields'] = $fields;

        $this->contents = $contents;

        return $this;
    }

    public function contents(): array
    {
        return $this->contents;
    }

    public function title()
    {
        return array_get($this->contents, 'title', Str::humanize($this->handle));
    }

    public function fields(): Fields
    {
        $fields = array_get($this->contents, 'fields', []);

        return new Fields($fields);
    }

    public function field(string $handle): ?Field
    {
        return $this->fields()->get($handle);
    }

    public function editUrl()
    {
        return cp_route('fieldsets.edit', $this->handle());
    }

    public function deleteUrl()
    {
        return cp_route('fieldsets.destroy', $this->handle());
    }

    public function afterSave($callback)
    {
        $this->afterSaveCallbacks[] = $callback;

        return $this;
    }

    public function saveQuietly()
    {
        $this->withEvents = false;

        $result = $this->save();

        $this->withEvents = true;

        return $result;
    }

    public function save()
    {
        $isNew = is_null(Facades\Fieldset::find($this->handle()));

        $afterSaveCallbacks = $this->afterSaveCallbacks;
        $this->afterSaveCallbacks = [];

        if ($this->withEvents) {
            if (FieldsetSaving::dispatch($this) === false) {
                return false;
            }
        }

        FieldsetRepository::save($this);

        foreach ($afterSaveCallbacks as $callback) {
            $callback($this);
        }

        if ($this->withEvents) {
            if ($isNew) {
                FieldsetCreated::dispatch($this);
            }

            FieldsetSaved::dispatch($this);
        }

        return $this;
    }

    public function delete()
    {
        FieldsetRepository::delete($this);

        FieldsetDeleted::dispatch($this);

        return true;
    }

    public static function __callStatic($method, $parameters)
    {
        return Facades\Fieldset::{$method}(...$parameters);
    }
}
