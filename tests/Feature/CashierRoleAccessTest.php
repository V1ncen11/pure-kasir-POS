<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CashierRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_dashboard_and_history_only_include_their_own_transactions_today(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $otherCashier = User::factory()->create(['role' => 'kasir']);
        $ownTransaction = $this->createTransaction($cashier, 'OWN-001', 120_000);
        $otherTransaction = $this->createTransaction($otherCashier, 'OTHER-001', 400_000);
        $voidedTransaction = $this->createTransaction($cashier, 'OWN-VOID-001', 50_000, 'voided');

        TransactionItem::create([
            'transaction_id' => $ownTransaction->id,
            'product_name' => 'Produk Uji',
            'product_sku' => 'QA-001',
            'price' => 60_000,
            'qty' => 2,
            'discount' => 0,
            'subtotal' => 120_000,
        ]);
        TransactionItem::create([
            'transaction_id' => $otherTransaction->id,
            'product_name' => 'Produk Kasir Lain',
            'product_sku' => 'QA-002',
            'price' => 400_000,
            'qty' => 1,
            'discount' => 0,
            'subtotal' => 400_000,
        ]);

        $this->actingAs($cashier)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.today_sales', 120_000)
                ->where('stats.today_transactions', 1)
                ->where('stats.today_items', 2)
                ->has('recentTransactions', 1)
                ->where('recentTransactions.0.invoice_number', 'OWN-001')
            );

        $this->actingAs($cashier)
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('transactions.data', 2)
                ->where('transactions.data', fn ($transactions) => collect($transactions)
                    ->pluck('invoice_number')
                    ->sort()
                    ->values()
                    ->all() === collect([$ownTransaction->invoice_number, $voidedTransaction->invoice_number])
                        ->sort()
                        ->values()
                        ->all()
                )
            );
    }

    public function test_cashier_can_view_own_transaction_but_not_another_cashiers_transaction(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $otherCashier = User::factory()->create(['role' => 'kasir']);
        $ownTransaction = $this->createTransaction($cashier, 'OWN-DETAIL-001', 20_000);
        $otherTransaction = $this->createTransaction($otherCashier, 'OTHER-DETAIL-001', 30_000);

        $this->actingAs($cashier)
            ->get(route('transactions.show', $ownTransaction))
            ->assertOk();

        $this->get(route('transactions.show', $otherTransaction))
            ->assertForbidden();
    }

    public function test_cashier_cannot_access_admin_management_pages_or_void_transactions(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $transaction = $this->createTransaction($cashier, 'OWN-VOID-AUTH-001', 20_000);

        $this->actingAs($cashier);

        foreach ([
            'products.index',
            'categories.index',
            'reports.index',
            'users.index',
            'customers.index',
            'suppliers.index',
            'satuan.index',
            'purchases.index',
            'settings.index',
            'activity-logs.index',
        ] as $routeName) {
            $this->get(route($routeName))->assertForbidden();
        }

        $this->post(route('transactions.void', $transaction), ['password' => 'password'])
            ->assertForbidden();
    }

    public function test_admin_can_view_transactions_from_all_cashiers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cashier = User::factory()->create(['role' => 'kasir']);
        $transaction = $this->createTransaction($cashier, 'CASHIER-DETAIL-001', 30_000);

        $this->actingAs($admin)
            ->get(route('transactions.show', $transaction))
            ->assertOk();
    }

    private function createTransaction(User $cashier, string $invoice, int $total, string $status = 'completed'): Transaction
    {
        return Transaction::create([
            'invoice_number' => $invoice,
            'cashier_id' => $cashier->id,
            'subtotal' => $total,
            'discount' => 0,
            'tax' => 0,
            'total' => $total,
            'paid_amount' => $total,
            'change_amount' => 0,
            'payment_method' => 'cash',
            'status' => $status,
        ]);
    }
}
