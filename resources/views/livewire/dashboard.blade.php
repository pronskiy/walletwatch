<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
            <p class="text-gray-600">Welcome back! Here's your financial overview.</p>
        </div>

        <!-- Total Balance and Monthly Summary -->
        <div class="mb-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Total Balance Card -->
            <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg shadow-lg p-6 text-white">
                <h2 class="text-lg font-medium mb-2">Total Balance</h2>
                <p class="text-3xl font-bold">${{ number_format($totalBalance, 2) }}</p>
            </div>

            <!-- Monthly Summary Card -->
            <div class="bg-gradient-to-r from-green-500 to-teal-600 rounded-lg shadow-lg p-6 text-white">
                <h2 class="text-lg font-medium mb-2">This Month</h2>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span>Income:</span>
                        <span class="font-bold">${{ number_format($monthlySummary['total_income'] ?? 0, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Expenses:</span>
                        <span class="font-bold">${{ number_format($monthlySummary['total_expenses'] ?? 0, 2) }}</span>
                    </div>
                    <div class="flex justify-between border-t border-white border-opacity-30 pt-2">
                        <span>Net:</span>
                        <span class="font-bold text-xl">
                            ${{ number_format($monthlySummary['net_amount'] ?? 0, 2) }}
                        </span>
                    </div>
                    <div class="text-sm opacity-90">
                        {{ $monthlySummary['transaction_count'] ?? 0 }} transactions
                    </div>
                </div>
            </div>
        </div>

        <!-- Accounts Summary -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Your Accounts</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse($accounts as $account)
                    <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-semibold text-gray-900">{{ $account->name }}</h3>
                                <p class="text-sm text-gray-600 capitalize">{{ $account->type }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-bold {{ $account->current_balance >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    ${{ number_format($account->current_balance, 2) }}
                                </p>
                                <p class="text-xs text-gray-500">{{ $account->currency }}</p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full text-center py-8">
                        <p class="text-gray-500">No accounts found. Create your first account to get started!</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Recent Transactions -->
        <div>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-2xl font-bold text-gray-900">Recent Transactions</h2>
                <a href="{{ route('transactions') }}" class="text-blue-600 hover:text-blue-800 font-medium">View All</a>
            </div>
            
            <div class="bg-white rounded-lg shadow overflow-hidden">
                @forelse($recentTransactions as $transaction)
                    <div class="px-6 py-4 border-b border-gray-200 hover:bg-gray-50">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    @if($transaction->category)
                                        <span class="text-2xl">{{ $transaction->category->icon ?? '💰' }}</span>
                                    @else
                                        <span class="text-2xl">💰</span>
                                    @endif
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">{{ $transaction->description }}</p>
                                    <div class="flex items-center space-x-2 text-sm text-gray-500">
                                        <span>{{ $transaction->account->name }}</span>
                                        <span>•</span>
                                        <span>{{ $transaction->category->name ?? 'Uncategorized' }}</span>
                                        <span>•</span>
                                        <span>{{ $transaction->date->format('M j, Y') }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-bold {{ $transaction->amount >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $transaction->amount >= 0 ? '+' : '' }}${{ number_format(abs($transaction->amount), 2) }}
                                </p>
                                <p class="text-xs text-gray-500 capitalize">{{ $transaction->type }}</p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-8 text-center">
                        <p class="text-gray-500">No transactions found. Add your first transaction to see it here!</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
