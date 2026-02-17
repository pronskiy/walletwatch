<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Transactions</h1>
            <p class="text-gray-600">Search and filter your transaction history</p>
        </div>

        <!-- Filter Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                            <span class="text-white text-sm font-bold">#</span>
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500">Total</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ number_format($stats['total_transactions']) }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <span class="text-white text-sm font-bold">+</span>
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500">Income</p>
                        <p class="text-2xl font-semibold text-green-600">${{ number_format($stats['total_income'], 2) }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center">
                            <span class="text-white text-sm font-bold">-</span>
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500">Expenses</p>
                        <p class="text-2xl font-semibold text-red-600">${{ number_format($stats['total_expenses'], 2) }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 {{ $stats['net_amount'] >= 0 ? 'bg-green-500' : 'bg-red-500' }} rounded-full flex items-center justify-center">
                            <span class="text-white text-sm font-bold">{{ $stats['net_amount'] >= 0 ? '=' : '=' }}</span>
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500">Net</p>
                        <p class="text-2xl font-semibold {{ $stats['net_amount'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            ${{ number_format($stats['net_amount'], 2) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Filters</h3>
                    <button wire:click="clearFilters" 
                            class="text-sm text-gray-500 hover:text-gray-700">
                        Clear all filters
                    </button>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <!-- Search -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" 
                               wire:model.live.debounce.300ms="search"
                               placeholder="Description, notes..."
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Account Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Account</label>
                        <select wire:model.live="selectedAccount" 
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All accounts</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Category Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select wire:model.live="selectedCategory" 
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->icon }} {{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Date Range -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" 
                               wire:model.live="startDate"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" 
                               wire:model.live="endDate"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Amount Range -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Min Amount</label>
                        <input type="number" 
                               wire:model.live.debounce.300ms="minAmount"
                               step="0.01"
                               placeholder="0.00"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Max Amount</label>
                        <input type="number" 
                               wire:model.live.debounce.300ms="maxAmount"
                               step="0.01"
                               placeholder="0.00"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th wire:click="sortBy('date')" 
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                                <div class="flex items-center space-x-1">
                                    <span>Date</span>
                                    @if($sortBy === 'date')
                                        <span class="text-blue-500">
                                            @if($sortDirection === 'asc') ↑ @else ↓ @endif
                                        </span>
                                    @endif
                                </div>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Description
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Account
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Category
                            </th>
                            <th wire:click="sortBy('amount')" 
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                                <div class="flex items-center space-x-1">
                                    <span>Amount</span>
                                    @if($sortBy === 'amount')
                                        <span class="text-blue-500">
                                            @if($sortDirection === 'asc') ↑ @else ↓ @endif
                                        </span>
                                    @endif
                                </div>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Type
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($transactions as $transaction)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $transaction->date->format('M j, Y') }}
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="flex items-center space-x-3">
                                        @if($transaction->category)
                                            <span class="text-xl">{{ $transaction->category->icon ?? '💰' }}</span>
                                        @else
                                            <span class="text-xl">💰</span>
                                        @endif
                                        <div>
                                            <div class="font-medium text-gray-900">{{ $transaction->description }}</div>
                                            @if($transaction->notes)
                                                <div class="text-gray-500 text-xs">{{ Str::limit($transaction->notes, 50) }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $transaction->account->name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $transaction->category->name ?? 'Uncategorized' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold {{ $transaction->amount >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $transaction->amount >= 0 ? '+' : '' }}${{ number_format(abs($transaction->amount), 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ 
                                        $transaction->type === 'income' ? 'bg-green-100 text-green-800' : 
                                        ($transaction->type === 'expense' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800') 
                                    }}">
                                        {{ ucfirst($transaction->type) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="text-gray-500">
                                        <div class="text-4xl mb-4">🔍</div>
                                        <h3 class="text-lg font-medium mb-2">No transactions found</h3>
                                        <p>Try adjusting your search criteria or date range.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            @if($transactions->hasPages())
                <div class="bg-white px-4 py-3 border-t border-gray-200">
                    {{ $transactions->links() }}
                </div>
            @endif
        </div>
    </div>
</div>