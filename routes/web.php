<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ApiClientController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OfflineController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Application hors connexion (PWA) : fichiers servis sans authentification, mis en cache par le service worker.
Route::get('/sw.js', [OfflineController::class, 'serviceWorker'])->name('pwa.sw');
Route::get('/manifest.webmanifest', [OfflineController::class, 'manifest'])->name('pwa.manifest');
Route::get('/hors-ligne', [OfflineController::class, 'app'])->name('offline.app');

// Photos des produits quand public/storage n'existe pas (liens symboliques interdits sur un mutualisé).
Route::get('/storage/{path}', function (string $path) {
    abort_if(str_contains($path, '..') || ! Storage::disk('public')->exists($path), 404);

    return response()->file(Storage::disk('public')->path($path), ['Cache-Control' => 'public, max-age=604800']);
})->where('path', '.*')->withoutMiddleware('web')->name('storage.public');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Synchronisation hors connexion
    Route::prefix('sync')->name('sync.')->group(function () {
        Route::get('/', [SyncController::class, 'index'])->name('index');
        Route::get('/bootstrap', [SyncController::class, 'bootstrap'])->name('bootstrap');
        Route::post('/push', [SyncController::class, 'push'])->middleware('throttle:sync')->name('push');
        Route::post('/{operation}/resolve', [SyncController::class, 'resolve'])->middleware('can:sync.resolve')->name('resolve');
    });

    // Commandes et ventes
    Route::middleware('can:orders.view')->group(function () {
        Route::get('/commandes', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/commandes/nouvelle', [OrderController::class, 'create'])->middleware('can:orders.manage')->name('orders.create');
        Route::post('/commandes', [OrderController::class, 'store'])->middleware('can:orders.manage')->name('orders.store');
        Route::get('/commandes/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('/commandes/{order}/statut', [OrderController::class, 'updateStatus'])->name('orders.status');
        Route::post('/commandes/{order}/paiement', [OrderController::class, 'payment'])->middleware('can:orders.manage')->name('orders.payment');
        Route::post('/commandes/{order}/livraison', [OrderController::class, 'addDelivery'])->middleware('can:orders.manage')->name('orders.delivery');
    });
    Route::get('/ventes', [SaleController::class, 'index'])->middleware('can:sales.view')->name('sales.index');

    // Clients
    Route::middleware('can:customers.view')->group(function () {
        Route::get('/clients', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/clients/meilleurs', [CustomerController::class, 'top'])->name('customers.top');
        Route::get('/clients/recherche', [CustomerController::class, 'search'])->name('customers.search');
        Route::get('/clients/nouveau', [CustomerController::class, 'create'])->middleware('can:customers.manage')->name('customers.create');
        Route::post('/clients', [CustomerController::class, 'store'])->middleware('can:customers.manage')->name('customers.store');
        Route::get('/clients/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::get('/clients/{customer}/modifier', [CustomerController::class, 'edit'])->middleware('can:customers.manage')->name('customers.edit');
        Route::put('/clients/{customer}', [CustomerController::class, 'update'])->middleware('can:customers.manage')->name('customers.update');
    });

    // Produits et stocks
    Route::middleware('can:products.view')->group(function () {
        Route::get('/produits', [ProductController::class, 'index'])->name('products.index');
        Route::get('/produits/nouveau', [ProductController::class, 'create'])->middleware('can:products.manage')->name('products.create');
        Route::post('/produits', [ProductController::class, 'store'])->middleware('can:products.manage')->name('products.store');
        Route::get('/produits/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::get('/produits/{product}/modifier', [ProductController::class, 'edit'])->middleware('can:products.manage')->name('products.edit');
        Route::put('/produits/{product}', [ProductController::class, 'update'])->middleware('can:products.manage')->name('products.update');
        Route::delete('/produits/{product}', [ProductController::class, 'destroy'])->middleware('can:products.manage')->name('products.destroy');
    });
    Route::middleware('can:products.manage')->group(function () {
        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    });
    Route::middleware('can:stock.view')->group(function () {
        Route::get('/stocks', [StockController::class, 'index'])->name('stock.index');
        Route::get('/stocks/alertes', [StockController::class, 'alerts'])->name('stock.alerts');
        Route::get('/stocks/mouvements', [StockController::class, 'movements'])->name('stock.movements');
        Route::get('/stocks/mouvement', [StockController::class, 'create'])->middleware('can:stock.manage')->name('stock.create');
        Route::post('/stocks/mouvement', [StockController::class, 'store'])->middleware('can:stock.manage')->name('stock.store');
        Route::get('/stocks/inventaire', [StockController::class, 'correction'])->middleware('can:stock.adjust')->name('stock.correction');
        Route::post('/stocks/inventaire', [StockController::class, 'correct'])->middleware('can:stock.adjust')->name('stock.correct');
        Route::get('/transferts', [StockTransferController::class, 'index'])->name('transfers.index');
        Route::get('/transferts/nouveau', [StockTransferController::class, 'create'])->middleware('can:stock.transfer')->name('transfers.create');
        Route::post('/transferts', [StockTransferController::class, 'store'])->middleware('can:stock.transfer')->name('transfers.store');
        Route::get('/transferts/{transfer}', [StockTransferController::class, 'show'])->name('transfers.show');
    });

    // Livraisons
    Route::middleware('can:deliveries.view')->group(function () {
        Route::get('/livraisons', [DeliveryController::class, 'index'])->name('deliveries.index');
        Route::get('/livraisons/{delivery}', [DeliveryController::class, 'show'])->name('deliveries.show');
        Route::put('/livraisons/{delivery}', [DeliveryController::class, 'update'])->middleware('can:deliveries.manage')->name('deliveries.update');
        Route::post('/livraisons/{delivery}/statut', [DeliveryController::class, 'updateStatus'])->middleware('can:deliveries.manage')->name('deliveries.status');
    });

    // Devis et factures
    Route::middleware('can:quotes.manage')->group(function () {
        Route::get('/devis', [QuoteController::class, 'index'])->name('quotes.index');
        Route::get('/devis/nouveau', [QuoteController::class, 'create'])->name('quotes.create');
        Route::post('/devis', [QuoteController::class, 'store'])->name('quotes.store');
        Route::get('/devis/{quote}', [QuoteController::class, 'show'])->name('quotes.show');
        Route::get('/devis/{quote}/modifier', [QuoteController::class, 'edit'])->name('quotes.edit');
        Route::put('/devis/{quote}', [QuoteController::class, 'update'])->name('quotes.update');
        Route::post('/devis/{quote}/statut', [QuoteController::class, 'updateStatus'])->name('quotes.status');
        Route::get('/devis/{quote}/pdf', [QuoteController::class, 'pdf'])->name('quotes.pdf');
        Route::post('/devis/{quote}/commande', [QuoteController::class, 'convertToOrder'])->middleware('can:orders.manage')->name('quotes.order');
        Route::post('/devis/{quote}/facture', [QuoteController::class, 'convertToInvoice'])->middleware('can:invoices.manage')->name('quotes.invoice');
    });
    Route::middleware('can:invoices.manage')->group(function () {
        Route::get('/factures', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/factures/nouvelle', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('/factures', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::post('/commandes/{order}/facture', [InvoiceController::class, 'fromOrder'])->name('invoices.from-order');
        Route::get('/factures/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::post('/factures/{invoice}/paiement', [InvoiceController::class, 'payment'])->name('invoices.payment');
        Route::post('/factures/{invoice}/annuler', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::get('/factures/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    });

    // Dépenses
    Route::get('/depenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::middleware('can:expenses.manage')->group(function () {
        Route::get('/depenses/nouvelle', [ExpenseController::class, 'create'])->name('expenses.create');
        Route::post('/depenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::get('/depenses/{expense}/modifier', [ExpenseController::class, 'edit'])->name('expenses.edit');
        Route::put('/depenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('/depenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    });

    // Rapports
    Route::middleware('can:reports.view')->group(function () {
        Route::get('/rapports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/rapports/{type}/pdf', [ReportController::class, 'pdf'])->whereIn('type', ReportController::TYPES)->name('reports.pdf');
        Route::get('/rapports/{type}/csv', [ReportController::class, 'csv'])->whereIn('type', ReportController::TYPES)->name('reports.csv');
    });

    // Administration (direction)
    Route::middleware('can:admin')->prefix('admin')->group(function () {
        Route::resource('magasins', StoreController::class)->except(['show', 'destroy'])->parameters(['magasins' => 'store'])->names('stores');
        Route::resource('utilisateurs', UserController::class)->except(['show', 'destroy'])->parameters(['utilisateurs' => 'user'])->names('users');
        Route::get('/categories-depenses', [ExpenseCategoryController::class, 'index'])->name('expense-categories.index');
        Route::post('/categories-depenses', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');
        Route::put('/categories-depenses/{expenseCategory}', [ExpenseCategoryController::class, 'update'])->name('expense-categories.update');
        Route::get('/api', [ApiClientController::class, 'index'])->name('api-clients.index');
        Route::post('/api', [ApiClientController::class, 'store'])->name('api-clients.store');
        Route::post('/api/{apiClient}/toggle', [ApiClientController::class, 'toggle'])->name('api-clients.toggle');
        Route::get('/historique', [ActivityLogController::class, 'index'])->name('activity.index');
    });
});
