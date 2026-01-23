<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\CategoryNominee;
use App\Models\Entry;
use App\Models\FinalVote;
use App\Models\Option;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Carbon\Carbon;

#[Layout('components.layouts.app')]
class FinalVoting extends Component
{
    #[Locked]
    public $voting_open = false;

    private $vote_limit = 1;

    public function mount()
    {
        $endDate = Option::get('final_voting_end_date');
        
        if ($endDate && now()->isAfter(Carbon::parse($endDate))) {
            abort(403, 'Final voting has ended.');
        }
        
        $this->voting_open = true;
    }

    #[Computed]
    public function user()
    {
        return Auth::user();
    }
    
    #[Computed]
    public function groups()
    {
        return [
            ['slug' => 'main', 'text' => 'Main Awards'],
            ['slug' => 'genre', 'text' => 'Genre Awards'],
            ['slug' => 'production', 'text' => 'Production Awards'],
            ['slug' => 'character', 'text' => 'Character Awards'],
        ];
    }

    // #[Computed(cache: true, key: 'finalvote-categories')]
    #[Computed]
    public function categories()
    {
        return Category::where('year', app('current-year'))
            ->has('nominees')
            // ->with('nominees.entry.parent.parent')
            ->orderBy('order')
            ->get()
            ->groupBy('type');
    }

    // #[Computed(cache: true, key: 'finalvote-nominees')]
    #[Computed]
    public function nominees()
    {
        return CategoryNominee::has('entry')
            ->whereHas('category', function(Builder $query) {
                $query->where('year', app('current-year'));
            })
            ->with('entry.parent.parent')
            ->get()
            ->groupBy('category_id');
    }

    // Unused
    #[Computed]
    public function entries()
    {
        return Entry::whereHas('category_nominees', function(Builder $query) {
            $query->whereHas('category', function (Builder $query) {$query->where('year', app('current-year'));});
        })
        ->with('parent.parent')
        ->get();
    }

    #[Computed]
    public function selections()
    {
        $votes = FinalVote::where('user_id', $this->user->id)
            ->with('entry.parent.parent')
            ->get();

        return $votes->groupBy('category_id')
            ->map(function ($categorySelections) {
                return $categorySelections->keyBy('cat_nom_id');
            });
    }

    private function canVoteMore($categoryId)
    {
        $count = FinalVote::where('user_id', $this->user->id)
            ->where('category_id', $categoryId)
            ->count();

        return $count < $this->vote_limit;
    }

    public function createVote($categoryId, $nomineeId, $entryId)
    {
        if (! $this->canVoteMore($categoryId)) {
            session()->flash('error', 'You cannot vote for any more entries in this category.');

            return response()->json(['error' => 'Cannot vote for any more entries in this category'], 400);
        }

        // Find the category nominee
        $nominee = CategoryNominee::find($nomineeId);
        if (! $nominee
            || $nominee->category_id != $categoryId
            || $nominee->entry_id != $entryId) {
            return response()->json(['error' => 'Invalid nominee'], 400); // Failed to validate nominee
        }

        // Check if vote already exists
        $existingVote = FinalVote::where('user_id', $this->user->id)
            ->where('category_id', $categoryId)
            ->where('entry_id', $entryId)
            ->first();

        if ($existingVote) {
            return response()->json(['error' => 'Vote already exists'], 400); // Vote already exists
        }

        // Create the vote
        $vote = FinalVote::create([
            'user_id' => $this->user->id,
            'category_id' => $categoryId,
            'cat_nom_id' => $nomineeId,
            'entry_id' => $entryId,
        ]);

        if (! $vote) {
            return response()->json(['error' => 'Failed to create Vote'], 500); // Creation failure
        }
        $this->dispatch('vote-added');

        return response()->json(['success' => 'Vote created']);
    }

    public function deleteVote($categoryId, $nomineeId, $entryId)
    {
        // Delete the vote
        $deleted = FinalVote::where('user_id', $this->user->id)
            ->where('category_id', $categoryId)
            ->where('entry_id', $entryId)
            ->delete();

        if (! $deleted) {
            return response()->json(['error' => 'Error deleting vote'], 500);
        }

        $this->dispatch('vote-removed');

        return response()->json(['success' => 'Vote deleted']);

    }

    public function render()
    {
        return view('livewire.final-voting');
    }
}
