<?php
/**
 * Cron Epesi
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage about
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_CronCommon extends ModuleCommon
{
    public static function admin_caption() {
		return [
				'label' => __('Cron'),
				'section' => __('Server Configuration')
		];
	}

    public static function get_cron_url()
    {
        return get_epesi_url() . 'cron.php?token=' . self::load_token();
    }

    public static function load_token()
    {
        $token_file = self::token_file();
        if (!file_exists($token_file)) {
            self::generate_token();
        }
        if (!defined('CRON_TOKEN')) {
            require_once $token_file;
        }
        return defined('CRON_TOKEN') ? CRON_TOKEN : '';
    }

    public static function generate_token()
    {
        $token = md5(time() . getcwd());
        $success = file_put_contents(self::token_file(), '<?php define("CRON_TOKEN", "' . $token . '");');
        if (!$success) {
            throw new ErrorException("Can't generate token file");
        }
        return $token;
    }

    private static function token_file()
    {
        return DATA_DIR . '/cron_token.php';
    }
}

?>
