-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: lavanderia_superior
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `lavanderia_superior`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `lavanderia_superior` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `lavanderia_superior`;

--
-- Table structure for table `clientes`
--

DROP TABLE IF EXISTS `clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clientes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombres` varchar(120) NOT NULL DEFAULT '',
  `apellidos` varchar(60) NOT NULL DEFAULT '',
  `nombre` varchar(120) NOT NULL,
  `cedula_rif` varchar(20) NOT NULL,
  `telefono` varchar(20) NOT NULL COMMENT 'Número de telefono',
  `direccion` text DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `puntos_acumulados` int(10) unsigned DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `cedula_rif` (`cedula_rif`),
  KEY `idx_cedula` (`cedula_rif`),
  KEY `idx_telefono` (`telefono`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Clientes registrados del negocio';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clientes`
--

LOCK TABLES `clientes` WRITE;
/*!40000 ALTER TABLE `clientes` DISABLE KEYS */;
INSERT INTO `clientes` VALUES (1,'Juan','Perez','Juan Perez','V-26786567','04262344033','Calle los cardones, casa 60','contactaconsebastian@gmail.com',0,1,'2026-03-22 14:38:38'),(2,'Yorman','Blanca','Yorman Blanca','V-16325348','04160310779','cabruta','yormanblanca2@gmail.com',0,1,'2026-03-22 15:12:35'),(3,'Andreina','Seijas','Andreina Seijas','V-29775645','04221970804','calle guayana, sector 12 de octubre','andreinaseijas@gmail.com',0,1,'2026-03-22 15:59:40'),(4,'José','Rivas','José Rivas','V-32492292','04167289323','jardin la pascua','rivas.06jv@gmail.com',0,1,'2026-03-22 16:13:23'),(5,'Anyer','Álvarez','Anyer Álvarez','V-31763619','04124317202','valle de la pascua','anyeralvarez@gmail.com',0,1,'2026-03-22 16:59:44'),(6,'Diannny','Marcano','Diannny Marcano','V-30375046','04124361870','valle de la pascua','diannymm2q@gmail.com',0,1,'2026-03-23 14:43:19'),(7,'Leonardo','Villarroel','Leonardo Villarroel','V-31160805','04260847687','valle de la pascua','leonardo@gmail.com',0,1,'2026-03-30 00:28:40'),(8,'Harryedmar','Parica','Harryedmar Parica','V-31499695','04245678765','valle de la pascua','harryedamar@gmail.com',0,1,'2026-04-03 23:49:37'),(9,'Crisbian','Cortéz','Crisbian Cortéz','V-18786354','04160241528','valle de la pascua','criscortez74@gmail.com',0,1,'2026-04-14 16:41:04'),(10,'Nohely','Loreto','Nohely Loreto','V-2288475','04243645457','Calle los cardones','nohely@gmail.com',0,1,'2026-04-20 01:57:20'),(11,'Leonor','Garrido','Leonor Garrido','V-26788900','04265578976','valle de la pascua','leonorjose@gmail.com',0,1,'2026-07-21 01:52:52'),(12,'José','Cortéz','José Cortéz','V-14456780','04243365095','calle bolivar','josecortez@gmail.com',0,1,'2026-10-11 19:29:26');
/*!40000 ALTER TABLE `clientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `compras`
--

DROP TABLE IF EXISTS `compras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `compras` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `proveedor_id` int(10) unsigned NOT NULL,
  `num_factura` varchar(50) DEFAULT NULL,
  `fecha_compra` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_usd` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tasa_cambio` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `proveedor_id` (`proveedor_id`),
  CONSTRAINT `compras_ibfk_1` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compras`
--

LOCK TABLES `compras` WRITE;
/*!40000 ALTER TABLE `compras` DISABLE KEYS */;
/*!40000 ALTER TABLE `compras` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `consumo_insumos`
--

DROP TABLE IF EXISTS `consumo_insumos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `consumo_insumos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `orden_id` int(10) unsigned NOT NULL,
  `insumo_id` int(10) unsigned NOT NULL,
  `cantidad_usada` decimal(10,4) NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `insumo_id` (`insumo_id`),
  KEY `idx_orden` (`orden_id`),
  CONSTRAINT `consumo_insumos_ibfk_1` FOREIGN KEY (`orden_id`) REFERENCES `ordenes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `consumo_insumos_ibfk_2` FOREIGN KEY (`insumo_id`) REFERENCES `inventario_insumos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Trazabilidad del consumo de insumos';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `consumo_insumos`
--

LOCK TABLES `consumo_insumos` WRITE;
/*!40000 ALTER TABLE `consumo_insumos` DISABLE KEYS */;
INSERT INTO `consumo_insumos` VALUES (1,1,1,25.0000,'2026-03-22 15:15:57'),(2,1,2,15.0000,'2026-03-22 15:15:57'),(3,1,3,10.0000,'2026-03-22 15:15:57'),(4,1,4,5.0000,'2026-03-22 15:15:57'),(5,2,1,25.0000,'2026-03-22 15:15:58'),(6,2,2,15.0000,'2026-03-22 15:15:58'),(7,2,3,10.0000,'2026-03-22 15:15:58'),(8,2,4,5.0000,'2026-03-22 15:15:58'),(9,3,1,25.0000,'2026-03-22 15:15:59'),(10,3,2,15.0000,'2026-03-22 15:15:59'),(11,3,3,10.0000,'2026-03-22 15:15:59'),(12,3,4,5.0000,'2026-03-22 15:15:59'),(13,4,1,25.0000,'2026-03-22 15:16:02'),(14,4,2,15.0000,'2026-03-22 15:16:02'),(15,4,3,10.0000,'2026-03-22 15:16:02'),(16,4,4,5.0000,'2026-03-22 15:16:02'),(17,5,1,50.0000,'2026-03-22 15:52:34'),(18,5,2,30.0000,'2026-03-22 15:52:34'),(19,5,3,20.0000,'2026-03-22 15:52:34'),(20,5,4,10.0000,'2026-03-22 15:52:34'),(21,6,1,25.0000,'2026-03-22 16:01:35'),(22,6,2,15.0000,'2026-03-22 16:01:35'),(23,6,3,10.0000,'2026-03-22 16:01:35'),(24,6,4,5.0000,'2026-03-22 16:01:35'),(25,7,1,75.0000,'2026-03-22 16:14:41'),(26,7,2,45.0000,'2026-03-22 16:14:41'),(27,7,3,30.0000,'2026-03-22 16:14:41'),(28,7,4,15.0000,'2026-03-22 16:14:41'),(29,8,1,275.0000,'2026-03-22 17:02:16'),(30,8,2,165.0000,'2026-03-22 17:02:16'),(31,8,3,110.0000,'2026-03-22 17:02:16'),(32,8,4,55.0000,'2026-03-22 17:02:16'),(33,9,1,300.0000,'2026-03-22 17:28:31'),(34,9,2,180.0000,'2026-03-22 17:28:31'),(35,9,3,120.0000,'2026-03-22 17:28:31'),(36,9,4,60.0000,'2026-03-22 17:28:31'),(37,10,1,50.0000,'2026-03-22 17:29:02'),(38,10,2,30.0000,'2026-03-22 17:29:02'),(39,10,3,20.0000,'2026-03-22 17:29:02'),(40,10,4,10.0000,'2026-03-22 17:29:02'),(41,11,1,25.0000,'2026-03-23 03:10:31'),(42,11,2,15.0000,'2026-03-23 03:10:31'),(43,11,3,10.0000,'2026-03-23 03:10:33'),(44,11,4,5.0000,'2026-03-23 03:10:36'),(45,12,1,375.0000,'2026-03-23 14:43:50'),(46,12,2,225.0000,'2026-03-23 14:43:50'),(47,12,3,150.0000,'2026-03-23 14:43:50'),(48,12,4,75.0000,'2026-03-23 14:43:50'),(49,13,1,200.0000,'2026-03-30 00:32:40'),(50,13,2,120.0000,'2026-03-30 00:32:40'),(51,13,3,80.0000,'2026-03-30 00:32:40'),(52,13,4,40.0000,'2026-03-30 00:32:40'),(53,14,1,250.0000,'2026-04-03 23:50:13'),(54,14,2,150.0000,'2026-04-03 23:50:13'),(55,14,3,100.0000,'2026-04-03 23:50:13'),(56,14,4,50.0000,'2026-04-03 23:50:13'),(57,15,1,250.0000,'2026-04-14 16:41:42'),(58,15,2,150.0000,'2026-04-14 16:41:42'),(59,15,3,100.0000,'2026-04-14 16:41:42'),(60,15,4,50.0000,'2026-04-14 16:41:42'),(61,16,1,250.0000,'2026-04-20 01:58:03'),(62,16,2,150.0000,'2026-04-20 01:58:03'),(63,16,3,100.0000,'2026-04-20 01:58:03'),(64,16,4,50.0000,'2026-04-20 01:58:03'),(65,17,1,25.0000,'2026-07-03 13:55:16'),(66,17,2,15.0000,'2026-07-03 13:55:16'),(67,17,3,10.0000,'2026-07-03 13:55:16'),(68,17,4,5.0000,'2026-07-03 13:55:16'),(69,18,1,175.0000,'2026-07-21 01:50:30'),(70,18,2,105.0000,'2026-07-21 01:50:30'),(71,18,3,70.0000,'2026-07-21 01:50:30'),(72,18,4,35.0000,'2026-07-21 01:50:30'),(73,19,1,25.0000,'2026-09-23 23:53:24'),(74,19,2,15.0000,'2026-09-23 23:53:24'),(75,19,3,10.0000,'2026-09-23 23:53:24'),(76,19,4,5.0000,'2026-09-23 23:53:24'),(77,20,1,75.0000,'2026-09-24 00:07:02'),(78,20,2,45.0000,'2026-09-24 00:07:02'),(79,20,3,30.0000,'2026-09-24 00:07:02'),(80,20,4,15.0000,'2026-09-24 00:07:02'),(93,21,1,25.0000,'2026-10-11 15:31:38'),(94,21,2,15.0000,'2026-10-11 15:31:38'),(95,21,3,10.0000,'2026-10-11 15:31:38'),(96,21,4,5.0000,'2026-10-11 15:31:38'),(97,22,1,125.0000,'2026-10-11 19:31:54'),(98,22,2,75.0000,'2026-10-11 19:31:54'),(99,22,3,50.0000,'2026-10-11 19:31:54'),(100,22,4,25.0000,'2026-10-11 19:31:54');
/*!40000 ALTER TABLE `consumo_insumos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalle_compra`
--

DROP TABLE IF EXISTS `detalle_compra`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `detalle_compra` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `compra_id` int(10) unsigned NOT NULL,
  `insumo_id` int(10) unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `compra_id` (`compra_id`),
  KEY `insumo_id` (`insumo_id`),
  CONSTRAINT `detalle_compra_ibfk_1` FOREIGN KEY (`compra_id`) REFERENCES `compras` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `detalle_compra_ibfk_2` FOREIGN KEY (`insumo_id`) REFERENCES `inventario_insumos` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_compra`
--

LOCK TABLES `detalle_compra` WRITE;
/*!40000 ALTER TABLE `detalle_compra` DISABLE KEYS */;
/*!40000 ALTER TABLE `detalle_compra` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalle_orden`
--

DROP TABLE IF EXISTS `detalle_orden`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `detalle_orden` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `orden_id` int(10) unsigned NOT NULL,
  `servicio_id` int(10) unsigned NOT NULL,
  `cantidad` decimal(8,3) NOT NULL COMMENT 'Kg o unidades según servicio',
  `precio_usd` decimal(10,4) NOT NULL COMMENT 'Precio capturado al momento',
  `subtotal` decimal(12,4) GENERATED ALWAYS AS (`cantidad` * `precio_usd`) STORED,
  `notas` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `servicio_id` (`servicio_id`),
  KEY `idx_orden` (`orden_id`),
  CONSTRAINT `detalle_orden_ibfk_1` FOREIGN KEY (`orden_id`) REFERENCES `ordenes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `detalle_orden_ibfk_2` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Líneas de detalle por orden';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_orden`
--

LOCK TABLES `detalle_orden` WRITE;
/*!40000 ALTER TABLE `detalle_orden` DISABLE KEYS */;
INSERT INTO `detalle_orden` VALUES (1,1,1,1.000,1.5000,1.5000,NULL),(2,2,1,1.000,1.5000,1.5000,NULL),(3,3,1,1.000,1.5000,1.5000,NULL),(4,4,1,1.000,1.5000,1.5000,NULL),(5,5,3,2.000,2.2000,4.4000,NULL),(6,6,8,1.000,3.0000,3.0000,NULL),(7,7,3,3.000,2.2000,6.6000,NULL),(8,8,1,10.000,1.5000,15.0000,NULL),(9,8,7,1.000,8.0000,8.0000,NULL),(10,9,1,12.000,1.5000,18.0000,NULL),(11,10,6,2.000,3.5000,7.0000,NULL),(12,11,3,1.000,2.2000,2.2000,NULL),(13,12,3,15.000,2.2000,33.0000,NULL),(14,13,8,1.000,3.0000,3.0000,NULL),(15,13,3,7.000,2.2000,15.4000,NULL),(16,14,3,10.000,2.2000,22.0000,NULL),(17,15,3,10.000,2.2000,22.0000,NULL),(18,16,3,10.000,2.2000,22.0000,NULL),(19,17,4,1.000,5.0000,5.0000,NULL),(20,18,3,7.000,2.2000,15.4000,NULL),(21,19,5,1.000,8.0000,8.0000,NULL),(22,20,8,3.000,3.0000,9.0000,NULL),(28,21,1,1.000,6.0000,6.0000,NULL),(29,22,3,5.000,2.2000,11.0000,NULL);
/*!40000 ALTER TABLE `detalle_orden` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventario_insumos`
--

DROP TABLE IF EXISTS `inventario_insumos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventario_insumos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `unidad` enum('ml','L','g','Kg','Unidad') NOT NULL DEFAULT 'ml',
  `stock_actual` decimal(10,3) NOT NULL DEFAULT 0.000,
  `stock_minimo` decimal(10,3) NOT NULL DEFAULT 500.000,
  `consumo_por_kg` decimal(8,4) DEFAULT NULL COMMENT 'Cantidad usada por cada kg de ropa lavada',
  `costo_unidad` decimal(10,4) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_stock` (`stock_actual`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Inventario de químicos e insumos';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventario_insumos`
--

LOCK TABLES `inventario_insumos` WRITE;
/*!40000 ALTER TABLE `inventario_insumos` DISABLE KEYS */;
INSERT INTO `inventario_insumos` VALUES (1,'Detergente Industrial','ml',5325.000,1000.000,25.0000,NULL,1),(2,'Suavizante','ml',3395.000,500.000,15.0000,NULL,1),(3,'Blanqueador','ml',1930.000,300.000,10.0000,NULL,1),(4,'Desengrasante','ml',1465.000,200.000,5.0000,NULL,1);
/*!40000 ALTER TABLE `inventario_insumos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `movimientos_caja`
--

DROP TABLE IF EXISTS `movimientos_caja`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `movimientos_caja` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tipo` enum('Ingreso','Egreso') NOT NULL,
  `concepto` enum('Servicio','Proveedor','Nomina','Suministros','Otro') NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `monto_usd` decimal(12,4) DEFAULT NULL,
  `monto_bs` decimal(16,2) DEFAULT NULL,
  `tasa_cambio` decimal(12,4) DEFAULT NULL,
  `orden_id` int(10) unsigned DEFAULT NULL COMMENT 'Vincula al ingreso de una orden',
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `registrado_por` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `orden_id` (`orden_id`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_tipo` (`tipo`),
  CONSTRAINT `movimientos_caja_ibfk_1` FOREIGN KEY (`orden_id`) REFERENCES `ordenes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Libro de caja del negocio';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `movimientos_caja`
--

LOCK TABLES `movimientos_caja` WRITE;
/*!40000 ALTER TABLE `movimientos_caja` DISABLE KEYS */;
INSERT INTO `movimientos_caja` VALUES (1,'Ingreso','Servicio','1',NULL,NULL,458.0000,1,'2026-03-22 11:15:57',NULL),(2,'Ingreso','Servicio','1',NULL,NULL,458.0000,2,'2026-03-22 11:15:58',NULL),(3,'Ingreso','Servicio','1',NULL,NULL,458.0000,3,'2026-03-22 11:15:59',NULL),(4,'Ingreso','Servicio','1',NULL,NULL,458.0000,4,'2026-03-22 11:16:02',NULL),(5,'Ingreso','Servicio','Orden #0005',4.4000,2015.20,458.0000,5,'2026-03-22 11:52:34',NULL),(6,'Ingreso','Servicio','Orden #0006',3.0000,1374.00,458.0000,6,'2026-03-22 12:01:35',NULL),(7,'Ingreso','Servicio','Orden #0007',6.6000,3022.80,458.0000,7,'2026-03-22 12:14:41',NULL),(8,'Ingreso','Servicio','Orden #0008',23.0000,10534.00,458.0000,8,'2026-03-22 13:02:16',NULL),(9,'Ingreso','Servicio','Orden #0009',18.0000,8227.26,457.0700,9,'2026-03-22 13:28:31',NULL),(10,'Ingreso','Servicio','Orden #0010',7.0000,3199.49,457.0700,10,'2026-03-22 13:29:02',NULL),(11,'Ingreso','Servicio','Orden #0011',2.2000,1005.55,457.0700,11,'2026-03-22 23:10:36',NULL),(12,'Ingreso','Servicio','Orden #0012',33.0000,15083.31,457.0700,12,'2026-03-23 10:43:50',NULL),(13,'Ingreso','Servicio','Orden #0013',18.4000,8679.28,471.7000,13,'2026-03-29 20:32:40',NULL),(14,'Ingreso','Servicio','Orden #0014',22.0000,10377.40,471.7000,14,'2026-04-03 19:50:13',NULL),(15,'Ingreso','Servicio','Orden #0015',22.0000,10377.40,471.7000,15,'2026-04-14 12:41:42',NULL),(16,'Ingreso','Servicio','Orden #0016',22.0000,10518.64,478.1200,16,'2026-04-19 21:58:03',NULL),(17,'Ingreso','Servicio','Orden #0017',5.0000,2405.60,481.1200,17,'2026-07-03 09:55:16',NULL),(18,'Ingreso','Servicio','Orden #0018',15.4000,11349.18,736.9600,18,'2026-07-20 21:50:30',NULL),(19,'Ingreso','Servicio','Orden #0019',8.0000,6912.00,864.0000,19,'2026-09-23 19:53:24',NULL),(20,'Ingreso','Servicio','Orden #0020',9.0000,7776.00,864.0000,20,'2026-09-23 20:07:02',NULL),(24,'Ingreso','Servicio','Orden #0021',6.0000,5184.00,864.0000,21,'2026-10-11 11:31:38','Sebastian Blanca'),(25,'Ingreso','Servicio','Orden #0022',11.0000,9504.00,864.0000,22,'2026-10-11 15:31:54','Sebastian Blanca');
/*!40000 ALTER TABLE `movimientos_caja` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ordenes`
--

DROP TABLE IF EXISTS `ordenes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ordenes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cliente_id` int(10) unsigned NOT NULL,
  `fecha_recepcion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_promesa` datetime NOT NULL,
  `fecha_entrega` datetime DEFAULT NULL,
  `estado` enum('Recibido','Lavando','Secando','Listo','Entregado','Cancelado') NOT NULL DEFAULT 'Recibido',
  `total_usd` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `total_bs` decimal(16,2) DEFAULT NULL,
  `tasa_cambio_id` int(10) unsigned DEFAULT NULL,
  `metodo_pago` enum('Efectivo_USD','Efectivo_BS','Pago_Movil','Punto_Venta','Transferencia','Mixto') DEFAULT 'Efectivo_USD',
  `notas` text DEFAULT NULL,
  `foto_recepcion` varchar(255) DEFAULT NULL COMMENT 'Ruta a imagen de evidencia',
  `notificado_whatsapp` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tasa_cambio_id` (`tasa_cambio_id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_fecha_promesa` (`fecha_promesa`),
  KEY `idx_cliente` (`cliente_id`),
  CONSTRAINT `ordenes_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `ordenes_ibfk_2` FOREIGN KEY (`tasa_cambio_id`) REFERENCES `tasas_cambio` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Órdenes de servicio';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ordenes`
--

LOCK TABLES `ordenes` WRITE;
/*!40000 ALTER TABLE `ordenes` DISABLE KEYS */;
INSERT INTO `ordenes` VALUES (1,2,'2026-03-22 11:15:57','2026-03-22 19:15:57','2026-03-22 11:17:20','Entregado',1.5000,687.00,2,'Efectivo_USD','',NULL,1,'2026-03-22 15:15:57','2026-03-22 15:17:20'),(2,2,'2026-03-22 11:15:58','2026-03-22 19:15:58','2026-03-22 11:17:21','Entregado',1.5000,687.00,2,'Efectivo_USD','',NULL,1,'2026-03-22 15:15:58','2026-03-22 15:17:21'),(3,2,'2026-03-22 11:15:59','2026-03-22 19:15:59','2026-03-22 11:17:22','Entregado',1.5000,687.00,2,'Efectivo_USD','',NULL,0,'2026-03-22 15:15:59','2026-03-22 15:17:22'),(4,2,'2026-03-22 11:16:02','2026-03-22 19:16:02','2026-03-22 11:17:22','Entregado',1.5000,687.00,2,'Efectivo_USD','',NULL,1,'2026-03-22 15:16:02','2026-03-22 15:17:22'),(5,1,'2026-03-22 11:52:34','2026-03-22 19:52:34','2026-03-22 11:58:45','Entregado',4.4000,2015.20,2,'Efectivo_USD','',NULL,1,'2026-03-22 15:52:34','2026-03-22 15:58:45'),(6,3,'2026-03-22 12:01:35','2026-03-22 20:01:35','2026-03-22 12:02:12','Entregado',3.0000,1374.00,2,'Efectivo_USD','ropa llena de barro',NULL,1,'2026-03-22 16:01:35','2026-03-22 16:02:12'),(7,4,'2026-03-22 12:14:41','2026-03-22 20:14:41','2026-03-22 12:16:31','Entregado',6.6000,3022.80,2,'Efectivo_USD','',NULL,1,'2026-03-22 16:14:41','2026-03-22 16:16:31'),(8,5,'2026-03-22 13:02:16','2026-03-23 13:02:16','2026-03-22 13:04:32','Entregado',23.0000,10534.00,2,'Efectivo_USD','',NULL,1,'2026-03-22 17:02:16','2026-03-22 17:04:32'),(9,3,'2026-03-22 13:28:31','2026-03-22 21:28:31','2026-03-22 13:29:20','Entregado',18.0000,8227.26,2,'Efectivo_USD','',NULL,1,'2026-03-22 17:28:31','2026-03-22 17:29:20'),(10,2,'2026-03-22 13:29:02','2026-03-22 21:29:02','2026-03-22 13:29:27','Entregado',7.0000,3199.49,2,'Efectivo_USD','',NULL,1,'2026-03-22 17:29:02','2026-03-22 17:29:27'),(11,1,'2026-03-22 23:10:29','2026-03-23 07:10:29','2026-03-22 23:12:11','Entregado',2.2000,1005.55,2,'Efectivo_USD','',NULL,1,'2026-03-23 03:10:29','2026-03-23 03:12:11'),(12,6,'2026-03-23 10:43:50','2026-03-23 18:43:50','2026-03-23 10:44:44','Entregado',33.0000,15083.31,2,'Efectivo_USD','',NULL,0,'2026-03-23 14:43:50','2026-03-23 14:44:44'),(13,7,'2026-03-29 20:32:40','2026-03-30 04:32:40','2026-03-29 20:36:47','Entregado',18.4000,8679.28,4,'Efectivo_USD','',NULL,1,'2026-03-30 00:32:40','2026-03-30 00:36:47'),(14,8,'2026-04-03 19:50:13','2026-04-04 03:50:13','2026-04-03 19:50:49','Entregado',22.0000,10377.40,4,'Efectivo_USD','',NULL,0,'2026-04-03 23:50:13','2026-04-03 23:50:49'),(15,9,'2026-04-14 12:41:42','2026-04-14 20:41:42','2026-04-14 12:43:05','Entregado',22.0000,10377.40,4,'Efectivo_USD','',NULL,1,'2026-04-14 16:41:42','2026-04-14 16:43:05'),(16,10,'2026-04-19 21:58:03','2026-04-20 05:58:03','2026-04-19 21:59:05','Entregado',22.0000,10518.64,5,'Efectivo_BS','',NULL,0,'2026-04-20 01:58:03','2026-04-20 01:59:05'),(17,3,'2026-07-03 09:55:16','2026-07-03 21:55:16','2026-07-03 09:55:26','Entregado',5.0000,2405.60,6,'Efectivo_USD','',NULL,1,'2026-07-03 13:55:16','2026-07-03 13:55:26'),(18,2,'2026-07-20 21:50:30','2026-07-21 05:50:30','2026-07-20 21:51:42','Entregado',15.4000,11349.18,8,'Efectivo_USD','',NULL,1,'2026-07-21 01:50:30','2026-07-21 01:51:42'),(19,4,'2026-09-23 19:53:24','2026-09-24 09:53:24','2026-09-23 19:54:57','Entregado',8.0000,6912.00,9,'Efectivo_USD','',NULL,1,'2026-09-23 23:53:24','2026-09-23 23:54:57'),(20,4,'2026-09-23 20:07:02','2026-09-24 04:07:02','2026-09-23 20:08:02','Entregado',9.0000,7776.00,9,'Efectivo_USD','',NULL,1,'2026-09-24 00:07:02','2026-09-24 00:08:02'),(21,3,'2026-10-11 11:31:36','2026-10-11 17:31:36','2026-10-11 15:27:13','Entregado',6.0000,5184.00,9,'Pago_Movil','',NULL,1,'2026-10-11 15:31:36','2026-10-11 19:27:13'),(22,12,'2026-10-11 15:31:54','2026-10-11 23:31:54','2026-10-11 15:32:43','Entregado',11.0000,9504.00,9,'Pago_Movil','',NULL,1,'2026-10-11 19:31:54','2026-10-11 19:32:43');
/*!40000 ALTER TABLE `ordenes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pagos`
--

DROP TABLE IF EXISTS `pagos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pagos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `orden_id` int(10) unsigned NOT NULL,
  `metodo_pago` enum('Efectivo','Pago Móvil','Transferencia','Zelle','Punto de Venta') NOT NULL,
  `moneda` enum('USD','VES') NOT NULL DEFAULT 'USD',
  `monto` decimal(10,2) NOT NULL,
  `tasa_cambio` decimal(10,2) NOT NULL,
  `fecha_pago` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `orden_id` (`orden_id`),
  CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`orden_id`) REFERENCES `ordenes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pagos`
--

LOCK TABLES `pagos` WRITE;
/*!40000 ALTER TABLE `pagos` DISABLE KEYS */;
/*!40000 ALTER TABLE `pagos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prendas_orden`
--

DROP TABLE IF EXISTS `prendas_orden`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `prendas_orden` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `orden_id` int(10) unsigned NOT NULL,
  `tipo_prenda_id` int(10) unsigned DEFAULT NULL,
  `descripcion` varchar(60) NOT NULL COMMENT 'Nombre del tipo al momento de registrar',
  `cantidad` smallint(5) unsigned NOT NULL,
  `observacion` varchar(150) DEFAULT NULL COMMENT 'Color, marca, daños previos...',
  PRIMARY KEY (`id`),
  KEY `tipo_prenda_id` (`tipo_prenda_id`),
  KEY `idx_orden` (`orden_id`),
  CONSTRAINT `prendas_orden_ibfk_1` FOREIGN KEY (`orden_id`) REFERENCES `ordenes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prendas_orden_ibfk_2` FOREIGN KEY (`tipo_prenda_id`) REFERENCES `tipos_prenda` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Prendas recibidas en cada orden';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prendas_orden`
--

LOCK TABLES `prendas_orden` WRITE;
/*!40000 ALTER TABLE `prendas_orden` DISABLE KEYS */;
INSERT INTO `prendas_orden` VALUES (5,21,1,'Camisa',3,NULL),(6,21,2,'Franela',2,NULL),(7,21,3,'Pantalón',2,NULL),(8,22,1,'Camisa',2,NULL),(9,22,2,'Franela',2,NULL),(10,22,3,'Pantalón',2,NULL),(11,22,7,'Blusa',1,NULL),(12,22,24,'Bufanda',1,NULL);
/*!40000 ALTER TABLE `prendas_orden` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proveedores`
--

DROP TABLE IF EXISTS `proveedores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proveedores` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rif` varchar(20) NOT NULL,
  `nombre_empresa` varchar(100) NOT NULL,
  `contacto_nombre` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rif` (`rif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proveedores`
--

LOCK TABLES `proveedores` WRITE;
/*!40000 ALTER TABLE `proveedores` DISABLE KEYS */;
/*!40000 ALTER TABLE `proveedores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `servicios`
--

DROP TABLE IF EXISTS `servicios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `servicios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `unidad_medida` enum('Kg','Unidad','Par','Pieza') NOT NULL DEFAULT 'Kg',
  `precio_usd` decimal(10,4) NOT NULL,
  `tiempo_estimado_horas` decimal(5,1) NOT NULL DEFAULT 4.0,
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_activo` (`activo`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Catálogo de servicios ofrecidos';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `servicios`
--

LOCK TABLES `servicios` WRITE;
/*!40000 ALTER TABLE `servicios` DISABLE KEYS */;
INSERT INTO `servicios` VALUES (1,'Lavado por Peso',NULL,'Kg',1.5000,6.0,1),(2,'Solo Secado',NULL,'Kg',0.8000,2.0,1),(3,'Lavado + Secado',NULL,'Kg',2.2000,8.0,1),(4,'Edredón Simple',NULL,'Unidad',5.0000,12.0,1),(5,'Edredón Doble',NULL,'Unidad',8.0000,14.0,1),(6,'Tintorería Camisa',NULL,'Unidad',3.5000,8.0,1),(7,'Tintorería Traje',NULL,'Unidad',8.0000,24.0,1),(8,'Ropa Delicada',NULL,'Kg',3.0000,8.0,1);
/*!40000 ALTER TABLE `servicios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sistema_config`
--

DROP TABLE IF EXISTS `sistema_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sistema_config` (
  `clave` varchar(60) NOT NULL,
  `valor` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Parámetros internos del sistema';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sistema_config`
--

LOCK TABLES `sistema_config` WRITE;
/*!40000 ALTER TABLE `sistema_config` DISABLE KEYS */;
INSERT INTO `sistema_config` VALUES ('schema_version','5','2026-10-11 19:49:44');
/*!40000 ALTER TABLE `sistema_config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tasas_cambio`
--

DROP TABLE IF EXISTS `tasas_cambio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tasas_cambio` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `tasa_bcv` decimal(12,4) NOT NULL COMMENT 'Tasa Banco Central de Venezuela',
  `tasa_paralelo` decimal(12,4) DEFAULT NULL COMMENT 'Tasa referencial paralela (opcional)',
  `registrado_por` varchar(80) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `fecha` (`fecha`),
  KEY `idx_fecha` (`fecha`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Historial de tasas de cambio BS/USD';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tasas_cambio`
--

LOCK TABLES `tasas_cambio` WRITE;
/*!40000 ALTER TABLE `tasas_cambio` DISABLE KEYS */;
INSERT INTO `tasas_cambio` VALUES (1,'2026-03-21',46.5000,NULL,'sistema','2026-03-21 23:25:58'),(2,'2026-03-22',457.0700,NULL,'admin','2026-03-22 13:53:18'),(4,'2026-03-29',471.7000,NULL,'admin','2026-03-30 00:32:09'),(5,'2026-04-14',478.1200,NULL,'admin','2026-04-14 16:52:39'),(6,'2026-04-19',481.1200,NULL,'admin','2026-04-20 02:01:21'),(7,'2026-07-03',652.9700,NULL,'admin','2026-07-03 13:56:11'),(8,'2026-07-20',736.9600,NULL,'admin','2026-07-20 20:25:58'),(9,'2026-09-22',864.0000,NULL,'admin','2026-09-22 15:34:45');
/*!40000 ALTER TABLE `tasas_cambio` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tipos_prenda`
--

DROP TABLE IF EXISTS `tipos_prenda`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tipos_prenda` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `orden` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Catálogo de tipos de prenda';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tipos_prenda`
--

LOCK TABLES `tipos_prenda` WRITE;
/*!40000 ALTER TABLE `tipos_prenda` DISABLE KEYS */;
INSERT INTO `tipos_prenda` VALUES (1,'Camisa',1,1),(2,'Franela',1,2),(3,'Pantalón',1,3),(4,'Short / Bermuda',1,4),(5,'Falda',1,5),(6,'Vestido',1,6),(7,'Blusa',1,7),(8,'Chaqueta / Suéter',1,8),(9,'Traje / Paltó',1,9),(10,'Uniforme',1,10),(11,'Ropa interior',1,11),(12,'Medias (par)',1,12),(13,'Pijama',1,13),(14,'Ropa de bebé',1,14),(15,'Sábana',1,15),(16,'Funda de almohada',1,16),(17,'Toalla',1,17),(18,'Cobija',1,18),(19,'Edredón',1,19),(20,'Cortina',1,20),(21,'Mantel',1,21),(22,'Zapatos deportivos (par)',1,22),(23,'Otro',1,23),(24,'Bufanda',1,24);
/*!40000 ALTER TABLE `tipos_prenda` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  `username` varchar(40) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'bcrypt hash',
  `rol` enum('admin','vendedor') NOT NULL DEFAULT 'vendedor',
  `activo` tinyint(1) DEFAULT 1,
  `ultimo_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Usuarios con acceso al sistema';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (2,'Sebastian Blanca','sebastianblanca','$2y$12$fX03puqcXZ61wf9.HWzKH.t9nzbsbK920SFFBi/DdvketqH01qudm','admin',1,'2026-10-11 11:29:24','2026-03-23 02:11:03'),(4,'Andreina Seijas','andrea','$2y$12$gdr6iNKVmiAvtdqkx7Ds3ORr3A34AnRoQ5.1TuAt/WWVET9Oi7zpe','vendedor',1,'2026-06-14 13:45:07','2026-06-14 13:51:41');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'lavanderia_superior'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-11 15:55:50
