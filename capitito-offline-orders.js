/**
 * Capitito IMS - Offline Orders Handler
 * Manages offline order storage and synchronization
 * 
 * @package Capitito_IMS
 * @version 2.0.0
 * @author Okonudo EseAbasi - Bendless Tech
 */

(function($) {
    'use strict';

    const DB_NAME = 'capitito_offline_orders';
    const DB_VERSION = 1;
    const ORDERS_STORE = 'pending_orders';
    
    let db = null;
    let isOnline = navigator.onLine;
    let syncInProgress = false;

    // ========================================
    // INITIALIZE
    // ========================================
    $(document).ready(function() {
        console.log('[Offline Orders] Initializing...');
        
        initializeDatabase();
        registerServiceWorker();
        setupOnlineOfflineListeners();
        setupUIIndicators();
        updateConnectionStatus();
        checkPendingOrders();
        
        // Listen for service worker messages
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', handleServiceWorkerMessage);
        }
    });

    // ========================================
    // SERVICE WORKER REGISTRATION
    // ========================================
    function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            console.warn('[Offline Orders] Service Workers not supported');
            return;
        }

        const swPath = capitito_ims_ajax.plugin_url + 'assets/js/capitito-service-worker.js';
        
        navigator.serviceWorker.register(swPath)
            .then(registration => {
                console.log('[Offline Orders] Service Worker registered:', registration.scope);
                
                // Check for updates
                registration.addEventListener('updatefound', () => {
                    console.log('[Offline Orders] Service Worker update found');
                });
            })
            .catch(error => {
                console.error('[Offline Orders] Service Worker registration failed:', error);
            });
    }

    // ========================================
    // INITIALIZE INDEXEDDB
    // ========================================
    function initializeDatabase() {
        const request = indexedDB.open(DB_NAME, DB_VERSION);
        
        request.onerror = () => {
            console.error('[Offline Orders] Database failed to open:', request.error);
        };
        
        request.onsuccess = () => {
            db = request.result;
            console.log('[Offline Orders] Database opened successfully');
            checkPendingOrders();
        };
        
        request.onupgradeneeded = event => {
            db = event.target.result;
            
            if (!db.objectStoreNames.contains(ORDERS_STORE)) {
                const store = db.createObjectStore(ORDERS_STORE, { keyPath: 'id', autoIncrement: true });
                store.createIndex('created_at', 'created_at', { unique: false });
                store.createIndex('synced', 'synced', { unique: false });
                console.log('[Offline Orders] Object store created');
            }
        };
    }

    // ========================================
    // ONLINE/OFFLINE LISTENERS
    // ========================================
    function setupOnlineOfflineListeners() {
        window.addEventListener('online', () => {
            console.log('[Offline Orders] Connection restored');
            isOnline = true;
            updateConnectionStatus();
            triggerSync();
        });
        
        window.addEventListener('offline', () => {
            console.log('[Offline Orders] Connection lost');
            isOnline = false;
            updateConnectionStatus();
        });
    }

    // ========================================
    // UI INDICATORS
    // ========================================
    function setupUIIndicators() {
        // Inject offline banner HTML if not exists
        if ($('#offlineModeBanner').length === 0 && $('#orderForm').length > 0) {
            const banner = `
                <div id="offlineModeBanner" style="display: none; position: fixed; top: 0; left: 0; right: 0; z-index: 999999; background: linear-gradient(135deg, #D02126 0%, #a01a1f 100%); color: white; padding: 12px 20px; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.3); font-weight: 700; font-size: 15px;">
                    📡 OFFLINE MODE - Orders will be saved locally and synced when connection is restored
                </div>
                <div id="onlineModeBanner" style="display: none; position: fixed; top: 0; left: 0; right: 0; z-index: 999999; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 12px 20px; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.3); font-weight: 700; font-size: 15px;">
                    ✓ ONLINE - Syncing pending orders...
                </div>
                <div id="pendingOrdersIndicator" style="display: none; position: fixed; bottom: 20px; right: 20px; background: #ff9800; color: white; padding: 12px 20px; border-radius: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); z-index: 99999; cursor: pointer; font-weight: 700;">
                    <span id="pendingOrdersCount">0</span> orders pending sync
                </div>
            `;
            $('body').prepend(banner);
        }
        
        // Click to trigger sync
        $(document).on('click', '#pendingOrdersIndicator', function() {
            if (isOnline) {
                triggerSync();
            } else {
                showNotification('Cannot sync while offline', 'warning');
            }
        });
    }

    function updateConnectionStatus() {
        if (isOnline) {
            $('#offlineModeBanner').slideUp(300);
            checkPendingOrders();
        } else {
            $('#offlineModeBanner').slideDown(300);
            $('#onlineModeBanner').slideUp(300);
        }
    }

    // ========================================
    // SAVE ORDER OFFLINE
    // ========================================
    window.saveOrderOffline = function(orderData) {
        if (!db) {
            console.error('[Offline Orders] Database not initialized');
            return Promise.reject(new Error('Database not available'));
        }

        return new Promise((resolve, reject) => {
            const transaction = db.transaction([ORDERS_STORE], 'readwrite');
            const store = transaction.objectStore(ORDERS_STORE);
            
            const offlineOrder = {
                ...orderData,
                created_at: new Date().toISOString(),
                synced: false,
                ajaxUrl: capitito_ims_ajax.ajax_url,
                nonce: capitito_ims_ajax.nonce
            };
            
            const request = store.add(offlineOrder);
            
            request.onsuccess = () => {
                console.log('[Offline Orders] Order saved offline:', request.result);
                checkPendingOrders();
                resolve(request.result);
            };
            
            request.onerror = () => {
                console.error('[Offline Orders] Failed to save order:', request.error);
                reject(request.error);
            };
        });
    };

    // ========================================
    // CHECK PENDING ORDERS
    // ========================================
    function checkPendingOrders() {
        if (!db) return;
        
        const transaction = db.transaction([ORDERS_STORE], 'readonly');
        const store = transaction.objectStore(ORDERS_STORE);
        const request = store.getAll();
        
        request.onsuccess = () => {
            const orders = request.result || [];
            const count = orders.length;
            
            if (count > 0) {
                $('#pendingOrdersCount').text(count);
                $('#pendingOrdersIndicator').fadeIn(300);
            } else {
                $('#pendingOrdersIndicator').fadeOut(300);
            }
            
            console.log(`[Offline Orders] ${count} pending orders`);
        };
    }

    // ========================================
    // TRIGGER SYNC
    // ========================================
    function triggerSync() {
        if (syncInProgress) {
            console.log('[Offline Orders] Sync already in progress');
            return;
        }
        
        if (!isOnline) {
            console.log('[Offline Orders] Cannot sync while offline');
            return;
        }
        
        syncInProgress = true;
        $('#onlineModeBanner').slideDown(300);
        
        if ('serviceWorker' in navigator && 'sync' in navigator.serviceWorker) {
            // Use Background Sync API
            navigator.serviceWorker.ready
                .then(registration => registration.sync.register('sync-offline-orders'))
                .then(() => console.log('[Offline Orders] Background sync registered'))
                .catch(err => {
                    console.error('[Offline Orders] Background sync failed:', err);
                    // Fallback to manual sync
                    manualSync();
                });
        } else {
            // Fallback to manual sync
            manualSync();
        }
    }

    // ========================================
    // MANUAL SYNC FALLBACK
    // ========================================
    function manualSync() {
        if (!db) {
            syncInProgress = false;
            return;
        }
        
        const transaction = db.transaction([ORDERS_STORE], 'readonly');
        const store = transaction.objectStore(ORDERS_STORE);
        const request = store.getAll();
        
        request.onsuccess = () => {
            const orders = request.result || [];
            
            if (orders.length === 0) {
                syncComplete(0);
                return;
            }
            
            let synced = 0;
            let failed = 0;
            
            const syncPromises = orders.map(order => syncOrderToServer(order));
            
            Promise.allSettled(syncPromises)
                .then(results => {
                    results.forEach((result, index) => {
                        if (result.status === 'fulfilled') {
                            synced++;
                            deleteOfflineOrder(orders[index].id);
                        } else {
                            failed++;
                            console.error('[Offline Orders] Sync failed for order:', orders[index].id, result.reason);
                        }
                    });
                    
                    syncComplete(synced, failed);
                });
        };
    }

    function syncOrderToServer(order) {
        return $.ajax({
            url: capitito_ims_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'capitito_ims_sync_offline_order',
                nonce: capitito_ims_ajax.nonce,
                offline_order_id: order.id,
                items: JSON.stringify(order.items),
                payment_method: order.payment_method,
                payment_breakdown: JSON.stringify(order.payment_breakdown || {}),
                grand_total: order.grand_total,
                total_discount: order.total_discount || 0,
                created_at_offline: order.created_at
            }
        });
    }

    function deleteOfflineOrder(orderId) {
        if (!db) return;
        
        const transaction = db.transaction([ORDERS_STORE], 'readwrite');
        const store = transaction.objectStore(ORDERS_STORE);
        store.delete(orderId);
    }

    function syncComplete(syncedCount, failedCount = 0) {
        syncInProgress = false;
        $('#onlineModeBanner').slideUp(300);
        checkPendingOrders();
        
        if (syncedCount > 0) {
            showNotification(`✓ ${syncedCount} offline orders synced successfully!`, 'success');
        }
        
        if (failedCount > 0) {
            showNotification(`⚠ ${failedCount} orders failed to sync`, 'warning');
        }
    }

    // ========================================
    // SERVICE WORKER MESSAGE HANDLER
    // ========================================
    function handleServiceWorkerMessage(event) {
        const { data } = event;
        
        if (data.type === 'SYNC_COMPLETE') {
            syncComplete(data.syncedCount || 0);
        }
    }

    // ========================================
    // NOTIFICATION HELPER
    // ========================================
    function showNotification(message, type = 'info') {
        const bgColor = type === 'success' ? '#28a745' : type === 'error' ? '#D02126' : type === 'warning' ? '#ff9800' : '#2196F3';
        
        const notification = $(`
            <div class="capitito-notification" style="
                position: fixed;
                top: 20px;
                right: 20px;
                background-color: ${bgColor};
                color: white;
                padding: 16px 24px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                z-index: 999999;
                min-width: 300px;
                animation: slideInRight 0.3s ease;
            ">
                <strong>${message}</strong>
            </div>
        `);

        $('body').append(notification);

        setTimeout(function() {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 4000);
    }

    // ========================================
    // EXPORT TO WINDOW
    // ========================================
    window.CapititoOfflineOrders = {
        isOnline: () => isOnline,
        saveOffline: saveOrderOffline,
        triggerSync: triggerSync,
        checkPending: checkPendingOrders
    };

    console.log('[Offline Orders] Module loaded successfully');

})(jQuery);