// FarmScout Online Service Worker
// Provides offline functionality and caching for better mobile experience

const CACHE_NAME = 'farmscout-v1.0.0';
const STATIC_CACHE = 'farmscout-static-v1.0.0';
const DYNAMIC_CACHE = 'farmscout-dynamic-v1.0.0';

// Files to cache for offline use
const STATIC_FILES = [
    '/',
    '/index.php',
    '/market-finder.php',
    '/market-map.php',
    '/price-alerts.php',
    '/css/main.css',
    '/css/tailwind.css',
    '/public/favicon.ico',
    '/public/manifest.json'
];

// API endpoints to cache
const API_CACHE_PATTERNS = [
    '/api/products',
    '/api/categories',
    '/api/market-status'
];

// Install event - cache static files
self.addEventListener('install', event => {
    console.log('Service Worker installing...');
    
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                console.log('Caching static files...');
                return cache.addAll(STATIC_FILES);
            })
            .then(() => {
                console.log('Static files cached successfully');
                return self.skipWaiting();
            })
            .catch(error => {
                console.error('Error caching static files:', error);
            })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    console.log('Service Worker activating...');
    
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
                            console.log('Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                console.log('Service Worker activated');
                return self.clients.claim();
            })
    );
});

// Fetch event - serve cached content when offline
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Handle API requests
    if (url.pathname.startsWith('/api/')) {
        event.respondWith(handleApiRequest(request));
        return;
    }
    
    // Handle page requests
    if (request.method === 'GET') {
        event.respondWith(handlePageRequest(request));
        return;
    }
    
    // For other requests, try network first
    event.respondWith(fetch(request));
});

// Handle API requests with network-first strategy
async function handleApiRequest(request) {
    try {
        // Try network first
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            // Cache successful responses
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.log('Network failed, trying cache for:', request.url);
        
        // Fall back to cache
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return offline response for API calls
        return new Response(
            JSON.stringify({
                success: false,
                error: 'You are offline. Please check your internet connection.',
                offline: true
            }),
            {
                status: 503,
                headers: { 'Content-Type': 'application/json' }
            }
        );
    }
}

// Handle page requests with cache-first strategy
async function handlePageRequest(request) {
    // Try cache first
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
        return cachedResponse;
    }
    
    try {
        // Try network
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            // Cache successful responses
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.log('Network failed for:', request.url);
        
        // Return offline page for navigation requests
        if (request.mode === 'navigate') {
            return caches.match('/offline.html') || new Response(
                `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Offline - FarmScout Online</title>
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <style>
                        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                        .offline-icon { font-size: 64px; margin-bottom: 20px; }
                        h1 { color: #2D5016; }
                        p { color: #6C757D; }
                        .retry-btn { 
                            background: #2D5016; color: white; padding: 10px 20px; 
                            border: none; border-radius: 5px; cursor: pointer; margin-top: 20px;
                        }
                    </style>
                </head>
                <body>
                    <div class="offline-icon">📱</div>
                    <h1>You're Offline</h1>
                    <p>FarmScout Online is not available right now.</p>
                    <p>Please check your internet connection and try again.</p>
                    <button class="retry-btn" onclick="window.location.reload()">Try Again</button>
                </body>
                </html>
                `,
                {
                    status: 200,
                    headers: { 'Content-Type': 'text/html' }
                }
            );
        }
        
        throw error;
    }
}

// Background sync for price alerts
self.addEventListener('sync', event => {
    if (event.tag === 'price-alert-sync') {
        event.waitUntil(syncPriceAlerts());
    }
});

// Sync price alerts when back online
async function syncPriceAlerts() {
    try {
        // Get pending price alerts from IndexedDB
        const pendingAlerts = await getPendingAlerts();
        
        for (const alert of pendingAlerts) {
            try {
                const response = await fetch('/api/price-alerts', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(alert)
                });
                
                if (response.ok) {
                    // Remove from pending alerts
                    await removePendingAlert(alert.id);
                }
            } catch (error) {
                console.error('Failed to sync price alert:', error);
            }
        }
    } catch (error) {
        console.error('Price alert sync failed:', error);
    }
}

// Push notifications for price alerts
self.addEventListener('push', event => {
    if (event.data) {
        const data = event.data.json();
        
        const options = {
            body: data.body,
            icon: '/public/favicon.ico',
            badge: '/public/favicon.ico',
            vibrate: [200, 100, 200],
            data: data.data,
            actions: [
                {
                    action: 'view',
                    title: 'View Product',
                    icon: '/public/favicon.ico'
                },
                {
                    action: 'dismiss',
                    title: 'Dismiss'
                }
            ]
        };
        
        event.waitUntil(
            self.registration.showNotification(data.title, options)
        );
    }
});

// Handle notification clicks
self.addEventListener('notificationclick', event => {
    event.notification.close();
    
    if (event.action === 'view' && event.notification.data) {
        event.waitUntil(
            clients.openWindow(event.notification.data.url)
        );
    }
});

// IndexedDB helpers for offline storage
async function getPendingAlerts() {
    // Implementation would use IndexedDB to store pending alerts
    return [];
}

async function removePendingAlert(alertId) {
    // Implementation would remove alert from IndexedDB
    return true;
}

// Cache management
async function cleanOldCache() {
    const cache = await caches.open(DYNAMIC_CACHE);
    const requests = await cache.keys();
    
    // Keep only the 50 most recent requests
    if (requests.length > 50) {
        const requestsToDelete = requests.slice(0, requests.length - 50);
        await Promise.all(
            requestsToDelete.map(request => cache.delete(request))
        );
    }
}

// Periodic cache cleanup
setInterval(cleanOldCache, 24 * 60 * 60 * 1000); // Daily cleanup
