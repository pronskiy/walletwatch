<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create demo user
        $demoUser = User::create([
            'name' => 'Demo User',
            'email' => 'demo@walletwatch.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        // Create 2 accounts
        $checkingAccount = Account::create([
            'name' => 'Main Checking',
            'type' => 'checking',
            'currency' => 'USD',
            'initial_balance' => 5000.00,
            'user_id' => $demoUser->id,
        ]);

        $savingsAccount = Account::create([
            'name' => 'Emergency Savings',
            'type' => 'savings',
            'currency' => 'USD',
            'initial_balance' => 10000.00,
            'user_id' => $demoUser->id,
        ]);

        // Get some categories for transactions
        $expenseCategories = Category::where('type', 'expense')->where('is_default', true)->get();
        $incomeCategories = Category::where('type', 'income')->where('is_default', true)->get();

        // Create ~100 transactions spread over 6 months
        $accounts = [$checkingAccount, $savingsAccount];
        
        for ($i = 0; $i < 100; $i++) {
            $account = $accounts[array_rand($accounts)];
            $type = rand(1, 10) <= 7 ? 'expense' : 'income'; // 70% expenses, 30% income
            $categories = $type === 'expense' ? $expenseCategories : $incomeCategories;
            $category = $categories->random();

            $amount = $type === 'expense' 
                ? -rand(10, 500) 
                : rand(100, 2000);

            Transaction::create([
                'amount' => $amount,
                'description' => $this->getRandomDescription($category->name, $type),
                'date' => now()->subDays(rand(1, 180)), // Random date within 6 months
                'type' => $type,
                'account_id' => $account->id,
                'category_id' => $category->id,
                'user_id' => $demoUser->id,
                'notes' => rand(1, 3) === 1 ? 'Demo note for this transaction' : null,
            ]);
        }

        // Create 3 budgets
        $budgetCategories = $expenseCategories->random(3);
        foreach ($budgetCategories as $category) {
            Budget::create([
                'category_id' => $category->id,
                'user_id' => $demoUser->id,
                'amount' => rand(200, 1000),
                'period' => rand(1, 2) === 1 ? 'monthly' : 'weekly',
                'start_date' => now()->startOfMonth(),
            ]);
        }

        // Create 5 tags
        $tagNames = ['work', 'personal', 'urgent', 'recurring', 'vacation'];
        foreach ($tagNames as $tagName) {
            Tag::create([
                'name' => $tagName,
                'user_id' => $demoUser->id,
            ]);
        }
    }

    private function getRandomDescription(string $categoryName, string $type): string
    {
        $descriptions = [
            'Food' => ['Grocery shopping', 'Restaurant dinner', 'Coffee shop', 'Food delivery'],
            'Transport' => ['Gas station', 'Bus ticket', 'Uber ride', 'Car maintenance'],
            'Entertainment' => ['Movie tickets', 'Concert', 'Streaming service', 'Video game'],
            'Shopping' => ['Clothing store', 'Online purchase', 'Electronics', 'Home goods'],
            'Salary' => ['Monthly salary', 'Bonus payment', 'Overtime pay'],
            'Freelance' => ['Client project', 'Consultation fee', 'Design work'],
        ];

        $categoryDescriptions = $descriptions[$categoryName] ?? ['Transaction for ' . $categoryName];
        return $categoryDescriptions[array_rand($categoryDescriptions)];
    }
}
