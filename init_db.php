<?php
$dbFile = __DIR__ . '/database.sqlite';
$needSeed = !file_exists($dbFile);

$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create tables
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT UNIQUE,
  password_hash TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS products (
  id TEXT PRIMARY KEY,
  name TEXT,
  category TEXT,
  price INTEGER,
  stock INTEGER,
  image TEXT
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER,
  customer_name TEXT,
  items TEXT,
  total INTEGER,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Always seed products (insert or replace)
$json = file_get_contents(__DIR__ . '/products.json');
$products = json_decode($json, true);
$stmt = $pdo->prepare('INSERT OR REPLACE INTO products (id,name,category,price,stock,image) VALUES (:id,:name,:category,:price,:stock,:image)');
foreach ($products as $p) {
    $stmt->execute([
        ':id' => $p['id'],
        ':name' => $p['name'],
        ':category' => $p['category'],
        ':price' => $p['price'],
        ':stock' => isset($p['stock']) ? $p['stock'] : 0,
        ':image' => isset($p['image']) ? $p['image'] : ''
    ]);
}

echo json_encode(['ok' => true, 'seeded' => true]);
