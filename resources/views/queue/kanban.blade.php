@extends('layouts.app')

@section('title', 'Kanban')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">🗂️ Kanban</h1>
        <p class="text-gray-600 mt-1">Ready to test (Queued) → In Testing → Sent Back → Done</p>
    </div>

    <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-sm border p-4 text-center">
            <span class="text-3xl font-bold text-amber-600">{{ $readyQueuedStories->count() }}</span>
            <p class="text-gray-500 text-sm mt-1">Ready to test (Queued)</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4 text-center">
            <span class="text-3xl font-bold text-blue-600">{{ $inTestingStories->count() }}</span>
            <p class="text-gray-500 text-sm mt-1">In Testing</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4 text-center">
            <span class="text-3xl font-bold text-red-600">{{ $sentBackStories->count() }}</span>
            <p class="text-gray-500 text-sm mt-1">Sent Back</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4 text-center">
            <span class="text-3xl font-bold text-green-600">{{ $doneStories->count() }}</span>
            <p class="text-gray-500 text-sm mt-1">Done</p>
        </div>
    </div>

    <div class="grid grid-cols-4 gap-6">
        <div class="bg-white rounded-lg shadow-sm border">
            <div class="p-4 border-b bg-amber-50">
                <h2 class="font-semibold text-amber-800">📥 Ready to test (Queued)</h2>
            </div>
            <div class="divide-y max-h-[34rem] overflow-y-auto">
                @forelse($readyQueuedStories as $story)
                    <div class="p-3 hover:bg-gray-50 transition">
                        <a href="{{ route('queue.show', $story) }}" class="font-medium text-gray-900 hover:text-purple-600 block truncate">
                            {{ $story->title }}
                        </a>
                        @if($story->epic)
                            <p class="text-xs text-purple-600 mt-1 truncate">{{ $story->epic->title }}</p>
                        @endif
                        <div class="mt-2">
                            <form action="{{ route('queue.start-testing', $story) }}" method="POST">
                                @csrf
                                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white text-xs px-2 py-1 rounded transition">
                                    ▶ Start Testing
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-gray-400">No queued stories</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border">
            <div class="p-4 border-b bg-blue-50">
                <h2 class="font-semibold text-blue-800">🔬 In Testing</h2>
            </div>
            <div class="divide-y max-h-[34rem] overflow-y-auto">
                @forelse($inTestingStories as $story)
                    <div class="p-3 hover:bg-gray-50 transition">
                        <a href="{{ route('queue.show', $story) }}" class="font-medium text-gray-900 hover:text-purple-600 block truncate">
                            {{ $story->title }}
                        </a>
                        @if($story->epic)
                            <p class="text-xs text-purple-600 mt-1 truncate">{{ $story->epic->title }}</p>
                        @endif
                        <div class="mt-2 flex gap-2">
                            <form action="{{ route('queue.mark-passed', $story) }}" method="POST" class="flex-1">
                                @csrf
                                <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white text-xs px-2 py-1 rounded transition">
                                    ✅ Done
                                </button>
                            </form>
                            <form action="{{ route('queue.mark-failed', $story) }}" method="POST" class="flex-1">
                                @csrf
                                <button type="submit" class="w-full bg-red-500 hover:bg-red-600 text-white text-xs px-2 py-1 rounded transition">
                                    ❌ Sent Back
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-gray-400">No active testing stories</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border">
            <div class="p-4 border-b bg-red-50">
                <h2 class="font-semibold text-red-800">↩️ Sent Back</h2>
            </div>
            <div class="divide-y max-h-[34rem] overflow-y-auto">
                @forelse($sentBackStories as $story)
                    <div class="p-3 hover:bg-gray-50 transition">
                        <a href="{{ route('queue.show', $story) }}" class="font-medium text-gray-900 hover:text-purple-600 block truncate">
                            {{ $story->title }}
                        </a>
                        @if($story->epic)
                            <p class="text-xs text-purple-600 mt-1 truncate">{{ $story->epic->title }}</p>
                        @endif
                        <div class="mt-2">
                            <form action="{{ route('queue.return-to-queue', $story) }}" method="POST">
                                @csrf
                                <button type="submit" class="bg-gray-500 hover:bg-gray-600 text-white text-xs px-2 py-1 rounded transition">
                                    Return to Dev Queue
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-gray-400">No sent-back stories</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border">
            <div class="p-4 border-b bg-green-50">
                <h2 class="font-semibold text-green-800">✅ Done</h2>
            </div>
            <div class="divide-y max-h-[34rem] overflow-y-auto">
                @forelse($doneStories as $story)
                    <div class="p-3 hover:bg-gray-50 transition">
                        <a href="{{ route('queue.show', $story) }}" class="font-medium text-gray-900 hover:text-purple-600 block truncate">
                            {{ $story->title }}
                        </a>
                        @if($story->epic)
                            <p class="text-xs text-purple-600 mt-1 truncate">{{ $story->epic->title }}</p>
                        @endif
                        <p class="text-xs text-gray-500 mt-1">{{ $story->updated_at?->diffForHumans() }}</p>
                    </div>
                @empty
                    <div class="p-6 text-center text-gray-400">No done stories</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
