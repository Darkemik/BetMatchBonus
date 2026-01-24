-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Gép: 127.0.0.1
-- Létrehozás ideje: 2026. Jan 24. 14:34
-- Kiszolgáló verziója: 10.4.32-MariaDB
-- PHP verzió: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Adatbázis: `bettingdb`
--

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `users`
--
CREATE DATABASE IF NOT EXISTS bettingdb;
USE bettingdb;
CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

--
-- A tábla adatainak kiíratása `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `created_at`) VALUES
(3, 'Elsoteszt@gmail.com', '$2y$10$mbtDp5FhbGvEVthIcHifbuVFOaVPQmSZzD.0xYBeFT1Guupg/qeAO', '2025-12-21 17:27:42'),
(4, '', '$2y$10$8p9zvL6jQojUe8avoyYise2RpiwKvT7oW3aQeK8fl4vwEQPZZgS7q', '2025-12-22 16:47:39'),
(6, 'backkiller14@gmail.com', '$2y$10$YHXuV9QeIDi9bV8QUrJ.ROStvB9OcNad.4sZ3VRTZiUIZ/TgXVLh6', '2025-12-22 17:41:46'),
(9, 'masodikteszt@gmail.com', '$2y$10$U1joiOvBUlC8BwwetjcLjuVZNjzZ9bi0J6cB959RuK7hB.kn/gi8.', '2025-12-22 19:27:24'),
(10, 'harmadikteszt@gmail.com', '$2y$10$iI/d6PVEYCJoxD2VqjDtieyi9QfM9JPsgffFwqXf5BGbMLV6.ZfOu', '2025-12-22 19:28:57'),
(11, 'negyedik@gmail.com', '$2y$10$nAX0fzhVH396cqxQZbkUwuIGHb1H0GQTPnz1UhPlbGCpzFFpxIuiq', '2025-12-22 19:31:03'),
(16, 'sokaidkteszt@gmail.com', '$2y$10$afGkgf8NYYCB.J6.J0p8LOEaN9VvYma/os4ltG8TNzNVJ/zjW.GOG', '2026-01-24 12:45:36');

--
-- Indexek a kiírt táblákhoz
--

--
-- A tábla indexei `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- A kiírt táblák AUTO_INCREMENT értéke
--

--
-- AUTO_INCREMENT a táblához `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
