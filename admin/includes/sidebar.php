<?php
// Dapatkan nama file saat ini untuk menentukan menu aktif
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar -->
<div class="fixed inset-y-0 left-0 w-64 bg-dark text-white">
    <div class="flex items-center justify-center h-16 border-b border-gray-700">
        <div class="flex items-center space-x-2">
            <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center">
                <i class="fas fa-utensils text-white"></i>
            </div>
            <span class="text-xl font-bold"><?php echo SITE_NAME; ?></span>
        </div>
    </div>
    
    <nav class="mt-6">
        <a href="dashboard.php" class="flex items-center px-6 py-3 <?php echo $current_page === 'dashboard.php' ? 'text-white bg-primary/20 border-l-4 border-primary' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
            <i class="fas fa-home w-6"></i>
            <span>Dashboard</span>
        </a>
        <a href="products.php" class="flex items-center px-6 py-3 <?php echo $current_page === 'products.php' ? 'text-white bg-primary/20 border-l-4 border-primary' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
            <i class="fas fa-box w-6"></i>
            <span>Produk</span>
        </a>
        <a href="orders.php" class="flex items-center px-6 py-3 <?php echo $current_page === 'orders.php' ? 'text-white bg-primary/20 border-l-4 border-primary' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
            <i class="fas fa-shopping-cart w-6"></i>
            <span>Pesanan</span>
        </a>
        <a href="categories.php" class="flex items-center px-6 py-3 <?php echo $current_page === 'categories.php' ? 'text-white bg-primary/20 border-l-4 border-primary' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
            <i class="fas fa-tags w-6"></i>
            <span>Kategori</span>
        </a>
        <a href="shipping_areas.php" class="flex items-center px-6 py-3 <?php echo $current_page === 'shipping_areas.php' ? 'text-white bg-primary/20 border-l-4 border-primary' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
            <i class="fas fa-truck w-6"></i>
            <span>Area Pengiriman</span>
        </a>
        <a href="customers.php" class="flex items-center px-6 py-3 <?php echo $current_page === 'customers.php' ? 'text-white bg-primary/20 border-l-4 border-primary' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
            <i class="fas fa-users w-6"></i>
            <span>Pelanggan</span>
        </a>
        <a href="contact_messages.php" class="flex items-center px-6 py-3 <?php echo $current_page === 'contact_messages.php' ? 'text-white bg-primary/20 border-l-4 border-primary' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
            <i class="fas fa-envelope w-6"></i>
            <span>Pesan Kontak</span>
        </a>
        <a href="stock_movements.php" class="flex items-center px-6 py-3 <?php echo $current_page === 'stock_movements.php' ? 'text-white bg-primary/20 border-l-4 border-primary' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
            <i class="fas fa-exchange-alt w-6"></i>
            <span>Riwayat Stok</span>
        </a>
        <a href="settings.php" class="flex items-center px-6 py-3 <?php echo $current_page === 'settings.php' ? 'text-white bg-primary/20 border-l-4 border-primary' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
            <i class="fas fa-cog w-6"></i>
            <span>Pengaturan</span>
        </a>
    </nav>
</div>