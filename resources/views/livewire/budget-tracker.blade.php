<div class="bg-white rounded-lg shadow p-6">
    <!-- Header with Month/Year Selector -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-900">📊 Budget Tracking</h2>
        
        <div class="flex items-center space-x-2">
            <select wire:model.live="month" class="px-3 py-1 border border-gray-300 rounded text-sm">
                @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}">{{ DateTime::createFromFormat('!m', $m)->format('F') }}</option>
                @endfor
            </select>
            <select wire:model.live="year" class="px-3 py-1 border border-gray-300 rounded text-sm">
                @for($y = now()->year - 2; $y <= now()->year + 1; $y++)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endfor
            </select>
        </div>
    </div>

    <!-- Overall Summary -->
    @if($totalSummary['total_budget'] > 0)
        <div class="bg-gray-50 rounded-lg p-4 mb-6">
            <h3 class="text-lg font-semibold mb-3">{{ $monthName }} Summary</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                <div class="text-center">
                    <p class="text-2xl font-bold text-blue-600">${{ number_format($totalSummary['total_budget'], 2) }}</p>
                    <p class="text-sm text-gray-600">Total Budget</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-orange-600">${{ number_format($totalSummary['total_spent'], 2) }}</p>
                    <p class="text-sm text-gray-600">Total Spent</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold {{ $totalSummary['total_remaining'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        ${{ number_format($totalSummary['total_remaining'], 2) }}
                    </p>
                    <p class="text-sm text-gray-600">Remaining</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold {{ $totalSummary['total_percentage'] <= 100 ? 'text-green-600' : 'text-red-600' }}">
                        {{ number_format($totalSummary['total_percentage'], 1) }}%
                    </p>
                    <p class="text-sm text-gray-600">Used</p>
                </div>
            </div>

            <!-- Overall Progress Bar -->
            <div class="w-full bg-gray-200 rounded-full h-4">
                <div class="h-4 rounded-full transition-all duration-300 {{ $totalSummary['total_percentage'] <= 100 ? 'bg-green-500' : 'bg-red-500' }}" 
                     style="width: {{ min($totalSummary['total_percentage'], 100) }}%"></div>
            </div>

            @if($totalSummary['over_budget_count'] > 0)
                <div class="mt-3 p-2 bg-red-50 border border-red-200 rounded text-sm text-red-700">
                    🚨 {{ $totalSummary['over_budget_count'] }} {{ $totalSummary['over_budget_count'] === 1 ? 'category is' : 'categories are' }} over budget
                </div>
            @endif
        </div>
    @endif

    <!-- Individual Budget Categories -->
    @forelse($budgetData as $item)
        <div class="border rounded-lg p-4 mb-4 {{ $item['is_over_budget'] ? 'border-red-200 bg-red-50' : 'border-gray-200' }}">
            <!-- Category Header -->
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center space-x-3">
                    <span class="text-2xl">{{ $item['category']->icon ?? '💰' }}</span>
                    <div>
                        <h3 class="font-semibold text-gray-900">{{ $item['category']->name }}</h3>
                        @if($item['is_over_budget'])
                            <p class="text-sm text-red-600">Over budget by ${{ number_format($item['over_amount'], 2) }} 🔴</p>
                        @endif
                    </div>
                </div>
                
                <div class="text-right">
                    <p class="text-lg font-semibold {{ $item['remaining'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        ${{ number_format(abs($item['remaining']), 2) }} {{ $item['remaining'] >= 0 ? 'left' : 'over' }}
                    </p>
                    <p class="text-sm text-gray-600">{{ number_format($item['percentage'], 1) }}% used</p>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="mb-3">
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div class="h-3 rounded-full transition-all duration-300 {{ $item['is_over_budget'] ? 'bg-red-500' : ($item['percentage'] > 80 ? 'bg-yellow-500' : 'bg-green-500') }}" 
                         style="width: {{ min($item['percentage'], 100) }}%"></div>
                </div>
            </div>

            <!-- Budget Details -->
            <div class="flex items-center justify-between text-sm text-gray-600">
                <span>Spent: ${{ number_format($item['spent'], 2) }}</span>
                <span>Budget: ${{ number_format($item['budget_amount'], 2) }}</span>
            </div>
        </div>
    @empty
        <div class="text-center py-12">
            <div class="text-gray-400 text-4xl mb-4">📊</div>
            <p class="text-gray-500 text-lg">No budgets set for {{ $monthName }}</p>
            <p class="text-gray-400 text-sm mt-2">Create some budgets to track your spending!</p>
            <button class="mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">
                Set Up Budgets
            </button>
        </div>
    @endforelse

    @if(count($budgetData) > 0)
        <!-- Quick Actions -->
        <div class="mt-6 pt-4 border-t border-gray-200">
            <div class="flex flex-wrap gap-2">
                <button class="px-3 py-1 bg-gray-100 text-gray-700 rounded text-sm hover:bg-gray-200 transition-colors">
                    📝 Edit Budgets
                </button>
                <button class="px-3 py-1 bg-blue-100 text-blue-700 rounded text-sm hover:bg-blue-200 transition-colors">
                    📈 View Trends
                </button>
                <button class="px-3 py-1 bg-green-100 text-green-700 rounded text-sm hover:bg-green-200 transition-colors">
                    💡 Budget Tips
                </button>
            </div>
        </div>
    @endif
</div>