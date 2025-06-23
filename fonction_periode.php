<?php
include 'connexion.php';
$conn = $bdd;

// Create - Fonction pour créer une période
function createPeriode($idDept, $moisDebut, $moisFin, $annee) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO periode (idDept, moisDebut, moisFin, annee) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiii", $idDept, $moisDebut, $moisFin, $annee);
    $stmt->execute();
    $stmt->close();
}

// Read - Fonction pour lire une période par ID
function readPeriode($idPeriode) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM periode WHERE idPeriode = ?");
    $stmt->bind_param("i", $idPeriode);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result->fetch_assoc();
}

// Read All - Fonction pour récupérer toutes les périodes d'un département
function readAllPeriodes($idDept) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM periode WHERE idDept = ? ORDER BY annee, moisDebut");
    $stmt->bind_param("i", $idDept);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Update - Fonction pour mettre à jour une période
function updatePeriode($idPeriode, $idDept, $moisDebut, $moisFin, $annee) {
    global $conn;
    $stmt = $conn->prepare("UPDATE periode SET idDept=?, moisDebut=?, moisFin=?, annee=? WHERE idPeriode=?");
    $stmt->bind_param("iiiii", $idDept, $moisDebut, $moisFin, $annee, $idPeriode);
    $stmt->execute();
    $stmt->close();
}

// Delete - Fonction pour supprimer une période
function deletePeriode($idPeriode) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM periode WHERE idPeriode = ?");
    $stmt->bind_param("i", $idPeriode);
    $stmt->execute();
    $stmt->close();
}

// Fonction utilitaire pour obtenir le nom de la période (ex: "Janvier-Mars 2024")
function getNomPeriode($moisDebut, $moisFin, $annee) {
    $mois = array(
        1 => "Janvier", 2 => "Février", 3 => "Mars", 
        4 => "Avril", 5 => "Mai", 6 => "Juin",
        7 => "Juillet", 8 => "Août", 9 => "Septembre",
        10 => "Octobre", 11 => "Novembre", 12 => "Décembre"
    );
    return $mois[$moisDebut] . "-" . $mois[$moisFin] . " " . $annee;
}

// Fonction pour vérifier si une période existe déjà
function periodeExists($idDept, $moisDebut, $moisFin, $annee) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM periode 
                           WHERE idDept = ? AND moisDebut = ? AND moisFin = ? AND annee = ?");
    $stmt->bind_param("iiii", $idDept, $moisDebut, $moisFin, $annee);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['count'] > 0;
}
?>