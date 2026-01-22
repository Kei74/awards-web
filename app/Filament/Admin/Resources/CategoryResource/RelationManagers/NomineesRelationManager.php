<?php

namespace App\Filament\Admin\Resources\CategoryResource\RelationManagers;

use App\Models\CategoryNominee;
use App\Models\Entry;
use App\Models\NomineeVote;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use App\Models\Category;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class NomineesRelationManager extends RelationManager
{
    protected static string $relationship = 'nominees';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('entry_id')
                    ->label('Entry')
                    ->required()
                    ->options(function (Get $get) {
                        $parent_cat = $this->getOwnerRecord();
                        $year = $parent_cat->year;

                        // TODO: Implement type constraints for search

                        return Entry::where('year', $year)
                            ->pluck('name', 'id');
                    })
                    ->searchable(),
                Toggle::make('active')
                    ->required()
                    ->default(true),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->columnSpanFull()
                    ->schema([
                        Fieldset::make()
                            ->columns(1)
                            ->contained(false)
                            ->schema([
                                TextEntry::make('entry.name'),
                                TextEntry::make('entry.year'),
                                TextEntry::make('entry_id')
                                    ->numeric(),
                                IconEntry::make('active')
                                    ->boolean(),
                                TextEntry::make('created_at')
                                    ->dateTime()
                                    ->placeholder('-'),
                                TextEntry::make('updated_at')
                                    ->dateTime()
                                    ->placeholder('-'),
                            ]),
                        ImageEntry::make('entry.image')
                            ->imageSize(400)
                            ->disk('public'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nominee')
            ->defaultSort('active', 'desc')
            ->columns([
                TextColumn::make('entry_id')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('entry.name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('entry.parent.name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('entry.parent.grandparents.name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('entry.type')
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('active'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
                Action::make('addFromTopVotes')
                    ->label('Add from Top Votes')
                    ->icon('heroicon-o-chart-bar')
                    ->form([
                        CheckboxList::make('entry_ids')
                            ->label('Select Nominees')
                            ->options(function () {
                                $category = $this->getOwnerRecord();
                                
                                // Get top 10 voted entries for this category
                                $topVoted = NomineeVote::selectRaw('nominee_votes.entry_id, entries.name as entry_name, parents.name as parent_name, COUNT(*) as vote_count')
                                    ->join('entries', 'nominee_votes.entry_id', '=', 'entries.id')
                                    ->leftJoin('entries as parents', 'entries.parent_id', '=', 'parents.id')
                                    ->where('nominee_votes.category_id', $category->id)
                                    ->groupBy('nominee_votes.entry_id', 'entries.name', 'parents.name')
                                    ->orderByDesc('vote_count')
                                    ->limit(10)
                                    ->get();
                                
                                $options = [];
                                foreach ($topVoted as $vote) {
                                    $label = $vote->entry_name;
                                    if ($vote->parent_name) {
                                        $label .= ' (' . $vote->parent_name . ')';
                                    }
                                    $label .= ' - ' . $vote->vote_count . ' vote' . ($vote->vote_count != 1 ? 's' : '');
                                    $options[$vote->entry_id] = $label;
                                }
                                
                                return $options;
                            })
                            ->required()
                            ->helperText('Select one or more entries from the top 10 voted nominees to add as category nominees'),
                        Toggle::make('active')
                            ->label('Set as active')
                            ->default(true)
                            ->helperText('If checked, nominees will be set as active'),
                    ])
                    ->action(function (array $data) {
                        $category = $this->getOwnerRecord();
                        $entryIds = $data['entry_ids'] ?? [];
                        $active = $data['active'] ?? true;
                        
                        if (empty($entryIds)) {
                            Notification::make()
                                ->title('No entries selected')
                                ->body('Please select at least one entry to add as a nominee.')
                                ->warning()
                                ->send();
                            return;
                        }
                        
                        $addedCount = 0;
                        $skippedCount = 0;
                        
                        foreach ($entryIds as $entryId) {
                            // Check if nominee already exists
                            $existing = CategoryNominee::where('category_id', $category->id)
                                ->where('entry_id', $entryId)
                                ->first();
                            
                            if ($existing) {
                                // Update active status if needed
                                if ($existing->active != $active) {
                                    $existing->update(['active' => $active]);
                                    $addedCount++;
                                } else {
                                    $skippedCount++;
                                }
                            } else {
                                // Create new nominee
                                CategoryNominee::create([
                                    'category_id' => $category->id,
                                    'entry_id' => $entryId,
                                    'active' => $active,
                                ]);
                                $addedCount++;
                            }
                        }
                        
                        $message = "Successfully added {$addedCount} nominee(s)";
                        if ($skippedCount > 0) {
                            $message .= ", {$skippedCount} already existed";
                        }
                        
                        Notification::make()
                            ->title($message)
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
