<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header name="LLM Usage">
        <x-slot:icon>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
            </svg>
        </x-slot:icon>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand">
        <div class="grid grid-cols-3 gap-px bg-gray-200 dark:bg-gray-700 rounded-lg overflow-hidden mb-3">
            <div class="bg-white dark:bg-gray-800 p-4">
                <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Cost Today</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    ${{ number_format($totalCost, $totalCost > 0 && $totalCost < 0.01 ? 6 : 4) }}
                </p>
            </div>
            <div class="bg-white dark:bg-gray-800 p-4">
                <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Requests</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    {{ number_format($totalRequests) }}
                </p>
            </div>
            <div class="bg-white dark:bg-gray-800 p-4">
                <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Error Rate</p>
                <p class="mt-1 text-2xl font-semibold {{ $errorRate > 5 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100' }}">
                    {{ $errorRate }}%
                </p>
            </div>
        </div>

        @if (count($sparkline) > 0)
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Cost — Last 7 Days</p>
                <div class="flex items-end gap-0.5 h-10">
                    @php $maxCost = max($sparkline) ?: 1; @endphp
                    @foreach ($sparkline as $day => $cost)
                        <div
                            class="flex-1 bg-purple-500 dark:bg-purple-400 rounded-sm min-h-[3px]"
                            style="height: {{ max(3, (int) round($cost / $maxCost * 100)) }}%"
                            title="{{ $day }}: ${{ number_format($cost, 4) }}"
                        ></div>
                    @endforeach
                </div>
            </div>
        @else
            <p class="text-xs text-gray-400 dark:text-gray-500">No LLM activity today.</p>
        @endif
    </x-pulse::scroll>
</x-pulse::card>
