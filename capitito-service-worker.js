/**
 * Capitito IMS - Service Worker for Offline Functionality
 * Handles caching, offline storage, and background sync
 * 
 * @package Capitito_IMS
 * @version 2.0.0
 * @author Okonudo EseAbasi - Bendless Tech
 */

const CACHE_VERSION = 'capitito-ims-v2.0.0';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const DYNAMIC_CACHE = `${CACHE_VERSION}-dynamic`;
const DB_NAME = 'capitito_offline_orders';
const DB_VERSION = 1;
const ORDERS_STORE = 'pending_orders';

// Assets to cache on install
const STATIC_ASSETS = [
    '/wp-content/plugins/capitito-inventory-management/assets/css/styles.css',
    '/wp-content/plugins/capitito-inventory-management/assets/js/scripts.js',
    '/wp-content/plugins/capitito-inventory-management/assets/js/capitito-offline-orders.js'
];

// ========================================
// SERVICE WORKER INSTALLATION
// ========================================
self.addEventListener('install', event => {
    console.log('[Service Worker] Installing...', CACHE_VERSION);
    
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                console.log('[Service Worker] Caching static assets');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => self.skipWaiting())
            .catch(err => console.error('[Service Worker] Install failed:', err))
    );
});

// ========================================
// SERVICE WORKER ACTIVATION
// ========================================
self.addEventListener('activate', event => {
    console.log('[Service Worker] Activating...', CACHE_VERSION);
    
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames
                        .filter(name => name.startsWith('capitito-ims-') && name !== STATIC_CACHE && name !== DYNAMIC_CACHE)
                        .map(name => {
                            console.log('[Service Worker] Deleting old cache:', name);
                            return caches.delete(name);
                        })
                );
            })
            .then(() => self.clients.claim())
    );
});

// ========================================
// FETCH EVENT - NETWORK FIRST STRATEGY
// ========================================
self.addEventListener('fetch', event => {
    const { request } = event;
    
    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }
    
    // Skip admin-ajax.php (AJAX requests)
    if (request.url.includes('admin-ajax.php')) {
        return;
    }
    
    event.respondWith(
        fetch(request)
            .then(response => {
                // Clone response before caching
                const responseClone = response.clone();
                
                caches.open(DYNAMIC_CACHE)
                    .then(cache => cache.put(request, responseClone));
                
                return response;
            })
            .catch(() => {
                // Network failed, try cache
                return caches.match(request)
                    .then(cached => {
                        if (cached) {
                            return cached;
                        }
                        
                        // Fallback offline page could go here
                        return new Response('Offline - Content not cached', {
                            status: 503,
                            statusText: 'Service Unavailable',
                            headers: new Headers({ 'Content-Type': 'text/plain' })
                        });
                    });
            })
    );
});

// ========================================
// BACKGROUND SYNC
// ========================================
self.addEventListener('sync', event => {
    console.log('[Service Worker] Background sync triggered:', event.tag);
    
    if (event.tag === 'sync-offline-orders') {
        event.waitUntil(syncOfflineOrders());
    }
});

// ========================================
// SYNC OFFLINE ORDERS FUNCTION
// ========================================
async function syncOfflineOrders() {
    console.log('[Service Worker] Starting offline orders sync...');
    
    try {
        const db = await openDatabase();
        const orders = await getAllPendingOrders(db);
        
        if (orders.length === 0) {
            console.log('[Service Worker] No pending orders to sync');
            return;
        }
        
        console.log(`[Service Worker] Syncing ${orders.length} pending orders`);
        
        for (const order of orders) {
            try {
                await syncSingleOrder(order, db);
            } catch (err) {
                console.error('[Service Worker] Failed to sync order:', order.id, err);
            }
        }
        
        // Notify clients that sync is complete
        const clients = await self.clients.matchAll();
        clients.forEach(client => {
            client.postMessage({
                type: 'SYNC_COMPLETE',
                syncedCount: orders.length
            });
        });
        
    } catch (error) {
        console.error('[Service Worker] Sync failed:', error);
        throw error;
    }
}

// ========================================
// SYNC SINGLE ORDER
// ========================================
async function syncSingleOrder(order, db) {
    // Extract admin-ajax URL from stored data
    const ajaxUrl = order.ajaxUrl || '/wp-admin/admin-ajax.php';
    
    const formData = new FormData();
    formData.append('action', 'capitito_ims_sync_offline_order');
    formData.append('nonce', order.nonce);
    formData.append('offline_order_id', order.id);
    formData.append('items', JSON.stringify(order.items));
    formData.append('payment_method', order.payment_method);
    formData.append('payment_breakdown', JSON.stringify(order.payment_breakdown || {}));
    formData.append('grand_total', order.grand_total);
    formData.append('total_discount', order.total_discount || 0);
    formData.append('created_at_offline', order.created_at);
    
    const response = await fetch(ajaxUrl, {
        method: 'POST',
        body: formData
    });
    
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }
    
    const result = await response.json();
    
    if (result.success) {
        // Remove from IndexedDB
        await deletePendingOrder(db, order.id);
        console.log('[Service Worker] Order synced successfully:', order.id);
    } else {
        throw new Error(result.data?.message || 'Sync failed');
    }
}

// ========================================
// INDEXEDDB HELPERS
// ========================================
function openDatabase() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
        
        request.onupgradeneeded = event => {
            const db = event.target.result;
            
            if (!db.objectStoreNames.contains(ORDERS_STORE)) {
                const store = db.createObjectStore(ORDERS_STORE, { keyPath: 'id', autoIncrement: true });
                store.createIndex('created_at', 'created_at', { unique: false });
                store.createIndex('synced', 'synced', { unique: false });
            }
        };
    });
}

function getAllPendingOrders(db) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([ORDERS_STORE], 'readonly');
        const store = transaction.objectStore(ORDERS_STORE);
        const request = store.getAll();
        
        request.onsuccess = () => resolve(request.result || []);
        request.onerror = () => reject(request.error);
    });
}

function deletePendingOrder(db, orderId) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([ORDERS_STORE], 'readwrite');
        const store = transaction.objectStore(ORDERS_STORE);
        const request = store.delete(orderId);
        
        request.onsuccess = () => resolve();
        request.onerror = () => reject(request.error);
    });
}

// ========================================
// MESSAGE HANDLER
// ========================================
self.addEventListener('message', event => {
    console.log('[Service Worker] Message received:', event.data);
    
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'TRIGGER_SYNC') {
        self.registration.sync.register('sync-offline-orders')
            .then(() => console.log('[Service Worker] Sync registered'))
            .catch(err => console.error('[Service Worker] Sync registration failed:', err));
    }
});

console.log('[Service Worker] Loaded successfully');