<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\DeliveryMethod;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $query = Order::with(['products', 'user', 'seller', 'deliveryMethod', 'paymentMethod']);

        // Админ и менеджер видят всё
        if ($user->hasRole('admin') || $user->hasRole('manager')) {
            return $query->latest()->get();
        }

        // Остальные видят только заказы со своими товарами (как продавцы)
        return $query->where('seller_id', $user->id)->latest()->get();
    }

    public function show(Order $order)
    {
        return $order->load(['products', 'user', 'seller', 'deliveryMethod', 'paymentMethod']);
    }

    /**
     * Update order fields (for admin/manager via policy middleware).
     * Allows updating status, payment_status, paid_at, tracking_number, carrier, estimated_delivery_date,
     * delivery_method_id, payment_method_id.
     */
    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(['pending','processing','shipped','completed','cancelled'])],
            'payment_status' => ['sometimes', 'string', Rule::in(['pending','paid','failed'])],
            'paid_at' => 'nullable|date',
            'tracking_number' => 'nullable|string|max:255',
            'carrier' => 'nullable|string|max:255',
            'estimated_delivery_date' => 'nullable|date',
            'delivery_method_id' => 'nullable|exists:delivery_methods,id',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
        ]);

        $order->fill($validated);
        $order->save();

        return $order->load(['products', 'user', 'seller', 'deliveryMethod', 'paymentMethod']);
    }

    public function destroy(Order $order)
    {
        $order->delete();
        return response()->json(null, 204);
    }

    /**
     * Store public order(s) for a guest/buyer.
     * If cart contains products from multiple sellers, separate orders are created per seller.
     */
    public function storePublic(Request $request)
    {
        $validated = $request->validate([
            // buyer
            'buyer_first_name' => 'required|string|max:120',
            'buyer_last_name' => 'required|string|max:120',
            'buyer_middle_name' => 'nullable|string|max:120',
            'buyer_phone' => 'required|string|max:32',
            'buyer_email' => 'required|email|max:255',
            // recipient optional
            'recipient_first_name' => 'nullable|string|max:120',
            'recipient_last_name' => 'nullable|string|max:120',
            'recipient_middle_name' => 'nullable|string|max:120',
            'recipient_phone' => 'nullable|string|max:32',
            // delivery/payment/address
            'delivery_method_id' => 'nullable|exists:delivery_methods,id',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'city' => 'required|string|max:255',
            'address' => 'required|string|max:1000',
            // cart (for one seller only)
            'cart' => 'required|array|min:1',
            'cart.*.product_id' => 'required|exists:products,id',
            'cart.*.quantity' => 'required|integer|min:1',
        ]);

        // Find product objects and validate stock, also group by seller_id
        $cart = $validated['cart'];
        $items = []; // [ {product, quantity, price}, ... ]
        $sellerId = null;

        foreach ($cart as $item) {
            $product = Product::find($item['product_id']);
            if (!$product) {
                return response()->json(['message' => "Product with id {$item['product_id']} not found."], 422);
            }
            if ($product->quantity < $item['quantity']) {
                return response()->json(['message' => 'Товара ' . ($product->title ?? '') . ' не хватает на складе.'], 422);
            }

            // Ensure all products belong to the same seller
            if ($sellerId === null) {
                $sellerId = $product->user_id; // set initial seller
            } elseif ($sellerId !== $product->user_id) {
                return response()->json(['message' => 'Корзина должна содержать товары только одного продавца.'], 422);
            }

            $items[] = [
                'product' => $product,
                'quantity' => (int) $item['quantity'],
                'price' => $product->price,
            ];
        }

        DB::beginTransaction();

        try {
            // Calculate total for this order
            $total = 0;
            foreach ($items as $it) {
                $total += $it['price'] * $it['quantity'];
            }

            $order = Order::create([
                'customer_name' => $validated['buyer_first_name'] . ' ' . $validated['buyer_last_name'],
                'customer_email' => $validated['buyer_email'],
                'total_price' => $total,
                'status' => 'pending',
                'user_id' => null, // guest
                'buyer_first_name' => $validated['buyer_first_name'],
                'buyer_last_name' => $validated['buyer_last_name'],
                'buyer_middle_name' => $validated['buyer_middle_name'] ?? null,
                'buyer_phone' => $validated['buyer_phone'],
                'buyer_email' => $validated['buyer_email'],
                'recipient_first_name' => $validated['recipient_first_name'] ?? null,
                'recipient_last_name' => $validated['recipient_last_name'] ?? null,
                'recipient_middle_name' => $validated['recipient_middle_name'] ?? null,
                'recipient_phone' => $validated['recipient_phone'] ?? null,
                'seller_id' => $sellerId, // related seller
                'delivery_method_id' => $validated['delivery_method_id'] ?? null,
                'payment_method_id' => $validated['payment_method_id'] ?? null,
                'city' => $validated['city'],
                'address' => $validated['address'],
                'payment_status' => 'pending', // default status
            ]);

            // Create products association in pivot table
            $order->products()->attach(
                collect($items)->mapWithKeys(fn ($it) => [
                    $it['product']['id'] => [
                        'quantity' => $it['quantity'],
                        'price' => $it['price'],
                    ],
                ])
            );

            // Decrement product quantities
            foreach ($items as $it) {
                $it['product']->decrement('quantity', $it['quantity']);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Ошибка при создании заказа: ' . $e->getMessage()], 500);
        }

        return response()->json(['order' => $order->load(['products', 'seller', 'deliveryMethod', 'paymentMethod'])], 201);
    }

    /**
     * Return list of active delivery methods for frontend.
     */
    public function deliveryMethods()
    {
        return DeliveryMethod::where('active', true)->orderBy('id')->get();
    }

    /**
     * Return list of active payment methods for frontend.
     */
    public function paymentMethods()
    {
        return PaymentMethod::where('active', true)->orderBy('id')->get();
    }
}