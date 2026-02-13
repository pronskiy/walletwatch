# Feature Request: Transaction Categorization Dropdown

## Overview
Add transaction categorization with a Category model relationship. Create a Livewire dropdown component that loads categories by type (income/expense based on transaction type). Include validation — category_id must exist and match the transaction type. Update the transaction creation form to include this dropdown. Add a test that verifies category filtering by type.

## Technical Requirements

### 1. Category Model Enhancements
- Ensure Category model has `type` field (income/expense) 
- Add relationship methods to Transaction model for category
- Create default categories if they don't exist

### 2. Livewire Component Development
- Create `CategoryDropdown` Livewire component
- Component should accept `transactionType` parameter
- Filter categories based on transaction type
- Emit events when category is selected
- Handle loading states and empty states

### 3. Form Integration
- Update transaction creation/edit forms to include category dropdown
- Dropdown should dynamically update when transaction type changes
- Maintain form state consistency
- Handle validation errors gracefully

### 4. Validation Rules
- Add `category_id` validation rule to Transaction requests
- Ensure selected category exists in database
- Validate that category type matches transaction type
- Provide meaningful error messages

### 5. Testing Requirements
- Unit tests for Category model relationships
- Feature tests for transaction creation with categories
- Component tests for CategoryDropdown functionality
- Integration tests for type-based category filtering

## Acceptance Criteria
- [ ] Category dropdown appears on transaction forms
- [ ] Categories are filtered by transaction type (income/expense)
- [ ] Validation prevents mismatched category types
- [ ] Form maintains state when switching transaction types
- [ ] All tests pass
- [ ] Database migrations run successfully

## Technical Considerations
- Use Livewire's reactive properties for type filtering
- Implement proper error handling and user feedback
- Ensure accessibility standards are met
- Consider performance implications of category queries