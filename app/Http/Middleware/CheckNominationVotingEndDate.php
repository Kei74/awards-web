<?php

namespace App\Http\Middleware;

use App\Models\Option;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;

class CheckNominationVotingEndDate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $endDate = Option::get('nomination_voting_end_date');
        
        if ($endDate && now()->isAfter(Carbon::parse($endDate))) {
            abort(403, 'Nomination voting has ended.');
        }
        
        return $next($request);
    }
}
