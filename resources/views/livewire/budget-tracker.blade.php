<div>
    <div class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-2xl font-bold text-gray-900">Monthly Budget Tracking</h2>
            <p class="text-sm text-gray-500">{{ $currentMonth->format('F Y') }}</p>
        </div>

        @forelse($budgetData as $data)
            <div class="bg-white rounded-lg shadow p-6 mb-4 {{ $data['isOverBudget'] ? 'ring-2 ring-red-500 ring-opacity-50' : '' }}">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center space-x-3">
                        <span class="text-2xl">{{ $data['category']->icon ?? '💰' }}</span>
                        <div>
                            <h3 class="font-semibold text-gray-900">{{ $data['category']->name }}</h3>
                            @if($data['isOverBudget'])
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    🔴 Over Budget
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-bold {{ $data['isOverBudget'] ? 'text-red-600' : 'text-gray-900' }}">
                            ${{ number_format($data['spent'], 2) }} / ${{ number_format($data['budget']->amount, 2) }}
                        </p>
                        <p class="text-sm {{ $data['remaining'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $data['remaining'] >= 0 ? 'Remaining:' : 'Over by:' }} ${{ number_format(abs($data['remaining']), 2) }}
                        </p>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                    <div class="h-3 rounded-full transition-all duration-300 ease-out {{ $data['isOverBudget'] ? 'bg-red-500' : 'bg-blue-500' }}"
                         style="width: {{ $data['percentage'] }}%">
                    </div>
                </div>

                <div class="flex justify-between mt-2 text-sm text-gray-600">
                    <span>{{ number_format($data['percentage'], 1) }}% used</span>
                    @if($data['percentage'] > 100)
                        <span class="text-red-600 font-medium">{{ number_format($data['percentage'] - 100, 1) }}% over</span>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-gray-50 rounded-lg p-8 text-center">
                <div class="text-gray-400 text-6xl mb-4">📊</div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No budgets set</h3>
                <p class="text-gray-500">Create monthly budgets for your categories to track your spending.</p>
            </div>
        @endforelse
    </div>
</div>