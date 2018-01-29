CREATE DATABASE  IF NOT EXISTS `projects4me` /*!40100 DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci */;
USE `projects4me`;
-- MySQL dump 10.13  Distrib 5.5.46, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: projects4me
-- ------------------------------------------------------
-- Server version	5.5.46-0ubuntu0.14.04.2

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `contacts`
--

DROP TABLE IF EXISTS `contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contacts` (
  `id` int(11) NOT NULL,
  `firstName` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `lastName` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `email` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `phoneHome` varchar(15) COLLATE utf8_unicode_ci DEFAULT NULL,
  `projectId` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contacts`
--

LOCK TABLES `contacts` WRITE;
/*!40000 ALTER TABLE `contacts` DISABLE KEYS */;
INSERT INTO `contacts` VALUES (1,'Test','Contact','test@example.com',NULL,0),(2,'Test','Contact 2','test2@example.com',NULL,0);
/*!40000 ALTER TABLE `contacts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contacts_users`
--

DROP TABLE IF EXISTS `contacts_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contacts_users` (
  `id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contacts_users`
--

LOCK TABLES `contacts_users` WRITE;
/*!40000 ALTER TABLE `contacts_users` DISABLE KEYS */;
INSERT INTO `contacts_users` VALUES (1,1,1),(2,1,2);
/*!40000 ALTER TABLE `contacts_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notes`
--

DROP TABLE IF EXISTS `notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notes` (
  `id` varchar(36) COLLATE utf8_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `body` text COLLATE utf8_unicode_ci NOT NULL,
  `contact_id` varchar(36) COLLATE utf8_unicode_ci NOT NULL,
  `userId` varchar(36) COLLATE utf8_unicode_ci NOT NULL,
  `projectId` varchar(36) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notes`
--

LOCK TABLES `notes` WRITE;
/*!40000 ALTER TABLE `notes` DISABLE KEYS */;
INSERT INTO `notes` VALUES ('0373af435661-2952-e698-f9b59e783fda','Notes API Post 1','This is a test note testing the POST','1','2','1'),('1','xy','This is test 1','1','1','2'),('2','ac12','This is test 2','1','1','1'),('3','Test 3','This is test 3','2','1','1'),('4','Test 4 ','','2','1','2'),('76aeaea75665-3157-1043-20f49486bbb0','Notes API Post 1','This is a test note testing the POST','1','1','1'),('9cf0d5495665-3157-1043-c9f143b724d4','Notes API Post 2','This is a test note testing the POST','1','1','2'),('ba6733c85661-c837-e698-a605baa4f113','Notes API Post 1','This is a test note testing the POST','1','1','1'),('e27299025661-c837-e698-4d2939f8f69c','Notes API Post 1','This is a test note testing the POST','1','1','1');
/*!40000 ALTER TABLE `notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `oauth_access_tokens`
--

DROP TABLE IF EXISTS `oauth_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oauth_access_tokens` (
  `access_token` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `client_id` varchar(36) COLLATE utf8_unicode_ci NOT NULL,
  `user_id` varchar(36) COLLATE utf8_unicode_ci DEFAULT NULL,
  `expires` datetime NOT NULL,
  `scope` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`access_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oauth_access_tokens`
--

LOCK TABLES `oauth_access_tokens` WRITE;
/*!40000 ALTER TABLE `oauth_access_tokens` DISABLE KEYS */;
INSERT INTO `oauth_access_tokens` VALUES ('007933d2c533e8ad185c32c5da404310df3bf425','testclient',NULL,'2015-09-18 11:00:01',NULL),('015cc47b7eb80d72f7359bf949e1cb9a0e2f919c','testclient',NULL,'2015-09-06 13:21:43',NULL),('04ecf8cf4d904338cbe244d5561a9359e3ffbb0a','testclient',NULL,'2015-09-18 11:36:02',NULL),('0647d89e5c76916db6e799e8063c57fe4abfa73c','testclient',NULL,'2015-09-18 11:17:44',NULL),('0acf871fb960c39b76ad1adf49b88eda6200a247','testclient','hammad','2015-12-01 09:38:05',NULL),('0b9e460dfe96b74424895bf5162937301398e6af','testclient','hammad','2015-12-01 08:37:52',NULL),('0c755cc730342d22553e22f049ee927fefc27aad','testclient','hammad','2015-12-01 10:52:30',NULL),('0ccda12c3191fefd8f0ba07082dc9dfc5625e99a','projects4me','hammad','2016-01-18 09:01:30','application'),('0d488e4f111c4370c0ef137fb0b517a3122db5dd','testclient',NULL,'2015-09-18 11:11:14',NULL),('100f8db7bb9d19dc64b0823107fa52de98bfa7fd','testclient','hammad','2015-09-10 10:54:46',NULL),('11a93f98dd5d7a95971aded650c8815347fac42b','projects4me','hammad','2016-01-17 14:13:51','application'),('11ffe16c73dbd375d4f21d76bfee34ac94f159fa','projects4me','hammad','2016-01-17 13:40:45','application'),('13b566eb27c032d2d87ff7f806362b5db14b5536','projects4me','hammad','2016-01-18 09:19:55','application'),('171727b21b941a0538dda06e693b930e61a47c81','projects4me','hammad','2016-01-17 12:34:41',NULL),('17f3950d37fae13331af1f115e28ecd3efe1e6d0','testclient','hammad','2015-09-07 09:49:02',NULL),('1c091ad0e1171250cfb195727030a36e3cf97c8e','testclient','hammad','2015-12-02 10:04:39',NULL),('1d2bb032c815d9a475521c544a84ee24620e41d9','projects4me','hammad','2016-01-17 14:12:35','application'),('1dac4b3f906fed9b94731933c5d8f3a89a066894','testclient',NULL,'2015-09-18 10:58:38',NULL),('1e25a388eae1b5f1fc8d33267de7ef60d8884e70','projects4me','hammad','2016-01-18 09:13:19','application'),('2527d6ab261616a4f04b9c9b39baa8dea09ae236','projects4me','hammad','2016-01-17 13:47:38','application'),('262e23df1f9c4461479fc02255ea118aeed874e3','projects4me','hammad','2016-01-17 13:39:05','application'),('266c6b39137583dfd221e65c44138683b43bc9ec','testclient','hammad','2015-11-27 08:23:39',NULL),('272db92effc778b1d9c22d371f159df71951ee1e','testclient','hammad','2015-12-02 10:01:21',NULL),('281cadc16e508c3d3c97de09514c596cd903ec51','projects4me','hammad','2016-01-17 13:35:00','application'),('29d3852b85df2ac5ee445ba26bf829543d100f3e','testclient',NULL,'2015-10-07 03:09:02',NULL),('2bd465a3781f15fd3688f1aa37f6a96c35168a5d','projects4me','hammad','2016-01-18 10:04:15','application'),('2ce4ab5e4fdfe34a3204b8215a5146ae5cc764c7','projects4me','hammad','2016-01-18 08:54:47','application'),('2fc1822335b715d25f56a566b78baee5c00d6942','testclient','hammad','2015-12-02 10:01:38',NULL),('37639eb51ec0af50d28da0de46af7ab10c2d48e1','testclient',NULL,'2015-10-07 03:07:40',NULL),('37a6ac65b0f5fb81d7adffd68618f5cd1bce5484','testclient','hammad','2015-12-02 09:53:46',NULL),('3a37eac1f1ee2196c0d6582e95ef0ba63cb3e55e','testclient','hammad','2015-10-29 17:18:24',NULL),('3e1d1d929136ee4c2bae15be83357bef27f978f5','testclient','hammad','2015-12-08 09:47:58',NULL),('434fde21bdea54ff35fc3c26b6f3878db203f443','testclient','hammad','2015-12-07 13:30:39',NULL),('43cb8645d84c0c8a80d9b40c6bfad8be1bf0e1fd','testclient','hammad','2015-11-24 07:19:24',NULL),('456bac6b3aa6d74dd0c3056ba2cdde898c5e275c','testclient','hammad','2015-11-27 10:45:04',NULL),('46b356cd7f7641fc7690c3c012822fc339c71727','testclient',NULL,'2015-09-06 13:00:11',NULL),('47e50bfcfe8808d32875c70113f6e3bd9f62ed06','testclient','hammad','2015-12-08 05:24:47',NULL),('4d3f5f8954f2b7a32d52c57eeaa607fae6936c6c','projects4me','hammad','2016-01-18 09:32:53','application'),('4dccd3c6ae5bb8dffd470f0c2e7289813af2d598','testclient','hammad','2015-11-30 08:42:09',NULL),('4dd5b68355a5f72750d8bde61228f4a1dffeaf32','testclient',NULL,'2015-09-18 11:37:14',NULL),('51b1e6386a6d017f9ac024078cab101e212eafa0','projects4me','hammad','2016-01-17 13:41:23','application'),('5231baccc9b7eedc9f53efe0bb864872b8429961','projects4me','hammad','2016-01-17 13:03:41','application'),('5787d90e66cb327b6cf02ac2c016d9cf0c63857a','testclient','hammad','2015-10-13 10:01:29',NULL),('580b6685edd9b38898309fe109c978d71176961c','projects4me','hammad','2016-01-17 13:36:07','application'),('5b0d6ba8cc54dce2eedb9a900c02b4c756343afb','testclient','hammad','2016-01-30 10:55:17',NULL),('60eb2bb8c217369538603fbf0f2b83d65aaa1731','projects4me','hammad','2016-01-17 13:06:12','application'),('640674302cabb1cbf46712e7a5ccae1111f3c40b','testclient','hammad','2015-12-02 09:49:38',NULL),('6565d53cfc19af6e0e5e12b0d16f6673406be758','testclient',NULL,'2015-09-18 11:16:34',NULL),('67fc46a71c8677ab6b8582d0d9a67368c5b37d63','projects4me','hammad','2016-01-17 13:33:56','application'),('68ce2fe9d376a8fca177b3a81073307f47b67bae','testclient','hammad','2015-09-10 10:42:48',NULL),('68dcd993465c5f7a7992c25f8a2929899b98f957','projects4me','hammad','2016-01-17 12:21:00',NULL),('6bdd364134af35185ca86ec7bdb00a49b1daf37e','projects4me','hammad','2016-01-17 13:31:28','application'),('6e32c21dd58645445324551e0b4b0f851d94c9f6','projects4me','hammad','2016-01-17 13:04:42','application'),('6f68cf93321275ad722afca171916930676ede4a','testclient',NULL,'2015-10-07 03:10:27',NULL),('74c2a8716cb24b3f5398efea1c474b3d22140c90','testclient','hammad','2015-11-11 19:36:21',NULL),('787f0e01d88e700798832e2c9df81aed858cf189','testclient','hammad','2015-10-07 03:10:44',NULL),('78847ccffb6e9711315017f2f71b13a9c0fa6473','testclient','hammad','2015-12-04 06:31:32',NULL),('7a18aa2cd530f4c734fe9b58e44453e24ba4215f','projects4me','hammad','2016-01-17 13:42:29','application'),('7ceb6d7456c93ec5afcd0b9618bb1ec28397f4ad','projects4me','hammad','2016-01-17 13:06:37','application'),('8129b39915488947b4cfd247e971486033a1fdf5','testclient','hammad','2015-11-11 19:42:24',NULL),('8270a09c9532d93884a97c543d5b739680ba74e2','testclient','hammad','2015-12-10 08:54:25',NULL),('858956116d7cb815551a0c07739ebbefc27b942f','projects4me','hammad','2016-01-17 12:55:20','application'),('8af769413477ce3f909edce136ab7b0b1552a17d','testclient','hammad','2015-11-25 07:33:20',NULL),('8d668474e66fc9cc52483cb4886d6869ab9131a7','projects4me','hammad','2016-01-17 14:13:46','application'),('974ee65704ce9a2d8891c2f9701cd23c02a656bb','projects4me','hammad','2016-01-17 13:43:51','application'),('980db97c404151a1fddb5c788e4b2188a216ddaa','testclient',NULL,'2015-09-18 11:11:32',NULL),('9c7bbe70fd87af04e2e06ecb66337072107a75fa','testclient',NULL,'2015-09-06 13:07:05',NULL),('a174d2501c1945870c9b705293c75af6836566cc','projects4me','hammad','2016-01-17 14:13:41','application'),('a2f9c47648ed7e1f100b913773d88b22c91c8bcd','testclient',NULL,'2015-09-07 09:42:16',NULL),('a4b673b17e780590edcc8c15702ead900c4229ec','testclient','hammad','2015-10-07 03:53:47',NULL),('a8927417f46f4c6f82c79feb4890d8a1ca540004','testclient','hammad','2015-12-10 09:54:56',NULL),('ad747dca7bca6f3bf3248cd5edc71b9d8e4881b4','testclient','hammad','2015-10-07 03:11:58',NULL),('aeaaa7dbd3dd90a46238c31d2fa44089639c44a0','testclient','hammad','2015-12-02 09:49:22',NULL),('afca003a131faa92d7a5856128411dd2f714ff7d','testclient','hammad','2015-12-04 02:27:19',NULL),('b5eaf36e996ed429b518cce01d01e5783bb40cc7','projects4me','hammad','2016-01-18 10:05:02','application'),('b686412b801d25311f57498d7b94379d0b8086f0','projects4me','hammad','2016-01-18 09:20:48','application'),('b92a04b9464398b443a8bfd5feaecba1a533b389','testclient',NULL,'2015-12-04 06:30:40',NULL),('b94ee682a889edb854dc7ba11ad01f750ec9e811','projects4me','hammad','2016-01-17 12:55:29','application'),('bdc48b9bbe7b1c7013c9ffdedc629d763106b75f','projects4me','hammad','2016-01-17 13:31:47','application'),('be7cb0ac2730d9a4057e4a3a4afb36cf4356dd8f','projects4me','hammad','2016-01-17 13:39:56','application'),('bea59ac1e0ea7f2a30a85dd9d8fe1299cbf27428','projects4me','hammad','2016-01-18 09:57:43','application'),('bf92d328368c73a6805831bf49058ab488032234','testclient','hammad','2015-12-04 06:31:39',NULL),('c02ee7fdb35559d26e2492b0a93da5364c668410','testclient','hammad','2015-11-27 07:36:52',NULL),('c2b2c3c634815f3695093baf36e104c39707a12c','projects4me','hammad','2016-01-18 09:19:18','application'),('c3ac765be856357971e577a69565417f1a0b8430','testclient',NULL,'2015-09-18 11:22:23',NULL),('c761085523b9a206cd5ff8ef778e0e426ebd86ce','projects4me','hammad','2016-01-18 09:18:14','application'),('c874b739031f625c895d71d13434906840637b54','testclient','hammad','2015-11-24 05:57:03',NULL),('d088613fbc399045387c746ba273b35e98686c30','testclient','hammad','2015-12-02 09:24:21',NULL),('d195fa2ceeaad988772a1890e50eb63fb6e66ed7','testclient','hammad','2015-12-02 09:56:03',NULL),('d3fa35d9ee2970fdfc361675e06f79788f16dce3','projects4me','hammad','2016-01-18 09:15:33','application'),('d45b86bf898ee48ebda8adc218d0922e53ce5851','testclient','hammad','2015-11-24 03:53:11',NULL),('d4f106a759c4c8355d74174cd4821813c41f9f7e','projects4me','hammad','2016-01-17 12:55:13','application'),('d76b4cecd57e58aad107fbfc6dc9f38bf11dd22f','projects4me','hammad','2016-01-17 12:56:44','application'),('d8e2bcb31319741ba488a24ad3c4aa3556b0dc87','testclient','hammad','2015-11-11 19:42:17',NULL),('d9516173229301e9dfbbdfd6ea81ad20618ec8c5','projects4me','hammad','2016-01-18 09:33:25','application'),('da98a95daebef711c098a0cbf07f21e19f054191','testclient','hammad','2015-12-02 09:49:31',NULL),('dce3579d7422c0e6776892ab8a63d7e00d56e6ce','projects4me','hammad','2016-01-17 13:36:46','application'),('e1741d8f3cb7f7988120a83cbd818483fc4e0397','projects4me','hammad','2015-01-18 09:24:10','application'),('e348572319685e6973ea746f076a1eec526879be','testclient','hammad','2015-12-09 11:59:05',NULL),('e43999b8eb3e7bef075d89dc982c89fba458b0cd','testclient','hammad','2015-12-02 08:10:55',NULL),('e8a31a73b73451fb6c8dbdcb328f7051791ca6a1','testclient','hammad','2015-12-04 05:24:35',NULL),('e9bb40a943b71166edf1bb1878480b6a741f5728','testclient',NULL,'2015-09-06 13:17:18',NULL),('ea381a241de7f851da4d985f5ea626b545522463','projects4me','hammad','2016-01-17 13:06:21','application'),('f1e969c2c1118dfe143b9ca8af9aa2224831fcfc','projects4me','hammad','2016-01-18 08:56:08','application'),('f20231d3e6ef1f184a78cbf2f867aff4e9e27c66','projects4me','hammad','2016-01-17 12:37:15','application'),('f45cca54656960dfcfed34f350de805204b81019','projects4me','hammad','2016-01-18 08:56:25','application'),('f6fea1ae0c0b60907f7f732617bd1ffa8959f15e','testclient',NULL,'2015-11-24 03:52:45',NULL),('f7af5bca7d85bf9ea1a5c1e2b08b97623ef8d138','testclient',NULL,'2015-09-18 11:27:17',NULL),('f822ef40339217e7d58961a533f552c15da88ba4','projects4me','hammad','2016-01-17 13:34:34','application'),('f856de5a143455fd79e58836b20f1ca67e66c00d','testclient',NULL,'2015-12-04 06:31:26',NULL),('f96778573cd7407da7571c5785e36730e8f92176','projects4me','hammad','2016-01-17 14:01:17','application'),('f9700c9a60b971d24f2ec3ac8ebf09f7acf5ac43','testclient','hammad','2015-09-06 13:42:27',NULL),('fbe3c5a3f9c94c7c499d51d2ed3d1a1c437db9c2','testclient',NULL,'2015-09-18 11:17:18',NULL);
/*!40000 ALTER TABLE `oauth_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `oauth_authorization_codes`
--

DROP TABLE IF EXISTS `oauth_authorization_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oauth_authorization_codes` (
  `authorization_code` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `client_id` varchar(36) COLLATE utf8_unicode_ci NOT NULL,
  `user_id` varchar(36) COLLATE utf8_unicode_ci DEFAULT NULL,
  `redirect_uri` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `scope` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `expires` datetime NOT NULL,
  PRIMARY KEY (`authorization_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oauth_authorization_codes`
--

LOCK TABLES `oauth_authorization_codes` WRITE;
/*!40000 ALTER TABLE `oauth_authorization_codes` DISABLE KEYS */;
/*!40000 ALTER TABLE `oauth_authorization_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `oauth_clients`
--

DROP TABLE IF EXISTS `oauth_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oauth_clients` (
  `id` varchar(36) COLLATE utf8_unicode_ci NOT NULL,
  `client_id` varchar(36) COLLATE utf8_unicode_ci NOT NULL,
  `client_secret` varchar(80) COLLATE utf8_unicode_ci NOT NULL,
  `redirect_uri` varchar(2000) COLLATE utf8_unicode_ci NOT NULL,
  `grant_types` varchar(80) COLLATE utf8_unicode_ci DEFAULT NULL,
  `scope` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_id` varchar(36) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oauth_clients`
--

LOCK TABLES `oauth_clients` WRITE;
/*!40000 ALTER TABLE `oauth_clients` DISABLE KEYS */;
INSERT INTO `oauth_clients` VALUES ('1','projects4me','06110fb83488715ca69057f4a7cedf93','http://projects4me/','password refresh_token','application','');
/*!40000 ALTER TABLE `oauth_clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `oauth_jwt`
--

DROP TABLE IF EXISTS `oauth_jwt`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oauth_jwt` (
  `client_id` varchar(36) COLLATE utf8_unicode_ci NOT NULL,
  `subject` varchar(80) COLLATE utf8_unicode_ci DEFAULT NULL,
  `public_key` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oauth_jwt`
--

LOCK TABLES `oauth_jwt` WRITE;
/*!40000 ALTER TABLE `oauth_jwt` DISABLE KEYS */;
/*!40000 ALTER TABLE `oauth_jwt` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `oauth_refresh_tokens`
--

DROP TABLE IF EXISTS `oauth_refresh_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oauth_refresh_tokens` (
  `refresh_token` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `client_id` varchar(36) COLLATE utf8_unicode_ci NOT NULL,
  `user_id` varchar(36) COLLATE utf8_unicode_ci DEFAULT NULL,
  `expires` datetime NOT NULL,
  `scope` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`refresh_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oauth_refresh_tokens`
--

LOCK TABLES `oauth_refresh_tokens` WRITE;
/*!40000 ALTER TABLE `oauth_refresh_tokens` DISABLE KEYS */;
INSERT INTO `oauth_refresh_tokens` VALUES ('000e50903dc87ce0cb131c751ca7334e4371f35a','testclient','hammad','2015-09-21 08:49:02',NULL),('00e8ae06d5e1b6b54a9f21c1b00cbd0cdcb81ee8','projects4me','hammad','2016-02-01 07:54:47','application'),('04b618f0f2f3b51bf29f6d1c060233cd47730dee','projects4me','hammad','2016-01-31 12:31:47','application'),('07ae294dd61f854fcb3649d7c28b39077e505052','projects4me','hammad','2016-01-31 11:56:44','application'),('0e81ead2e456162ff27c42491398010495de56eb','projects4me','hammad','2016-01-31 12:41:23','application'),('0fa654525d7fa7d2244d092a646ed8e198fee780','projects4me','hammad','2016-01-31 12:43:51','application'),('1755a97e592eb191601001087b70e7fa412ea7a6','projects4me','hammad','2016-02-01 08:33:25','application'),('1987d7430285655413823cd3dc6ac0b40027cd4e','projects4me','hammad','2016-01-31 12:39:56','application'),('199e8a6cba5265351c539158217f043b0f09d6bb','testclient','hammad','2015-09-24 09:42:48',NULL),('1c935aa84316c550c33e0ba0fcc5b9fd3afc1097','testclient','hammad','2015-10-27 09:01:29',NULL),('1ddeb6aba48c824dad0ffd8a36b9871526440dda','testclient','hammad','2015-09-20 12:42:27',NULL),('208e4f8e6d5af816bc2f193516867a337a43185f','testclient','hammad','2015-11-12 15:18:24',NULL),('21b27c6fdeba20d90cc7fff23b1a900ce774a0f3','projects4me','hammad','2016-02-01 07:56:25','application'),('28099fc8aa58a82fe8e11db4432f54526fb2f81a','testclient','hammad','2015-10-21 02:10:44',NULL),('2cfcb98a2c0e5fc37d826fd8091ce5c9c6b8601e','testclient','hammad','2015-12-18 05:31:32',NULL),('2f98139d6197fe0be4b1094234e2515e2d1e8e42','projects4me','hammad','2016-01-31 13:12:35','application'),('33bda7f9c968920584fc4eaf0993228035c8f05d','projects4me','hammad','2016-02-01 08:32:53','application'),('340c32267f055d2230b54dff4db39cf63e2b1f0d','projects4me','hammad','2016-01-31 12:34:34','application'),('379d70c2cbe5ea7eed68ee240b770f0260f82a06','projects4me','hammad','2016-01-31 12:39:05','application'),('3aee32d715a242c1ab513873f14b6b39def664ff','projects4me','hammad','2016-02-01 08:01:30','application'),('42c800791369921b1265e378b4afc8d58d314399','projects4me','hammad','2016-01-31 12:36:07','application'),('4fc1ae37b26facee8b4be96b18539cfc825c22e0','testclient','hammad','2015-12-15 08:38:05',NULL),('521023e85425c23940601b85f67c5b4b60a409be','testclient','hammad','2015-12-16 08:49:22',NULL),('522d70cf4b3efd2ca4addb360a7dfd882d6b16f2','projects4me','hammad','2016-01-31 13:13:52','application'),('53456ed02bafb2e3defe5fffe32749afc8efd785','projects4me','hammad','2016-02-01 08:24:10','application'),('5527fe26ef17355b384dbaae90d8debb309e0245','projects4me','hammad','2016-01-31 12:47:38','application'),('59379e0855d831fe9482258b9a07b0384f57a9ca','testclient','hammad','2015-12-08 06:19:24',NULL),('5cb46854cfc8df196a5d164939417d72cd2b2914','projects4me','hammad','2016-01-31 11:21:00',NULL),('5db9a35db63ba509a55782b83f1b1e26bd71cbec','projects4me','hammad','2016-01-31 12:04:42','application'),('63c0dbc8d5eb63ca26b7cf7f987c5b4ad1800cbd','testclient','hammad','2015-12-11 09:45:04',NULL),('6713867b33b26c300c5fad3907493c14c8b8c528','projects4me','hammad','2016-01-31 13:13:46','application'),('68d5b4358a3d871de471e9abbda9603faa4af2ad','testclient','hammad','2015-11-25 18:36:21',NULL),('6a599e3a0804aa0b32adf47731058d9aea3357fc','projects4me','hammad','2016-02-01 08:13:19','application'),('6c599d08620d1c5afb549b9c1548510ec688aec1','testclient','hammad','2015-12-08 04:57:03',NULL),('6ce536632bdd4cd62d60e236083b1b607caa59ef','testclient','hammad','2015-12-16 09:04:39',NULL),('6d5f01d6794c2308c1424bf5e731c0cd11d16e26','testclient','hammad','2015-12-16 08:49:38',NULL),('7017cc71a7259874f525e7ee3b2727259caa2a54','testclient','hammad','2015-12-16 09:01:38',NULL),('74f56a0888998dc185a083cf455ade9f1c44bfd2','testclient','hammad','2015-12-08 02:53:11',NULL),('78f01bea92cf98f5b8de580da1d94b2e047ed33e','projects4me','hammad','2016-01-31 11:55:20','application'),('7c9adfaf9cdf5b43fee4a2aec133a703990550ba','testclient','hammad','2015-12-23 10:59:05',NULL),('7d6af6a88f60ebc0d0654a992ed021a2550353f9','projects4me','hammad','2016-01-31 12:06:37','application'),('7d831f7a05d402faa03370ed812747d00aeb0efe','projects4me','hammad','2016-01-31 11:55:13','application'),('7dc7e421fc89983172688bcbcbe219a84174ad8c','testclient','hammad','2015-12-24 09:55:17',NULL),('85217a184efee2ca4399286a1bd182f4bfd7da90','testclient','hammad','2015-09-24 09:54:46',NULL),('87d4ea9d015092a548e1de68970a6630e8452869','testclient','hammad','2015-12-16 08:24:21',NULL),('8923bce8773fe5f6951fd2df14da545629985062','projects4me','hammad','2016-01-31 13:01:17','application'),('8ceb87a17720c04050b20c03baa36d837f76eaea','testclient','hammad','2015-12-15 07:37:52',NULL),('94ca0221c9d07540707e255e0c07ac85927f731f','testclient','hammad','2015-12-18 04:24:35',NULL),('975be567d7ff9b7798d2b284e1649b592be1b8dc','testclient','hammad','2015-11-25 18:42:24',NULL),('990a34843743c242505bad8faa3fa1b5bff0617e','projects4me','hammad','2016-02-01 08:19:18','application'),('9c4e222da52c1fc307d3f9d02379e4344c8012a9','projects4me','hammad','2016-02-01 09:05:02','application'),('9f285b786456523d2ba0f8e14e62fc257eaa84a1','projects4me','hammad','2016-02-01 08:57:43','application'),('9faff2097ed8bd18b5a580ab4e517936c47c773d','projects4me','hammad','2016-01-31 12:31:28','application'),('a36c79a75299013ab2f0f5d5e6ca6fd5b7042627','projects4me','hammad','2016-01-31 11:55:29','application'),('a36ca121770a5f7ffc17b27aa0d0b1ff3f36fe5f','testclient','hammad','2015-12-24 07:54:25',NULL),('a530cfde1cd3b19a0123be295f242998c99e6c2e','projects4me','hammad','2016-02-01 08:18:14','application'),('a550808b1c9490ae8bcff7b5ccfd15efa86a0f42','projects4me','hammad','2016-01-31 11:37:15','application'),('a71311c61df4da191ff51059db83096d69e6cca1','projects4me','hammad','2016-01-31 12:33:56','application'),('ae412fd571cb4f879812a38e1c7b21b78a899694','projects4me','hammad','2016-01-31 11:34:41',NULL),('b3f03d9e73dd8721ff3d4e71d2427e25a5a08449','testclient','hammad','2015-10-21 02:53:47',NULL),('b611af3aae6d63d97ee6155a30dba1f4f670f5d9','projects4me','hammad','2016-01-31 13:13:41','application'),('b6b5167d163635f12ba5be3a52139569b7c63359','projects4me','hammad','2016-02-01 08:20:48','application'),('b6d0965d2f1b3e77b5e14bdf641ae8f58030da38','testclient','hammad','2015-12-16 08:56:03',NULL),('bb5b738c05645052e31613ef9be77d1c163f1513','testclient','hammad','2015-10-21 02:11:58',NULL),('c01de9c83b0af0d88ea48780c437703e4901e150','testclient','hammad','2015-12-18 01:27:19',NULL),('c1515948b63762aa4accb917e735ec07e2cf5f91','testclient','hammad','2015-12-14 07:42:09',NULL),('c23e98496195b1f720978d58989a179a7e075220','testclient','hammad','2015-12-16 08:53:46',NULL),('c34fc0b6f6119005cee3e28bfc857c9d0a605a2b','testclient','hammad','2015-12-16 09:01:21',NULL),('c985d767c944e08d25ff4dd5e73ba2aa25f44bf4','projects4me','hammad','2016-01-31 12:06:22','application'),('c9b0aa131f3a5adb7061e5b84f9cbd74f9f5b6da','projects4me','hammad','2016-01-31 12:40:45','application'),('ca569be7cb3ad5bd2646f961b101f64bf8f9801d','testclient','hammad','2015-12-22 08:47:58',NULL),('d1e8b82b02fdf2cf70915a4e68965b7a6b831fc1','testclient','hammad','2015-12-11 07:23:39',NULL),('d322f4d0f760acdbef0126c7724b6c85b642bba0','testclient','hammad','2015-12-16 07:10:56',NULL),('da03100941365239135c845b6950152478499510','testclient','hammad','2015-12-15 09:52:30',NULL),('da171ba22f8c6aad91ae4ad2a292e1d68fba8634','projects4me','hammad','2016-02-01 07:56:08','application'),('da83c82ec5f532ef5b3c9622a4c098ec4780501d','projects4me','hammad','2016-01-31 12:36:46','application'),('e0b75ea33c66203eda6784709f4c91cf744dff38','testclient','hammad','2015-12-11 06:36:52',NULL),('e190992eb713a168bbf21a638d340069e4d4a49d','testclient','hammad','2015-11-25 18:42:17',NULL),('e2e2cf709fe90d5364138e7061e684e15e68d2cd','testclient','hammad','2015-12-18 05:31:39',NULL),('e87d7f041034180b4c0177b6408698e00ffa336a','testclient','hammad','2015-12-16 08:49:31',NULL),('ea6d6ea374d8078f1dec6d50f1a67cda75090d93','projects4me','hammad','2016-01-31 12:42:29','application'),('eb8081372e6c5cfe3aa18910399e67baa4c14697','testclient','hammad','2015-12-22 04:24:47',NULL),('f008785b54fb785059af129af35cd81d06ed15b2','testclient','hammad','2015-12-09 06:33:20',NULL),('f34745d02771110e30a356dca45dc417c5cf822d','projects4me','hammad','2016-01-31 12:35:00','application'),('f5ac3ceb1f16520fd154c1f721d375ba28df10f0','testclient','hammad','2015-12-24 08:54:56',NULL),('f76017bf3506821625b661dfca4046b3b98cb29c','testclient','hammad','2015-12-21 12:30:39',NULL),('f7f2ebb288c210e74c1cef0592eaeef9e7a40603','projects4me','hammad','2016-02-01 08:19:55','application'),('fc4d2bfe244b17a673f6170bf42ab5a1f04b49c8','projects4me','hammad','2016-01-31 12:03:41','application'),('fc53be0528f09de173bebe32bd49eeecdeaa9f17','projects4me','hammad','2016-02-01 08:15:33','application');
/*!40000 ALTER TABLE `oauth_refresh_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `oauth_scopes`
--

DROP TABLE IF EXISTS `oauth_scopes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oauth_scopes` (
  `id` varchar(36) COLLATE utf8_unicode_ci NOT NULL,
  `scope` text COLLATE utf8_unicode_ci NOT NULL,
  `is_default` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oauth_scopes`
--

LOCK TABLES `oauth_scopes` WRITE;
/*!40000 ALTER TABLE `oauth_scopes` DISABLE KEYS */;
INSERT INTO `oauth_scopes` VALUES ('1','application',1);
/*!40000 ALTER TABLE `oauth_scopes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `_read` int(1) DEFAULT NULL,
  `_search` int(1) DEFAULT NULL,
  `_create` int(1) DEFAULT NULL,
  `_update` int(1) DEFAULT NULL,
  `_delete` int(1) DEFAULT NULL,
  `resourceId` int(11) NOT NULL,
  `roleId` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,2,1,1,1,1,3,3),(2,1,1,1,1,1,2,3),(3,1,1,1,1,1,4,3),(4,1,1,1,1,1,4,2),(5,1,1,1,1,1,2,2),(6,1,1,1,1,1,6,1),(7,1,1,1,1,1,7,1),(8,1,1,1,1,1,8,1),(9,1,1,1,1,1,1,1);
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `dateCreated` datetime NOT NULL,
  `dateModified` datetime NOT NULL,
  `notes` text COLLATE utf8_unicode_ci,
  `startDate` date DEFAULT NULL,
  `endDate` date DEFAULT NULL,
  `shortCode` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `projects`
--

LOCK TABLES `projects` WRITE;
/*!40000 ALTER TABLE `projects` DISABLE KEYS */;
INSERT INTO `projects` VALUES (1,'Projects4Me','0000-00-00 00:00:00','0000-00-00 00:00:00','Open Source Project Management and Team Collaboration Software','2015-03-19','0000-00-00','p4me','software'),(2,'MetaCircle','0000-00-00 00:00:00','0000-00-00 00:00:00',NULL,NULL,NULL,'',''),(3,'Kya','0000-00-00 00:00:00','0000-00-00 00:00:00',NULL,NULL,NULL,'','');
/*!40000 ALTER TABLE `projects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `projects_roles`
--

DROP TABLE IF EXISTS `projects_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `projects_roles` (
  `id` int(11) NOT NULL,
  `projectId` int(11) NOT NULL,
  `roleId` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `projects_roles`
--

LOCK TABLES `projects_roles` WRITE;
/*!40000 ALTER TABLE `projects_roles` DISABLE KEYS */;
INSERT INTO `projects_roles` VALUES (1,1,2,1),(2,1,1,2),(3,2,1,2),(4,2,1,1),(5,-1,1,1);
/*!40000 ALTER TABLE `projects_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `projects_teams`
--

DROP TABLE IF EXISTS `projects_teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `projects_teams` (
  `id` int(11) NOT NULL,
  `projectId` int(11) NOT NULL,
  `teamId` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `projects_teams`
--

LOCK TABLES `projects_teams` WRITE;
/*!40000 ALTER TABLE `projects_teams` DISABLE KEYS */;
INSERT INTO `projects_teams` VALUES (1,1,1),(2,1,2),(3,2,3),(4,2,2);
/*!40000 ALTER TABLE `projects_teams` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resources`
--

DROP TABLE IF EXISTS `resources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resources` (
  `id` int(11) NOT NULL,
  `parentId` int(11) DEFAULT NULL,
  `entity` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `entityId` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resources`
--

LOCK TABLES `resources` WRITE;
/*!40000 ALTER TABLE `resources` DISABLE KEYS */;
INSERT INTO `resources` VALUES (1,NULL,'Contacts',0),(2,0,'Projects',0),(3,0,'Accounts',0),(4,NULL,'Notes',0),(5,3,'Accounts.name',0),(6,0,'Roles',0),(7,NULL,'Resources',0),(8,NULL,'Permissions',0);
/*!40000 ALTER TABLE `resources` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Admin'),(2,'Project Manager'),(3,'Developer'),(4,'Client');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teams`
--

DROP TABLE IF EXISTS `teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teams` (
  `id` int(11) NOT NULL,
  `name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teams`
--

LOCK TABLES `teams` WRITE;
/*!40000 ALTER TABLE `teams` DISABLE KEYS */;
INSERT INTO `teams` VALUES (1,'Gryfindor'),(2,'Hufflepuff'),(3,'Ravenclaw'),(4,'Slytherine');
/*!40000 ALTER TABLE `teams` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teams_memberships`
--

DROP TABLE IF EXISTS `teams_memberships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teams_memberships` (
  `id` int(11) NOT NULL,
  `teamId` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teams_memberships`
--

LOCK TABLES `teams_memberships` WRITE;
/*!40000 ALTER TABLE `teams_memberships` DISABLE KEYS */;
INSERT INTO `teams_memberships` VALUES (1,1,1),(2,1,2),(3,2,1),(4,2,2);
/*!40000 ALTER TABLE `teams_memberships` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(51) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `email` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `status` varchar(60) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `username_UNIQUE` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'hammad','08f26c07987bb6587fb165bc29ebde2d31eb6538','hammad.hassan@rolustech.com','Active'),(2,'hassan','hassan','hassan@example.com','Active');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2016-01-18  9:06:40
