<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\OfflineController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierInvoiceController;
use App\Http\Controllers\SupplierPaymentController;
use App\Http\Controllers\SupplierReturnController;
use App\Http\Controllers\SyncConflictController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login');
});

// Lien public signé pour télécharger un document (devis/facture) sans connexion.
// Utilisé par le bouton "Partager WhatsApp" — le lien expire automatiquement.
Route::get('/documents/{document}/partage-pdf', [DocumentController::class, 'sharedPdf'])
    ->middleware('signed')
    ->name('documents.share.pdf');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/mot-de-passe', [LoginController::class, 'editPassword'])->name('password.edit');
    Route::put('/mot-de-passe', [LoginController::class, 'updatePassword'])->name('password.update');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Hors connexion et synchronisation
    Route::get('/hors-ligne', [OfflineController::class, 'index'])->name('offline');
    Route::get('/hors-ligne/donnees', [OfflineController::class, 'data'])->name('offline.data');
    Route::get('/hors-ligne/historique', [OfflineController::class, 'history'])->name('offline.history');
    Route::post('/sync', [OfflineController::class, 'sync'])->name('sync');

    // Produits
    Route::middleware('role:products.view')->group(function () {
        Route::get('/produits', [ProductController::class, 'index'])->name('products.index');
        Route::get('/produits/nouveau', [ProductController::class, 'create'])->middleware('role:products.manage')->name('products.create');
        Route::post('/produits', [ProductController::class, 'store'])->middleware('role:products.manage')->name('products.store');
        Route::get('/produits/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::get('/produits/{product}/modifier', [ProductController::class, 'edit'])->middleware('role:products.manage')->name('products.edit');
        Route::put('/produits/{product}', [ProductController::class, 'update'])->middleware('role:products.manage')->name('products.update');
        Route::delete('/produits/{product}', [ProductController::class, 'destroy'])->middleware('role:products.manage')->name('products.destroy');
    });

    // Stocks
    Route::middleware('role:stock.view')->group(function () {
        Route::get('/stocks', [StockController::class, 'index'])->name('stocks.index');
        Route::get('/stocks/mouvements', [StockController::class, 'movements'])->name('stocks.movements');
        Route::get('/stocks/transferts', [StockController::class, 'transfers'])->name('stocks.transfers');
    });
    Route::middleware('role:stock.move,stock.adjust')->group(function () {
        Route::get('/stocks/mouvements/nouveau', [StockController::class, 'createMovement'])->name('stocks.movements.create');
        Route::post('/stocks/mouvements', [StockController::class, 'storeMovement'])->name('stocks.movements.store');
        Route::put('/stocks/{stock}/seuil', [StockController::class, 'updateThreshold'])->name('stocks.threshold');
    });
    Route::middleware('role:stock.transfer')->group(function () {
        Route::get('/stocks/transferts/nouveau', [StockController::class, 'createTransfer'])->name('stocks.transfers.create');
        Route::post('/stocks/transferts', [StockController::class, 'storeTransfer'])->name('stocks.transfers.store');
    });

    // Clients
    Route::middleware('role:customers.view')->group(function () {
        Route::get('/clients', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/clients/recherche', [CustomerController::class, 'search'])->name('customers.search');
        Route::get('/clients/nouveau', [CustomerController::class, 'create'])->middleware('role:customers.manage')->name('customers.create');
        Route::post('/clients', [CustomerController::class, 'store'])->middleware('role:customers.manage')->name('customers.store');
        Route::get('/clients/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::get('/clients/{customer}/modifier', [CustomerController::class, 'edit'])->middleware('role:customers.manage')->name('customers.edit');
        Route::put('/clients/{customer}', [CustomerController::class, 'update'])->middleware('role:customers.manage')->name('customers.update');
        Route::delete('/clients/{customer}', [CustomerController::class, 'destroy'])->middleware('role:customers.delete')->name('customers.destroy');
    });

    // Commandes et ventes
    Route::middleware('role:orders.view')->group(function () {
        Route::get('/commandes', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/commandes/nouvelle', [OrderController::class, 'create'])->middleware('role:orders.create')->name('orders.create');
        Route::post('/commandes', [OrderController::class, 'store'])->middleware('role:orders.create')->name('orders.store');
        Route::get('/commandes/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::put('/commandes/{order}/statut', [OrderController::class, 'updateStatus'])->middleware('role:orders.status')->name('orders.status');
        Route::post('/commandes/{order}/paiement', [OrderController::class, 'payment'])->middleware('role:orders.create')->name('orders.payment');
        Route::post('/commandes/{order}/facture', [OrderController::class, 'invoice'])->middleware('role:documents.manage')->name('orders.invoice');
    });

    // Livraisons
    Route::middleware('role:deliveries.view')->group(function () {
        Route::get('/livraisons', [DeliveryController::class, 'index'])->name('deliveries.index');
        Route::get('/livraisons/{delivery}', [DeliveryController::class, 'edit'])->name('deliveries.edit');
        Route::put('/livraisons/{delivery}', [DeliveryController::class, 'update'])->middleware('role:deliveries.manage')->name('deliveries.update');
        Route::put('/livraisons/{delivery}/statut', [DeliveryController::class, 'updateStatus'])->middleware('role:deliveries.manage')->name('deliveries.status');
    });

    // Devis et factures
    Route::middleware('role:documents.view')->group(function () {
        Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::get('/documents/nouveau', [DocumentController::class, 'create'])->middleware('role:documents.manage')->name('documents.create');
        Route::post('/documents', [DocumentController::class, 'store'])->middleware('role:documents.manage')->name('documents.store');
        Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
        Route::get('/documents/{document}/pdf', [DocumentController::class, 'pdf'])->name('documents.pdf');
        Route::post('/documents/{document}/facturer', [DocumentController::class, 'convert'])->middleware('role:documents.manage')->name('documents.convert');
        Route::put('/documents/{document}/reglement', [DocumentController::class, 'payment'])->middleware('role:documents.manage')->name('documents.payment');
        Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->middleware('role:documents.delete')->name('documents.destroy');
    });

    // Module Fournisseurs / approvisionnement
    Route::middleware('role:suppliers.view')->group(function () {
        Route::get('/fournisseurs/tableau-de-bord', [SupplierController::class, 'dashboard'])->name('suppliers.dashboard');
        Route::get('/fournisseurs', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('/fournisseurs/produits', [SupplierController::class, 'productsIndex'])->name('suppliers.products');
        Route::middleware('role:suppliers.manage')->group(function () {
            Route::get('/fournisseurs/nouveau', [SupplierController::class, 'create'])->name('suppliers.create');
            Route::post('/fournisseurs', [SupplierController::class, 'store'])->name('suppliers.store');
            Route::post('/fournisseurs/{supplier}/produits', [SupplierController::class, 'attachProduct'])->name('suppliers.products.attach');
            Route::put('/fournisseurs/{supplier}/produits/{product}', [SupplierController::class, 'updateProduct'])->name('suppliers.products.update');
            Route::delete('/fournisseurs/{supplier}/produits/{product}', [SupplierController::class, 'detachProduct'])->name('suppliers.products.detach');
            Route::post('/fournisseurs/{supplier}/documents', [SupplierController::class, 'attachDocument'])->name('suppliers.documents.attach');
            Route::put('/fournisseurs/{supplier}/reactiver', [SupplierController::class, 'restore'])->name('suppliers.restore');
        });
        Route::get('/fournisseurs/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
        Route::get('/fournisseurs/{supplier}/modifier', [SupplierController::class, 'edit'])->middleware('role:suppliers.manage')->name('suppliers.edit');
        Route::put('/fournisseurs/{supplier}', [SupplierController::class, 'update'])->middleware('role:suppliers.manage')->name('suppliers.update');
        Route::delete('/fournisseurs/{supplier}', [SupplierController::class, 'destroy'])->middleware('role:suppliers.manage')->name('suppliers.destroy');
        Route::get('/fournisseurs/documents/{document}/telecharger', [SupplierController::class, 'downloadDocument'])->name('suppliers.documents.download');
    });

    // Commandes d'achat
    Route::middleware('role:purchases.view')->group(function () {
        Route::get('/achats', [PurchaseOrderController::class, 'index'])->name('purchases.index');
        Route::get('/achats/nouveau', [PurchaseOrderController::class, 'create'])->middleware('role:purchases.manage')->name('purchases.create');
        Route::post('/achats', [PurchaseOrderController::class, 'store'])->middleware('role:purchases.manage')->name('purchases.store');
        Route::get('/achats/{purchase}', [PurchaseOrderController::class, 'show'])->name('purchases.show');
        Route::get('/achats/{purchase}/pdf', [PurchaseOrderController::class, 'pdf'])->name('purchases.pdf');
        Route::put('/achats/{purchase}/statut', [PurchaseOrderController::class, 'status'])->name('purchases.status');
    });

    // Réceptions
    Route::middleware('role:receipts.view')->group(function () {
        Route::get('/receptions', [GoodsReceiptController::class, 'index'])->name('receipts.index');
        Route::get('/receptions/{receipt}', [GoodsReceiptController::class, 'show'])->name('receipts.show');
        Route::middleware('role:receipts.manage')->group(function () {
            Route::get('/achats/{purchase}/recevoir', [GoodsReceiptController::class, 'create'])->name('receipts.create');
            Route::post('/achats/{purchase}/recevoir', [GoodsReceiptController::class, 'store'])->name('receipts.store');
        });
    });

    // Factures fournisseurs et règlements
    Route::middleware('role:supplier_invoices.view')->group(function () {
        Route::get('/factures-fournisseurs', [SupplierInvoiceController::class, 'index'])->name('supplier-invoices.index');
        Route::get('/factures-fournisseurs/nouvelle', [SupplierInvoiceController::class, 'create'])->middleware('role:supplier_invoices.manage')->name('supplier-invoices.create');
        Route::post('/factures-fournisseurs', [SupplierInvoiceController::class, 'store'])->middleware('role:supplier_invoices.manage')->name('supplier-invoices.store');
        Route::get('/factures-fournisseurs/{supplierInvoice}', [SupplierInvoiceController::class, 'show'])->name('supplier-invoices.show');
        Route::get('/factures-fournisseurs/{supplierInvoice}/piece', [SupplierInvoiceController::class, 'attachment'])->name('supplier-invoices.attachment');
        Route::post('/factures-fournisseurs/{supplierInvoice}/reglement', [SupplierInvoiceController::class, 'storePayment'])->middleware('role:supplier_payments.manage')->name('supplier-invoices.pay');
    });
    Route::middleware('role:supplier_payments.view')->group(function () {
        Route::get('/paiements-dettes', [SupplierPaymentController::class, 'index'])->name('supplier-payments.index');
    });

    // Retours fournisseurs
    Route::middleware('role:supplier_returns.view')->group(function () {
        Route::get('/retours-fournisseurs', [SupplierReturnController::class, 'index'])->name('supplier-returns.index');
        Route::middleware('role:supplier_returns.manage')->group(function () {
            Route::get('/retours-fournisseurs/nouveau', [SupplierReturnController::class, 'create'])->name('supplier-returns.create');
            Route::post('/retours-fournisseurs', [SupplierReturnController::class, 'store'])->name('supplier-returns.store');
            Route::put('/retours-fournisseurs/{supplierReturn}/statut', [SupplierReturnController::class, 'status'])->name('supplier-returns.status');
        });
        Route::get('/retours-fournisseurs/{supplierReturn}', [SupplierReturnController::class, 'show'])->name('supplier-returns.show');
    });

    // Dépenses
    Route::middleware('role:expenses.view')->group(function () {
        Route::get('/depenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::middleware('role:expenses.manage')->group(function () {
            Route::get('/depenses/nouvelle', [ExpenseController::class, 'create'])->name('expenses.create');
            Route::post('/depenses', [ExpenseController::class, 'store'])->name('expenses.store');
            Route::get('/depenses/{expense}/modifier', [ExpenseController::class, 'edit'])->name('expenses.edit');
            Route::put('/depenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
            Route::delete('/depenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
        });
    });

    // Rapports
    Route::middleware('role:reports.view')->group(function () {
        Route::get('/rapports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/rapports/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
    });

    // Conflits de synchronisation
    Route::middleware('role:conflicts.view')->group(function () {
        Route::get('/conflits', [SyncConflictController::class, 'index'])->name('conflicts.index');
        Route::put('/conflits/{conflict}', [SyncConflictController::class, 'resolve'])->name('conflicts.resolve');
    });

    // Administration (Direction)
    Route::middleware('role:stores.manage')->group(function () {
        Route::get('/magasins', [StoreController::class, 'index'])->name('stores.index');
        Route::get('/magasins/nouveau', [StoreController::class, 'create'])->name('stores.create');
        Route::post('/magasins', [StoreController::class, 'store'])->name('stores.store');
        Route::get('/magasins/{store}/modifier', [StoreController::class, 'edit'])->name('stores.edit');
        Route::put('/magasins/{store}', [StoreController::class, 'update'])->name('stores.update');
    });
    Route::middleware('role:users.manage')->group(function () {
        Route::get('/utilisateurs', [UserController::class, 'index'])->name('users.index');
        Route::get('/utilisateurs/nouveau', [UserController::class, 'create'])->name('users.create');
        Route::post('/utilisateurs', [UserController::class, 'store'])->name('users.store');
        Route::get('/utilisateurs/{user}/modifier', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/utilisateurs/{user}', [UserController::class, 'update'])->name('users.update');
    });
    Route::get('/historique', [ActivityLogController::class, 'index'])->middleware('role:activity.view')->name('activity.index');
});
