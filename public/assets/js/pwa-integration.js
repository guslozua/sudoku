/**
 * PWA Integration y Mobile Optimization
 * Sudoku Minimalista v2.1.0 - Fase 3
 */

class PWAManager {
  constructor() {
    this.isStandalone = false;
    this.deferredPrompt = null;
    this.swRegistration = null;
    this.isOnline = navigator.onLine;
    
    this.init();
  }
  
  async init() {
    console.log('PWA: Inicializando PWA Manager');
    
    // Detecta si está en modo standalone
    this.detectStandaloneMode();
    
    // Registra Service Worker
    await this.registerServiceWorker();
    
    // Configura event listeners
    this.setupEventListeners();
    
    // Configura UI para PWA
    this.setupPWAUI();
    
    // Optimizaciones móviles
    this.setupMobileOptimizations();
    
    // Preload crítico
    this.preloadCriticalResources();
  }
  
  detectStandaloneMode() {
    this.isStandalone = window.matchMedia('(display-mode: standalone)').matches ||
                       window.navigator.standalone ||
                       document.referrer.includes('android-app://');
                       
    if (this.isStandalone) {
      document.body.classList.add('standalone-mode');
      console.log('PWA: Ejecutándose en modo standalone');
    }
  }
  
  async registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
      console.warn('PWA: Service Workers no soportado');
      return;
    }
    
    try {
      const registration = await navigator.serviceWorker.register('/Sudoku/public/sw.js', {
        scope: '/Sudoku/public/'
      });
      
      this.swRegistration = registration;
      console.log('PWA: Service Worker registrado:', registration.scope);
      
      // Maneja actualizaciones
      registration.addEventListener('updatefound', () => {
        this.handleServiceWorkerUpdate(registration);
      });
      
      // Verifica actualizaciones
      registration.update();
      
    } catch (error) {
      console.error('PWA: Error registrando Service Worker:', error);
    }
  }
  
  handleServiceWorkerUpdate(registration) {
    const newWorker = registration.installing;
    
    newWorker.addEventListener('statechange', () => {
      if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
        // Nueva versión disponible
        this.showUpdateAvailableNotification();
      }
    });
  }
  
  showUpdateAvailableNotification() {
    const notification = document.createElement('div');
    notification.className = 'update-notification';
    notification.innerHTML = `
      <div class="update-content">
        <span>🚀 Nueva versión disponible</span>
        <button id="update-btn" class="btn-update">Actualizar</button>
        <button id="dismiss-btn" class="btn-dismiss">×</button>
      </div>
    `;
    
    document.body.appendChild(notification);
    
    // Event listeners para la notificación
    document.getElementById('update-btn').addEventListener('click', () => {
      if (this.swRegistration && this.swRegistration.waiting) {
        this.swRegistration.waiting.postMessage({ type: 'SKIP_WAITING' });
        window.location.reload();
      }
    });
    
    document.getElementById('dismiss-btn').addEventListener('click', () => {
      notification.remove();
    });
    
    // Auto-dismiss después de 10 segundos
    setTimeout(() => {
      if (notification.parentNode) {
        notification.remove();
      }
    }, 10000);
  }
  
  setupEventListeners() {
    // Install prompt
    window.addEventListener('beforeinstallprompt', (e) => {
      e.preventDefault();
      this.deferredPrompt = e;
      this.showInstallButton();
    });
    
    // App instalada
    window.addEventListener('appinstalled', () => {
      console.log('PWA: App instalada exitosamente');
      this.hideInstallButton();
      this.trackEvent('pwa_installed');
    });
    
    // Online/Offline
    window.addEventListener('online', () => {
      this.isOnline = true;
      this.updateOnlineStatus();
      this.syncWhenOnline();
    });
    
    window.addEventListener('offline', () => {
      this.isOnline = false;
      this.updateOnlineStatus();
    });
    
    // Visibility changes para optimizar performance
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) {
        this.handleAppHidden();
      } else {
        this.handleAppVisible();
      }
    });
  }
  
  showInstallButton() {
    let installBtn = document.getElementById('install-pwa-btn');
    
    if (!installBtn) {
      installBtn = document.createElement('button');
      installBtn.id = 'install-pwa-btn';
      installBtn.className = 'install-btn';
      installBtn.innerHTML = `
        📱 <span>Instalar App</span>
      `;
      
      // Busca el mejor lugar para colocar el botón
      const header = document.querySelector('header') || 
                    document.querySelector('.header') || 
                    document.querySelector('nav') ||
                    document.body;
      
      header.appendChild(installBtn);
    }
    
    installBtn.style.display = 'flex';
    
    installBtn.addEventListener('click', async () => {
      if (this.deferredPrompt) {
        this.deferredPrompt.prompt();
        const result = await this.deferredPrompt.userChoice;
        
        if (result.outcome === 'accepted') {
          this.trackEvent('pwa_install_accepted');
        } else {
          this.trackEvent('pwa_install_dismissed');
        }
        
        this.deferredPrompt = null;
        this.hideInstallButton();
      }
    });
  }
  
  hideInstallButton() {
    const installBtn = document.getElementById('install-pwa-btn');
    if (installBtn) {
      installBtn.style.display = 'none';
    }
  }
  
  updateOnlineStatus() {
    const statusIndicator = document.getElementById('online-status') || 
                           this.createOnlineStatusIndicator();
    
    statusIndicator.className = this.isOnline ? 'online' : 'offline';
    statusIndicator.textContent = this.isOnline ? '🟢 Online' : '🔴 Offline';
    
    // Toast notification
    this.showToast(
      this.isOnline ? 'Conexión restaurada' : 'Sin conexión - Modo offline activo',
      this.isOnline ? 'success' : 'warning'
    );
  }
  
  createOnlineStatusIndicator() {
    const indicator = document.createElement('div');
    indicator.id = 'online-status';
    indicator.className = 'online-status';
    
    const header = document.querySelector('header') || document.body;
    header.appendChild(indicator);
    
    return indicator;
  }
  
  setupPWAUI() {
    // Añade meta tags específicos para móvil si no existen
    this.addMetaTag('theme-color', '#2563eb');
    this.addMetaTag('apple-mobile-web-app-capable', 'yes');
    this.addMetaTag('apple-mobile-web-app-status-bar-style', 'default');
    this.addMetaTag('apple-mobile-web-app-title', 'Sudoku');
    
    // Apple Touch Icons
    this.addAppleTouchIcon(180, '/Sudoku/public/assets/icons/icon-192x192.png');
    this.addAppleTouchIcon(152, '/Sudoku/public/assets/icons/icon-152x152.png');
    this.addAppleTouchIcon(144, '/Sudoku/public/assets/icons/icon-144x144.png');
    
    // Safe area para notch
    this.addSafeAreaCSS();
  }
  
  addMetaTag(name, content) {
    if (!document.querySelector(`meta[name="${name}"]`)) {
      const meta = document.createElement('meta');
      meta.name = name;
      meta.content = content;
      document.head.appendChild(meta);
    }
  }
  
  addAppleTouchIcon(size, href) {
    if (!document.querySelector(`link[sizes="${size}x${size}"]`)) {
      const link = document.createElement('link');
      link.rel = 'apple-touch-icon';
      link.sizes = `${size}x${size}`;
      link.href = href;
      document.head.appendChild(link);
    }
  }
  
  addSafeAreaCSS() {
    if (!document.getElementById('safe-area-css')) {
      const style = document.createElement('style');
      style.id = 'safe-area-css';
      style.textContent = `
        @supports (padding: max(0px)) {
          .safe-area-top { padding-top: max(20px, env(safe-area-inset-top)); }
          .safe-area-bottom { padding-bottom: max(20px, env(safe-area-inset-bottom)); }
          .safe-area-left { padding-left: max(20px, env(safe-area-inset-left)); }
          .safe-area-right { padding-right: max(20px, env(safe-area-inset-right)); }
        }
      `;
      document.head.appendChild(style);
    }
  }
  
  setupMobileOptimizations() {
    // Previene zoom en inputs
    this.preventZoomOnInputs();
    
    // Mejora scroll en iOS
    this.improveIOSScrolling();
    
    // Touch feedback mejorado
    this.enhanceTouchFeedback();
    
    // Orientación y resize
    this.handleOrientationChange();
  }
  
  preventZoomOnInputs() {
    const inputs = document.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
      if (input.style.fontSize === '' || parseFloat(input.style.fontSize) < 16) {
        input.style.fontSize = '16px';
      }
    });
  }
  
  improveIOSScrolling() {
    document.body.style.webkitOverflowScrolling = 'touch';
    
    // Fix para scroll momentum en iOS
    const scrollContainers = document.querySelectorAll('.scroll-container, .game-area');
    scrollContainers.forEach(container => {
      container.style.webkitOverflowScrolling = 'touch';
      container.style.overflowScrolling = 'touch';
    });
  }
  
  enhanceTouchFeedback() {
    // Añade haptic feedback si está disponible
    if ('vibrate' in navigator) {
      document.addEventListener('touchstart', (e) => {
        if (e.target.matches('button, .btn, .cell, .number-btn')) {
          navigator.vibrate(10); // Vibración suave
        }
      });
    }
    
    // Mejora el touch feedback visual
    const style = document.createElement('style');
    style.textContent = `
      .touch-feedback {
        transition: transform 0.1s ease, background-color 0.1s ease;
      }
      .touch-feedback:active {
        transform: scale(0.95);
        background-color: rgba(37, 99, 235, 0.1);
      }
    `;
    document.head.appendChild(style);
    
    // Aplica la clase a elementos interactivos
    const interactiveElements = document.querySelectorAll('button, .btn, .cell, .number-btn');
    interactiveElements.forEach(el => el.classList.add('touch-feedback'));
  }
  
  handleOrientationChange() {
    window.addEventListener('orientationchange', () => {
      setTimeout(() => {
        this.adjustLayoutForOrientation();
      }, 100);
    });
    
    window.addEventListener('resize', () => {
      this.adjustLayoutForOrientation();
    });
  }
  
  adjustLayoutForOrientation() {
    const isLandscape = window.innerWidth > window.innerHeight;
    document.body.classList.toggle('landscape', isLandscape);
    document.body.classList.toggle('portrait', !isLandscape);
    
    // Ajusta el viewport height para móviles
    document.documentElement.style.setProperty('--vh', `${window.innerHeight * 0.01}px`);
  }
  
  preloadCriticalResources() {
    const criticalResources = [
      '/Sudoku/public/assets/css/mobile-optimizations.css',
      '/Sudoku/public/api/puzzle/new/easy'
    ];
    
    criticalResources.forEach(url => {
      const link = document.createElement('link');
      link.rel = 'prefetch';
      link.href = url;
      document.head.appendChild(link);
    });
  }
  
  handleAppHidden() {
    // Pausa timers, reduce actividad
    if (window.gameTimer) {
      window.gameTimer.pause();
    }
  }
  
  handleAppVisible() {
    // Reanuda timers, actualiza estado
    if (window.gameTimer) {
      window.gameTimer.resume();
    }
    
    // Verifica si hay actualizaciones
    if (this.swRegistration) {
      this.swRegistration.update();
    }
  }
  
  syncWhenOnline() {
    if ('serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype) {
      navigator.serviceWorker.ready.then(registration => {
        return registration.sync.register('background-sync');
      });
    }
  }
  
  showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 100);
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }
  
  trackEvent(eventName, data = {}) {
    // Analytics integration
    if (typeof gtag !== 'undefined') {
      gtag('event', eventName, {
        event_category: 'PWA',
        ...data
      });
    }
    
    console.log(`PWA Event: ${eventName}`, data);
  }
  
  // Método para obtener información del cache
  async getCacheInfo() {
    if (!this.swRegistration) return null;
    
    return new Promise((resolve) => {
      const messageChannel = new MessageChannel();
      messageChannel.port1.onmessage = (event) => {
        resolve(event.data);
      };
      
      this.swRegistration.active.postMessage(
        { type: 'GET_CACHE_INFO' }, 
        [messageChannel.port2]
      );
    });
  }
  
  // Método para limpiar cache manualmente
  async clearCache() {
    if ('caches' in window) {
      const cacheNames = await caches.keys();
      const sudokuCaches = cacheNames.filter(name => 
        name.includes('sudoku-minimalista')
      );
      
      await Promise.all(
        sudokuCaches.map(name => caches.delete(name))
      );
      
      this.showToast('Cache limpiado exitosamente', 'success');
      
      if (this.swRegistration) {
        this.swRegistration.update();
      }
    }
  }
}

// Inicialización automática cuando el DOM esté listo
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    window.pwaManager = new PWAManager();
  });
} else {
  window.pwaManager = new PWAManager();
}