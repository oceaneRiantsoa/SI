DROP DATABASE IF EXISTS SI;
CREATE DATABASE SI;
USE SI;

CREATE TABLE Departement (
    idDept INT AUTO_INCREMENT PRIMARY KEY,
    nomDept VARCHAR(40),
    mdp VARCHAR(40)
);

    CREATE TABLE prevision (
        idPrevision INT AUTO_INCREMENT PRIMARY KEY,
        idDept INT,
        idPeriode INT,
        nomPrevision VARCHAR(40),
        idType INT,
        montant DOUBLE
    );

CREATE TABLE type (
    idType INT AUTO_INCREMENT PRIMARY KEY,
    nomType VARCHAR(40)
);

CREATE TABLE realisation (
    idRealisation INT AUTO_INCREMENT PRIMARY KEY,
    idDept INT,
    idPeriode INT,
    nomRealisation VARCHAR(40),
    idType INT,
    montant DOUBLE
);

CREATE TABLE temp_realisation (
    idTempRealisation INT AUTO_INCREMENT PRIMARY KEY,
    idDept INT,
    idPeriode INT,
    nomRealisation VARCHAR(40),
    idType INT,
    montant DOUBLE
);

CREATE TABLE periode (
    idPeriode INT AUTO_INCREMENT PRIMARY KEY,
    idDept INT,
    moisDebut INT,
    moisFin INT,
    annee INT
);

INSERT INTO type (nomType) VALUES ("Recette");
INSERT INTO type (nomType) VALUES ("Depense");
ALTER TABLE Departement
ADD montantActions DECIMAL(12,2) DEFAULT 0;


-- 2. TABLE produit
CREATE TABLE produit (
    idProduit INT PRIMARY KEY AUTO_INCREMENT,
    nomProduit VARCHAR(100),
    prixUnitaire DECIMAL(10,2),
    photo VARCHAR(255)
);

-- 3. TABLE action_temp (propositions à valider)
CREATE TABLE action_temp (
    id INT PRIMARY KEY AUTO_INCREMENT,
    idDept INT,
    nomAction VARCHAR(255),
    budgetAction DECIMAL(10,2),
    FOREIGN KEY (idDept) REFERENCES departement(idDept)
);

-- 4. TABLE reaction_temp (propositions à valider)
CREATE TABLE reaction_temp (
    id INT PRIMARY KEY AUTO_INCREMENT,
    idDept INT,
    nomReaction VARCHAR(255),
    budgetReaction DECIMAL(10,2),
    FOREIGN KEY (idDept) REFERENCES departement(idDept)
);

-- 5. TABLE action (validée par Finance)
CREATE TABLE action (
    id INT PRIMARY KEY AUTO_INCREMENT,
    idDept INT,
    nomAction VARCHAR(255),
    budgetAction DECIMAL(10,2),
    dateValidation DATE,

);

-- 6. TABLE reaction (validée par Finance) avec 'solution' et seulement 'dateValidation'
CREATE TABLE reaction (
    id INT PRIMARY KEY AUTO_INCREMENT,
    idDept INT,
    nomReaction VARCHAR(255),
    budgetReaction DECIMAL(10,2),
    solution VARCHAR(255),
    dateValidation DATE,

);

-- 7. TABLE venteProduit
CREATE TABLE venteProduit (
    idVente INT PRIMARY KEY AUTO_INCREMENT,
    nomClient VARCHAR(100),
    idProduit INT,
    nbAchete INT,
    dateVente DATE,

);

-- 8. TABLE statistique (avant/après action ou réaction) mbola mila ovaina
-- CREATE TABLE statistique (
--     idStat INT PRIMARY KEY AUTO_INCREMENT,
--     idProduit INT,
--     periodeAvant BOOLEAN, -- TRUE = avant, FALSE = après
--     idAction INT,
--     idReaction INT,
--     nbVentes INT,
--     totalRevenue DECIMAL(10,2),

-- );

CREATE TABLE statistique (
    idStat INT PRIMARY KEY AUTO_INCREMENT,
    idProduit INT,
    periodeAvant BOOLEAN, -- TRUE = avant, FALSE = après
    idAction INT,
    idReaction INT,
    nbVentes INT,
    totalRevenue DECIMAL(10,2),
    dateStat DATE
);

-- --------------------------------------------
-- DONNÉES DE TEST (exemples pour vente de chaussures)
-- --------------------------------------------

-- Départements
-- INSERT INTO departement (nomDept) VALUES 
-- ('Production'), ('Marketing'), ('Finance');

-- Produits (chaussures)
INSERT INTO produit (nomProduit, prixUnitaire, photo) VALUES
('Baskets Reko', 50000.00, 'baskets_reko.jpg'),
('Sandales Tropic', 30000.00, 'sandales_tropic.jpg');

-- Actions proposées (temp)
INSERT INTO action_temp (idDept, nomAction, budgetAction) VALUES
(3, 'Nouveau design pour la boîte de livraison', 200000.00);

-- Réactions proposées (temp)
INSERT INTO reaction_temp (idDept, nomReaction, budgetReaction) VALUES
(3, 'Sondage téléphonique suite à plaintes sur confort', 50000.00);

-- Ventes avant action/réaction
INSERT INTO venteProduit (nomClient, idProduit, nbAchete, dateVente) VALUES
('Rakoto', 1, 2, '2025-03-12'),
('Rasoa', 2, 1, '2025-03-15');

-- Action validée (par Finance)
INSERT INTO action (idDept, nomAction, budgetAction, dateValidation) VALUES
(3, 'Nouveau design pour la boîte de livraison', 200000.00, '2025-03-20');

-- Reaction validée (avec solution)
INSERT INTO reaction (idDept, nomReaction, budgetReaction, solution, dateValidation) VALUES
(3, 'Réaction aux plaintes de semelles trop dures', 45000.00, 'Changer de fournisseur pour semelle plus souple', '2025-04-01');


