@extends('layouts.app')

@section('title', 'Task Queue')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">⚙️ Task Queue</h1>
        <p class="text-gray-600 mt-1">Automated CCDF task pipeline (execution + QA states)</p>
    </div>

    <div class="grid grid-cols-6 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow-sm border p-4 text-center">
            <span class="text-2xl font-bold text-gray-800">{{ $queuedTasks->count() }}</span>
            <p class="text-gray-500 text-xs mt-1">Queued</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4 text-center">
            <span class="text-2xl font-bold text-blue-600">{{ $runningTasks->count() }}</span>
            <p class="text-gray-500 text-xs mt-1">Running</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4 text-center">
            <span class="text-2xl font-bold text-indigo-600">{{ $awaitingQaTasks->count() }}</span>
            <p class="text-gray-500 text-xs mt-1">Awaiting QA</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4 text-center">
            <span class="text-2xl font-bold text-amber-600">{{ $retryTasks->count() }}</span>
            <p class="text-gray-500 text-xs mt-1">Retry</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4 text-center">
            <span class="text-2xl font-bold text-green-600">{{ $passedTasks->count() }}</span>
            <p class="text-gray-500 text-xs mt-1">Passed (recent)</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4 text-center">
            <span class="text-2xl font-bold text-red-600">{{ $escalatedTasks->count() }}</span>
            <p class="text-gray-500 text-xs mt-1">Exhausted/Escalated</p>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-6">
        <div class="bg-white rounded-lg shadow-sm border">
            <div class="p-4 border-b bg-gray-50">
                <h2 class="font-semibold text-gray-800">📥 Queued</h2>
            </div>
            <div class="divide-y max-h-80 overflow-y-auto">
                @forelse($queuedTasks as $task)
                    <div class="p-3">
                        <p class="font-medium text-gray-900 truncate">{{ $task->title }}</p>
                        <p class="text-xs text-gray-500">Story: {{ $task->story?->title }}</p>
                        <p class="text-xs text-gray-500">Priority: {{ $task->priority }} · Attempt {{ $task->attempt }}/{{ $task->max_attempts }}</p>
                    </div>
                @empty
                    <div class="p-5 text-center text-gray-400">No queued tasks</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border">
            <div class="p-4 border-b bg-blue-50">
                <h2 class="font-semibold text-blue-800">🏃 Running / Awaiting QA</h2>
            </div>
            <div class="divide-y max-h-80 overflow-y-auto">
                @forelse($runningTasks as $task)
                    <div class="p-3">
                        <p class="font-medium text-gray-900 truncate">{{ $task->title }}</p>
                        <p class="text-xs text-blue-600">Running · {{ $task->last_provider ?? 'n/a' }}</p>
                    </div>
                @empty
                @endforelse

                @forelse($awaitingQaTasks as $task)
                    <div class="p-3">
                        <p class="font-medium text-gray-900 truncate">{{ $task->title }}</p>
                        <p class="text-xs text-indigo-600">Awaiting QA · Provider: {{ $task->latestRunRecord?->provider_name ?? $task->last_provider ?? 'n/a' }}</p>
                    </div>
                @empty
                    @if($runningTasks->isEmpty())
                        <div class="p-5 text-center text-gray-400">No active execution/QA tasks</div>
                    @endif
                @endforelse
            </div>
        </div>

        <div class="space-y-4">
            <div class="bg-white rounded-lg shadow-sm border">
                <div class="p-4 border-b bg-amber-50">
                    <h2 class="font-semibold text-amber-800">🔁 Retry</h2>
                </div>
                <div class="divide-y max-h-40 overflow-y-auto">
                    @forelse($retryTasks as $task)
                        <div class="p-3">
                            <p class="font-medium text-gray-900 truncate">{{ $task->title }}</p>
                            <p class="text-xs text-amber-700">Attempt {{ $task->attempt }}/{{ $task->max_attempts }} · Last: {{ $task->last_provider ?? 'n/a' }}</p>
                        </div>
                    @empty
                        <div class="p-5 text-center text-gray-400">No retry tasks</div>
                    @endforelse
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border">
                <div class="p-4 border-b bg-green-50">
                    <h2 class="font-semibold text-green-800">✅ Recently Passed</h2>
                </div>
                <div class="divide-y max-h-40 overflow-y-auto">
                    @forelse($passedTasks as $task)
                        <div class="p-3">
                            <p class="font-medium text-gray-900 truncate">{{ $task->title }}</p>
                            <p class="text-xs text-gray-500">{{ $task->updated_at?->diffForHumans() }}</p>
                        </div>
                    @empty
                        <div class="p-5 text-center text-gray-400">No passed tasks</div>
                    @endforelse
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border">
                <div class="p-4 border-b bg-red-50">
                    <h2 class="font-semibold text-red-800">🚨 Exhausted / Escalated</h2>
                </div>
                <div class="divide-y max-h-40 overflow-y-auto">
                    @forelse($escalatedTasks as $task)
                        <div class="p-3">
                            <p class="font-medium text-gray-900 truncate">{{ $task->title }}</p>
                            <p class="text-xs text-red-700">Status: {{ $task->status?->name ?? 'Unknown' }}</p>
                        </div>
                    @empty
                        <div class="p-5 text-center text-gray-400">No exhausted/escalated tasks</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
