<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class CsvImportService
{
    protected array $bankFormats = [
        'chase' => [
            'name' => 'Chase Bank',
            'expected_headers' => ['Transaction Date', 'Post Date', 'Description', 'Category', 'Type', 'Amount'],
            'mapping' => [
                'date' => 'Transaction Date',
                'description' => 'Description',
                'amount' => 'Amount',
                'type' => 'Type',
                'category' => 'Category',
            ],
        ],
        'bank_of_america' => [
            'name' => 'Bank of America',
            'expected_headers' => ['Date', 'Description', 'Amount', 'Running Bal.'],
            'mapping' => [
                'date' => 'Date',
                'description' => 'Description',
                'amount' => 'Amount',
            ],
        ],
        'generic' => [
            'name' => 'Generic Format',
            'expected_headers' => [],
            'mapping' => [],
        ],
    ];

    public function detectBankFormat(array $headers): array
    {
        foreach ($this->bankFormats as $formatKey => $format) {
            if ($formatKey === 'generic') continue;
            
            $matchingHeaders = array_intersect($format['expected_headers'], $headers);
            $matchScore = count($matchingHeaders) / count($format['expected_headers']);
            
            if ($matchScore >= 0.6) { // 60% match threshold
                return [
                    'format' => $formatKey,
                    'name' => $format['name'],
                    'confidence' => $matchScore,
                    'suggested_mapping' => $format['mapping'],
                ];
            }
        }

        return [
            'format' => 'generic',
            'name' => 'Generic Format',
            'confidence' => 0,
            'suggested_mapping' => [],
        ];
    }

    public function parseCsv(string $filePath): array
    {
        $csvData = [];
        $headers = [];
        
        if (($handle = fopen($filePath, 'r')) !== false) {
            // Read headers
            if (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $headers = array_map('trim', $data);
            }
            
            // Read data rows
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                if (count($data) === count($headers)) {
                    $csvData[] = array_combine($headers, $data);
                }
            }
            fclose($handle);
        }

        return [
            'headers' => $headers,
            'data' => $csvData,
            'row_count' => count($csvData),
        ];
    }

    public function previewTransactions(array $csvData, array $columnMapping): array
    {
        $previews = [];
        
        foreach ($csvData as $index => $row) {
            if ($index >= 10) break; // Only preview first 10 rows
            
            $preview = [
                'row_index' => $index,
                'date' => $this->parseDate($row[$columnMapping['date']] ?? ''),
                'description' => trim($row[$columnMapping['description']] ?? ''),
                'amount' => $this->parseAmount($row[$columnMapping['amount']] ?? ''),
                'original_row' => $row,
            ];
            
            // Determine transaction type
            $preview['type'] = $preview['amount'] < 0 ? 'expense' : 'income';
            
            $previews[] = $preview;
        }
        
        return $previews;
    }

    public function importTransactions(
        array $csvData,
        array $columnMapping,
        int $accountId,
        int $userId,
        ?int $defaultCategoryId = null
    ): array {
        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($csvData as $index => $row) {
            try {
                $date = $this->parseDate($row[$columnMapping['date']] ?? '');
                $description = trim($row[$columnMapping['description']] ?? '');
                $amount = $this->parseAmount($row[$columnMapping['amount']] ?? '');
                
                if (!$date || !$description || $amount === null) {
                    $errors[] = "Row " . ($index + 1) . ": Missing required data";
                    continue;
                }
                
                // Check for duplicate
                $exists = Transaction::where('user_id', $userId)
                    ->where('account_id', $accountId)
                    ->where('date', $date)
                    ->where('description', $description)
                    ->where('amount', $amount)
                    ->exists();
                
                if ($exists) {
                    $skipped++;
                    continue;
                }
                
                Transaction::create([
                    'user_id' => $userId,
                    'account_id' => $accountId,
                    'category_id' => $defaultCategoryId,
                    'date' => $date,
                    'description' => $description,
                    'amount' => $amount,
                    'type' => $amount < 0 ? 'expense' : 'income',
                ]);
                
                $imported++;
                
            } catch (\Exception $e) {
                $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
            }
        }
        
        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    protected function parseDate(string $dateString): ?Carbon
    {
        $dateString = trim($dateString);
        if (empty($dateString)) return null;
        
        $formats = [
            'Y-m-d',
            'm/d/Y',
            'm/d/y',
            'd/m/Y',
            'd/m/y',
            'M d, Y',
        ];
        
        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $dateString);
            } catch (\Exception $e) {
                continue;
            }
        }
        
        // Try general parsing as last resort
        try {
            return Carbon::parse($dateString);
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function parseAmount(string $amountString): ?float
    {
        $amountString = trim($amountString);
        if (empty($amountString)) return null;
        
        // Remove currency symbols and whitespace
        $cleaned = preg_replace('/[^\d\.\-,]/', '', $amountString);
        
        // Handle different decimal separators
        if (substr_count($cleaned, '.') === 1 && substr_count($cleaned, ',') === 0) {
            // Standard format: 1234.56
            return (float) $cleaned;
        } elseif (substr_count($cleaned, ',') === 1 && substr_count($cleaned, '.') === 0) {
            // European format: 1234,56
            return (float) str_replace(',', '.', $cleaned);
        } else {
            // Remove commas and treat as thousands separator
            $cleaned = str_replace(',', '', $cleaned);
            return (float) $cleaned;
        }
    }
}