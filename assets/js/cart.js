/**
 * Complete Shopping Cart Management System
 * assets/js/cart.js
 * 
 * Features:
 * - Add/Update/Remove items
 * - Local storage persistence
 * - Server synchronization
 * - Real-time UI updates
 * - Stock validation
 * - Shipping calculation
 * - Cart notifications
 * - Mobile responsive
 */

class ShoppingCart {
    constructor(options = {}) {
        this.options = {
            apiEndpoint: '../includes/ajax-cart.php',
            storageKey: 'somay_cart',
            autoSync: true,
            syncInterval: 30000, // 30 seconds
            notificationDuration: 3000,
            debug: false,
            ...options
        };

        this.cart = [];
        this.isLoading = false;
        this.syncTimer = null;
        this.isCartOpen = false;

        this.init();
    }

    /**
     * Initialize cart system
     */
    init() {
        this.log('Initializing shopping cart...');
        
        // Load cart from localStorage
        this.loadFromStorage();
        
        // Bind all events
        this.bindEvents();
        
        // Update UI
        this.updateUI();
        
        // Sync with server
        if (this.options.autoSync) {
            this.syncWithServer();
            this.startAutoSync();
        }

        this.log('Shopping cart initialized successfully');
    }

    /**
     * Bind all event listeners
     */
    bindEvents() {
        // Add to cart buttons
        this.bindAddToCartEvents();
        
        // Cart item controls
        this.bindCartControlEvents();
        
        // Cart sidebar events
        this.bindCartSidebarEvents();
        
        // Form events
        this.bindFormEvents();
        
        // Page events
        this.bindPageEvents();
    }

    /**
     * Bind add to cart events
     */
    bindAddToCartEvents() {
        document.addEventListener('click', (e) => {
            const button = e.target.closest('[data-add-to-cart]');
            if (!button) return;

            e.preventDefault();
            e.stopPropagation();

            const productId = parseInt(button.dataset.productId || button.dataset.addToCart);
            const quantity = parseInt(button.dataset.quantity || 1);
            const productName = button.dataset.productName || 'Produk';

            if (!productId || productId <= 0) {
                this.showError('ID produk tidak valid');
                return;
            }

            this.addToCart(productId, quantity, productName, button);
        });

        // Quick add buttons (with + icon)
        document.addEventListener('click', (e) => {
            const button = e.target.closest('[data-quick-add]');
            if (!button) return;

            e.preventDefault();
            e.stopPropagation();

            const productId = parseInt(button.dataset.productId || button.dataset.quickAdd);
            this.addToCart(productId, 1, '', button);
        });
    }

    /**
     * Bind cart control events
     */
    bindCartControlEvents() {
        // Quantity controls
        document.addEventListener('click', (e) => {
            // Increase quantity
            const increaseBtn = e.target.closest('[data-quantity-increase]');
            if (increaseBtn) {
                e.preventDefault();
                const productId = parseInt(increaseBtn.dataset.productId);
                this.increaseQuantity(productId);
                return;
            }

            // Decrease quantity
            const decreaseBtn = e.target.closest('[data-quantity-decrease]');
            if (decreaseBtn) {
                e.preventDefault();
                const productId = parseInt(decreaseBtn.dataset.productId);
                this.decreaseQuantity(productId);
                return;
            }

            // Remove item
            const removeBtn = e.target.closest('[data-remove-item]');
            if (removeBtn) {
                e.preventDefault();
                const productId = parseInt(removeBtn.dataset.productId);
                const productName = removeBtn.dataset.productName || 'item';
                this.removeFromCart(productId, productName);
                return;
            }

            // Clear cart
            const clearBtn = e.target.closest('[data-clear-cart]');
            if (clearBtn) {
                e.preventDefault();
                this.clearCart();
                return;
            }
        });

        // Quantity input changes
        document.addEventListener('change', (e) => {
            const input = e.target.closest('[data-quantity-input]');
            if (!input) return;

            const productId = parseInt(input.dataset.productId);
            const newQuantity = parseInt(input.value) || 0;
            
            if (newQuantity < 0) {
                input.value = 0;
                return;
            }

            this.updateQuantity(productId, newQuantity);
        });

        // Prevent quantity input from going negative
        document.addEventListener('input', (e) => {
            const input = e.target.closest('[data-quantity-input]');
            if (!input) return;

            if (parseInt(input.value) < 0) {
                input.value = 0;
            }
        });
    }

    /**
     * Bind cart sidebar events
     */
    bindCartSidebarEvents() {
        // Toggle cart sidebar
        document.addEventListener('click', (e) => {
            const toggleBtn = e.target.closest('[data-toggle-cart]');
            if (!toggleBtn) return;

            e.preventDefault();
            this.toggleCartSidebar();
        });

        // Close cart overlay
        document.addEventListener('click', (e) => {
            if (e.target.matches('#cart-overlay, [data-close-cart]')) {
                this.closeCartSidebar();
            }
        });

        // Prevent cart content clicks from closing
        document.addEventListener('click', (e) => {
            const cartSidebar = e.target.closest('#cart-sidebar');
            if (cartSidebar) {
                e.stopPropagation();
            }
        });

        // ESC key to close cart
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isCartOpen) {
                this.closeCartSidebar();
            }
        });
    }

    /**
     * Bind form events
     */
    bindFormEvents() {
        // Shipping area change
        document.addEventListener('change', (e) => {
            const select = e.target.closest('[data-shipping-select]');
            if (!select) return;

            const areaId = parseInt(select.value);
            this.calculateShipping(areaId);
        });

        // Checkout form validation
        document.addEventListener('submit', (e) => {
            const form = e.target.closest('[data-checkout-form]');
            if (!form) return;

            if (!this.validateCheckout()) {
                e.preventDefault();
                return false;
            }
        });
    }

    /**
     * Bind page events
     */
    bindPageEvents() {
        // Page visibility change
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.syncWithServer();
            }
        });

        // Before page unload
        window.addEventListener('beforeunload', () => {
            this.saveToStorage();
        });

        // Page load
        window.addEventListener('load', () => {
            this.updateUI();
        });
    }

    /**
     * Add product to cart
     */
    async addToCart(productId, quantity = 1, productName = '', button = null) {
        if (this.isLoading) return;

        this.log(`Adding to cart: Product ${productId}, Quantity ${quantity}`);

        // Show loading state
        if (button) {
            this.setButtonLoading(button, true);
        }

        try {
            const response = await this.apiCall('add', {
                product_id: productId,
                quantity: quantity
            });

            if (response.success) {
                this.showSuccess(response.message || `${productName} ditambahkan ke keranjang`);
                
                // Update local cart
                this.updateLocalCart(productId, quantity, 'add');
                
                // Update UI
                this.updateUI();
                
                // Auto open cart if on mobile
                if (this.isMobile()) {
                    setTimeout(() => this.openCartSidebar(), 500);
                }
            } else {
                this.showError(response.message || 'Gagal menambahkan produk ke keranjang');
            }
        } catch (error) {
            this.log('Add to cart error:', error);
            this.showError('Terjadi kesalahan saat menambahkan produk');
        } finally {
            if (button) {
                this.setButtonLoading(button, false);
            }
        }
    }

    /**
     * Update product quantity
     */
    async updateQuantity(productId, newQuantity) {
        if (this.isLoading) return;

        this.log(`Updating quantity: Product ${productId}, New quantity ${newQuantity}`);

        try {
            const response = await this.apiCall('update', {
                product_id: productId,
                quantity: newQuantity
            });

            if (response.success) {
                // Update local cart
                this.updateLocalCart(productId, newQuantity, 'set');
                
                // Update UI
                this.updateUI();
                
                // Show success message for significant changes
                if (newQuantity === 0) {
                    this.showSuccess('Item dihapus dari keranjang');
                }
            } else {
                this.showError(response.message || 'Gagal mengupdate jumlah item');
                // Revert UI to previous state
                this.renderCartItems();
            }
        } catch (error) {
            this.log('Update quantity error:', error);
            this.showError('Terjadi kesalahan saat mengupdate keranjang');
            this.renderCartItems();
        }
    }

    /**
     * Increase product quantity
     */
    async increaseQuantity(productId) {
        const currentQty = this.getItemQuantity(productId);
        await this.updateQuantity(productId, currentQty + 1);
    }

    /**
     * Decrease product quantity
     */
    async decreaseQuantity(productId) {
        const currentQty = this.getItemQuantity(productId);
        const newQty = Math.max(0, currentQty - 1);
        await this.updateQuantity(productId, newQty);
    }

    /**
     * Remove product from cart
     */
    async removeFromCart(productId, productName = 'item') {
        if (this.isLoading) return;

        // Confirm removal
        if (!confirm(`Hapus ${productName} dari keranjang?`)) {
            return;
        }

        this.log(`Removing from cart: Product ${productId}`);

        try {
            const response = await this.apiCall('remove', {
                product_id: productId
            });

            if (response.success) {
                this.showSuccess(response.message || `${productName} dihapus dari keranjang`);
                
                // Update local cart
                this.removeFromLocalCart(productId);
                
                // Update UI
                this.updateUI();
                
                // Close cart if empty
                if (this.getItemCount() === 0) {
                    setTimeout(() => this.closeCartSidebar(), 1000);
                }
            } else {
                this.showError(response.message || 'Gagal menghapus item dari keranjang');
            }
        } catch (error) {
            this.log('Remove from cart error:', error);
            this.showError('Terjadi kesalahan saat menghapus item');
        }
    }

    /**
     * Clear entire cart
     */
    async clearCart() {
        if (this.isLoading) return;

        if (!confirm('Kosongkan seluruh keranjang belanja?')) {
            return;
        }

        this.log('Clearing cart');

        try {
            const response = await this.apiCall('clear');

            if (response.success) {
                this.showSuccess(response.message || 'Keranjang berhasil dikosongkan');
                
                // Clear local cart
                this.cart = [];
                this.saveToStorage();
                
                // Update UI
                this.updateUI();
                
                // Close cart
                setTimeout(() => this.closeCartSidebar(), 1000);
            } else {
                this.showError(response.message || 'Gagal mengosongkan keranjang');
            }
        } catch (error) {
            this.log('Clear cart error:', error);
            this.showError('Terjadi kesalahan saat mengosongkan keranjang');
        }
    }

    /**
     * Calculate shipping cost
     */
    async calculateShipping(areaId) {
        if (!areaId) {
            this.updateShippingUI(0, 0, false);
            return;
        }

        this.log(`Calculating shipping for area ${areaId}`);

        try {
            const response = await this.apiCall('shipping', {
                shipping_area_id: areaId
            });

            if (response.success) {
                this.updateShippingUI(
                    response.shipping_cost,
                    response.total,
                    response.is_free_shipping,
                    response.shipping_cost_formatted,
                    response.total_formatted
                );
            } else {
                this.showError(response.message || 'Gagal menghitung ongkos kirim');
            }
        } catch (error) {
            this.log('Calculate shipping error:', error);
            this.showError('Terjadi kesalahan saat menghitung ongkir');
        }
    }

    /**
     * Sync cart with server
     */
    async syncWithServer() {
        if (this.isLoading) return;

        this.log('Syncing cart with server...');

        try {
            const response = await this.apiCall('sync');

            if (response.success) {
                if (response.updated) {
                    this.showInfo(response.message || 'Keranjang telah diperbarui');
                    this.updateUI();
                }

                // Show warnings if any
                if (response.warnings && response.warnings.length > 0) {
                    this.showCartWarnings(response.warnings);
                }
            }
        } catch (error) {
            this.log('Sync error:', error);
            // Don't show error for sync failures to avoid annoying users
        }
    }

    /**
     * Validate cart for checkout
     */
    async validateCheckout() {
        this.log('Validating cart for checkout...');

        try {
            const response = await this.apiCall('validate');

            if (response.success) {
                if (response.valid) {
                    return true;
                } else {
                    this.showCartErrors(response.errors || ['Keranjang tidak valid']);
                    return false;
                }
            } else {
                this.showError('Gagal validasi keranjang');
                return false;
            }
        } catch (error) {
            this.log('Validation error:', error);
            this.showError('Terjadi kesalahan saat validasi');
            return false;
        }
    }

    /**
     * Load cart from server
     */
    async loadCartFromServer() {
        this.log('Loading cart from server...');

        try {
            const response = await this.apiCall('get');

            if (response.success) {
                this.cart = response.items || [];
                this.saveToStorage();
                this.updateUI();
                
                // Show warnings
                if (response.warnings && response.warnings.length > 0) {
                    this.showCartWarnings(response.warnings);
                }
            }
        } catch (error) {
            this.log('Load cart error:', error);
        }
    }

    /**
     * Update UI elements
     */
    updateUI() {
        this.updateCartCount();
        this.updateCartTotal();
        this.renderCartItems();
        this.updateCartButtons();
    }

    /**
     * Update cart count displays
     */
    updateCartCount() {
        const count = this.getItemCount();
        const countElements = document.querySelectorAll('[data-cart-count]');

        countElements.forEach(element => {
            element.textContent = count;
            
            // Show/hide badge
            if (count > 0) {
                element.classList.remove('hidden');
                element.classList.add('inline-flex');
            } else {
                element.classList.add('hidden');
                element.classList.remove('inline-flex');
            }
        });
    }

    /**
     * Update cart total displays
     */
    updateCartTotal() {
        const total = this.getCartTotal();
        const formattedTotal = this.formatCurrency(total);
        const totalElements = document.querySelectorAll('[data-cart-total]');

        totalElements.forEach(element => {
            element.textContent = formattedTotal;
        });
    }

    /**
     * Render cart items in sidebar
     */
    renderCartItems() {
        const container = document.getElementById('cart-items');
        if (!container) return;

        if (this.cart.length === 0) {
            this.renderEmptyCart(container);
            return;
        }

        let html = '';
        this.cart.forEach(item => {
            html += this.createCartItemHTML(item);
        });

        container.innerHTML = html;
    }

    /**
     * Render empty cart state
     */
    renderEmptyCart(container) {
        container.innerHTML = `
            <div class="text-center py-8">
                <div class="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-shopping-cart text-gray-400 text-xl"></i>
                </div>
                <p class="text-gray-500 mb-4">Keranjang belanja kosong</p>
                <a href="products.php" class="text-primary hover:text-secondary font-medium text-sm">
                    Mulai belanja sekarang →
                </a>
            </div>
        `;
    }

    /**
     * Create cart item HTML
     */
    createCartItemHTML(item) {
        const imageUrl = item.image ? `../uploads/products/${item.image}` : '';
        const stockWarning = item.stock < item.quantity ? 
            '<span class="text-red-500 text-xs"><i class="fas fa-exclamation-triangle mr-1"></i>Stok terbatas</span>' : '';

        return `
            <div class="cart-item flex items-center py-4 border-b border-gray-100 last:border-b-0" data-product-id="${item.id}">
                <div class="w-16 h-16 flex-shrink-0 mr-4">
                    ${imageUrl ? 
                        `<img src="${imageUrl}" alt="${item.name}" class="w-16 h-16 object-cover rounded-lg border">` :
                        `<div class="w-16 h-16 bg-gray-100 rounded-lg border flex items-center justify-center">
                            <i class="fas fa-image text-gray-400"></i>
                        </div>`
                    }
                </div>
                
                <div class="flex-1 min-w-0">
                    <h5 class="font-medium text-gray-900 truncate">${item.name}</h5>
                    <p class="text-sm text-gray-500 mb-1">${this.formatCurrency(item.price)}</p>
                    ${stockWarning}
                    
                    <div class="flex items-center mt-2">
                        <button type="button" 
                                class="w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200 transition-colors" 
                                data-quantity-decrease data-product-id="${item.id}"
                                ${item.quantity <= 1 ? 'title="Hapus item"' : ''}>
                            <i class="fas fa-minus text-xs"></i>
                        </button>
                        
                        <input type="number" 
                               value="${item.quantity}" 
                               min="0" 
                               max="${item.stock}"
                               class="w-12 mx-2 text-center text-sm border border-gray-300 rounded focus:border-primary focus:ring-1 focus:ring-primary"
                               data-quantity-input 
                               data-product-id="${item.id}">
                        
                        <button type="button" 
                                class="w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200 transition-colors" 
                                data-quantity-increase 
                                data-product-id="${item.id}"
                                ${item.quantity >= item.stock ? 'disabled title="Stok tidak mencukupi"' : ''}>
                            <i class="fas fa-plus text-xs"></i>
                        </button>
                    </div>
                </div>
                
                <div class="ml-4 text-right">
                    <p class="font-medium text-gray-900">${this.formatCurrency(item.subtotal)}</p>
                    <button type="button" 
                            class="text-red-500 hover:text-red-700 text-sm mt-1" 
                            data-remove-item 
                            data-product-id="${item.id}" 
                            data-product-name="${item.name}"
                            title="Hapus dari keranjang">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Update cart-related buttons
     */
    updateCartButtons() {
        // Update add to cart buttons
        const addButtons = document.querySelectorAll('[data-add-to-cart]');
        addButtons.forEach(button => {
            const productId = parseInt(button.dataset.productId || button.dataset.addToCart);
            const quantity = this.getItemQuantity(productId);
            
            if (quantity > 0) {
                button.classList.add('in-cart');
                const originalText = button.dataset.originalText || button.textContent;
                button.dataset.originalText = originalText;
                button.innerHTML = `<i class="fas fa-check mr-2"></i>Di Keranjang (${quantity})`;
            } else {
                button.classList.remove('in-cart');
                if (button.dataset.originalText) {
                    button.innerHTML = button.dataset.originalText;
                }
            }
        });
    }

    /**
     * Update shipping UI
     */
    updateShippingUI(cost, total, isFree, costFormatted = null, totalFormatted = null) {
        // Update shipping cost
        const shippingElements = document.querySelectorAll('[data-shipping-cost]');
        shippingElements.forEach(el => {
            el.textContent = costFormatted || this.formatCurrency(cost);
        });

        // Update total
        const totalElements = document.querySelectorAll('[data-total-amount]');
        totalElements.forEach(el => {
            el.textContent = totalFormatted || this.formatCurrency(total);
        });

        // Show/hide free shipping info
        const freeShippingElements = document.querySelectorAll('[data-free-shipping-info]');
        freeShippingElements.forEach(el => {
            if (isFree && cost === 0) {
                el.classList.remove('hidden');
            } else {
                el.classList.add('hidden');
            }
        });
    }

    /**
     * Cart sidebar controls
     */
    toggleCartSidebar() {
        if (this.isCartOpen) {
            this.closeCartSidebar();
        } else {
            this.openCartSidebar();
        }
    }

    openCartSidebar() {
        const sidebar = document.getElementById('cart-sidebar');
        const overlay = document.getElementById('cart-overlay');

        if (sidebar && overlay) {
            sidebar.classList.remove('translate-x-full');
            overlay.classList.remove('hidden');
            this.isCartOpen = true;
            
            // Load fresh cart data
            this.loadCartFromServer();
            
            // Prevent body scroll
            document.body.style.overflow = 'hidden';
        }
    }

    closeCartSidebar() {
        const sidebar = document.getElementById('cart-sidebar');
        const overlay = document.getElementById('cart-overlay');

        if (sidebar && overlay) {
            sidebar.classList.add('translate-x-full');
            overlay.classList.add('hidden');
            this.isCartOpen = false;
            
            // Restore body scroll
            document.body.style.overflow = '';
        }
    }

    /**
     * Notification system
     */
    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showError(message) {
        this.showNotification(message, 'error');
    }

    showInfo(message) {
        this.showNotification(message, 'info');
    }

    showWarning(message) {
        this.showNotification(message, 'warning');
    }

    showNotification(message, type = 'info', duration = null) {
        duration = duration || this.options.notificationDuration;

        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 max-w-sm p-4 rounded-lg shadow-lg z-50 transform translate-x-full transition-all duration-300`;
        
        const configs = {
            success: { bg: 'bg-green-500', icon: 'fa-check-circle', text: 'text-white' },
            error: { bg: 'bg-red-500', icon: 'fa-exclamation-circle', text: 'text-white' },
            warning: { bg: 'bg-yellow-500', icon: 'fa-exclamation-triangle', text: 'text-gray-900' },
            info: { bg: 'bg-blue-500', icon: 'fa-info-circle', text: 'text-white' }
        };

        const config = configs[type] || configs.info;
        notification.className += ` ${config.bg} ${config.text}`;
        
        notification.innerHTML = `
            <div class="flex items-start">
                <i class="fas ${config.icon} mr-3 mt-0.5 flex-shrink-0"></i>
                <div class="flex-1">
                    <p class="text-sm font-medium">${message}</p>
                </div>
                <button type="button" class="ml-3 flex-shrink-0 opacity-70 hover:opacity-100" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>
        `;

        document.body.appendChild(notification);

        // Animate in
        setTimeout(() => notification.classList.remove('translate-x-full'), 100);

        // Auto remove
        setTimeout(() => {
            notification.classList.add('translate-x-full');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, duration);
    }

    showCartWarnings(warnings) {
        warnings.forEach(warning => {
            this.showNotification(warning.message, warning.type, 5000);
        });
    }

    showCartErrors(errors) {
        errors.forEach(error => {
            this.showError(error);
        });
    }

    /**
     * Local cart management
     */
    updateLocalCart(productId, quantity, action = 'set') {
        const existingIndex = this.cart.findIndex(item => item.id === productId);

        if (action === 'add') {
            if (existingIndex >= 0) {
                this.cart[existingIndex].quantity += quantity;
            } else {
                // Will be populated by server response
                this.cart.push({ id: productId, quantity: quantity });
            }
        } else if (action === 'set') {
            if (existingIndex >= 0) {
                if (quantity > 0) {
                    this.cart[existingIndex].quantity = quantity;
                } else {
                    this.cart.splice(existingIndex, 1);
                }
            }
        }

        this.saveToStorage();
    }

    removeFromLocalCart(productId) {
        this.cart = this.cart.filter(item => item.id !== productId);
        this.saveToStorage();
    }

    getItemQuantity(productId) {
        const item = this.cart.find(item => item.id === productId);
        return item ? item.quantity : 0;
    }

    getItemCount() {
        return this.cart.reduce((total, item) => total + (item.quantity || 0), 0);
    }

    getCartTotal() {
        return this.cart.reduce((total, item) => {
            return total + ((item.price || 0) * (item.quantity || 0));
        }, 0);
    }

    /**
     * Storage management
     */
    saveToStorage() {
        try {
            localStorage.setItem(this.options.storageKey, JSON.stringify(this.cart));
        } catch (error) {
            this.log('Storage save error:', error);
        }
    }

    loadFromStorage() {
        try {
            const stored = localStorage.getItem(this.options.storageKey);
            if (stored) {
                this.cart = JSON.parse(stored);
            }
        } catch (error) {
            this.log('Storage load error:', error);
            this.cart = [];
        }
    }

    clearStorage() {
        try {
            localStorage.removeItem(this.options.storageKey);
        } catch (error) {
            this.log('Storage clear error:', error);
        }
    }

    /**
     * Auto sync management
     */
    startAutoSync() {
        if (this.syncTimer) {
            clearInterval(this.syncTimer);
        }

        this.syncTimer = setInterval(() => {
            if (!document.hidden) {
                this.syncWithServer();
            }
        }, this.options.syncInterval);
    }

    stopAutoSync() {
        if (this.syncTimer) {
            clearInterval(this.syncTimer);
            this.syncTimer = null;
        }
    }

    /**
     * API communication
     */
    async apiCall(action, data = {}) {
        if (this.isLoading && action !== 'get' && action !== 'sync') {
            throw new Error('Another operation is in progress');
        }

        this.isLoading = true;

        try {
            const formData = new FormData();
            formData.append('action', action);
            
            for (const [key, value] of Object.entries(data)) {
                formData.append(key, value);
            }

            const response = await fetch(this.options.apiEndpoint, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            this.log(`API ${action} response:`, result);
            
            return result;
        } catch (error) {
            this.log(`API ${action} error:`, error);
            throw error;
        } finally {
            this.isLoading = false;
        }
    }

    /**
     * Utility methods
     */
    setButtonLoading(button, loading) {
        if (!button) return;

        if (loading) {
            button.disabled = true;
            button.dataset.originalContent = button.innerHTML;
            button.innerHTML = `
                <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-current inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Loading...
            `;
            button.classList.add('opacity-75', 'cursor-not-allowed');
        } else {
            button.disabled = false;
            if (button.dataset.originalContent) {
                button.innerHTML = button.dataset.originalContent;
                delete button.dataset.originalContent;
            }
            button.classList.remove('opacity-75', 'cursor-not-allowed');
        }
    }

    formatCurrency(amount) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0
        }).format(amount || 0);
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    isMobile() {
        return window.innerWidth < 768;
    }

    isTablet() {
        return window.innerWidth >= 768 && window.innerWidth < 1024;
    }

    isDesktop() {
        return window.innerWidth >= 1024;
    }

    log(...args) {
        if (this.options.debug) {
            console.log('[ShoppingCart]', ...args);
        }
    }

    /**
     * Advanced features
     */

    // Get cart summary for analytics
    getCartSummary() {
        return {
            itemCount: this.getItemCount(),
            totalValue: this.getCartTotal(),
            items: this.cart.map(item => ({
                id: item.id,
                name: item.name,
                quantity: item.quantity,
                price: item.price,
                category: item.category
            }))
        };
    }

    // Export cart data
    exportCart() {
        return {
            cart: this.cart,
            timestamp: new Date().toISOString(),
            version: '1.0'
        };
    }

    // Import cart data
    async importCart(cartData) {
        if (!cartData || !cartData.cart) {
            throw new Error('Invalid cart data');
        }

        this.cart = cartData.cart;
        this.saveToStorage();
        await this.syncWithServer();
        this.updateUI();
    }

    // Get recommended products based on cart
    async getRecommendations() {
        try {
            const response = await this.apiCall('recommendations');
            return response.success ? response.products : [];
        } catch (error) {
            this.log('Get recommendations error:', error);
            return [];
        }
    }

    // Apply coupon code
    async applyCoupon(couponCode) {
        try {
            const response = await this.apiCall('apply_coupon', {
                coupon_code: couponCode
            });

            if (response.success) {
                this.showSuccess(response.message);
                this.updateUI();
                return true;
            } else {
                this.showError(response.message);
                return false;
            }
        } catch (error) {
            this.log('Apply coupon error:', error);
            this.showError('Terjadi kesalahan saat menerapkan kupon');
            return false;
        }
    }

    // Remove coupon
    async removeCoupon() {
        try {
            const response = await this.apiCall('remove_coupon');

            if (response.success) {
                this.showInfo(response.message);
                this.updateUI();
                return true;
            }
        } catch (error) {
            this.log('Remove coupon error:', error);
        }
        return false;
    }

    // Save cart for later (wishlist functionality)
    async saveForLater(productId) {
        try {
            const response = await this.apiCall('save_for_later', {
                product_id: productId
            });

            if (response.success) {
                this.showSuccess('Produk disimpan untuk nanti');
                this.removeFromLocalCart(productId);
                this.updateUI();
            } else {
                this.showError(response.message);
            }
        } catch (error) {
            this.log('Save for later error:', error);
            this.showError('Gagal menyimpan produk');
        }
    }

    // Move from saved items back to cart
    async moveToCart(productId) {
        try {
            const response = await this.apiCall('move_to_cart', {
                product_id: productId
            });

            if (response.success) {
                this.showSuccess('Produk dipindahkan ke keranjang');
                this.updateUI();
            } else {
                this.showError(response.message);
            }
        } catch (error) {
            this.log('Move to cart error:', error);
            this.showError('Gagal memindahkan produk');
        }
    }

    // Quick checkout for single product
    async quickCheckout(productId, quantity = 1) {
        this.log(`Quick checkout: Product ${productId}, Quantity ${quantity}`);
        
        // Clear current cart
        this.cart = [];
        
        // Add product to cart
        await this.addToCart(productId, quantity);
        
        // Redirect to checkout
        window.location.href = 'checkout.php';
    }

    // Bulk actions
    async bulkRemove(productIds) {
        if (!Array.isArray(productIds) || productIds.length === 0) {
            return;
        }

        try {
            const response = await this.apiCall('bulk_remove', {
                product_ids: productIds.join(',')
            });

            if (response.success) {
                this.showSuccess(`${productIds.length} item berhasil dihapus`);
                productIds.forEach(id => this.removeFromLocalCart(id));
                this.updateUI();
            } else {
                this.showError(response.message);
            }
        } catch (error) {
            this.log('Bulk remove error:', error);
            this.showError('Gagal menghapus item');
        }
    }

    // Update multiple quantities at once
    async bulkUpdateQuantities(updates) {
        if (!Array.isArray(updates) || updates.length === 0) {
            return;
        }

        try {
            const response = await this.apiCall('bulk_update', {
                updates: JSON.stringify(updates)
            });

            if (response.success) {
                this.showSuccess('Keranjang berhasil diperbarui');
                updates.forEach(update => {
                    this.updateLocalCart(update.product_id, update.quantity, 'set');
                });
                this.updateUI();
            } else {
                this.showError(response.message);
            }
        } catch (error) {
            this.log('Bulk update error:', error);
            this.showError('Gagal memperbarui keranjang');
        }
    }

    // Analytics tracking
    trackEvent(event, data = {}) {
        // Integration with analytics services
        if (typeof gtag !== 'undefined') {
            gtag('event', event, {
                event_category: 'Shopping Cart',
                ...data
            });
        }

        if (typeof fbq !== 'undefined') {
            fbq('track', event, data);
        }

        this.log('Analytics event:', event, data);
    }

    // Cart abandonment prevention
    setupAbandonmentPrevention() {
        let abandonmentTimer;
        
        const resetTimer = () => {
            clearTimeout(abandonmentTimer);
            if (this.getItemCount() > 0) {
                abandonmentTimer = setTimeout(() => {
                    this.showCartAbandonmentReminder();
                }, 300000); // 5 minutes
            }
        };

        // Reset timer on user activity
        ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
            document.addEventListener(event, resetTimer, true);
        });

        resetTimer();
    }

    showCartAbandonmentReminder() {
        if (this.getItemCount() === 0) return;

        const reminder = document.createElement('div');
        reminder.className = 'fixed bottom-4 right-4 max-w-sm bg-white border border-gray-200 rounded-lg shadow-lg p-4 z-50';
        reminder.innerHTML = `
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-shopping-cart text-primary text-xl"></i>
                </div>
                <div class="ml-3 flex-1">
                    <h4 class="text-sm font-medium text-gray-900">Jangan lupa pesanan Anda!</h4>
                    <p class="text-sm text-gray-600 mt-1">
                        Anda memiliki ${this.getItemCount()} item di keranjang senilai ${this.formatCurrency(this.getCartTotal())}
                    </p>
                    <div class="mt-3 flex space-x-2">
                        <button onclick="window.shoppingCart.openCartSidebar(); this.parentElement.parentElement.parentElement.parentElement.remove();" 
                                class="text-xs bg-primary text-white px-3 py-1 rounded hover:bg-secondary">
                            Lihat Keranjang
                        </button>
                        <button onclick="this.parentElement.parentElement.parentElement.parentElement.remove();" 
                                class="text-xs text-gray-500 hover:text-gray-700">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(reminder);

        // Auto remove after 10 seconds
        setTimeout(() => {
            if (reminder.parentNode) {
                reminder.parentNode.removeChild(reminder);
            }
        }, 10000);
    }

    /**
     * Destroy cart instance
     */
    destroy() {
        this.stopAutoSync();
        this.closeCartSidebar();
        this.clearStorage();
        
        // Remove event listeners would require more complex tracking
        // For now, just clear the cart data
        this.cart = [];
        this.isLoading = false;
        this.isCartOpen = false;
        
        this.log('Shopping cart destroyed');
    }
}

/**
 * Cart Manager - Singleton pattern for global cart management
 */
class CartManager {
    constructor() {
        if (CartManager.instance) {
            return CartManager.instance;
        }

        this.cart = null;
        this.isInitialized = false;
        CartManager.instance = this;
    }

    init(options = {}) {
        if (this.isInitialized) {
            return this.cart;
        }

        this.cart = new ShoppingCart(options);
        this.isInitialized = true;
        
        // Setup global cart abandonment prevention
        this.cart.setupAbandonmentPrevention();
        
        // Expose global methods
        this.exposeGlobalMethods();
        
        return this.cart;
    }

    exposeGlobalMethods() {
        // Add convenient global functions
        window.addToCart = (productId, quantity, productName, button) => {
            return this.cart.addToCart(productId, quantity, productName, button);
        };

        window.removeFromCart = (productId, productName) => {
            return this.cart.removeFromCart(productId, productName);
        };

        window.toggleCart = () => {
            return this.cart.toggleCartSidebar();
        };

        window.getCartCount = () => {
            return this.cart.getItemCount();
        };

        window.getCartTotal = () => {
            return this.cart.getCartTotal();
        };
    }

    getInstance() {
        return this.cart;
    }
}

/**
 * Auto-initialization
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize cart manager
    const cartManager = new CartManager();
    const cart = cartManager.init({
        debug: window.location.hostname === 'localhost',
        autoSync: true
    });

    // Make cart globally accessible
    window.shoppingCart = cart;
    window.cartManager = cartManager;

    // Initialize any existing cart data display
    cart.updateUI();

    console.log('🛒 Shopping Cart System Initialized');
});

// Handle page visibility changes for better sync
document.addEventListener('visibilitychange', function() {
    if (!document.hidden && window.shoppingCart) {
        window.shoppingCart.syncWithServer();
    }
});

// Handle online/offline status
window.addEventListener('online', function() {
    if (window.shoppingCart) {
        window.shoppingCart.syncWithServer();
    }
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ShoppingCart, CartManager };
}