<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\Carbon;

class CsvImportService
{
    public function parseCsvFile(string $filePath): array
    {
        $csvData = [];
        $file = fopen($filePath, 'r');
        
        if ($file) {
            $header = fgetcsv($file);
            
            while (($data = fgetcsv($file)) !== FALSE) {
                $csvData[] = array_combine($header, $data);
            }
            
            fclose($file);
        }
        
        return $csvData;
    }

    public function detectBankFormat(array $headers): string
    {
        $headers = array_map('strtolower', $headers);
        
        // Chase format detection
        if (in_array('posting date', $headers) && in_array('description', $headers) && in_array('amount', $headers)) {
            return 'chase';
        }
        
        // Bank of America format detection
        if (in_array('date', $headers) && in_array('description', $headers) && in_array('amount', $headers) && in_array('running bal.', $headers)) {
            return 'bank_of_america';
        }
        
        return 'generic';
    }

    public function getColumnMappings(string $format): array
    {
        return match($format) {
            'chase' => [
                'date' => 'Posting Date',
                'description' => 'Description', 
                'amount' => 'Amount',
                'category' => 'Category',
            ],
            'bank_of_america' => [
                'date' => 'Date',
                'description' => 'Description',
                'amount' => 'Amount',
                'balance' => 'Running Bal.',
            ],
            default => [
                'date' => 'Date',
                'description' => 'Description',
                'amount' => 'Amount',
            ],
        };
    }

    public function previewTransactions(array $csvData, array $columnMapping, int $accountId): array
    {
        $preview = [];
        
        foreach (array_slice($csvData, 0, 10) as $row) { // Preview first 10
            $amount = $this->parseAmount($row[$columnMapping['amount']] ?? '0');
            $date = $this->parseDate($row[$columnMapping['date']] ?? '');
            
            $preview[] = [
                'date' => $date,
                'description' => $row[$columnMapping['description']] ?? '',
                'amount' => $amount,
                'type' => $amount >= 0 ? 'income' : 'expense',
                'account_id' => $accountId,
            ];
        }
        
        return $preview;
    }

    public function importTransactions(array $csvData, array $columnMapping, int $accountId, int $userId): int
    {
        $imported = 0;
        
        foreach ($csvData as $row) {
            $amount = $this->parseAmount($row[$columnMapping['amount']] ?? '0');
            $date = $this->parseDate($row[$columnMapping['date']] ?? '');
            $description = trim($row[$columnMapping['description']] ?? '');
            
            if (empty($description) || !$date) {
                continue; // Skip invalid rows
            }
            
            // Check for duplicate
            $existing = Transaction::where([
                'user_id' => $userId,
                'account_id' => $accountId,
                'date' => $date,
                'description' => $description,
                'amount' => $amount,
            ])->first();
            
            if (!$existing) {
                Transaction::create([
                    'user_id' => $userId,
                    'account_id' => $accountId,
                    'date' => $date,
                    'description' => $description,
                    'amount' => $amount,
                    'type' => $amount >= 0 ? 'income' : 'expense',
                ]);
                
                $imported++;
            }
        }
        
        return $imported;
    }

    private function parseAmount(string $amount): float
    {
        // Remove currency symbols, commas, and parentheses
        $amount = preg_replace('/[^\d.-]/', '', $amount);
        
        // Handle parentheses as negative (Bank of America format)
        if (strpos($amount, '(') !== false) {
            $amount = '-' . str_replace(['(', ')'], '', $amount);
        }
        
        return (float) $amount;
    }

    private function parseDate(string $date): ?Carbon
    {
        try {
            // Try common date formats
            $formats = [
                'Y-m-d',
                'm/d/Y',
                'm/d/y',
                'd/m/Y',
                'd-m-Y',
                'Y-m-d H:i:s',
            ];
            
            foreach ($formats as $format) {
                try {
                    return Carbon::createFromFormat($format, trim($date));
                } catch (\Exception $e) {
                    continue;
                }
            }
            
            // Fallback to Carbon's parse method
            return Carbon::parse($date);
        } catch (\Exception $e) {
            return null;
        }
    }
}