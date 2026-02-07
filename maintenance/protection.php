<?php

// === CONFIGURATION ===
$rootDir = '/home/csresip/www'; // Répertoire de départ
$CibleLn = $rootDir . '/index.php'; // Lien cible absolu

// === FONCTIONS ===

// Vérifie si index.php existe
function hasIndexPhp(string $dir): bool {
    return file_exists("$dir/index.php");
}

// Vérifie si index.php est un lien symbolique
function isSymlink(string $dir): bool {
    return is_link("$dir/index.php");
}

// Vérifie si le lien symbolique pointe vers la bonne cible
function linkPointsToCorrectTarget(string $dir, string $target): bool {
    $link = readlink("$dir/index.php");
    return realpath($link) === realpath($target);
}

// Supprime le fichier ou lien existant
function removeIndexPhp(string $dir): void {
    unlink("$dir/index.php");
}

// Crée le lien symbolique
function createSymlink(string $dir, string $target): void {
    symlink($target, "$dir/index.php");
    // echo "[+] Lien créé : $dir/index.php → $target\n";
}

// Traite un seul répertoire
function processDirectory(string $dir, string $target): void {
    $path = "$dir/index.php";
    if (!hasIndexPhp($dir)) {
        // echo "[ ] Aucun index.php dans $dir. Création...\n";
        createSymlink($dir, $target);
    } elseif (!isSymlink($dir)) {
        // echo "[!] $path est un fichier normal. Remplacement...\n";
        removeIndexPhp($dir);
        createSymlink($dir, $target);
    } elseif (!linkPointsToCorrectTarget($dir, $target)) {
        // echo "[!] Mauvais lien dans $dir. Remplacement...\n";
        removeIndexPhp($dir);
        createSymlink($dir, $target);
    } else {
        // echo "[✓] $path est déjà correct.\n";
    }
}

// Scan récursif
function scanDirectories(string $root, string $target): void {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            processDirectory($item->getPathname(), $target);
        }
    }
}

// === EXECUTION ===
if (!is_dir($rootDir)) {
    die("Erreur : répertoire de départ invalide.\n");
}

if (!file_exists($CibleLn)) {
    die("Erreur : fichier cible '$CibleLn' introuvable.\n");
}

scanDirectories($rootDir, $CibleLn);