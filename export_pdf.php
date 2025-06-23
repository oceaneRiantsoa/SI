<?php
session_start();
require('fpdf/fpdf.php');

$conn = new mysqli("localhost", "root", "", "SI");
if ($conn->connect_error) {
    die("Échec de la connexion : " . $conn->connect_error);
}

$idDept = $_SESSION["idDept"] ?? null;

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(50, 10, 'Rubrique', 1);
$pdf->Cell(30, 10, 'Periode', 1);
$pdf->Cell(30, 10, 'Type', 1);
$pdf->Cell(30, 10, 'Somme Prev', 1);
$pdf->Cell(30, 10, 'Somme Real', 1);
$pdf->Cell(30, 10, 'Ecart', 1);
$pdf->Ln();
$pdf->SetFont('Arial', '', 10);

$query = "SELECT p.idPeriode, t.nomType, SUM(p.montant) AS sommePrev, SUM(r.montant) AS sommeReal 
          FROM prevision p 
          JOIN realisation r ON p.idDept = r.idDept AND p.idPeriode = r.idPeriode AND p.idType = r.idType
          JOIN type t ON p.idType = t.idType ";

if ($idDept != 2) {
    $query .= " WHERE p.idDept = $idDept";
}

$query .= " GROUP BY p.idPeriode, t.nomType";
$result = $conn->query($query);

$soldeDebut = [];
$soldeFin = [];
$currentPeriod = null;
$prevPeriod = null;

while ($row = $result->fetch_assoc()) {
    $period = $row['idPeriode'];
    if ($currentPeriod !== $period) {
        if ($currentPeriod !== null) {
            $soldeFin[$currentPeriod] = $soldeDebut[$currentPeriod];
        }
        $currentPeriod = $period;
        $prevPeriod = $period - 1;
        $soldeDebut[$period] = $soldeFin[$prevPeriod] ?? 0;
    }

    $ecart = $row['sommePrev'] - $row['sommeReal'];
    // Update soldeFin based on type
    if ($row['nomType'] == 'Recette') {
        $soldeFin[$period] = ($soldeFin[$period] ?? $soldeDebut[$period]) + $row['sommeReal'];
    } else if ($row['nomType'] == 'Depense') {
        $soldeFin[$period] = ($soldeFin[$period] ?? $soldeDebut[$period]) - $row['sommeReal'];
    }

    $pdf->Cell(50, 10, "Departement", 1);
    $pdf->Cell(30, 10, $row['idPeriode'], 1);
    $pdf->Cell(30, 10, $row['nomType'], 1);
    $pdf->Cell(30, 10, $row['sommePrev'], 1);
    $pdf->Cell(30, 10, $row['sommeReal'], 1);
    $pdf->Cell(30, 10, $ecart, 1);
    $pdf->Ln();
}

// Handle last period
if ($currentPeriod !== null) {
    $soldeFin[$currentPeriod] = $soldeFin[$currentPeriod] ?? $soldeDebut[$currentPeriod];
}

$pdf->SetFont('Arial', 'B', 10);
$pdf->Ln();
$pdf->Cell(50, 10, 'Resume par periode:', 1, 1);

foreach ($soldeDebut as $periode => $solde) {
    $pdf->Cell(50, 10, "Periode $periode", 1);
    $pdf->Cell(45, 10, "Solde debut: " . $solde, 1);
    $pdf->Cell(45, 10, "Solde fin: " . $soldeFin[$periode], 1);
    $pdf->Ln();
}

$pdf->Output();
