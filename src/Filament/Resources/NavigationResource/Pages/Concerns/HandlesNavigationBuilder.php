<?php

namespace RyanChandler\FilamentNavigation\Filament\Resources\NavigationResource\Pages\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Filament\Pages\Actions\Action;
use Filament\Forms\Components\Select;

use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\TextInput;
use function Filament\Forms\array_move_after;
use function Filament\Forms\array_move_before;

trait HandlesNavigationBuilder
{
    public $mountedItem;

    public $mountedItemData = [];

    public $mountedChildTarget;

    public function addChild(string $statePath)
    {
        $this->mountedChildTarget = $statePath;

        $this->mountAction('add');
    }

    public function removeItem(string $statePath)
    {
        $uuid = Str::afterLast($statePath, '.');

        $parentPath = Str::beforeLast($statePath, '.');
        $parent = data_get($this, $parentPath);

        data_set($this, $parentPath, Arr::except($parent, $uuid));
    }

    public function indentItem(string $statePath)
    {
        $item = data_get($this, $statePath);
        $uuid = Str::afterLast($statePath, '.');

        $parentPath = Str::beforeLast($statePath, '.');
        $parent = data_get($this, $parentPath);

        $keys = array_keys($parent);
        $position = array_search($uuid, $keys);

        $previous = $parent[$keys[$position - 1]];

        if (! isset($previous['children'])) {
            $previous['children'] = [];
        }

        $previous['children'][(string) Str::uuid()] = $item;
        $parent[$keys[$position - 1]] = $previous;

        data_set($this, $parentPath, Arr::except($parent, $uuid));
    }

    public function dedentItem(string $statePath)
    {
        $item = data_get($this, $statePath);
        $uuid = Str::afterLast($statePath, '.');

        $parentPath = Str::beforeLast($statePath, '.');
        $parent = data_get($this, $parentPath);

        $pathToMoveInto = Str::of($statePath)->beforeLast('.')->rtrim('.children')->beforeLast('.');
        $pathToMoveIntoData = data_get($this, $pathToMoveInto);

        $pathToMoveIntoData[(string) Str::uuid()] = $item;
        data_set($this, $pathToMoveInto, $pathToMoveIntoData);

        data_set($this, $parentPath, Arr::except($parent, $uuid));
    }

    public function moveItemUp(string $statePath)
    {
        $parentPath = Str::beforeLast($statePath, '.');
        $uuid = Str::afterLast($statePath, '.');

        $parent = data_get($this, $parentPath);
        $hasMoved = false;

        uksort($parent, function ($_, $b) use ($uuid, &$hasMoved) {
            if ($b === $uuid && ! $hasMoved) {
                $hasMoved = true;

                return 1;
            }

            return 0;
        });

        data_set($this, $parentPath, $parent);
    }

    public function moveItemDown(string $statePath)
    {
        $parentPath = Str::beforeLast($statePath, '.');
        $uuid = Str::afterLast($statePath, '.');

        $parent = data_get($this, $parentPath);
        $hasMoved = false;

        uksort($parent, function ($a, $_) use ($uuid, &$hasMoved) {
            if ($a === $uuid && ! $hasMoved) {
                $hasMoved = true;

                return 1;
            }

            return 0;
        });

        data_set($this, $parentPath, $parent);
    }

    public function editItem(string $statePath)
    {
        $this->mountedItem = $statePath;
        $this->mountedItemData = Arr::except(data_get($this, $statePath), 'children');

        $this->mountAction('add');
    }

    protected function getActions(): array
    {
        return [
            Action::make('add')
                ->mountUsing(function (ComponentContainer $form) {
                    $form->fill($this->mountedItemData);
                })
                ->view('filament-navigation::hidden-action')
                ->form([
                    TextInput::make('label')
                        ->required(),
                    TextInput::make('url')
                        ->label('URL')
                        ->required(),
                    Select::make('target')
                        ->default('')
                        ->options([
                            '' => 'Same tab',
                            '_blank' => 'New tab',
                        ])
                        ->nullable(),
                ])
                ->modalWidth('md')
                ->action(function (array $data) {
                    if ($this->mountedItem) {
                        data_set($this, $this->mountedItem, array_merge(data_get($this, $this->mountedItem), $data));

                        $this->mountedItem = null;
                        $this->mountedItemData = [];
                    } elseif ($this->mountedChildTarget) {
                        $children = data_get($this, $this->mountedChildTarget . '.children', []);

                        $children[(string) Str::uuid()] = [
                            ...$data,
                            ...['children' => []],
                        ];

                        data_set($this, $this->mountedChildTarget . '.children', $children);

                        $this->mountedChildTarget = null;
                    } else {
                        $this->data['items'][(string) Str::uuid()] = [
                            ...$data,
                            ...['children' => []],
                        ];
                    }
                })
                ->modalButton('Save')
                ->label('Item')
        ];
    }
}