<?php
// Dapatkan nama file saat ini untuk menentukan menu aktif
$current_page = basename($_SERVER['PHP_SELF']);

// Hitung notifikasi
try {
    $pdo = getDB();
    
    // Pesanan pending
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'");
    $stmt->execute();
    $pending_orders = $stmt->fetchColumn();
    
    // Produk stok menipis
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE stock_quantity <= min_stock AND is_active = 1");
    $stmt->execute();
    $low_stock_products = $stmt->fetchColumn();
    
    // Pesan kontak baru
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'");
    $stmt->execute();
    $new_messages = $stmt->fetchColumn();
    
} catch (Exception $e) {
    $pending_orders = 0;
    $low_stock_products = 0;
    $new_messages = 0;
}
?>
<!-- Sidebar -->
<div class="fixed inset-y-0 left-0 w-64 bg-dark text-white shadow-xl z-30">
    <div class="flex items-center justify-center h-16 border-b border-gray-700">
        <div class="flex items-center space-x-2">
            <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center">
                <i class="fas fa-utensils text-white"></i>
            </div>
            <span class="text-xl font-bold"><?php echo SITE_NAME; ?></span>
        </div>
    </div>
    
    <nav class="mt-6">
        <!-- Dashboard -->
        <a href="dashboard.php" class="flex items-center px-6 py-3 transition-colors duration-200 <?php echo $current_page === 'dashboard.php' ? 'text-white bg-primary/20 border-r-4 border-primary' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
            <i class="fas fa-home w-6"></i>
            <span>Dashboard</span>
        </a>
        
        <!-- Products -->
        <a href="products.php" class="flex items-center px-6 py-3 transition-colors duration-200 <?php echo $current_page === 'products.php' ? 'text-white bg-primary/20 border-r-4 border-primary' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
            <i class="fas fa-box w-6"></i>
            <span>Produk</span>
            <?php if ($low_stock_products > 0): ?>
            <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full"><?php echo $low_stock_products; ?></span>
            <?php endif; ?>
        </a>
        
        <!-- Orders -->
        <a href="orders.php" class="flex items-center px-6 py-3 transition-colors duration-200 <?php echo $current_page === 'orders.php' ? 'text-white bg-primary/20 border-r-4 border-primary' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
            <i class="fas fa-shopping-cart w-6"></i>
            <span>Pesanan</span>
            <?php if ($pending_orders > 0): ?>
            <span class="ml-auto bg-yellow-500 text-white text-xs px-2 py-1 rounded-full"><?php echo $pending_orders; ?></span>
            <?php endif; ?>
        </a>
        
        <!-- Categories -->
        <a href="categories.php" class="flex items-center px-6 py-3 transition-colors duration-200 <?php echo $current_page === 'categories.php' ? 'text-white bg-primary/20 border-r-4 border-primary' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
            <i class="fas fa-tags w-6"></i>
            <span>Kategori</span>
        </a>
        
        <!-- Shipping Areas -->
        <a href="shipping_areas.php" class="flex items-center px-6 py-3 transition-colors duration-200 <?php echo $current_page === 'shipping_areas.php' ? 'text-white bg-primary/20 border-r-4 border-primary' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
            <i class="fas fa-truck w-6"></i>
            <span>Area Pengiriman</span>
        </a>
        
        <!-- Customers -->
        <a href="customers.php" class="flex items-center px-6 py-3 transition-colors duration-200 <?php echo $current_page === 'customers.php' ? 'text-white bg-primary/20 border-r-4 border-primary' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
            <i class="fas fa-users w-6"></i>
            <span>Pelanggan</span>
        </a>
        
        <!-- Contact Messages -->
        <a href="contact_messages.php" class="flex items-center px-6 py-3 transition-colors duration-200 <?php echo $current_page === 'contact_messages.php' ? 'text-white bg-primary/20 border-r-4 border-primary' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
            <i class="fas fa-envelope w-6"></i>
            <span>Pesan Kontak</span>
            <?php if ($new_messages > 0): ?>
            <span class="ml-auto bg-blue-500 text-white text-xs px-2 py-1 rounded-full"><?php echo $new_messages; ?></span>
            <?php endif; ?>
        </a>
        
        <!-- Stock Movements -->
        <a href="stock_movements.php" class="flex items-center px-6 py-3 transition-colors duration-200 <?php echo $current_page === 'stock_movements.php' ? 'text-white bg-primary/20 border-r-4 border-primary' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
            <i class="fas fa-exchange-alt w-6"></i>
            <span>Riwayat Stok</span>
        </a>
        
        <!-- Divider -->
        <div class="border-t border-gray-700 my-4"></div>
        
        <!-- Settings -->
        <a href="settings.php" class="flex items-center px-6 py-3 transition-colors duration-200 <?php echo $current_page === 'settings.php' ? 'text-white bg-primary/20 border-r-4 border-primary' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
            <i class="fas fa-cog w-6"></i>
            <span>Pengaturan</span>
        </a>
        
        <!-- User Info -->
        <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-700">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-primary rounded-full flex items-center justify-center">
                    <i class="fas fa-user text-white"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium text-white"><?php echo htmlspecialchars($_SESSION['admin_name']); ?></p>
                    <p class="text-xs text-gray-400"><?php echo ucfirst($_SESSION['admin_role']); ?></p>
                </div>
                <a href="logout.php" class="text-gray-400 hover:text-red-400 transition-colors" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </nav>
</div>