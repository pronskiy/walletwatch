<div>
    <label for="category" class="block text-sm font-medium text-gray-700 mb-1">
        Category
        @if($required)
            <span class="text-red-500">*</span>
        @endif
    </label>
    
    <select 
        wire:model.live="selectedCategory"
        id="category"
        name="category_id"
        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('category_id') border-red-300 @enderror"
        @if($required) required @endif
    >
        <option value="">{{ $placeholder }}</option>
        @forelse($categories as $category)
            <option value="{{ $category->id }}">
                {{ $category->icon }} {{ $category->name }}
            </option>
        @empty
            <option value="" disabled>No categories available for {{ ucfirst($transactionType) }}</option>
        @endforelse
    </select>
    
    @error('category_id')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
    
    @if($categories->isEmpty())
        <p class="mt-1 text-sm text-gray-500">
            No {{ $transactionType }} categories found. Please create some categories first.
        </p>
    @endif
</div>