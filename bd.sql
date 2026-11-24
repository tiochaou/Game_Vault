CREATE database if not exists gamevault;

USE gamevault;

CREATE TABLE IF NOT EXISTS usuarios (id INT AUTO_INCREMENT PRIMARY KEY, 
    usuario VARCHAR(50) NOT NULL UNIQUE, 
    senha VARCHAR(255) NOT NULL, 
    );

CREATE TABLE IF NOT EXISTS desenvolvedoras (id INT AUTO_INCREMENT PRIMARY KEY, 
    nome VARCHAR(100) NOT NULL, 
    pais VARCHAR(60) NOT NULL ,
    ano_fundacao YEAR,
    website VARCHAR(255)
    descrição TEXT,
    imagem VARCHAR(255)

    );

CREATE TABLE IF NOT EXISTS jogos (id INT AUTO_INCREMENT PRIMARY KEY,
desenvolvedora_id INT NOT NULL,
    titulo VARCHAR(100) NOT NULL,
    genero VARCHAR(50) NOT NULL,
    plataforma VARCHAR(50) NOT NULL,
    data_lancamento DATE,
    descricao TEXT,
    imagem_capa VARCHAR(255),
    trailer_link VARCHAR(255),
    FOREIGN KEY (desenvolvedora_id) REFERENCES desenvolvedoras(id)
    ON DELETE RESTRICT
    ON  UPDATE CASCADE
    );

INSERT IGNORE INTO usuarios (usuario, senha) VALUES ( 'admin',
    '$2y$12$zw1c2aDgt4HHnAzXc3g3dOV6Mn96Dm2UaSIketIhYe1xmtuaJG6qa'); -- senha:123

