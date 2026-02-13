@extends('layouts.app')

@section('title', $story->title)

@section('content')
<div class="max-w-4xl mx-auto">
    {{-- Back navigation link --}}
    <div class="mb-6">
        <a href="{{ route('queue.index') }}" class="text-purple-600 hover:text-purple-800">
            ← Back to Queue
        </a>
    </div>

    {{-- Story header with title and status --}}
    <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
        <div class="flex justify-between items-start mb-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $story->title }}</h1>
                <p class="text-gray-500 mt-1">Story #{{ $story->id }}</p>
            </div>

            {{-- Status badge with color coding --}}
            @php
                $statusColors = [
                    'draft' => 'bg-gray-100 text-gray-700',
                    'ready' => 'bg-amber-100 text-amber-700',
                    'completed' => 'bg-blue-100 text-blue-700',
                    'in_testing' => 'bg-purple-100 text-purple-700',
                    'passed' => 'bg-green-100 text-green-700',
                    'failed' => 'bg-red-100 text-red-700',
                ];
                $statusColor = $statusColors[$story->status->key ?? 'draft'] ?? 'bg-gray-100 text-gray-700';
            @endphp
            <span class="px-3 py-1 rounded-full text-sm font-medium {{ $statusColor }}">
                {{ $story->status->name ?? 'Unknown' }}
            </span>
        </div>

        {{-- Story metadata badges --}}
        <div class="flex items-center gap-4 text-sm">
            @if($story->epic)
                <span class="bg-purple-100 text-purple-700 px-2 py-1 rounded">
                    📁 {{ $story->epic->title }}
                </span>
            @endif
            @if($story->persona)
                <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded">
                    👤 {{ $story->persona->name }}
                </span>
            @endif
            @if($story->est_points)
                <span class="bg-green-100 text-green-700 px-2 py-1 rounded">
                    {{ $story->est_points }} pts
                </span>
            @endif
        </div>
    </div>

    {{-- User story narrative section --}}
    <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
        <h2 class="font-semibold text-gray-900 mb-3">📖 User Story</h2>
        <div class="text-gray-700 bg-gray-50 p-4 rounded-lg italic">
            {{ $story->narrative }}
        </div>
    </div>

    {{-- Acceptance criteria section --}}
    @if($story->acceptance_criteria)
        <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
            <h2 class="font-semibold text-gray-900 mb-3">✅ Acceptance Criteria</h2>
            <div class="prose prose-sm max-w-none text-gray-700">
                {!! nl2br(e($story->acceptance_criteria)) !!}
            </div>
        </div>
    @endif

    {{-- Action buttons based on current status --}}
    <div class="bg-white rounded-lg shadow-sm border p-6">
        <h2 class="font-semibold text-gray-900 mb-4">🎯 Actions</h2>

        <div class="flex gap-4">
            {{-- Completed status: can start testing --}}
            @if($story->story_status_id === 3)
                <form action="{{ route('queue.start-testing', $story) }}" method="POST">
                    @csrf
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition">
                        ▶ Start Testing
                    </button>
                </form>
            @endif

            {{-- In testing status: can pass or fail --}}
            @if($story->story_status_id === 4)
                <form action="{{ route('queue.mark-passed', $story) }}" method="POST">
                    @csrf
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg transition">
                        ✅ Mark as Passed
                    </button>
                </form>
                <form action="{{ route('queue.mark-failed', $story) }}" method="POST">
                    @csrf
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg transition">
                        ❌ Mark as Failed
                    </button>
                </form>
            @endif

            {{-- Failed status: can return to queue --}}
            @if($story->story_status_id === 6)
                <form action="{{ route('queue.return-to-queue', $story) }}" method="POST">
                    @csrf
                    <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg transition">
                        ↩ Return to Development
                    </button>
                </form>
            @endif

            {{-- Passed status: no actions available --}}
            @if($story->story_status_id === 5)
                <p class="text-green-600">
                    ✅ This story has passed QA testing.
                </p>
            @endif
        </div>
    </div>
</div>
@endsection
