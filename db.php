<?php
// db.php â€” Connexion MariaDB pour l'application de traçabilité

$host = "localhost";
$dbname = "tracabilite";
$user = "traca";
$pass = "Traca2026!";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("Erreur de connexion Ã  la base de données");
}
