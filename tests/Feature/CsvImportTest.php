<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CsvImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Account $account;
    private CsvImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->account = Account::factory()->create(['user_id' => $this->user->id]);
        $this->service = new CsvImportService();
    }

    /** @test */
    public function it_can_parse_csv_file()
    {
        // Create a temporary CSV file
        $csvContent = "Date,Description,Amount\n2024-01-01,Test Transaction,100.00\n2024-01-02,Another Transaction,-50.00";
        $tempFile = tempnam(sys_get_temp_dir(), 'test_csv');
        file_put_contents($tempFile, $csvContent);

        $data = $this->service->parseCsvFile($tempFile);

        $this->assertCount(2, $data);
        $this->assertEquals('2024-01-01', $data[0]['Date']);
        $this->assertEquals('Test Transaction', $data[0]['Description']);
        $this->assertEquals('100.00', $data[0]['Amount']);

        unlink($tempFile);
    }

    /** @test */
    public function it_detects_chase_bank_format()
    {
        $headers = ['Posting Date', 'Description', 'Amount', 'Category'];
        
        $format = $this->service->detectBankFormat($headers);
        
        $this->assertEquals('chase', $format);
    }

    /** @test */
    public function it_detects_bank_of_america_format()
    {
        $headers = ['Date', 'Description', 'Amount', 'Running Bal.'];
        
        $format = $this->service->detectBankFormat($headers);
        
        $this->assertEquals('bank_of_america', $format);
    }

    /** @test */
    public function it_returns_generic_format_for_unknown_headers()
    {
        $headers = ['Transaction Date', 'Details', 'Value'];
        
        $format = $this->service->detectBankFormat($headers);
        
        $this->assertEquals('generic', $format);
    }

    /** @test */
    public function it_provides_column_mappings_for_different_formats()
    {
        $chaseMappings = $this->service->getColumnMappings('chase');
        $this->assertEquals('Posting Date', $chaseMappings['date']);
        $this->assertEquals('Description', $chaseMappings['description']);
        $this->assertEquals('Amount', $chaseMappings['amount']);

        $boaMappings = $this->service->getColumnMappings('bank_of_america');
        $this->assertEquals('Date', $boaMappings['date']);
        $this->assertEquals('Running Bal.', $boaMappings['balance']);
    }

    /** @test */
    public function it_previews_transactions_correctly()
    {
        $csvData = [
            ['Date' => '2024-01-01', 'Description' => 'Deposit', 'Amount' => '100.00'],
            ['Date' => '2024-01-02', 'Description' => 'Purchase', 'Amount' => '-50.00'],
        ];
        
        $columnMapping = [
            'date' => 'Date',
            'description' => 'Description',
            'amount' => 'Amount',
        ];

        $preview = $this->service->previewTransactions($csvData, $columnMapping, $this->account->id);

        $this->assertCount(2, $preview);
        $this->assertEquals('income', $preview[0]['type']);
        $this->assertEquals(100.00, $preview[0]['amount']);
        $this->assertEquals('expense', $preview[1]['type']);
        $this->assertEquals(-50.00, $preview[1]['amount']);
    }

    /** @test */
    public function it_imports_transactions_without_duplicates()
    {
        $csvData = [
            ['Date' => '2024-01-01', 'Description' => 'Salary', 'Amount' => '2000.00'],
            ['Date' => '2024-01-02', 'Description' => 'Groceries', 'Amount' => '-150.50'],
            ['Date' => '2024-01-01', 'Description' => 'Salary', 'Amount' => '2000.00'], // Duplicate
        ];
        
        $columnMapping = [
            'date' => 'Date',
            'description' => 'Description',
            'amount' => 'Amount',
        ];

        $this->assertDatabaseCount('transactions', 0);

        $imported = $this->service->importTransactions($csvData, $columnMapping, $this->account->id, $this->user->id);

        $this->assertEquals(2, $imported); // Only 2 imported, duplicate skipped
        $this->assertDatabaseCount('transactions', 2);
        
        // Verify transactions were created correctly
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Salary',
            'amount' => 2000.00,
            'type' => 'income',
        ]);
        
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Groceries',
            'amount' => -150.50,
            'type' => 'expense',
        ]);
    }

    /** @test */
    public function it_handles_different_date_formats()
    {
        $csvData = [
            ['Date' => '01/15/2024', 'Description' => 'Test 1', 'Amount' => '100.00'],
            ['Date' => '2024-01-16', 'Description' => 'Test 2', 'Amount' => '200.00'],
        ];
        
        $columnMapping = [
            'date' => 'Date',
            'description' => 'Description',
            'amount' => 'Amount',
        ];

        $imported = $this->service->importTransactions($csvData, $columnMapping, $this->account->id, $this->user->id);

        $this->assertEquals(2, $imported);
        
        // Both dates should be parsed correctly
        $transactions = Transaction::where('account_id', $this->account->id)->get();
        $this->assertCount(2, $transactions);
    }

    /** @test */
    public function it_handles_parentheses_as_negative_amounts()
    {
        $csvData = [
            ['Date' => '2024-01-01', 'Description' => 'Withdrawal', 'Amount' => '(100.00)'],
            ['Date' => '2024-01-02', 'Description' => 'Fee', 'Amount' => '($25.50)'],
        ];
        
        $columnMapping = [
            'date' => 'Date',
            'description' => 'Description',
            'amount' => 'Amount',
        ];

        $preview = $this->service->previewTransactions($csvData, $columnMapping, $this->account->id);

        $this->assertEquals(-100.00, $preview[0]['amount']);
        $this->assertEquals('expense', $preview[0]['type']);
        $this->assertEquals(-25.50, $preview[1]['amount']);
    }

    /** @test */
    public function it_skips_invalid_rows()
    {
        $csvData = [
            ['Date' => '2024-01-01', 'Description' => 'Valid Transaction', 'Amount' => '100.00'],
            ['Date' => '', 'Description' => 'Invalid - No Date', 'Amount' => '50.00'],
            ['Date' => '2024-01-03', 'Description' => '', 'Amount' => '75.00'], // Invalid - No Description
            ['Date' => 'invalid-date', 'Description' => 'Invalid Date Format', 'Amount' => '25.00'],
        ];
        
        $columnMapping = [
            'date' => 'Date',
            'description' => 'Description',
            'amount' => 'Amount',
        ];

        $imported = $this->service->importTransactions($csvData, $columnMapping, $this->account->id, $this->user->id);

        $this->assertEquals(1, $imported); // Only 1 valid transaction
        $this->assertDatabaseCount('transactions', 1);
        
        $this->assertDatabaseHas('transactions', [
            'description' => 'Valid Transaction',
            'amount' => 100.00,
        ]);
    }
}