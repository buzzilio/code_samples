<?php
/**
 * Logging actions of wordstat processing
 * User: Alexey Vasilkov
 * Date: 10.07.13
 */

namespace zzr\yandex\api;

use zzr\config\C;

class WordstatLogger
{
    protected static $instance;

    protected   $config             = null;
    protected   $fp                 = null;

    protected function __construct()
    {
        $this->config       = C::i();
        $this->fp           = fopen($this->config->site . $this->config->paths['tmp'] . 'wordstat.log', 'a');
    }

    /*
     * Singleton
     *
     * @return WordstatLogger
     */
    public static function i()
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /*
     * Write message to log
     *
     * string $message
     *
     * @return bool
     */
    public function log($message)
    {
        if (!$this->config->isProductionServer())
            echo "\n" . $message;

        if (!is_resource($this->fp))
            return false;

        return fwrite($this->fp, "\n[" . date('H:i:s d.m.Y') . '] ' . $message);
    }

    /*
     * Destructor, closes log file if necessary
     */
    public function __destruct()
    {
        if (is_resource($this->fp))
            fclose($this->fp);
    }
}