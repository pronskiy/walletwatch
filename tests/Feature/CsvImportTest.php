<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class CsvImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_csv_import_service_detects_chase_format()
    {
        $service = new CsvImportService();
        
        $chaseHeaders = ['Transaction Date', 'Post Date', 'Description', 'Category', 'Type', 'Amount'];
        $result = $service->detectBankFormat($chaseHeaders);
        
        $this->assertEquals('chase', $result['format']);
        $this->assertEquals('Chase Bank', $result['name']);
        $this->assertGreaterThan(0.5, $result['confidence']);
    }

    public function test_csv_import_service_detects_bank_of_america_format()
    {
        $service = new CsvImportService();
        
        $boaHeaders = ['Date', 'Description', 'Amount', 'Running Bal.'];
        $result = $service->detectBankFormat($boaHeaders);
        
        $this->assertEquals('bank_of_america', $result['format']);
        $this->assertEquals('Bank of America', $result['name']);
        $this->assertGreaterThan(0.5, $result['confidence']);
    }

    public function test_csv_import_service_falls_back_to_generic_format()
    {
        $service = new CsvImportService();
        
        $genericHeaders = ['Some Date', 'Some Description', 'Some Amount'];
        $result = $service->detectBankFormat($genericHeaders);
        
        $this->assertEquals('generic', $result['format']);
        $this->assertEquals('Generic Format', $result['name']);
        $this->assertEquals(0, $result['confidence']);
    }

    public function test_csv_import_service_parses_csv_correctly()
    {
        $service = new CsvImportService();
        
        // Create a temporary CSV file
        $csvContent = "Date,Description,Amount\n2024-01-15,Test Transaction,-50.00\n2024-01-16,Another Transaction,100.00";
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test');
        file_put_contents($tempFile, $csvContent);
        
        $result = $service->parseCsv($tempFile);
        
        $this->assertEquals(['Date', 'Description', 'Amount'], $result['headers']);
        $this->assertEquals(2, $result['row_count']);
        $this->assertEquals('Test Transaction', $result['data'][0]['Description']);
        $this->assertEquals('-50.00', $result['data'][0]['Amount']);
        
        unlink($tempFile);
    }

    public function test_csv_import_service_previews_transactions()
    {
        $service = new CsvImportService();
        
        $csvData = [
            ['Date' => '2024-01-15', 'Description' => 'Grocery Store', 'Amount' => '-45.50'],
            ['Date' => '2024-01-16', 'Description' => 'Salary', 'Amount' => '2000.00'],
        ];
        
        $columnMapping = [
            'date' => 'Date',
            'description' => 'Description',
            'amount' => 'Amount',
        ];
        
        $preview = $service->previewTransactions($csvData, $columnMapping);
        
        $this->assertCount(2, $preview);
        $this->assertEquals('Grocery Store', $preview[0]['description']);
        $this->assertEquals(-45.50, $preview[0]['amount']);
        $this->assertEquals('expense', $preview[0]['type']);
        $this->assertEquals('income', $preview[1]['type']);
    }

    public function test_csv_import_service_imports_transactions()
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $category = Category::factory()->create();
        
        $service = new CsvImportService();
        
        $csvData = [
            ['Date' => '2024-01-15', 'Description' => 'Test Purchase', 'Amount' => '-25.00'],
            ['Date' => '2024-01-16', 'Description' => 'Income', 'Amount' => '100.00'],
        ];
        
        $columnMapping = [
            'date' => 'Date',
            'description' => 'Description',
            'amount' => 'Amount',
        ];
        
        $result = $service->importTransactions(
            $csvData,
            $columnMapping,
            $account->id,
            $user->id,
            $category->id
        );
        
        $this->assertEquals(2, $result['imported']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEmpty($result['errors']);
        
        $this->assertEquals(2, Transaction::count());
        
        $expenseTransaction = Transaction::where('description', 'Test Purchase')->first();
        $this->assertEquals(-25.00, $expenseTransaction->amount);
        $this->assertEquals('expense', $expenseTransaction->type);
        
        $incomeTransaction = Transaction::where('description', 'Income')->first();
        $this->assertEquals(100.00, $incomeTransaction->amount);
        $this->assertEquals('income', $incomeTransaction->type);
    }

    public function test_csv_import_service_prevents_duplicates()
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        
        $service = new CsvImportService();
        
        $csvData = [
            ['Date' => '2024-01-15', 'Description' => 'Duplicate Transaction', 'Amount' => '-30.00'],
        ];
        
        $columnMapping = [
            'date' => 'Date',
            'description' => 'Description',
            'amount' => 'Amount',
        ];
        
        // Import once
        $firstImport = $service->importTransactions(
            $csvData,
            $columnMapping,
            $account->id,
            $user->id
        );
        
        $this->assertEquals(1, $firstImport['imported']);
        $this->assertEquals(0, $firstImport['skipped']);
        $this->assertEquals(1, Transaction::count());
        
        // Try to import the same data again
        $secondImport = $service->importTransactions(
            $csvData,
            $columnMapping,
            $account->id,
            $user->id
        );
        
        $this->assertEquals(0, $secondImport['imported']); // Nothing new imported
        $this->assertEquals(1, $secondImport['skipped']); // Duplicate skipped
        $this->assertEquals(1, Transaction::count()); // Still only 1 total
    }

    public function test_csv_import_component_upload_step()
    {
        $user = User::factory()->create();
        Account::factory()->create(['user_id' => $user->id]);
        
        $this->actingAs($user);
        
        $component = Livewire::test('csv-import');
        
        $component->assertSee('Upload CSV File')
                  ->assertSee('Supported formats');
        
        $this->assertEquals(1, $component->get('currentStep'));
    }

    public function test_csv_import_component_processes_file()
    {
        $user = User::factory()->create();
        Account::factory()->create(['user_id' => $user->id]);
        
        $this->actingAs($user);
        
        // Create a mock CSV file
        $csvContent = "Transaction Date,Description,Amount\n2024-01-15,Test,-50.00";
        $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);
        
        $component = Livewire::test('csv-import');
        
        $component->set('csvFile', $file);
        
        // Should advance to step 2 after processing
        $this->assertEquals(2, $component->get('currentStep'));
        $component->assertSee('Map CSV Columns');
    }

    public function test_csv_import_component_validates_required_fields()
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        
        $this->actingAs($user);
        
        $component = Livewire::test('csv-import');
        
        // Set to step 2 manually
        $component->set('currentStep', 2)
                  ->set('headers', ['Date', 'Description', 'Amount'])
                  ->set('selectedAccount', '') // Clear the default account
                  ->set('columnMapping', ['date' => '', 'description' => '', 'amount' => '']);
        
        // Try to proceed without required fields
        $component->call('nextStep')
                  ->assertHasErrors(['selectedAccount', 'columnMapping.date', 'columnMapping.description', 'columnMapping.amount']);
    }

    public function test_csv_import_component_completes_full_flow()
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        
        $this->actingAs($user);
        
        $component = Livewire::test('csv-import');
        
        // Simulate the full flow
        $csvData = [
            ['Date' => '2024-01-15', 'Description' => 'Test Transaction', 'Amount' => '-25.00'],
        ];
        
        $component->set('currentStep', 2)
                  ->set('csvData', $csvData)
                  ->set('headers', ['Date', 'Description', 'Amount'])
                  ->set('selectedAccount', $account->id)
                  ->set('columnMapping', [
                      'date' => 'Date',
                      'description' => 'Description',
                      'amount' => 'Amount',
                  ]);
        
        // Go to preview
        $component->call('nextStep');
        $this->assertEquals(3, $component->get('currentStep'));
        $component->assertSee('Preview Transactions');
        
        // Confirm import
        $component->call('confirmImport');
        $this->assertEquals(4, $component->get('currentStep'));
        $component->assertSee('Import Complete!');
        
        // Check that transaction was created
        $this->assertEquals(1, Transaction::count());
        
        $transaction = Transaction::first();
        $this->assertEquals('Test Transaction', $transaction->description);
        $this->assertEquals(-25.00, $transaction->amount);
    }
}