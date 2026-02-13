<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>TheQAQueue - @yield('title', 'Testing Queue')</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Tailwind CSS via CDN for rapid development -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Alpine.js for interactivity -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="font-sans antialiased bg-gray-100 dark:bg-gray-900">
    <div class="min-h-screen">
        {{-- Navigation bar with brand and links --}}
        <nav class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        {{-- Brand logo and name --}}
                        <div class="shrink-0 flex items-center">
                            <a href="/" class="text-xl font-bold text-purple-600 dark:text-purple-400">
                                🧪 TheQAQueue
                            </a>
                        </div>

                        {{-- Navigation links --}}
                        <div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">
                            <a href="{{ route('queue.index') }}" 
                               class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out
                                      {{ request()->routeIs('queue.*') ? 'border-purple-400 text-gray-900 dark:text-gray-100' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-700' }}">
                                Testing Queue
                            </a>
                        </div>
                    </div>

                    {{-- Cross-app navigation links --}}
                    <div class="hidden sm:flex sm:items-center sm:ml-6">
                        <span class="text-gray-400 text-sm mr-4">Go to:</span>
                        <a href="https://stories.elasticgun.com" class="text-sm text-indigo-600 hover:text-indigo-800 mr-4">✍️ Writers</a>
                        <a href="https://dev.elasticgun.com" class="text-sm text-green-600 hover:text-green-800">🛠️ Dev</a>
                    </div>
                </div>
            </div>
        </nav>

        {{-- Page header section --}}
        @hasSection('header')
        <header class="bg-white dark:bg-gray-800 shadow">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                @yield('header')
            </div>
        </header>
        @endif

        {{-- Main page content area --}}
        <main class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                {{-- Success message display --}}
                @if (session('success'))
                    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                        {{ session('success') }}
                    </div>
                @endif

                {{-- Error message display --}}
                @if (session('error'))
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                        {{ session('error') }}
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
