-- phpMyAdmin SQL Dump
-- version 5.0.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 20 Nov 2026 pada 18.52
-- Versi server: 10.4.14-MariaDB
-- Versi PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `kasir_inventori_db`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `cashflows`
--

CREATE TABLE `cashflows` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('IN','OUT') NOT NULL,
  `category` varchar(100) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` text DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `cashflows`
--

INSERT INTO `cashflows` (`id`, `user_id`, `type`, `category`, `amount`, `description`, `transaction_date`, `created_at`, `updated_at`) VALUES
(1, 1, 'IN', 'Modal 2026', '10000000.00', '[Cash] Sisa modal dari tahun 2026', '2026-11-21', '2026-11-20 10:02:34', '2026-11-20 10:02:34'),
(7, 1, 'IN', 'Penjualan Kasir', '70000.00', 'Pendapatan Bersih Order #INV-261121-89946913', '2026-11-21', '2026-11-20 17:49:45', '2026-11-20 17:49:45'),
(8, 1, 'IN', 'Penjualan Kasir', '75000.00', 'Pendapatan Bersih Order #INV-261121-22721872', '2026-11-21', '2026-11-20 17:50:02', '2026-11-20 17:50:02'),
(9, 1, 'IN', 'Penjualan Kasir (QRIS/Pelunasan)', '580000.00', 'Pelunasan Bersih Order #INV-261121-40481569', '2026-11-21', '2026-11-20 17:50:42', '2026-11-20 17:50:42');

-- --------------------------------------------------------

--
-- Struktur dari tabel `categories`
--

CREATE TABLE `categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `image`, `created_at`, `updated_at`) VALUES
(1, 'Sensor', 'sensor', NULL, '2026-11-20 10:13:29', '2026-11-20 10:13:29'),
(2, 'Kontroller', 'kontroller', NULL, '2026-11-20 10:13:45', '2026-11-20 10:13:45'),
(3, 'Smart-Farming', 'smart-farming', NULL, '2026-11-20 10:16:14', '2026-11-20 10:16:14');

-- --------------------------------------------------------

--
-- Struktur dari tabel `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2014_10_12_100000_create_password_resets_table', 1),
(2, '2019_12_14_000001_create_personal_access_tokens_table', 1);

-- --------------------------------------------------------

--
-- Struktur dari tabel `orders`
--

CREATE TABLE `orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_number` varchar(100) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `customer_name` varchar(150) DEFAULT 'Umum',
  `platform` varchar(100) NOT NULL DEFAULT 'Offline Store',
  `payment_method` enum('CASH','CARD','QRIS') NOT NULL DEFAULT 'CASH',
  `numberCard` varchar(100) DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(15,2) NOT NULL DEFAULT 0.00,
  `supplier_sales_tax` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_bill` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cash_received` decimal(15,2) DEFAULT 0.00,
  `cash_change` decimal(15,2) DEFAULT 0.00,
  `status` enum('pending','success','canceled') NOT NULL DEFAULT 'success',
  `qris_reference` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `orders`
--

INSERT INTO `orders` (`id`, `invoice_number`, `user_id`, `customer_name`, `platform`, `payment_method`, `numberCard`, `subtotal`, `discount_amount`, `tax`, `supplier_sales_tax`, `total_cost`, `total_bill`, `cash_received`, `cash_change`, `status`, `qris_reference`, `created_at`, `updated_at`) VALUES
(6, 'INV-261121-40481569', 1, 'Umum', 'Offline Store', 'QRIS', NULL, '580000.00', '0.00', '0.00', '0.00', '150000.00', '580000.00', '580000.00', '0.00', 'success', NULL, '2026-11-20 17:49:21', '2026-11-20 17:50:42'),
(7, 'INV-261121-89946913', 1, 'Umum', 'Tokopedia', 'CARD', 'qwerty', '80000.00', '0.00', '0.00', '10000.00', '50000.00', '70000.00', '70000.00', '0.00', 'success', NULL, '2026-11-20 17:49:45', '2026-11-20 17:49:45'),
(8, 'INV-261121-22721872', 1, 'Umum', 'Shopee', 'CASH', NULL, '80000.00', '0.00', '0.00', '5000.00', '50000.00', '75000.00', '75000.00', '0.00', 'success', NULL, '2026-11-20 17:50:02', '2026-11-20 17:50:02');

-- --------------------------------------------------------

--
-- Struktur dari tabel `order_cancellation_reports`
--

CREATE TABLE `order_cancellation_reports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `invoice_number` varchar(100) NOT NULL,
  `nominal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `berita_acara` text NOT NULL,
  `canceled_by` bigint(20) UNSIGNED DEFAULT NULL,
  `canceled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `order_cancellation_reports`
--

INSERT INTO `order_cancellation_reports` (`id`, `order_id`, `invoice_number`, `nominal`, `berita_acara`, `canceled_by`, `canceled_at`, `created_at`, `updated_at`) VALUES
(1, 5, 'INV-261121-86576948', '80000.00', 'batal', 1, '2026-11-20 17:47:36', '2026-11-20 17:47:36', '2026-11-20 17:47:36'),
(2, 4, 'INV-261120-38585299', '500000.00', 'batal', 1, '2026-11-20 17:47:44', '2026-11-20 17:47:44', '2026-11-20 17:47:44'),
(3, 3, 'INV-261120-80909958', '60000.00', 'batal', 1, '2026-11-20 17:47:52', '2026-11-20 17:47:52', '2026-11-20 17:47:52'),
(4, 2, 'INV-261120-64968839', '70000.00', 'batal', 1, '2026-11-20 17:48:00', '2026-11-20 17:48:00', '2026-11-20 17:48:00'),
(5, 1, 'INV-261120-21302185', '500000.00', 'batal', 1, '2026-11-20 17:48:08', '2026-11-20 17:48:08', '2026-11-20 17:48:08');

-- --------------------------------------------------------

--
-- Struktur dari tabel `order_details`
--

CREATE TABLE `order_details` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `raw_material_id` bigint(20) UNSIGNED DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `promotion_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `cost_product` decimal(15,2) NOT NULL DEFAULT 0.00,
  `price_product` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `order_details`
--

INSERT INTO `order_details` (`id`, `order_id`, `product_id`, `raw_material_id`, `item_name`, `promotion_id`, `quantity`, `cost_product`, `price_product`, `subtotal`, `created_at`, `updated_at`) VALUES
(6, 6, 1, NULL, 'Auto Dosing TDS Only (ppm)', NULL, 1, '100000.00', '500000.00', '500000.00', '2026-11-20 17:49:21', '2026-11-20 17:49:21'),
(7, 6, NULL, 2, 'Arduino Nano V3', NULL, 1, '50000.00', '80000.00', '80000.00', '2026-11-20 17:49:21', '2026-11-20 17:49:21'),
(8, 7, NULL, 2, 'Arduino Nano V3', NULL, 1, '50000.00', '80000.00', '80000.00', '2026-11-20 17:49:45', '2026-11-20 17:49:45'),
(9, 8, NULL, 2, 'Arduino Nano V3', NULL, 1, '50000.00', '80000.00', '80000.00', '2026-11-20 17:50:02', '2026-11-20 17:50:02');

-- --------------------------------------------------------

--
-- Struktur dari tabel `password_resets`
--

CREATE TABLE `password_resets` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `personal_access_tokens`
--

INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `created_at`, `updated_at`) VALUES
(1, 'App\\Models\\User', 1, 'pos-token', '4a3da905cb06410ca34a4dcbc61bda6ca91a211a055a76c21d8c52e505516e59', '[\"*\"]', NULL, '2026-11-16 09:55:37', '2026-11-16 09:55:37'),
(2, 'App\\Models\\User', 1, 'pos-token', '97b0cd972f0972b063c298df51721aebbbd273718c5596edd14b3c0166c4e088', '[\"*\"]', NULL, '2026-11-16 09:56:41', '2026-11-16 09:56:41'),
(5, 'App\\Models\\User', 1, 'pos-token', '0128df68895c3f50aa4a92882e0bd670424e862d366517fdbe829b72bfbf75f8', '[\"*\"]', NULL, '2026-11-17 19:49:21', '2026-11-17 19:49:21'),
(10, 'App\\Models\\User', 1, 'pos-token', 'dc15f6c16ba2bc86d7162e72914092a7568465569801b6f69aea52dd87d6d57d', '[\"*\"]', '2026-11-17 20:49:55', '2026-11-17 20:47:37', '2026-11-17 20:49:55'),
(11, 'App\\Models\\User', 1, 'pos-token', '9de13694d91608705d96260df01206f2e9a96b1b1ca862db7d17d68e99575387', '[\"*\"]', '2026-11-18 01:06:25', '2026-11-17 20:52:38', '2026-11-18 01:06:25'),
(12, 'App\\Models\\User', 1, 'pos-token', 'cf6f12e241fbf47d349d52b3a63614a459859346349e26e2920458511c2d4b74', '[\"*\"]', '2026-11-18 02:03:43', '2026-11-18 01:55:08', '2026-11-18 02:03:43');

-- --------------------------------------------------------

--
-- Struktur dari tabel `products`
--

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `sku` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `unit` varchar(50) NOT NULL DEFAULT 'pcs',
  `product_type` enum('retail','resep') NOT NULL DEFAULT 'retail',
  `cover` varchar(255) DEFAULT NULL,
  `cost_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `stock` int(11) DEFAULT 0,
  `weight` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `products`
--

INSERT INTO `products` (`id`, `category_id`, `sku`, `title`, `unit`, `product_type`, `cover`, `cost_price`, `price`, `stock`, `weight`, `created_at`, `updated_at`) VALUES
(1, 3, 'SKU-VAQ9XM', 'Auto Dosing TDS Only (ppm)', 'pcs', 'resep', 'http://localhost/api-kasir-backend/public/storage/products/product_1795195065_XoJx.jpeg', '100000.00', '500000.00', 0, 0, '2026-11-20 10:17:45', '2026-11-20 17:44:02');

-- --------------------------------------------------------

--
-- Struktur dari tabel `product_prices_platform`
--

CREATE TABLE `product_prices_platform` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `platform_name` varchar(100) NOT NULL,
  `price` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struktur dari tabel `product_recipes`
--

CREATE TABLE `product_recipes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `raw_material_id` bigint(20) UNSIGNED NOT NULL,
  `qty_needed` decimal(15,4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `product_recipes`
--

INSERT INTO `product_recipes` (`id`, `product_id`, `raw_material_id`, `qty_needed`) VALUES
(5, 1, 2, '1.0000'),
(6, 1, 1, '1.0000');

-- --------------------------------------------------------

--
-- Struktur dari tabel `promotions`
--

CREATE TABLE `promotions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `typ_promotion_id` tinyint(1) NOT NULL COMMENT '1=Nominal (Rp), 2=Persen (%)',
  `required` decimal(15,2) NOT NULL DEFAULT 0.00,
  `result` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `promotions`
--

INSERT INTO `promotions` (`id`, `title`, `typ_promotion_id`, `required`, `result`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Lebaran', 2, '1500000.00', '5.00', 1, '2026-11-20 10:10:40', '2026-11-20 10:10:40');

-- --------------------------------------------------------

--
-- Struktur dari tabel `raw_materials`
--

CREATE TABLE `raw_materials` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `stock` decimal(15,3) NOT NULL DEFAULT 0.000,
  `min_stock` decimal(15,3) NOT NULL DEFAULT 1.000,
  `cost_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_sellable` tinyint(1) NOT NULL DEFAULT 0,
  `sell_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `category_id` bigint(20) UNSIGNED DEFAULT NULL,
  `cover` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `raw_materials`
--

INSERT INTO `raw_materials` (`id`, `name`, `unit`, `stock`, `min_stock`, `cost_price`, `is_sellable`, `sell_price`, `category_id`, `cover`, `created_at`, `updated_at`) VALUES
(1, 'Sensor TDS', 'pcs', '9.000', '1.000', '50000.00', 0, '0.00', NULL, NULL, '2026-11-20 10:12:51', '2026-11-20 17:49:21'),
(2, 'Arduino Nano V3', 'pcs', '6.000', '1.000', '50000.00', 1, '80000.00', 2, 'http://localhost/api-kasir-backend/public/storage/raw_materials/raw_material_1795194927_mNhT.png', '2026-11-20 10:15:27', '2026-11-20 17:50:02');

-- --------------------------------------------------------

--
-- Struktur dari tabel `settings`
--

CREATE TABLE `settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `store_name` varchar(255) NOT NULL DEFAULT 'Toko Sejahtera',
  `location_store` text DEFAULT NULL,
  `contact` varchar(100) DEFAULT NULL,
  `logo_path_url` varchar(255) DEFAULT NULL,
  `qris_path_url` varchar(255) DEFAULT NULL,
  `tax` decimal(5,2) NOT NULL DEFAULT 11.00,
  `receipt_width` varchar(20) NOT NULL DEFAULT '58 mm',
  `receipt_footer` varchar(255) NOT NULL DEFAULT 'Terima kasih telah berbelanja',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `settings`
--

INSERT INTO `settings` (`id`, `store_name`, `location_store`, `contact`, `logo_path_url`, `qris_path_url`, `tax`, `receipt_width`, `receipt_footer`, `created_at`, `updated_at`) VALUES
(1, 'indorobotika', 'Cisauk, Kab. Tanggerang, Banten.', '085852108546', 'http://localhost/api-kasir-backend/public/storage/logos/logo_1795194609_4AhMHk.png', 'http://localhost/api-kasir-backend/public/storage/payment/qris_1795194610_rZnj7h.png', '0.00', '58 mm', 'TERIMA KASIH TELAH BERBELANJA!  Terima kasih telah memilih Indorobotika sebagai partner kebutuhan robotika dan elektronika Anda.  Kami tunggu pesanan Anda berikutnya!  🌐 www.indorobotika.com', '2026-11-20 09:43:18', '2026-11-20 10:10:10');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('owner','admin','kasir') NOT NULL DEFAULT 'owner',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`, `updated_at`) VALUES
(1, 'indorobotika 01', 'indorobotika01@gmail.com', '$2y$10$wKrCr61lmNdYX8JO.Sy3YegnpnqzFwfdsdoaUGCNNafoqT4xCtZt.', 'owner', '2026-11-16 16:52:35', '2026-11-16 16:55:21');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `cashflows`
--
ALTER TABLE `cashflows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indeks untuk tabel `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `order_cancellation_reports`
--
ALTER TABLE `order_cancellation_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_invoice_number` (`invoice_number`),
  ADD KEY `idx_canceled_by` (`canceled_by`);

--
-- Indeks untuk tabel `order_details`
--
ALTER TABLE `order_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `raw_material_id` (`raw_material_id`),
  ADD KEY `promotion_id` (`promotion_id`);

--
-- Indeks untuk tabel `password_resets`
--
ALTER TABLE `password_resets`
  ADD KEY `password_resets_email_index` (`email`);

--
-- Indeks untuk tabel `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`);

--
-- Indeks untuk tabel `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `category_id` (`category_id`);

--
-- Indeks untuk tabel `product_prices_platform`
--
ALTER TABLE `product_prices_platform`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indeks untuk tabel `product_recipes`
--
ALTER TABLE `product_recipes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `raw_material_id` (`raw_material_id`);

--
-- Indeks untuk tabel `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `raw_materials`
--
ALTER TABLE `raw_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indeks untuk tabel `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `cashflows`
--
ALTER TABLE `cashflows`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `order_cancellation_reports`
--
ALTER TABLE `order_cancellation_reports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `order_details`
--
ALTER TABLE `order_details`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT untuk tabel `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `product_prices_platform`
--
ALTER TABLE `product_prices_platform`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `product_recipes`
--
ALTER TABLE `product_recipes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `promotions`
--
ALTER TABLE `promotions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `raw_materials`
--
ALTER TABLE `raw_materials`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `settings`
--
ALTER TABLE `settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `cashflows`
--
ALTER TABLE `cashflows`
  ADD CONSTRAINT `cashflows_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `order_cancellation_reports`
--
ALTER TABLE `order_cancellation_reports`
  ADD CONSTRAINT `order_cancellation_reports_canceled_by_fk` FOREIGN KEY (`canceled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `order_details`
--
ALTER TABLE `order_details`
  ADD CONSTRAINT `order_details_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_details_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `order_details_ibfk_3` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `order_details_ibfk_4` FOREIGN KEY (`raw_material_id`) REFERENCES `raw_materials` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `product_prices_platform`
--
ALTER TABLE `product_prices_platform`
  ADD CONSTRAINT `product_prices_platform_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `product_recipes`
--
ALTER TABLE `product_recipes`
  ADD CONSTRAINT `product_recipes_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_recipes_ibfk_2` FOREIGN KEY (`raw_material_id`) REFERENCES `raw_materials` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `raw_materials`
--
ALTER TABLE `raw_materials`
  ADD CONSTRAINT `raw_materials_category_id_fk` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
