<?php

namespace App\Livewire;

use App\Models\Account;
use App\Models\Category;
use App\Services\CsvImportService;
use Livewire\Component;
use Livewire\WithFileUploads;

class CsvImport extends Component
{
    use WithFileUploads;

    public $csvFile;
    public $currentStep = 1;
    public $csvData = [];
    public $headers = [];
    public $detectedFormat = [];
    public $columnMapping = [
        'date' => '',
        'description' => '',
        'amount' => '',
    ];
    public $selectedAccount = '';
    public $defaultCategory = '';
    public $previewData = [];
    public $importResults = [];

    protected $rules = [
        'csvFile' => 'required|file|mimes:csv,txt|max:2048',
        'selectedAccount' => 'required|exists:accounts,id',
        'columnMapping.date' => 'required',
        'columnMapping.description' => 'required',
        'columnMapping.amount' => 'required',
    ];

    public function mount()
    {
        $this->selectedAccount = auth()->user()->accounts()->first()?->id ?? '';
    }

    public function updatedCsvFile()
    {
        $this->validate(['csvFile' => 'required|file|mimes:csv,txt|max:2048']);
        $this->processFile();
    }

    public function processFile()
    {
        if (!$this->csvFile) return;

        $csvImportService = new CsvImportService();
        
        // Parse CSV
        $result = $csvImportService->parseCsv($this->csvFile->getRealPath());
        $this->headers = $result['headers'];
        $this->csvData = $result['data'];

        // Detect bank format
        $this->detectedFormat = $csvImportService->detectBankFormat($this->headers);
        
        // Apply suggested mapping if available
        if (!empty($this->detectedFormat['suggested_mapping'])) {
            foreach ($this->detectedFormat['suggested_mapping'] as $field => $header) {
                if (in_array($header, $this->headers)) {
                    $this->columnMapping[$field] = $header;
                }
            }
        }

        $this->currentStep = 2;
    }

    public function nextStep()
    {
        if ($this->currentStep === 2) {
            $this->validate([
                'selectedAccount' => 'required|exists:accounts,id',
                'columnMapping.date' => 'required',
                'columnMapping.description' => 'required',
                'columnMapping.amount' => 'required',
            ]);

            $this->generatePreview();
            $this->currentStep = 3;
        }
    }

    public function previousStep()
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function generatePreview()
    {
        $csvImportService = new CsvImportService();
        $this->previewData = $csvImportService->previewTransactions($this->csvData, $this->columnMapping);
    }

    public function confirmImport()
    {
        $csvImportService = new CsvImportService();
        
        $this->importResults = $csvImportService->importTransactions(
            $this->csvData,
            $this->columnMapping,
            $this->selectedAccount,
            auth()->id(),
            $this->defaultCategory ?: null
        );

        $this->currentStep = 4;
    }

    public function resetImport()
    {
        $this->reset([
            'csvFile', 'currentStep', 'csvData', 'headers', 'detectedFormat',
            'columnMapping', 'previewData', 'importResults'
        ]);
        $this->selectedAccount = auth()->user()->accounts()->first()?->id ?? '';
        $this->columnMapping = ['date' => '', 'description' => '', 'amount' => ''];
    }

    public function render()
    {
        $accounts = Account::where('user_id', auth()->id())->get();
        $categories = Category::all();
        
        return view('livewire.csv-import', compact('accounts', 'categories'));
    }
}