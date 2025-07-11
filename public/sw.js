const CACHE_NAME = 'sudoku-minimalista-v2.1.0';
const CACHE_VERSION = '2.1.0';
const STATIC_CACHE = `${CACHE_NAME}-static`;
const DYNAMIC_CACHE = `${CACHE_NAME}-dynamic`;
const API_CACHE = `${CACHE_NAME}-api`;

// Recursos críticos para cache inmediato (solo los que existen)
const STATIC_ASSETS = [
  '/Sudoku/public/',
  '/Sudoku/public/index.php',
  '/Sudoku/public/assets/css/mobile-optimizations.css',
  '/Sudoku/public/manifest.json',
  '/Sudoku/public/assets/js/pwa-integration.js'
];

// Recursos dinámicos con TTL
const DYNAMIC_ASSETS = [
  '/Sudoku/public/api/',
  '/Sudoku/public/assets/'
];

// TTL para diferentes tipos de contenido (en segundos)
const CACHE_TTL = {
  static: 7 * 24 * 60 * 60, // 7 días
  api: 5 * 60,               // 5 minutos
  dynamic: 24 * 60 * 60      // 1 día
};

// Instalación del Service Worker
self.addEventListener('install', event => {
  console.log('SW: Instalando Service Worker v' + CACHE_VERSION);
  
  event.waitUntil(
    Promise.all([
      // Cache recursos críticos individualmente para evitar errores
      caches.open(STATIC_CACHE).then(cache => {
        console.log('SW: Cacheando recursos estáticos');
        return Promise.allSettled(
          STATIC_ASSETS.map(url => 
            fetch(url)
              .then(response => {
                if (response.ok) {
                  return cache.put(url, response);
                }
                console.warn(`SW: No se pudo cachear ${url} - Status: ${response.status}`);
              })
              .catch(err => {
                console.warn(`SW: Error cacheando ${url}:`, err.message);
              })
          )
        );
      }),
      self.skipWaiting()
    ])
    .then(() => {
      console.log('SW: Recursos estáticos cacheados (con tolerancia a errores)');
    })
    .catch(err => {
      console.error('SW: Error en instalación:', err);
    })
  );
});

// Activación del Service Worker
self.addEventListener('activate', event => {
  console.log('SW: Activando Service Worker v' + CACHE_VERSION);
  
  event.waitUntil(
    caches.keys()
      .then(cacheNames => {
        return Promise.all(
          cacheNames.map(cacheName => {
            // Elimina caches antiguos
            if (cacheName.includes('sudoku-minimalista') && 
                !cacheName.includes(CACHE_VERSION)) {
              console.log('SW: Eliminando cache antiguo:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(() => {
        console.log('SW: Service Worker activado');
        return self.clients.claim(); // Controla todas las pestañas
      })
  );
});

// Interceptación de requests con mejor manejo de errores
self.addEventListener('fetch', event => {
  const request = event.request;
  const url = new URL(request.url);
  
  // Solo maneja requests del mismo origen
  if (url.origin !== location.origin) {
    return;
  }
  
  // Ignora requests problemáticos
  if (url.pathname.includes('chrome-extension') || 
      url.pathname.includes('moz-extension') ||
      request.method !== 'GET') {
    return;
  }
  
  // Estrategia basada en el tipo de recurso
  if (isStaticAsset(request.url)) {
    event.respondWith(cacheFirstStrategy(request, STATIC_CACHE));
  } else if (isApiRequest(request.url)) {
    event.respondWith(networkFirstStrategy(request, API_CACHE));
  } else {
    event.respondWith(staleWhileRevalidateStrategy(request, DYNAMIC_CACHE));
  }
});

// Determina si es un recurso estático
function isStaticAsset(url) {
  return STATIC_ASSETS.some(asset => url.includes(asset)) ||
         /\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$/i.test(url);
}

// Determina si es una request de API
function isApiRequest(url) {
  return url.includes('/api/') || url.includes('api_router.php');
}

// Estrategia Cache First (para recursos estáticos) con mejor error handling
async function cacheFirstStrategy(request, cacheName) {
  try {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);
    
    if (cached && !isExpired(cached, CACHE_TTL.static)) {
      return cached;
    }
    
    const response = await fetch(request);
    if (response.status === 200 && request.method === 'GET') {
      // Clone antes de usar
      const responseClone = response.clone();
      cache.put(request, responseClone).catch(err => {
        console.warn('SW: Error cacheando respuesta:', err);
      });
    }
    return response;
    
  } catch (error) {
    console.log('SW: Cache First fallback para:', request.url);
    try {
      const cache = await caches.open(cacheName);
      const cached = await cache.match(request);
      return cached || new Response('Offline', {
        status: 503,
        statusText: 'Service Unavailable'
      });
    } catch (cacheError) {
      return new Response('Cache Error', {
        status: 503,
        statusText: 'Service Unavailable'
      });
    }
  }
}

// Estrategia Network First (para API) con mejor error handling
async function networkFirstStrategy(request, cacheName) {
  try {
    const response = await fetch(request);
    
    if (response.status === 200 && request.method === 'GET') {
      const cache = await caches.open(cacheName);
      const responseClone = response.clone();
      cache.put(request, responseClone).catch(err => {
        console.warn('SW: Error cacheando API response:', err);
      });
    }
    return response;
    
  } catch (error) {
    console.log('SW: Network First fallback para:', request.url);
    try {
      const cache = await caches.open(cacheName);
      const cached = await cache.match(request);
      
      if (cached && !isExpired(cached, CACHE_TTL.api)) {
        return cached;
      }
      
      return new Response(JSON.stringify({
        error: 'Offline',
        message: 'No hay conexión disponible'
      }), {
        status: 503,
        headers: { 'Content-Type': 'application/json' }
      });
    } catch (cacheError) {
      return new Response(JSON.stringify({
        error: 'Cache Error',
        message: 'Error accediendo al cache'
      }), {
        status: 503,
        headers: { 'Content-Type': 'application/json' }
      });
    }
  }
}

// Estrategia Stale While Revalidate (para contenido dinámico)
async function staleWhileRevalidateStrategy(request, cacheName) {
  try {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);
    
    const fetchPromise = fetch(request).then(response => {
      if (response.status === 200 && request.method === 'GET') {
        const responseClone = response.clone();
        cache.put(request, responseClone).catch(err => {
          console.warn('SW: Error cacheando en SWR:', err);
        });
      }
      return response;
    }).catch(() => cached);
    
    return cached || fetchPromise;
  } catch (error) {
    console.warn('SW: Error en SWR strategy:', error);
    return fetch(request).catch(() => new Response('Offline', {
      status: 503,
      statusText: 'Service Unavailable'
    }));
  }
}

// Verifica si un recurso ha expirado
function isExpired(response, ttl) {
  try {
    const dateHeader = response.headers.get('date');
    if (!dateHeader) return false;
    
    const responseTime = new Date(dateHeader).getTime();
    const now = Date.now();
    return (now - responseTime) > (ttl * 1000);
  } catch (error) {
    console.warn('SW: Error verificando expiración:', error);
    return false;
  }
}

// Manejo de mensajes del cliente
self.addEventListener('message', event => {
  try {
    if (event.data && event.data.type === 'SKIP_WAITING') {
      self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'GET_CACHE_INFO') {
      getCacheInfo().then(info => {
        event.ports[0].postMessage(info);
      }).catch(err => {
        console.error('SW: Error obteniendo info de cache:', err);
        event.ports[0].postMessage({ error: err.message });
      });
    }
  } catch (error) {
    console.error('SW: Error procesando mensaje:', error);
  }
});

// Información del cache para debugging
async function getCacheInfo() {
  try {
    const cacheNames = await caches.keys();
    const info = {};
    
    for (const name of cacheNames) {
      try {
        const cache = await caches.open(name);
        const keys = await cache.keys();
        info[name] = {
          count: keys.length,
          urls: keys.map(req => req.url).slice(0, 10) // Limitar para evitar overflow
        };
      } catch (cacheError) {
        info[name] = { error: cacheError.message };
      }
    }
    
    return {
      version: CACHE_VERSION,
      caches: info,
      timestamp: new Date().toISOString()
    };
  } catch (error) {
    return {
      error: error.message,
      version: CACHE_VERSION,
      timestamp: new Date().toISOString()
    };
  }
}

// Sincronización en background
self.addEventListener('sync', event => {
  if (event.tag === 'background-sync') {
    event.waitUntil(doBackgroundSync());
  }
});

async function doBackgroundSync() {
  console.log('SW: Ejecutando sincronización en background');
  // Aquí puedes agregar lógica para sincronizar datos cuando vuelva la conexión
}

// Notificaciones push (para futuras features)
self.addEventListener('push', event => {
  try {
    if (event.data) {
      const data = event.data.json();
      showNotification(data);
    }
  } catch (error) {
    console.error('SW: Error procesando push:', error);
  }
});

function showNotification(data) {
  const options = {
    body: data.body,
    icon: '/Sudoku/public/assets/favicons/android-chrome-192x192.png',
    badge: '/Sudoku/public/assets/favicons/favicon-32x32.png',
    vibrate: [200, 100, 200],
    data: data.data || {},
    actions: data.actions || []
  };
  
  return self.registration.showNotification(data.title, options);
}

// Manejo global de errores
self.addEventListener('error', event => {
  console.error('SW: Error global:', event.error);
});

self.addEventListener('unhandledrejection', event => {
  console.error('SW: Promise rechazada:', event.reason);
  event.preventDefault(); // Previene el log en consola
});

console.log('SW: Service Worker cargado - Sudoku Minimalista v' + CACHE_VERSION);