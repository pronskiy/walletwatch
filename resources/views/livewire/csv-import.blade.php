<div class="max-w-4xl mx-auto py-6">
    <div class="bg-white shadow-lg rounded-lg">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-2xl font-bold text-gray-900">CSV Bank Statement Import</h2>
            <p class="text-gray-600 mt-1">Import transactions from your bank's CSV export</p>
        </div>

        <!-- Progress Steps -->
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <!-- Step 1 -->
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium {{ $currentStep >= 1 ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-600' }}">
                            1
                        </div>
                        <span class="ml-2 text-sm {{ $currentStep >= 1 ? 'text-blue-600 font-medium' : 'text-gray-500' }}">Upload</span>
                    </div>
                    
                    <div class="w-8 h-px {{ $currentStep > 1 ? 'bg-blue-500' : 'bg-gray-200' }}"></div>
                    
                    <!-- Step 2 -->
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium {{ $currentStep >= 2 ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-600' }}">
                            2
                        </div>
                        <span class="ml-2 text-sm {{ $currentStep >= 2 ? 'text-blue-600 font-medium' : 'text-gray-500' }}">Map</span>
                    </div>
                    
                    <div class="w-8 h-px {{ $currentStep > 2 ? 'bg-blue-500' : 'bg-gray-200' }}"></div>
                    
                    <!-- Step 3 -->
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium {{ $currentStep >= 3 ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-600' }}">
                            3
                        </div>
                        <span class="ml-2 text-sm {{ $currentStep >= 3 ? 'text-blue-600 font-medium' : 'text-gray-500' }}">Preview</span>
                    </div>
                    
                    <div class="w-8 h-px {{ $currentStep > 3 ? 'bg-blue-500' : 'bg-gray-200' }}"></div>
                    
                    <!-- Step 4 -->
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium {{ $currentStep >= 4 ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600' }}">
                            ✓
                        </div>
                        <span class="ml-2 text-sm {{ $currentStep >= 4 ? 'text-green-600 font-medium' : 'text-gray-500' }}">Complete</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="px-6 py-6">
            @if($currentStep === 1)
                <!-- Step 1: Upload -->
                <div class="text-center">
                    <div class="mb-6">
                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                    
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Upload CSV File</h3>
                    <p class="text-gray-600 mb-6">Select your bank's CSV export file to import transactions</p>
                    
                    <div class="max-w-md mx-auto">
                        <div class="mb-4">
                            <input type="file" wire:model="csvFile" accept=".csv,.txt" 
                                   class="block w-full text-sm text-gray-500
                                          file:mr-4 file:py-2 file:px-4
                                          file:rounded-lg file:border-0
                                          file:text-sm file:font-medium
                                          file:bg-blue-50 file:text-blue-700
                                          hover:file:bg-blue-100">
                        </div>
                        
                        @error('csvFile') 
                            <p class="text-red-500 text-sm">{{ $message }}</p>
                        @enderror
                        
                        <div wire:loading wire:target="csvFile" class="text-blue-600 text-sm mt-2">
                            Processing file...
                        </div>
                    </div>
                    
                    <div class="mt-6 p-4 bg-blue-50 rounded-lg text-left max-w-md mx-auto">
                        <h4 class="font-medium text-blue-900 mb-2">Supported formats:</h4>
                        <ul class="text-sm text-blue-700 space-y-1">
                            <li>• Chase Bank CSV exports</li>
                            <li>• Bank of America CSV exports</li>
                            <li>• Generic CSV with date, description, amount columns</li>
                        </ul>
                    </div>
                </div>

            @elseif($currentStep === 2)
                <!-- Step 2: Column Mapping -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Map CSV Columns</h3>
                    
                    @if(!empty($detectedFormat))
                        <div class="mb-6 p-4 {{ $detectedFormat['confidence'] > 0 ? 'bg-green-50 border border-green-200' : 'bg-yellow-50 border border-yellow-200' }} rounded-lg">
                            <div class="flex items-center">
                                <span class="text-lg mr-2">{{ $detectedFormat['confidence'] > 0 ? '✅' : '⚠️' }}</span>
                                <div>
                                    <p class="font-medium {{ $detectedFormat['confidence'] > 0 ? 'text-green-800' : 'text-yellow-800' }}">
                                        {{ $detectedFormat['confidence'] > 0 ? 'Format Detected' : 'Generic Format' }}
                                    </p>
                                    <p class="text-sm {{ $detectedFormat['confidence'] > 0 ? 'text-green-600' : 'text-yellow-600' }}">
                                        {{ $detectedFormat['name'] }}
                                        @if($detectedFormat['confidence'] > 0)
                                            ({{ number_format($detectedFormat['confidence'] * 100) }}% confidence)
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Account</label>
                            <select wire:model="selectedAccount" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                <option value="">Select account...</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->name }} ({{ ucfirst($account->type) }})</option>
                                @endforeach
                            </select>
                            @error('selectedAccount') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Default Category (Optional)</label>
                            <select wire:model="defaultCategory" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                <option value="">No default category</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->icon }} {{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <h4 class="text-md font-medium text-gray-900 mb-4">Column Mapping</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Date Column *</label>
                                <select wire:model="columnMapping.date" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                    <option value="">Select column...</option>
                                    @foreach($headers as $header)
                                        <option value="{{ $header }}">{{ $header }}</option>
                                    @endforeach
                                </select>
                                @error('columnMapping.date') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Description Column *</label>
                                <select wire:model="columnMapping.description" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                    <option value="">Select column...</option>
                                    @foreach($headers as $header)
                                        <option value="{{ $header }}">{{ $header }}</option>
                                    @endforeach
                                </select>
                                @error('columnMapping.description') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Amount Column *</label>
                                <select wire:model="columnMapping.amount" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                    <option value="">Select column...</option>
                                    @foreach($headers as $header)
                                        <option value="{{ $header }}">{{ $header }}</option>
                                    @endforeach
                                </select>
                                @error('columnMapping.amount') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                </div>

            @elseif($currentStep === 3)
                <!-- Step 3: Preview -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Preview Transactions</h3>
                    <p class="text-gray-600 mb-6">Review the first 10 transactions before importing</p>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($previewData as $preview)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $preview['date'] ? $preview['date']->format('M j, Y') : 'Invalid Date' }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">{{ $preview['description'] }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium {{ $preview['amount'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $preview['amount'] >= 0 ? '+' : '' }}${{ number_format(abs($preview['amount']), 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 capitalize">
                                            {{ $preview['type'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-4 text-sm text-gray-600">
                        Showing first 10 rows. Total rows to import: {{ count($csvData) }}
                    </div>
                </div>

            @elseif($currentStep === 4)
                <!-- Step 4: Complete -->
                <div class="text-center">
                    <div class="mb-6">
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                    </div>
                    
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Import Complete!</h3>
                    
                    <div class="max-w-md mx-auto">
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-green-700">Imported:</span>
                                    <span class="font-medium text-green-900">{{ $importResults['imported'] ?? 0 }} transactions</span>
                                </div>
                                @if(($importResults['skipped'] ?? 0) > 0)
                                    <div class="flex justify-between">
                                        <span class="text-green-700">Skipped (duplicates):</span>
                                        <span class="font-medium text-green-900">{{ $importResults['skipped'] }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                        
                        @if(!empty($importResults['errors']))
                            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                                <h4 class="font-medium text-red-900 mb-2">Errors:</h4>
                                <ul class="text-sm text-red-700 space-y-1">
                                    @foreach(array_slice($importResults['errors'], 0, 5) as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                    @if(count($importResults['errors']) > 5)
                                        <li class="italic">... and {{ count($importResults['errors']) - 5 }} more</li>
                                    @endif
                                </ul>
                            </div>
                        @endif
                        
                        <button wire:click="resetImport" 
                                class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                            Import Another File
                        </button>
                    </div>
                </div>
            @endif
        </div>

        <!-- Footer with action buttons -->
        @if($currentStep < 4)
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-between">
                <div>
                    @if($currentStep > 1)
                        <button wire:click="previousStep" 
                                class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition-colors">
                            Previous
                        </button>
                    @endif
                </div>
                
                <div>
                    @if($currentStep === 2)
                        <button wire:click="nextStep" 
                                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                            Preview
                        </button>
                    @elseif($currentStep === 3)
                        <button wire:click="confirmImport" 
                                class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                            Import {{ count($csvData) }} Transactions
                        </button>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>