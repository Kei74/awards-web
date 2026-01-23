<!DOCTYPE html>
<html data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Share Final Votes - r/anime Awards</title>
    <link rel="icon" type="image/png" href="{{ asset('images/pubjury.png') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body>
    <div class="voting-page-content has-background-anti-spotify">
        <div class="container mt-4">
            <div class="columns is-centered">
                <div class="column is-8">
                    <div class="box has-background-dark">
                        <h1 class="title is-3 has-text-light mb-4">Share Your Final Votes</h1>
                        
                        @auth
                            @php
                                $user = auth()->user();
                                $selections = \App\Models\FinalVote::where('user_id', $user->id)
                                    ->with('entry.parent.parent')
                                    ->with('category')
                                    ->get()
                                    ->groupBy('category_id');
                                
                                $categories = \App\Models\Category::where('year', app('current-year'))
                                    ->whereIn('id', $selections->keys())
                                    ->orderBy('order')
                                    ->get();
                            @endphp
                            
                            @if($selections->isEmpty())
                                <p class="has-text-light">You haven't made any final votes yet.</p>
                                <a href="/participate/final-vote" class="button is-primary mt-4">Go to Final Voting</a>
                            @else
                                <div class="content">
                                    <p class="has-text-light mb-4">Copy your votes below to share them:</p>
                                    
                                    <div class="field">
                                        <div class="control">
                                            <textarea 
                                                id="votes-text" 
                                                class="textarea has-background-dark has-text-light" 
                                                rows="15"
                                                readonly
                                            >@foreach($categories as $category)
*{{ $category->name }}:*

@foreach($selections->get($category->id, []) as $vote)
 - {{ $vote->entry->name }}@if($vote->entry->parent) ({{ $vote->entry->parent->name }})@endif
@endforeach

@endforeach</textarea>
                                        </div>
                                    </div>
                                    
                                    <div class="field is-grouped">
                                        <div class="control">
                                            <button 
                                                class="button is-primary" 
                                                onclick="copyToClipboard()"
                                            >
                                                <span class="icon">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                        <path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1V14a1 1 0 0 1-1V3.5a1 1 0 0 1-1h-1v-1Z"/>
                                                        <path d="M9.5 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5h3Zm-3-1A1.5 1.5 0 0 0 5 1.5v1A1.5 1.5 0 0 0 6.5 4h3A1.5 1.5 0 0 0 11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3Z"/>
                                                    </svg>
                                                </span>
                                                <span>Copy to Clipboard</span>
                                            </button>
                                        </div>
                                        <div class="control">
                                            <a href="/participate/final-vote/image" class="button is-success" target="_blank">
                                                <span class="icon">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                        <path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/>
                                                        <path d="M2.002 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2h-12zm12 1a1 1 0 0 1 1 1v6.5l-3.777-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12V3a1 1 0 0 1 1-1h12z"/>
                                                    </svg>
                                                </span>
                                                <span>Generate Image</span>
                                            </a>
                                        </div>
                                        <div class="control">
                                            <a href="/participate/final-vote" class="button is-light">
                                                <span>Back to Voting</span>
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <div id="copy-success" class="notification is-success is-light mt-4" style="display: none;">
                                        Your votes have been copied to your clipboard!
                                    </div>
                                </div>
                            @endif
                        @else
                            <p class="has-text-light">Please <a href="/login">log in</a> to view your votes.</p>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        .has-background-anti-spotify {
            background: linear-gradient(360deg, rgba(107, 156, 232, 0.35) 0%, rgba(45, 56, 83, 0) 76.46%), #1B1E25;
            min-height: 100vh;
            padding: 2rem 0;
        }
    </style>
    
    <script>
        function copyToClipboard() {
            const textarea = document.getElementById('votes-text');
            textarea.select();
            textarea.setSelectionRange(0, 99999); // For mobile devices
            
            try {
                document.execCommand('copy');
                const successMsg = document.getElementById('copy-success');
                successMsg.style.display = 'block';
                setTimeout(() => {
                    successMsg.style.display = 'none';
                }, 3000);
            } catch (err) {
                // Fallback for modern browsers
                navigator.clipboard.writeText(textarea.value).then(() => {
                    const successMsg = document.getElementById('copy-success');
                    successMsg.style.display = 'block';
                    setTimeout(() => {
                        successMsg.style.display = 'none';
                    }, 3000);
                }).catch(() => {
                    alert('Failed to copy to clipboard');
                });
            }
        }
    </script>
</body>
</html>
