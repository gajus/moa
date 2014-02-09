# ************************************************************
# Sequel Pro SQL dump
# Version 4096
#
# http://www.sequelpro.com/
# http://code.google.com/p/sequel-pro/
#
# Host: 127.0.0.1 (MySQL 5.5.35-33.0-log)
# Database: moa
# Generation Time: 2014-02-09 17:42:53 +0000
# ************************************************************


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# Dump of table datetime
# ------------------------------------------------------------

DROP TABLE IF EXISTS `datetime`;

CREATE TABLE `datetime` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `datetime` datetime DEFAULT NULL,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Dump of table duplicate
# ------------------------------------------------------------

DROP TABLE IF EXISTS `duplicate`;

CREATE TABLE `duplicate` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `foo` varchar(100) DEFAULT NULL,
  `bar` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `foo` (`foo`,`bar`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Dump of table greedy
# ------------------------------------------------------------

DROP TABLE IF EXISTS `greedy`;

CREATE TABLE `greedy` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Dump of table number
# ------------------------------------------------------------

DROP TABLE IF EXISTS `number`;

CREATE TABLE `number` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tinyint` tinyint(4) DEFAULT NULL,
  `unsigned_tinyint` tinyint(3) unsigned DEFAULT NULL,
  `smallint` smallint(6) DEFAULT NULL,
  `unsigned_smallint` smallint(5) unsigned DEFAULT NULL,
  `int` int(11) DEFAULT NULL,
  `unsigned_int` int(10) unsigned DEFAULT NULL,
  `bigint` bigint(20) DEFAULT NULL,
  `unsigned_bigint` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Dump of table string
# ------------------------------------------------------------

DROP TABLE IF EXISTS `string`;

CREATE TABLE `string` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;




/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
