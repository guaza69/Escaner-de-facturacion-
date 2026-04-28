CREATE DATABASE IF NOT EXISTS facturacion_control;
USE facturacion_control;

CREATE TABLE IF NOT EXISTS facturas (
  id VARCHAR(20) PRIMARY KEY,
  cufe TEXT,
  fecha DATETIME,
  valor DECIMAL(12,2),
  estado VARCHAR(20) DEFAULT 'PENDIENTE',
  responsable VARCHAR(100),
  soporte_url TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fecha (fecha),
  INDEX idx_estado (estado)
);

CREATE TABLE IF NOT EXISTS facturas_historico LIKE facturas;

CREATE TABLE IF NOT EXISTS logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  factura_id VARCHAR(20),
  usuario VARCHAR(100),
  accion VARCHAR(100),
  fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_factura (factura_id),
  FOREIGN KEY (factura_id) REFERENCES facturas(id)
);

CREATE TABLE IF NOT EXISTS logs_historico LIKE logs;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(50) UNIQUE NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- Ejecuta api/setup.php una sola vez para crear el usuario admin (admin / Admin123!)
