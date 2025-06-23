<?php
session_start();
include 'fonction_prev.php';

if (!isset($_GET['id'])) {
    die("ID manquant");
}

$idPrevision = $_GET['id'];
$prevision = readPrevisionById($idPrevision);

if (!$prevision) {
    die("Prévision non trouvée");
}

header('Content-Type: application/json');
echo json_encode([
    'nomPrevision' => $prevision['nomPrevision'],
    'idPeriode' => $prevision['idPeriode'],
    'idType' => $prevision['idType'],
    'montant' => $prevision['montant']
]);
?>