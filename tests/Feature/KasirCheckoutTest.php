<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use App\Models\StockLog;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KasirCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_uses_database_price_and_updates_stock_atomically(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $product = Product::create([
            'name' => 'Teh Botol',
            'sku' => 'TEH-001',
            'unit' => 'pcs',
            'buy_price' => 50,
            'sell_price' => 100,
            'stock' => 5,
            'is_active' => true,
        ]);
        Setting::set('tax_percentage', '10');
        $this->actingAs($cashier);

        $response = $this->postJson(route('kasir.store'), [
            'items' => [[
                'product_id' => $product->id,
                'qty' => 2,
                'price' => 1,
                'discount' => 0,
            ]],
            'discount' => 20,
            'tax' => 0,
            'paid_amount' => 200,
            'payment_method' => 'cash',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $transaction = Transaction::with('items')->firstOrFail();
        $this->assertSame(200.0, (float) $transaction->subtotal);
        $this->assertSame(20.0, (float) $transaction->discount);
        $this->assertSame(18.0, (float) $transaction->tax);
        $this->assertSame(198.0, (float) $transaction->total);
        $this->assertSame(2.0, (float) $transaction->change_amount);
        $this->assertSame(100.0, (float) $transaction->items->first()->price);
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame(1, StockLog::where('transaction_id', $transaction->id)->count());
    }

    public function test_checkout_rejects_insufficient_stock_without_creating_a_transaction(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $product = Product::create([
            'name' => 'Teh Botol',
            'sku' => 'TEH-002',
            'unit' => 'pcs',
            'buy_price' => 50,
            'sell_price' => 100,
            'stock' => 1,
            'is_active' => true,
        ]);
        $this->actingAs($cashier);

        $response = $this->postJson(route('kasir.store'), [
            'items' => [[
                'product_id' => $product->id,
                'qty' => 2,
                'price' => 100,
            ]],
            'paid_amount' => 200,
            'payment_method' => 'cash',
        ]);

        $response->assertUnprocessable();
        $this->assertSame(0, Transaction::count());
        $this->assertSame(1, $product->fresh()->stock);
        $this->assertSame(0, StockLog::count());
    }
}
