<?php

class ConstantesPDO {

   private static ?ConstantesPDO $instance = null;
   private $config;

   private function __construct()
   {
      try {
            $json = file_get_contents(__DIR__ . "/config.json");
            $this->config = json_decode($json, true);
      } catch (Exception $e) {
            die('Récupération du fichier de configuration échouer: ' . $e->getMessage());
      }
   }

   public static function getInstance(): ConstantesPDO
   {
      if (self::$instance == null) {
            self::$instance = new ConstantesPDO();
      }
      return self::$instance;
   }

   public function getConfig(): array
   {
      return $this->config;
   }

   private function __clone() {}

   public function __wakeup()
   {
      throw new Exception("Un singleton ne doit pas être deserialisé.");
   }


}