# INCIDENT REPORT — Balance Inconsistencies

**Severity:** P0  
**Date:** Monday morning  
**Reporter:** Support team  

Multiple users reporting incorrect account balances. One user shows -$340 in checking but actual transactions sum to $2,100. Another user transferred money between accounts and the total across both accounts changed (money appeared from nowhere).

We suspect race conditions during concurrent operations. Need tests that PROVE our system maintains financial accuracy under ALL conditions:

1. Account balance ALWAYS equals initial_balance + SUM(income) - SUM(expenses) + SUM(transfers_in) - SUM(transfers_out)
2. Transfers between accounts are atomic — total money across both accounts is preserved
3. Deleting a transaction correctly updates the account balance
4. Editing a transaction amount correctly adjusts the balance
5. Concurrent transaction creation doesn't cause lost updates
6. Bulk import doesn't create phantom money
7. No floating point errors in balance calculations (use cents or bcmath)

This is a financial application. Getting math wrong is unacceptable.

## Critical Requirements

### Mathematical Accuracy
- Balance calculations must be precise to the cent
- No rounding errors that accumulate over time
- Decimal arithmetic must handle edge cases (like $19.99 × 1000)

### Atomicity
- Transfers between accounts must be atomic operations
- Total money in the system is always conserved
- No partial state where one account is debited but the other isn't credited

### Concurrency Safety
- Multiple users creating transactions simultaneously must not cause lost updates
- Database transactions and proper locking must prevent race conditions
- Balance calculations must be consistent under concurrent load

### Data Integrity
- Deleting transactions must correctly adjust account balances
- Editing transaction amounts must apply the correct delta
- Bulk operations must maintain mathematical accuracy

## Examples of Failures We Must Prevent

- User creates transaction for $100, account balance increases by $101 due to floating point error
- Two users transfer money simultaneously, race condition causes money duplication
- Bulk import creates 1000 transactions, balance is off by several cents due to accumulation errors
- Transfer between accounts fails halfway, leaving system in inconsistent state

## Success Criteria

Every test must pass to prove mathematical integrity. Any failure indicates a critical bug that could result in financial losses or regulatory violations.