@extends('layouts.app')

@section('title', 'Testing Queue')

@section('content')
<div class="max-w-7xl mx-auto">
    {{-- Page header with title and stats --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">🧪 Testing Queue</h1>
        <p class="text-gray-600 mt-1">QA testing dashboard for completed stories</p>
    </div>

    {{-- Statistics summary cards --}}
    <div class="grid grid-cols-4 gap-4 mb-8">
        {{-- Ready for testing count --}}
        <div class="bg-white rounded-lg shadow-sm border p-4 text-center">
            <span class="text-3xl font-bold text-amber-600">{{ $readyForTestingStories->count() }}</span>
            <p class="text-gray-500 text-sm mt-1">Ready for Testing</p>
        </div>

        {{-- In testing count --}}
        <div class="bg-white rounded-lg shadow-sm border p-4 text-center">
            <span class="text-3xl font-bold text-blue-600">{{ $inTestingStories->count() }}</span>
            <p class="text-gray-500 text-sm mt-1">In Testing</p>
        </div>

        {{-- Passed count --}}
        <div class="bg-white rounded-lg shadow-sm border p-4 text-center">
            <span class="text-3xl font-bold text-green-600">{{ $passedStories->count() }}</span>
            <p class="text-gray-500 text-sm mt-1">Passed (Recent)</p>
        </div>

        {{-- Failed count --}}
        <div class="bg-white rounded-lg shadow-sm border p-4 text-center">
            <span class="text-3xl font-bold text-red-600">{{ $failedStories->count() }}</span>
            <p class="text-gray-500 text-sm mt-1">Failed / Needs Rework</p>
        </div>
    </div>

    {{-- Main queue columns layout --}}
    <div class="grid grid-cols-3 gap-6">
        {{-- Column 1: Ready for Testing --}}
        <div class="bg-white rounded-lg shadow-sm border">
            <div class="p-4 border-b bg-amber-50">
                <h2 class="font-semibold text-amber-800">📥 Ready for Testing</h2>
                <p class="text-xs text-amber-600 mt-1">Click to start testing</p>
            </div>

            <div class="divide-y max-h-96 overflow-y-auto">
                @forelse($readyForTestingStories as $story)
                    <div class="p-3 hover:bg-gray-50 transition">
                        <div class="flex justify-between items-start">
                            <div class="flex-1 min-w-0">
                                <a href="{{ route('queue.show', $story) }}" class="font-medium text-gray-900 hover:text-purple-600 truncate block">
                                    {{ $story->title }}
                                </a>
                                @if($story->epic)
                                    <span class="text-xs text-purple-600">{{ $story->epic->title }}</span>
                                @endif
                            </div>

                            {{-- Start testing button --}}
                            <form action="{{ route('queue.start-testing', $story) }}" method="POST">
                                @csrf
                                <button type="submit" class="ml-2 bg-blue-500 hover:bg-blue-600 text-white text-xs px-2 py-1 rounded transition">
                                    ▶ Start
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-gray-400">
                        <div class="text-4xl mb-2">✨</div>
                        <p>No stories waiting</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Column 2: In Testing --}}
        <div class="bg-white rounded-lg shadow-sm border">
            <div class="p-4 border-b bg-blue-50">
                <h2 class="font-semibold text-blue-800">🔬 In Testing</h2>
                <p class="text-xs text-blue-600 mt-1">Currently being tested</p>
            </div>

            <div class="divide-y max-h-96 overflow-y-auto">
                @forelse($inTestingStories as $story)
                    <div class="p-3 hover:bg-gray-50 transition">
                        <div class="mb-2">
                            <a href="{{ route('queue.show', $story) }}" class="font-medium text-gray-900 hover:text-purple-600 block truncate">
                                {{ $story->title }}
                            </a>
                            @if($story->epic)
                                <span class="text-xs text-purple-600">{{ $story->epic->title }}</span>
                            @endif
                        </div>

                        {{-- Pass/Fail action buttons --}}
                        <div class="flex gap-2">
                            <form action="{{ route('queue.mark-passed', $story) }}" method="POST" class="flex-1">
                                @csrf
                                <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white text-xs px-2 py-1 rounded transition">
                                    ✅ Pass
                                </button>
                            </form>
                            <form action="{{ route('queue.mark-failed', $story) }}" method="POST" class="flex-1">
                                @csrf
                                <button type="submit" class="w-full bg-red-500 hover:bg-red-600 text-white text-xs px-2 py-1 rounded transition">
                                    ❌ Fail
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-gray-400">
                        <div class="text-4xl mb-2">🔍</div>
                        <p>No active tests</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Column 3: Results (Passed/Failed) --}}
        <div class="space-y-4">
            {{-- Failed stories section --}}
            @if($failedStories->count() > 0)
                <div class="bg-white rounded-lg shadow-sm border">
                    <div class="p-4 border-b bg-red-50">
                        <h2 class="font-semibold text-red-800">❌ Failed - Needs Rework</h2>
                    </div>
                    <div class="divide-y max-h-40 overflow-y-auto">
                        @foreach($failedStories as $story)
                            <div class="p-3 hover:bg-gray-50 transition flex justify-between items-center">
                                <a href="{{ route('queue.show', $story) }}" class="font-medium text-gray-900 hover:text-purple-600 truncate flex-1">
                                    {{ $story->title }}
                                </a>
                                <form action="{{ route('queue.return-to-queue', $story) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="ml-2 bg-gray-500 hover:bg-gray-600 text-white text-xs px-2 py-1 rounded transition">
                                        ↩ Return
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Recently passed stories section --}}
            <div class="bg-white rounded-lg shadow-sm border">
                <div class="p-4 border-b bg-green-50">
                    <h2 class="font-semibold text-green-800">✅ Recently Passed</h2>
                </div>
                <div class="divide-y max-h-48 overflow-y-auto">
                    @forelse($passedStories as $story)
                        <div class="p-3 hover:bg-gray-50 transition">
                            <a href="{{ route('queue.show', $story) }}" class="font-medium text-gray-900 hover:text-purple-600 block truncate">
                                {{ $story->title }}
                            </a>
                            <span class="text-xs text-gray-500">{{ $story->updated_at->diffForHumans() }}</span>
                        </div>
                    @empty
                        <div class="p-6 text-center text-gray-400">
                            <div class="text-4xl mb-2">🎯</div>
                            <p>No passed stories yet</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
