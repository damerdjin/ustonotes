CREATE TABLE usto_students (
    id INT(5) NOT NULL AUTO_INCREMENT,
    matricule VARCHAR(80) NOT NULL,
    nom VARCHAR(150) NOT NULL,
    prenom VARCHAR(150) NOT NULL,
    t01 DECIMAL(5, 2),
    t02 DECIMAL(5, 2),
    participation DECIMAL(5, 2),
    note_cc DECIMAL(5, 2),
    exam DECIMAL(5, 2),
    moy1 DECIMAL(5, 2),
    ratt DECIMAL(5, 2),
    moy2 DECIMAL(5, 2),
    moygen DECIMAL(5, 2),
    groupe VARCHAR(25),
    id_prof INT(11) NOT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (id_prof) REFERENCES usto_users(id)
);

ALTER TABLE `usto_students` CHANGE `matricule` `matricule` VARCHAR(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL, CHANGE `nom` `nom` VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL, CHANGE `prenom` `prenom` VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL, CHANGE `groupe` `groupe` VARCHAR(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;
CREATE TABLE usto_users (
    id INT(11) NOT NULL AUTO_INCREMENT,
    nom VARCHAR(255) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(50) NOT NULL,
    passwd VARCHAR(15) NOT NULL,
    admin INT(11) NOT NULL,
    activated TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id)
);

CREATE TABLE usto_prof_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    prof_id INT(11) NOT NULL,
    note_type VARCHAR(20) NOT NULL,
    can_view BOOLEAN DEFAULT 1,
    can_edit BOOLEAN DEFAULT 0,
    FOREIGN KEY (prof_id) REFERENCES usto_users(id),
    UNIQUE(prof_id, note_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

